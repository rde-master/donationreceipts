<template>
	<div class="dr-tile" :class="{ 'dr-tile--hero': hero }">
		<div class="dr-tile__label">
			{{ label }}
		</div>
		<div class="dr-tile__value">
			{{ value }}
		</div>
		<div v-if="delta !== null" class="dr-tile__delta">
			<span :class="delta >= 0 ? 'dr-delta--up' : 'dr-delta--down'">
				<NcIconSvgWrapper :path="delta >= 0 ? mdiArrowUp : mdiArrowDown" :size="14" inline />
				{{ formatPercent(delta) }}
			</span>
			<span class="dr-muted">{{ deltaLabel }}</span>
		</div>
		<div v-if="$slots.default" class="dr-tile__sub dr-muted">
			<slot />
		</div>
	</div>
</template>

<script setup>
import { mdiArrowDown, mdiArrowUp } from '@mdi/js'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

defineProps({
	label: { type: String, required: true },
	value: { type: String, required: true },
	/** relative change, e.g. 0.12 for +12 %; null = no comparison */
	delta: { type: Number, default: null },
	deltaLabel: { type: String, default: '' },
	hero: { type: Boolean, default: false },
})

function formatPercent(delta) {
	return (delta >= 0 ? '+' : '−') + new Intl.NumberFormat('de-DE', { maximumFractionDigits: 0 }).format(Math.abs(delta) * 100) + ' %'
}
</script>

<style scoped>
.dr-tile {
	display: flex;
	flex-direction: column;
	gap: 2px;
	padding: calc(var(--default-grid-baseline) * 4);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.dr-tile__label {
	color: var(--color-text-maxcontrast);
}

.dr-tile__value {
	font-size: 28px;
	line-height: 1.2;
	font-weight: 600;
}

.dr-tile--hero .dr-tile__value {
	font-size: 48px;
	line-height: 1.1;
}

.dr-tile__delta {
	display: flex;
	flex-wrap: wrap;
	gap: 0 6px;
	font-size: 13px;
}

.dr-tile__sub {
	font-size: 13px;
}

.dr-delta--up {
	color: var(--dr-delta-up);
	font-weight: 600;
}

.dr-delta--down {
	color: var(--dr-delta-down);
	font-weight: 600;
}
</style>
