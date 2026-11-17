<template>
	<form class="dr-shell" @submit.prevent="submit">
		<div class="dr-shell__scroll">
			<div class="dr-public">
				<header class="dr-public__card dr-public__hero">
					<p class="dr-public__org">
						{{ state.org }}
					</p>
					<h2>{{ state.campaign }}</h2>
					<p class="dr-public__sponsor">
						Rückmeldung für <strong>{{ state.sponsor }}</strong>
					</p>
					<p v-if="state.intro" class="dr-pre dr-public__intro">
						{{ state.intro }}
					</p>
				</header>

				<div class="dr-public__notes">
					<NcNoteCard v-if="state.received" type="success">
						Ihre Spende ist eingegangen – vielen Dank! Die Zuwendungsbestätigung haben wir Ihnen per E-Mail
						zugesandt. Änderungen sind nicht mehr möglich.
					</NcNoteCard>
					<NcNoteCard v-else-if="state.closed" type="info">
						Die Aktion ist beendet; Rückmeldungen sind nicht mehr möglich.
					</NcNoteCard>
					<NcNoteCard v-else-if="declinedBefore" type="info">
						Sie haben uns mitgeteilt: {{ ANSWERS[state.outcome].short }}. Falls Sie es sich anders überlegen,
						können Sie Ihre Antwort hier ändern.
					</NcNoteCard>
					<NcNoteCard v-else-if="state.respondedAt" type="info">
						Ihre Rückmeldung vom {{ formatDateTime(state.respondedAt) }} liegt uns vor. Sie können sie hier
						ändern, bis Ihre Spende eingegangen ist.
					</NcNoteCard>
				</div>

				<section v-if="state.editable" class="dr-public__card dr-public__answer">
					<h3>Ihre Antwort</h3>
					<NcCheckboxRadioSwitch v-for="(option, value) in ANSWERS"
						:key="value"
						v-model="answer"
						type="radio"
						:value="value"
						name="dr-answer">
						{{ option.label.replace('{aktion}', state.campaign) }}
					</NcCheckboxRadioSwitch>
				</section>

				<section v-if="answer !== 'yes'" class="dr-public__card dr-public__decline">
					<h3>{{ answer === 'no_contact' ? 'Keine Anfragen mehr' : 'Diesmal nicht' }}</h3>
					<p class="dr-muted">
						{{ answer === 'no_contact'
							? 'Schade! Wir nehmen Sie aus unserem Verteiler und schreiben Sie nicht mehr an.'
							: 'Schade – vielleicht klappt es beim nächsten Mal. Für diese Aktion fragen wir nicht mehr nach.' }}
					</p>
					<NcTextArea v-model="message"
						label="Möchten Sie uns noch etwas mitteilen? (optional)"
						resize="vertical"
						rows="3"
						:disabled="!state.editable" />
				</section>

				<div v-else class="dr-public__form">
					<ResponseFields v-model="form" :tickets="state.tickets" :disabled="!state.editable">
						<template #publish>
							<div v-if="form.publish === 'yes' && state.logoUpload" class="dr-logo">
								<label for="dr-logo-input">
									Logo hochladen (PNG, JPG, SVG oder PDF, höchstens 10 MB) – optional
								</label>
								<input id="dr-logo-input"
									ref="logoInput"
									type="file"
									accept=".png,.jpg,.jpeg,.svg,.pdf,image/png,image/jpeg,image/svg+xml,application/pdf"
									@change="pickLogo">
								<p v-if="state.hasLogo" class="dr-muted">
									Ihr Logo liegt uns bereits vor. Eine neue Datei ersetzt es.
								</p>
							</div>
						</template>
					</ResponseFields>
				</div>
			</div>
		</div>

		<div v-if="state.editable" class="dr-shell__bar">
			<!-- results of saving right above the button, where the user looks -->
			<div v-if="saved || error || logoWarning" class="dr-shell__messages">
				<NcNoteCard v-if="saved && answer !== 'yes'" type="success">
					Vielen Dank für Ihre Antwort – wir haben uns notiert: {{ ANSWERS[answer].short }}. Sie können sie
					über diesen Link jederzeit ändern.
				</NcNoteCard>
				<NcNoteCard v-else-if="saved" type="success">
					Vielen Dank! Ihre Angaben sind gespeichert. Sie erhalten eine Bestätigung per E-Mail und können
					Ihre Angaben über diesen Link ändern, bis Ihre Spende eingegangen ist.
				</NcNoteCard>
				<NcNoteCard v-if="logoWarning" type="warning">
					{{ logoWarning }}
				</NcNoteCard>
				<NcNoteCard v-if="error" type="error">
					{{ error }}
				</NcNoteCard>
			</div>
			<div class="dr-shell__bar-inner">
				<p class="dr-public__missing" :class="{ 'dr-public__missing--ok': missing.length === 0 }">
					<template v-if="answer !== 'yes'">
						{{ ANSWERS[answer].short }}
					</template>
					<template v-else-if="missing.length">
						Noch offen: {{ missing.join(', ') }}
					</template>
					<template v-else>
						Alle Angaben vollständig.
					</template>
				</p>
				<NcButton type="submit"
					variant="primary"
					size="large"
					:disabled="saving || missing.length > 0">
					<template #icon>
						<NcLoadingIcon v-if="saving" :size="20" />
					</template>
					{{ buttonLabel }}
				</NcButton>
			</div>
		</div>
	</form>
</template>

<script setup>
import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import { computed, ref, watch } from 'vue'
import { errorMessage } from '../api.js'
import ResponseFields from '../components/ResponseFields.vue'
import { formatDateTime } from '../format.js'
import { responseMissing, responseForm, responsePayload } from '../responseForm.js'

const MAX_LOGO_BYTES = 10 * 1024 * 1024

// the sponsor's answer; the declines need no data
const ANSWERS = {
	yes: { label: 'Ja, wir unterstützen {aktion}', short: 'Zusage' },
	declined: { label: 'Diesmal leider nicht', short: 'diesmal nicht' },
	no_contact: { label: 'Bitte keine Anfragen mehr', short: 'bitte keine Anfragen mehr' },
}

const state = ref(loadState('donationreceipts', 'response'))
const form = ref(responseForm(state.value.response))
const logo = ref(null)
const logoInput = ref(null)
const saving = ref(false)
const saved = ref(false)
const error = ref('')
const logoWarning = ref('')
const answer = ref(ANSWERS[state.value.outcome] && state.value.outcome !== 'pledged' ? state.value.outcome : 'yes')
const message = ref(state.value.outcomeNote ?? '')

// the sponsor said no before (and has not changed it in this visit)
const declinedBefore = computed(() => !saved.value && ['declined', 'no_contact'].includes(state.value.outcome))

const buttonLabel = computed(() => {
	if (answer.value !== 'yes') {
		return answer.value === state.value.outcome ? 'Antwort aktualisieren' : 'Antwort senden'
	}
	return state.value.respondedAt ? 'Änderungen speichern' : 'Rückmeldung absenden'
})

// what still keeps the submit button disabled, shown next to it
const missing = computed(() => answer.value === 'yes' ? responseMissing(form.value) : [])

// a new change is not saved yet
watch([form, answer, message], () => {
	saved.value = false
}, { deep: true })

function pickLogo(event) {
	const file = event.target.files?.[0] ?? null
	error.value = ''
	if (file && file.size > MAX_LOGO_BYTES) {
		error.value = 'Das Logo ist größer als 10 MB'
		event.target.value = ''
		logo.value = null
		return
	}
	logo.value = file
}

async function submit() {
	saving.value = true
	error.value = ''
	logoWarning.value = ''
	const data = new FormData()
	data.append('data', JSON.stringify(answer.value === 'yes'
		? responsePayload(form.value)
		: { answer: answer.value, message: message.value }))
	if (answer.value === 'yes' && logo.value && form.value.publish === 'yes') {
		data.append('logo', logo.value)
	}
	try {
		const result = (await axios.post(generateUrl(`/apps/donationreceipts/r/${state.value.token}`), data)).data
		state.value = { ...state.value, ...result }
		logoWarning.value = result.logoWarning ?? ''
		saved.value = true
		logo.value = null
		if (logoInput.value) {
			logoInput.value.value = ''
		}
	} catch (e) {
		error.value = errorMessage(e)
	} finally {
		saving.value = false
	}
}
</script>

<style scoped>
.dr-shell {
	display: flex;
	flex-direction: column;
	height: 100%;
	width: 100%;
}

.dr-shell__scroll {
	flex: 1 1 auto;
	min-height: 0;
	overflow-y: auto;
	overflow-x: hidden;
}

.dr-public {
	--dr-gap: calc(var(--default-grid-baseline) * 4);
	max-width: 760px;
	margin: 0 auto;
	box-sizing: border-box;
	padding: calc(var(--default-grid-baseline) * 6) var(--dr-gap);
	display: flex;
	flex-direction: column;
	gap: var(--dr-gap);
}

.dr-public__card,
.dr-public :deep(.dr-response-fields section) {
	background: var(--color-main-background);
	color: var(--color-main-text);
	border-radius: var(--border-radius-container-large, 16px);
	box-shadow: 0 1px 4px var(--color-box-shadow);
	padding: calc(var(--default-grid-baseline) * 5) calc(var(--default-grid-baseline) * 6);
}

.dr-public__hero {
	border-top: 6px solid var(--color-primary-element);
}

.dr-public__hero h2 {
	margin: 0;
	font-size: 26px;
	line-height: 1.3;
}

.dr-public__org {
	margin: 0 0 var(--default-grid-baseline);
	color: var(--color-text-maxcontrast);
	text-transform: uppercase;
	letter-spacing: .05em;
	font-size: var(--font-size-small, 13px);
}

.dr-public__sponsor {
	margin: var(--default-grid-baseline) 0 0;
	color: var(--color-text-maxcontrast);
}

.dr-public__intro {
	margin: calc(var(--default-grid-baseline) * 4) 0 0;
	padding-top: calc(var(--default-grid-baseline) * 4);
	border-top: 1px solid var(--color-border);
	line-height: 1.6;
}

.dr-public__notes:empty {
	display: none;
}

.dr-public__notes :deep(.notecard) {
	margin: 0;
}

.dr-public__form {
	display: flex;
	flex-direction: column;
	gap: var(--dr-gap);
}

.dr-public :deep(.dr-response-fields section) {
	margin-bottom: var(--dr-gap);
}

.dr-public :deep(.dr-response-fields section:last-child) {
	margin-bottom: 0;
}

.dr-public :deep(.dr-response-fields h3) {
	margin: 0 0 var(--default-grid-baseline);
	font-size: 18px;
	font-weight: bold;
}

/* the bar sits below the scroll area, so it never covers a field */
.dr-shell__bar {
	flex: 0 0 auto;
	background: var(--color-main-background);
	border-top: 1px solid var(--color-border);
	box-shadow: 0 -2px 8px var(--color-box-shadow);
}

.dr-shell__messages {
	max-width: 760px;
	margin: 0 auto;
	box-sizing: border-box;
	padding: calc(var(--default-grid-baseline) * 3) calc(var(--default-grid-baseline) * 4) 0;
}

.dr-shell__messages :deep(.notecard) {
	margin: 0 0 var(--default-grid-baseline);
}

.dr-shell__bar-inner {
	max-width: 760px;
	margin: 0 auto;
	box-sizing: border-box;
	display: flex;
	align-items: center;
	justify-content: space-between;
	flex-wrap: wrap;
	gap: calc(var(--default-grid-baseline) * 3);
	padding: calc(var(--default-grid-baseline) * 3) calc(var(--default-grid-baseline) * 4);
}

.dr-public__missing {
	margin: 0;
	flex: 1 1 260px;
	color: var(--color-text-maxcontrast);
}

.dr-public__missing--ok {
	color: var(--color-success-text);
}

.dr-public__answer,
.dr-public__decline {
	display: flex;
	flex-direction: column;
	gap: var(--default-grid-baseline);
}

.dr-public__answer h3,
.dr-public__decline h3 {
	margin: 0 0 var(--default-grid-baseline);
	font-size: 18px;
	font-weight: bold;
}

.dr-public__decline p {
	margin: 0 0 calc(var(--default-grid-baseline) * 2);
}

.dr-logo {
	display: flex;
	flex-direction: column;
	gap: var(--default-grid-baseline);
}

@media (max-width: 600px) {
	.dr-public__card,
	.dr-public :deep(.dr-response-fields section) {
		padding: calc(var(--default-grid-baseline) * 4);
	}

	.dr-shell__bar-inner :deep(.button-vue) {
		width: 100%;
	}
}
</style>
