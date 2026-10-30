<template>
	<NcDialog :name="inquiry?.response ? `Rückmeldung von ${sponsor.name} bearbeiten` : `Rückmeldung von ${sponsor.name} erfassen`"
		size="large"
		@closing="$emit('close')">
		<form id="dr-manual-response" @submit.prevent="save">
			<p class="dr-muted">
				Zum Beispiel nach einem Telefonat oder einer Antwort per E-Mail. Die Rückmeldung zählt wie
				eine über das Formular; aus ihr wird nach dem Spendeneingang die Bescheinigung erstellt.
			</p>
			<ResponseFields v-model="form" :tickets="ticketLabels" />
			<NcCheckboxRadioSwitch v-model="sendConfirmation">
				Bestätigungsmail mit den Angaben an {{ sponsor.email }} senden
			</NcCheckboxRadioSwitch>
			<NcNoteCard v-if="error" type="error">
				{{ error }}
			</NcNoteCard>
		</form>
		<template #actions>
			<NcButton @click="$emit('close')">
				Abbrechen
			</NcButton>
			<NcButton type="submit"
				form="dr-manual-response"
				variant="primary"
				:disabled="saving || !responseComplete(form)">
				Speichern
			</NcButton>
		</template>
	</NcDialog>
</template>

<script setup>
import { showSuccess } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import { ref } from 'vue'
import { errorMessage, saveManualResponse } from '../api.js'
import { responseComplete, responseForm, responsePayload } from '../responseForm.js'
import ResponseFields from './ResponseFields.vue'

const props = defineProps({
	campaignId: { type: Number, required: true },
	sponsor: { type: Object, required: true },
	// existing inquiry of the sponsor in this campaign, or null
	inquiry: { type: Object, default: null },
	ticketLabels: { type: Object, required: true },
})

const emit = defineEmits(['close', 'saved'])

// a new response starts with the sponsor's name as company
const form = ref(responseForm(props.inquiry?.response ?? { kind: 'company', company: props.sponsor.name }))
const sendConfirmation = ref(false)
const saving = ref(false)
const error = ref('')

async function save() {
	saving.value = true
	error.value = ''
	try {
		const inquiry = await saveManualResponse(props.campaignId, props.sponsor.id, responsePayload(form.value), sendConfirmation.value)
		showSuccess('Rückmeldung gespeichert')
		emit('saved', inquiry)
		emit('close')
	} catch (e) {
		error.value = errorMessage(e)
	} finally {
		saving.value = false
	}
}
</script>
