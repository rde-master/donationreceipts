<template>
	<div class="dr-send">
		<div v-if="total"
			class="dr-send__bar"
			role="img"
			:aria-label="parts.map((p) => `${p.label}: ${p.count}`).join(', ')">
			<div v-for="p in visible"
				:key="p.key"
				class="dr-send__segment"
				:style="{ flexGrow: p.count, background: `var(${p.color})` }"
				:title="`${p.label}: ${p.count}`" />
		</div>
		<ul class="dr-send__legend">
			<li v-for="p in parts" :key="p.key" :class="{ 'dr-muted': !p.count }">
				<NcIconSvgWrapper :path="p.icon" :size="18" inline :style="{ color: p.count ? `var(${p.color})` : undefined }" />
				<strong>{{ p.count }}</strong>
				<span>{{ p.label }}</span>
			</li>
		</ul>
	</div>
</template>

<script setup>
import { mdiAlertCircle, mdiCheckCircle, mdiEmailArrowRight, mdiEmailOff } from '@mdi/js'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { computed } from 'vue'

const props = defineProps({
	/** { sent, toSend, changed, noEmail } */
	counts: { type: Object, required: true },
})

const parts = computed(() => [
	{ key: 'sent', label: 'versendet', color: '--dr-good', icon: mdiCheckCircle },
	{ key: 'toSend', label: 'noch zu versenden', color: '--dr-warning', icon: mdiEmailArrowRight },
	{ key: 'changed', label: 'nach Versand geändert', color: '--dr-serious', icon: mdiAlertCircle },
	{ key: 'noEmail', label: 'Spender ohne E-Mail', color: '--dr-neutral', icon: mdiEmailOff },
].map((p) => ({ ...p, count: props.counts[p.key] ?? 0 })))
const visible = computed(() => parts.value.filter((p) => p.count > 0))
const total = computed(() => parts.value.reduce((sum, p) => sum + p.count, 0))
</script>

<style scoped>
.dr-send__bar {
	display: flex;
	gap: 2px;
	height: 16px;
	margin-bottom: calc(var(--default-grid-baseline) * 3);
}

.dr-send__segment {
	min-width: 4px;
}

.dr-send__segment:first-child {
	border-start-start-radius: 4px;
	border-end-start-radius: 4px;
}

.dr-send__segment:last-child {
	border-start-end-radius: 4px;
	border-end-end-radius: 4px;
}

.dr-send__legend {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
	gap: calc(var(--default-grid-baseline) * 1) calc(var(--default-grid-baseline) * 4);
}

.dr-send__legend li {
	display: flex;
	align-items: center;
	gap: 6px;
}
</style>
