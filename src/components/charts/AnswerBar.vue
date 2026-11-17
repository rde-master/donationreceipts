<template>
	<SegmentBar :label="label"
		:parts="parts"
		:compact="compact"
		:selectable="selectable"
		@select="$emit('select', $event)" />
</template>

<script setup>
import { computed } from 'vue'
import { ANSWER_GROUPS } from '../../outcome.js'
import SegmentBar from './SegmentBar.vue'

const props = defineProps({
	// {received, pledged, incomplete, open, declined}
	counts: { type: Object, required: true },
	label: { type: String, default: 'Antworten der Angeschriebenen' },
	compact: { type: Boolean, default: false },
	selectable: { type: Boolean, default: false },
})

defineEmits(['select'])

const parts = computed(() => ANSWER_GROUPS.map((g) => ({ ...g, value: props.counts[g.key] ?? 0 })))
</script>
