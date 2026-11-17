<template>
	<NcDialog :name="`Status der Rückmeldung – ${sponsor.name}`"
		size="normal"
		@closing="$emit('close')">
		<form id="dr-outcome" class="dr-outcome" @submit.prevent="save">
			<NcButton v-if="!inquiry?.receiptId" wide @click="$emit('manual')">
				<template #icon>
					<NcIconSvgWrapper :path="mdiClipboardEditOutline" />
				</template>
				{{ inquiry?.response ? 'Angaben der Zusage bearbeiten' : 'Spende zugesagt – Angaben erfassen' }}
			</NcButton>
			<p class="dr-muted">
				Oder den Stand ohne Angaben festhalten:
			</p>
			<NcCheckboxRadioSwitch v-for="value in choices"
				:key="value"
				v-model="outcome"
				type="radio"
				:value="value"
				name="dr-outcome">
				<strong>{{ OUTCOMES[value].label }}</strong>
				<span class="dr-outcome__hint">{{ OUTCOMES[value].description }}</span>
			</NcCheckboxRadioSwitch>
			<NcTextArea v-model="note"
				label="Notiz (optional, z. B. „ruft im März wieder an“)"
				resize="vertical"
				rows="2" />
			<p v-if="replyId" class="dr-muted">
				Die Antwort-Mail wird dabei als erledigt markiert.
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
				form="dr-outcome"
				variant="primary"
				:disabled="saving || !outcome">
				Status speichern
			</NcButton>
		</template>
	</NcDialog>
</template>

<script setup>
import { mdiClipboardEditOutline } from '@mdi/js'
import { showSuccess } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import { computed, ref } from 'vue'
import { errorMessage, setOutcome } from '../api.js'
import { OUTCOMES } from '../outcome.js'

const props = defineProps({
	campaignId: { type: Number, required: true },
	sponsor: { type: Object, required: true },
	// the sponsor's inquiry in this campaign, or null
	inquiry: { type: Object, default: null },
	// reply mail this answers (marked done on save)
	replyId: { type: Number, default: null },
})

// manual: open the response form instead
const emit = defineEmits(['close', 'saved', 'manual'])

// "Zusage" needs the data of a response
const choices = computed(() => Object.keys(OUTCOMES).filter((value) => value !== 'pledged' || props.inquiry?.response))
const outcome = ref(props.inquiry?.outcome ?? '')
const note = ref(props.inquiry?.outcomeNote ?? '')
const saving = ref(false)
const error = ref('')

async function save() {
	saving.value = true
	error.value = ''
	try {
		const inquiry = await setOutcome(props.campaignId, props.sponsor.id, outcome.value, note.value, props.replyId)
		showSuccess(`Status gespeichert: ${OUTCOMES[outcome.value].label}`)
		emit('saved', inquiry)
		emit('close')
	} catch (e) {
		error.value = errorMessage(e)
	} finally {
		saving.value = false
	}
}
</script>

<style scoped>
.dr-outcome {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 2);
}

.dr-outcome__hint {
	display: block;
	color: var(--color-text-maxcontrast);
	font-weight: normal;
}
</style>
