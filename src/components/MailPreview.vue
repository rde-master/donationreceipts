<template>
	<div class="dr-preview">
		<div v-if="to">
			<span class="dr-muted">An:</span> {{ to }}
		</div>
		<div><span class="dr-muted">Betreff:</span> {{ subject }}</div>
		<!-- No scripts: the HTML is sanitized on the server, the sandbox stays as second line of defence.
			Same origin only so the template images (behind the login) load. -->
		<iframe ref="frame"
			class="dr-preview__frame"
			sandbox="allow-same-origin"
			title="Vorschau der E-Mail"
			:srcdoc="html"
			@load="resize" />
	</div>
</template>

<script setup>
import { ref } from 'vue'

defineProps({
	to: { type: String, default: '' },
	subject: { type: String, default: '' },
	// complete HTML document from the server
	html: { type: String, default: '' },
})

const frame = ref(null)

function resize() {
	const doc = frame.value?.contentDocument
	if (doc?.body) {
		frame.value.style.height = Math.min(doc.documentElement.scrollHeight + 4, 900) + 'px'
	}
}
</script>

<style scoped>
.dr-preview {
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	padding: calc(var(--default-grid-baseline) * 3);
	display: flex;
	flex-direction: column;
	gap: var(--default-grid-baseline);
}

.dr-preview__frame {
	width: 100%;
	min-height: 200px;
	border: none;
	margin-top: calc(var(--default-grid-baseline) * 2);
	/* mails are light; keep the preview readable in the dark theme */
	background: #ffffff;
	border-radius: var(--border-radius);
}
</style>
