<template>
	<div class="dr-seg" :class="{ 'dr-seg--compact': compact }">
		<div v-if="total"
			class="dr-seg__bar"
			role="img"
			:aria-label="`${label}: ${parts.map((p) => `${p.label} ${format(p.value)}`).join(', ')}`">
			<component :is="selectable ? 'button' : 'div'"
				v-for="p in visible"
				:key="p.key"
				:type="selectable ? 'button' : undefined"
				class="dr-seg__part"
				:class="{ 'dr-seg__part--hover': hover === p.key }"
				:style="{ flexGrow: p.value, background: `var(${p.color})` }"
				:tabindex="selectable ? -1 : undefined"
				@mouseenter="hover = p.key"
				@mouseleave="hover = null"
				@click="selectable && $emit('select', p.key)" />
			<div v-if="hovered" class="dr-seg__tip" role="tooltip">
				<NcIconSvgWrapper v-if="hovered.icon"
					:path="hovered.icon"
					:size="16"
					inline
					:style="{ color: `var(${hovered.color})` }" />
				{{ hovered.label }}: <strong>{{ format(hovered.value) }}</strong>
				<span class="dr-muted">({{ percent(hovered.value) }})</span>
			</div>
		</div>
		<div v-else class="dr-seg__bar dr-seg__bar--empty" />
		<ul v-if="!compact" class="dr-seg__legend">
			<li v-for="p in parts" :key="p.key" :class="{ 'dr-muted': !p.value }">
				<component :is="selectable && p.value ? 'button' : 'span'"
					:type="selectable && p.value ? 'button' : undefined"
					class="dr-seg__item"
					@mouseenter="hover = p.key"
					@mouseleave="hover = null"
					@click="selectable && p.value && $emit('select', p.key)">
					<NcIconSvgWrapper v-if="p.icon"
						:path="p.icon"
						:size="16"
						inline
						:style="{ color: p.value ? `var(${p.color})` : undefined }" />
					<span v-else class="dr-seg__swatch" :style="{ background: `var(${p.color})` }" />
					<strong>{{ format(p.value) }}</strong>
					<span>{{ p.label }}</span>
				</component>
			</li>
		</ul>
	</div>
</template>

<script setup>
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { computed, ref } from 'vue'

const props = defineProps({
	// what the bar shows, for screen readers
	label: { type: String, required: true },
	// [{key, label, value, color: '--dr-…', icon?}] in fixed order
	parts: { type: Array, required: true },
	format: { type: Function, default: (v) => String(v) },
	// thin bar without legend (e.g. in a table row)
	compact: { type: Boolean, default: false },
	// segments and legend entries emit "select" with the key
	selectable: { type: Boolean, default: false },
})

defineEmits(['select'])

const hover = ref(null)
const visible = computed(() => props.parts.filter((p) => p.value > 0))
const total = computed(() => props.parts.reduce((sum, p) => sum + p.value, 0))
const hovered = computed(() => props.parts.find((p) => p.key === hover.value && p.value > 0) ?? null)

function percent(value) {
	return new Intl.NumberFormat('de-DE', { style: 'percent', maximumFractionDigits: 0 }).format(value / total.value)
}
</script>

<style scoped>
.dr-seg__bar {
	position: relative;
	display: flex;
	gap: 2px;
	height: 14px;
}

.dr-seg--compact .dr-seg__bar {
	height: 8px;
}

.dr-seg__bar--empty {
	border-radius: 4px;
	background: var(--dr-dim);
	opacity: .5;
}

.dr-seg__part {
	min-width: 4px;
	margin: 0;
	padding: 0;
	border: none;
	border-radius: 0;
	min-height: 0;
}

button.dr-seg__part {
	cursor: pointer;
}

.dr-seg__part:first-of-type {
	border-start-start-radius: 4px;
	border-end-start-radius: 4px;
}

.dr-seg__part:nth-last-child(1 of .dr-seg__part) {
	border-start-end-radius: 4px;
	border-end-end-radius: 4px;
}

.dr-seg__part--hover {
	filter: brightness(1.1);
	outline: 2px solid var(--color-main-text);
	outline-offset: 1px;
}

.dr-seg__tip {
	position: absolute;
	bottom: calc(100% + 6px);
	left: 0;
	z-index: 10;
	padding: 4px 8px;
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	box-shadow: 0 1px 6px var(--color-box-shadow);
	white-space: nowrap;
	font-size: 13px;
	pointer-events: none;
}

.dr-seg__legend {
	display: flex;
	flex-wrap: wrap;
	gap: var(--default-grid-baseline) calc(var(--default-grid-baseline) * 4);
	margin-top: calc(var(--default-grid-baseline) * 2);
}

.dr-seg__item {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	margin: 0;
	padding: 0;
	border: none;
	background: none;
	min-height: 0;
	font-weight: normal;
	color: inherit;
}

button.dr-seg__item {
	cursor: pointer;
}

button.dr-seg__item:hover span:last-child {
	text-decoration: underline;
}

.dr-seg__swatch {
	display: inline-block;
	width: 10px;
	height: 10px;
	border-radius: 2px;
}
</style>
