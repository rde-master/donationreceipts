<template>
	<div class="dr-image">
		<div class="dr-image__preview">
			<img v-if="present" :src="src" :alt="label">
			<span v-else class="dr-muted">kein Bild</span>
		</div>
		<div class="dr-image__actions">
			<strong>{{ label }}</strong>
			<input ref="input"
				type="file"
				accept="image/png"
				hidden
				@change="upload">
			<NcButton :disabled="busy" @click="$refs.input.click()">
				{{ present ? 'Ersetzen' : 'PNG hochladen' }}
			</NcButton>
			<NcButton v-if="present"
				variant="tertiary"
				:disabled="busy"
				@click="remove">
				Entfernen
			</NcButton>
		</div>
	</div>
</template>

<script setup>
import { showError, showSuccess } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import { ref } from 'vue'
import { deleteImage, errorMessage, imageUrl, uploadImage } from '../api.js'

const props = defineProps({
	kind: { type: String, required: true },
	label: { type: String, required: true },
	present: { type: Boolean, default: false },
})

const emit = defineEmits(['changed'])

const busy = ref(false)
const src = ref(imageUrl(props.kind))

async function upload(event) {
	const file = event.target.files[0]
	event.target.value = ''
	if (!file) {
		return
	}
	busy.value = true
	try {
		await uploadImage(props.kind, file)
		src.value = imageUrl(props.kind)
		showSuccess(`${props.label} gespeichert`)
		emit('changed')
	} catch (e) {
		showError(errorMessage(e))
	} finally {
		busy.value = false
	}
}

async function remove() {
	busy.value = true
	try {
		await deleteImage(props.kind)
		emit('changed')
	} catch (e) {
		showError(errorMessage(e))
	} finally {
		busy.value = false
	}
}
</script>

<style scoped>
.dr-image {
	display: flex;
	gap: calc(var(--default-grid-baseline) * 4);
	align-items: center;
}

.dr-image__preview {
	width: 220px;
	height: 110px;
	display: flex;
	align-items: center;
	justify-content: center;
	border: 1px dashed var(--color-border-dark);
	border-radius: var(--border-radius-large);
	/* checkerboard, so transparency is visible */
	background: repeating-conic-gradient(var(--color-background-dark) 0% 25%, transparent 0% 50%) 50% / 16px 16px;
}

.dr-image__preview img {
	max-width: 100%;
	max-height: 100%;
}

.dr-image__actions {
	display: flex;
	flex-direction: column;
	align-items: flex-start;
	gap: var(--default-grid-baseline);
}
</style>
