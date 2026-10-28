<template>
	<div ref="root" class="dr-chart">
		<div class="dr-chart__bar">
			<ul v-if="series.length > 1" class="dr-legend">
				<li v-for="s in series" :key="s.key">
					<span class="dr-legend__swatch" :style="{ background: `var(${s.color})` }" />
					{{ s.label }}
				</li>
			</ul>
			<NcButton variant="tertiary"
				size="small"
				class="dr-chart__toggle"
				:aria-label="table ? 'Als Diagramm zeigen' : 'Als Tabelle zeigen'"
				@click="table = !table">
				<template #icon>
					<NcIconSvgWrapper :path="table ? mdiChartBar : mdiTableLarge" :size="18" />
				</template>
				{{ table ? 'Diagramm' : 'Tabelle' }}
			</NcButton>
		</div>

		<table v-if="table" class="dr-table dr-chart__table">
			<caption class="hidden-visually">
				{{ caption }}
			</caption>
			<thead>
				<tr>
					<th />
					<th v-for="s in series" :key="s.key" class="dr-num">
						{{ s.label }}
					</th>
					<th v-if="series.length > 1" class="dr-num">
						Gesamt
					</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="(c, i) in columns" :key="i">
					<td>{{ c.title }}</td>
					<td v-for="s in c.parts" :key="s.key" class="dr-num">
						{{ format(s.value) }}
					</td>
					<td v-if="series.length > 1" class="dr-num">
						{{ format(c.total) }}
					</td>
				</tr>
			</tbody>
		</table>

		<div v-else class="dr-chart__plot">
			<svg :width="width"
				:height="height"
				role="img"
				:aria-label="caption">
				<!-- grid and y ticks -->
				<g v-for="t in ticks" :key="t.value">
					<line :x1="left"
						:x2="width"
						:y1="t.y"
						:y2="t.y"
						:stroke="t.value === 0 ? 'var(--dr-axis)' : 'var(--dr-grid)'"
						stroke-width="1"
						shape-rendering="crispEdges" />
					<text :x="left - 8"
						:y="t.y"
						class="dr-chart__tick"
						text-anchor="end"
						dominant-baseline="middle">{{ formatTick(t.value) }}</text>
				</g>

				<g v-for="(c, i) in columns" :key="i">
					<rect v-if="hover === i"
						:x="c.x - band / 2 + 2"
						:y="top"
						:width="band - 4"
						:height="plotHeight"
						rx="4"
						class="dr-chart__wash" />
					<path v-for="seg in c.segments"
						:key="seg.key"
						:d="seg.d"
						:fill="seg.fill" />
					<text v-if="c.labelled"
						:x="c.x"
						:y="c.top - 6"
						class="dr-chart__value"
						text-anchor="middle">{{ formatTick(c.total) }}</text>
					<text v-if="c.showTick"
						:x="c.x"
						:y="height - 6"
						class="dr-chart__tick"
						:class="{ 'dr-chart__tick--strong': highlight === i }"
						text-anchor="middle">{{ c.label }}</text>
				</g>

				<!-- hit targets: whole band, not only the painted column -->
				<rect v-for="(c, i) in columns"
					:key="'hit' + i"
					:x="c.x - band / 2"
					:y="top"
					:width="band"
					:height="plotHeight + bottom"
					fill="transparent"
					tabindex="0"
					:class="{ 'dr-clickable': selectable }"
					:aria-label="`${c.title}: ${c.parts.map((p) => `${p.label} ${format(p.value)}`).join(', ')}`"
					@pointerenter="hover = i"
					@pointerleave="hover = null"
					@focus="hover = i"
					@blur="hover = null"
					@click="selectable && $emit('select', i)"
					@keydown.enter="selectable && $emit('select', i)" />
			</svg>

			<div v-if="hover !== null"
				class="dr-tooltip"
				:style="tooltipStyle">
				<div class="dr-tooltip__title">
					{{ columns[hover].title }}
				</div>
				<div v-for="p in [...columns[hover].parts].reverse()" :key="p.key" class="dr-tooltip__row">
					<span class="dr-tooltip__key" :style="{ background: `var(${p.color})` }" />
					<strong>{{ format(p.value) }}</strong>
					<span class="dr-muted">{{ p.label }}</span>
				</div>
				<div v-if="series.length > 1" class="dr-tooltip__row dr-tooltip__total">
					<strong>{{ format(columns[hover].total) }}</strong>
					<span class="dr-muted">gesamt</span>
				</div>
			</div>
		</div>
	</div>
</template>

<script setup>
/**
 * Column chart, optionally stacked. Every series is { key, label, color,
 * values }, color being a CSS custom property name such as '--dr-series-1'.
 * Single-series charts can emphasise one column (`highlight`) and dim the rest.
 */
import { mdiChartBar, mdiTableLarge } from '@mdi/js'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

const props = defineProps({
	caption: { type: String, required: true },
	labels: { type: Array, required: true },
	titles: { type: Array, default: null },
	series: { type: Array, required: true },
	format: { type: Function, required: true },
	formatTick: { type: Function, required: true },
	highlight: { type: Number, default: null },
	selectable: { type: Boolean, default: false },
	height: { type: Number, default: 220 },
})
defineEmits(['select'])

const MAX_BAR = 24
const GAP = 2
const RADIUS = 4

const root = ref(null)
const width = ref(600)
const hover = ref(null)
const table = ref(false)

const top = 18
const bottom = 24
const plotHeight = computed(() => props.height - top - bottom)

const totals = computed(() => props.labels.map((_, i) => props.series.reduce((sum, s) => sum + (s.values[i] ?? 0), 0)))

const ticks = computed(() => {
	const max = Math.max(...totals.value, 0)
	const step = niceStep(max / 4)
	const count = max === 0 ? 1 : Math.ceil(max / step)
	const scale = step * count
	return Array.from({ length: count + 1 }, (_, i) => ({
		value: i * step,
		y: top + plotHeight.value - (i * step / scale) * plotHeight.value,
	}))
})
const scaleMax = computed(() => ticks.value[ticks.value.length - 1].value)

// room for the longest tick label (system sans, 12px ≈ 7px per character)
const left = computed(() => Math.max(...ticks.value.map((t) => props.formatTick(t.value).length)) * 7 + 12)
const band = computed(() => (width.value - left.value) / Math.max(props.labels.length, 1))
const barWidth = computed(() => Math.min(MAX_BAR, band.value * 0.6))
const tickEvery = computed(() => Math.ceil(36 / band.value))

// Selective direct label: the emphasised column, else the highest one.
const labelledIndex = computed(() => {
	if (props.highlight !== null) {
		return props.highlight
	}
	const max = Math.max(...totals.value)
	return max > 0 ? totals.value.indexOf(max) : null
})

const columns = computed(() => props.labels.map((label, i) => {
	const x = left.value + band.value * (i + 0.5)
	const w = barWidth.value
	const parts = props.series.map((s) => ({ key: s.key, label: s.label, color: s.color, value: s.values[i] ?? 0 }))
	const nonZero = parts.filter((p) => p.value > 0)
	const segments = []
	let base = top + plotHeight.value
	nonZero.forEach((p, n) => {
		const h = (p.value / scaleMax.value) * plotHeight.value
		const isTop = n === nonZero.length - 1
		// a surface gap separates a segment from the one below
		const drawn = n > 0 ? Math.max(h - GAP, 1) : h
		const fill = props.highlight !== null && props.highlight !== i ? 'var(--dr-dim)' : `var(${p.color})`
		segments.push({ key: p.key, fill, d: columnPath(x - w / 2, base - h, w, drawn, isTop ? RADIUS : 0) })
		base -= h
	})
	return {
		x,
		label,
		title: props.titles?.[i] ?? label,
		parts,
		segments,
		total: totals.value[i],
		top: base,
		labelled: labelledIndex.value === i && totals.value[i] > 0,
		showTick: i % tickEvery.value === 0 || props.highlight === i,
	}
}))

// Beside the hovered column inside the plot, on the side with more room.
const tooltipStyle = computed(() => {
	const x = columns.value[hover.value]?.x ?? 0
	const offset = barWidth.value / 2 + 8
	return x < width.value / 2
		? { left: `${x + offset}px`, top: `${top}px` }
		: { left: `${x - offset}px`, top: `${top}px`, transform: 'translateX(-100%)' }
})

/**
 * Column from the baseline up; only the data end is rounded.
 *
 * @param {number} x left
 * @param {number} y top
 * @param {number} w width
 * @param {number} h height
 * @param {number} r corner radius at the top
 */
function columnPath(x, y, w, h, r) {
	if (h <= 0) {
		return ''
	}
	r = Math.min(r, h, w / 2)
	return `M${x},${y + h} V${y + r} A${r},${r} 0 0 1 ${x + r},${y} H${x + w - r} A${r},${r} 0 0 1 ${x + w},${y + r} V${y + h} Z`
}

/**
 * @param {number} rough approximate step
 * @return {number} 1, 2 or 5 times a power of ten
 */
function niceStep(rough) {
	if (rough <= 0) {
		return 1
	}
	const power = 10 ** Math.floor(Math.log10(rough))
	const n = rough / power
	return (n <= 1 ? 1 : n <= 2 ? 2 : n <= 5 ? 5 : 10) * power
}

let observer = null
onMounted(() => {
	observer = new ResizeObserver(([entry]) => {
		width.value = Math.max(240, Math.floor(entry.contentRect.width))
	})
	observer.observe(root.value)
})
onBeforeUnmount(() => observer?.disconnect())
</script>

<style scoped>
.dr-chart {
	position: relative;
}

.dr-chart__bar {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: calc(var(--default-grid-baseline) * 2);
	min-height: 34px;
}

.dr-chart__toggle {
	flex-shrink: 0;
	margin-inline-start: auto;
}

.dr-chart__plot {
	position: relative;
}

.dr-chart__plot svg {
	display: block;
	overflow: visible;
}

.dr-chart__tick {
	font-size: 12px;
	fill: var(--color-text-maxcontrast);
	font-variant-numeric: tabular-nums;
}

.dr-chart__tick--strong {
	fill: var(--color-main-text);
	font-weight: bold;
}

.dr-chart__value {
	font-size: 12px;
	fill: var(--color-main-text);
}

.dr-chart__wash {
	fill: var(--color-background-hover);
}

rect[tabindex]:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: -2px;
}
</style>
