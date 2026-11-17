<template>
	<ol class="dr-barlist" :aria-label="label">
		<li v-for="item in items" :key="item.key" class="dr-barlist__item">
			<component :is="item.to ? 'RouterLink' : 'span'" :to="item.to" class="dr-barlist__name">
				{{ item.label }}
			</component>
			<div class="dr-barlist__track" :title="`${item.label}: ${format(item.value)}${item.sub ? ' – ' + item.sub : ''}`">
				<div class="dr-barlist__bar" :style="{ width: `calc((100% - 7.5em) * ${max ? item.value / max : 0})` }" />
				<span class="dr-barlist__value">{{ format(item.value) }}</span>
			</div>
			<span v-if="item.sub" class="dr-muted dr-barlist__sub">{{ item.sub }}</span>
		</li>
	</ol>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
	label: { type: String, required: true },
	// [{key, label, value, to?, sub?}] - one series, sorted by the caller
	items: { type: Array, required: true },
	format: { type: Function, default: (v) => String(v) },
})

const max = computed(() => Math.max(...props.items.map((i) => i.value), 0))
</script>

<style scoped>
.dr-barlist {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 2);
}

.dr-barlist__item {
	display: grid;
	grid-template-columns: minmax(8em, 14em) 1fr auto;
	align-items: center;
	gap: calc(var(--default-grid-baseline) * 2);
}

.dr-barlist__name {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.dr-barlist__track {
	display: flex;
	align-items: center;
	gap: 6px;
	min-width: 0;
}

.dr-barlist__bar {
	height: 12px;
	min-width: 2px;
	border-radius: 0 4px 4px 0;
	background: var(--dr-series-1);
}

.dr-barlist__value {
	white-space: nowrap;
	font-variant-numeric: tabular-nums;
}

.dr-barlist__sub {
	white-space: nowrap;
	font-size: 13px;
}

@media (max-width: 600px) {
	.dr-barlist__item {
		grid-template-columns: 1fr;
		gap: 2px;
	}
}
</style>
