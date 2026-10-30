<template>
	<NcDialog :name="`Bescheinigung ${receipt.number} per E-Mail senden`"
		size="large"
		@closing="$emit('close')">
		<NcLoadingIcon v-if="!mail" :size="32" />
		<form v-else
			id="dr-send-form"
			class="dr-send"
			@submit.prevent="send">
			<NcNoteCard v-if="note" type="info">
				{{ note }}
			</NcNoteCard>
			<NcNoteCard v-if="receipt.sentAt" type="warning">
				Bereits am {{ formatDateTime(receipt.sentAt) }} an {{ receipt.sentTo }} versendet.
			</NcNoteCard>
			<NcNoteCard v-if="mail.missing.length" type="error">
				Für den Versand fehlt in den Einstellungen: {{ mail.missing.join(', ') }}
			</NcNoteCard>
			<NcNoteCard v-if="mail.error" type="error">
				{{ mail.error }}
			</NcNoteCard>
			<NcTextField v-model="mail.to"
				type="email"
				label="Empfänger"
				required />
			<NcTextField v-model="mail.subject" label="Betreff" required />
			<NcTextArea v-model="mail.body"
				label="Text"
				resize="vertical"
				rows="9" />
			<p class="dr-attachment">
				<NcIconSvgWrapper :path="mdiPaperclip" :size="18" />
				<a v-if="mail.attachment" :href="receiptFileUrl(receipt.id, 'signed')" target="_blank">{{ mail.attachment }}</a>
				<span v-else class="dr-warning">Unterschriebene Fassung fehlt</span>
				<span class="dr-muted">(mit Unterschrift und Stempel)</span>
			</p>
			<NcNoteCard v-if="error" type="error">
				{{ error }}
			</NcNoteCard>
		</form>
		<template #actions>
			<NcButton @click="$emit('close')">
				Abbrechen
			</NcButton>
			<NcButton type="submit"
				form="dr-send-form"
				variant="primary"
				:disabled="!mail || sending || mail.missing.length > 0 || !!mail.error || !mail.attachment">
				<template #icon>
					<NcLoadingIcon v-if="sending" :size="20" />
					<NcIconSvgWrapper v-else :path="mdiSend" />
				</template>
				Senden
			</NcButton>
		</template>
	</NcDialog>
</template>

<script setup>
import { mdiPaperclip, mdiSend } from '@mdi/js'
import { showSuccess } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { onMounted, ref } from 'vue'
import { errorMessage, getMailPreview, receiptFileUrl, sendReceiptMail } from '../api.js'
import { formatDateTime } from '../format.js'

const props = defineProps({
	receipt: { type: Object, required: true },
	// optional hint at the top, e.g. after a change of a sent receipt
	note: { type: String, default: '' },
})

const emit = defineEmits(['close', 'sent'])

const mail = ref(null)
const sending = ref(false)
const error = ref('')

onMounted(async () => {
	try {
		mail.value = await getMailPreview(props.receipt.id)
	} catch (e) {
		error.value = errorMessage(e)
		mail.value = { to: '', subject: '', body: '', attachment: null, missing: [], error: null }
	}
})

async function send() {
	sending.value = true
	error.value = ''
	try {
		const updated = await sendReceiptMail(props.receipt.id, {
			to: mail.value.to,
			subject: mail.value.subject,
			body: mail.value.body,
		})
		showSuccess(`Bescheinigung ${props.receipt.number} an ${mail.value.to} gesendet`)
		emit('sent', updated)
		emit('close')
	} catch (e) {
		// stays open with the error, so nothing typed gets lost
		error.value = errorMessage(e)
	} finally {
		sending.value = false
	}
}
</script>

<style scoped>
.dr-send {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 2);
}

.dr-attachment {
	display: flex;
	align-items: center;
	gap: var(--default-grid-baseline);
}
</style>
