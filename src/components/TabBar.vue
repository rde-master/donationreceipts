<template>
	<div class="dr-tabs" role="tablist" :aria-label="label">
		<button v-for="tab in tabs"
			:key="tab.id"
			type="button"
			role="tab"
			class="dr-tabs__tab"
			:class="{ 'dr-tabs__tab--active': tab.id === modelValue }"
			:aria-selected="tab.id === modelValue"
			@click="$emit('update:modelValue', tab.id)">
			{{ tab.label }}
			<span v-if="tab.warning"
				class="dr-tabs__dot"
				:title="tab.warning"
				:aria-label="tab.warning" />
		</button>
	</div>
</template>

<script setup>
defineProps({
	// [{id, label, warning?}] - warning: text of a dot shown on the tab
	tabs: { type: Array, required: true },
	modelValue: { type: String, required: true },
	label: { type: String, default: 'Bereiche' },
})

defineEmits(['update:modelValue'])
</script>

<style scoped>
.dr-tabs {
	display: flex;
	flex-wrap: wrap;
	gap: var(--default-grid-baseline);
	border-bottom: 1px solid var(--color-border);
	margin: calc(var(--default-grid-baseline) * 3) 0 calc(var(--default-grid-baseline) * 5);
}

.dr-tabs__tab {
	position: relative;
	margin: 0 0 -1px;
	padding: calc(var(--default-grid-baseline) * 2) calc(var(--default-grid-baseline) * 3);
	border: none;
	border-bottom: 3px solid transparent;
	border-radius: var(--border-radius) var(--border-radius) 0 0;
	background: transparent;
	color: var(--color-text-maxcontrast);
	font-weight: normal;
	min-height: 0;
	cursor: pointer;
}

.dr-tabs__tab:hover,
.dr-tabs__tab:focus-visible {
	background: var(--color-background-hover);
	color: var(--color-main-text);
}

.dr-tabs__tab--active {
	border-bottom-color: var(--color-primary-element);
	color: var(--color-main-text);
	font-weight: bold;
}

.dr-tabs__dot {
	display: inline-block;
	width: 8px;
	height: 8px;
	margin-inline-start: var(--default-grid-baseline);
	border-radius: 50%;
	background: var(--color-warning);
	vertical-align: middle;
}
</style>
