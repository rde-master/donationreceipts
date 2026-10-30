<template>
	<div class="dr-public">
		<h2>{{ state.campaign }}</h2>
		<p class="dr-muted">
			Rückmeldung für {{ state.sponsor }}
		</p>
		<p class="dr-pre dr-intro">
			{{ state.intro }}
		</p>

		<NcNoteCard v-if="state.received" type="success">
			Ihre Spende ist eingegangen – vielen Dank! Die Zuwendungsbestätigung haben wir Ihnen per E-Mail
			zugesandt. Änderungen sind nicht mehr möglich.
		</NcNoteCard>
		<NcNoteCard v-else-if="state.closed" type="info">
			Die Aktion ist beendet; Rückmeldungen sind nicht mehr möglich.
		</NcNoteCard>
		<NcNoteCard v-else-if="saved" type="success">
			Vielen Dank! Ihre Angaben sind gespeichert. Sie erhalten eine Bestätigung per E-Mail und können
			Ihre Angaben über diesen Link ändern, bis Ihre Spende eingegangen ist.
		</NcNoteCard>
		<NcNoteCard v-else-if="state.respondedAt" type="info">
			Ihre Rückmeldung vom {{ formatDateTime(state.respondedAt) }} liegt uns vor. Sie können sie hier
			ändern, bis Ihre Spende eingegangen ist.
		</NcNoteCard>
		<NcNoteCard v-if="logoWarning" type="warning">
			{{ logoWarning }}
		</NcNoteCard>

		<form @submit.prevent="submit">
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

			<NcNoteCard v-if="error" type="error">
				{{ error }}
			</NcNoteCard>
			<div v-if="state.editable" class="dr-actions">
				<NcButton type="submit"
					variant="primary"
					:disabled="saving || !responseComplete(form)">
					<template #icon>
						<NcLoadingIcon v-if="saving" :size="20" />
					</template>
					{{ state.respondedAt ? 'Änderungen speichern' : 'Absenden' }}
				</NcButton>
			</div>
		</form>
	</div>
</template>

<script setup>
import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import { ref } from 'vue'
import { errorMessage } from '../api.js'
import ResponseFields from '../components/ResponseFields.vue'
import { formatDateTime } from '../format.js'
import { responseComplete, responseForm, responsePayload } from '../responseForm.js'

const MAX_LOGO_BYTES = 10 * 1024 * 1024

const state = ref(loadState('donationreceipts', 'response'))
const form = ref(responseForm(state.value.response))
const logo = ref(null)
const logoInput = ref(null)
const saving = ref(false)
const saved = ref(false)
const error = ref('')
const logoWarning = ref('')

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
	data.append('data', JSON.stringify(responsePayload(form.value)))
	if (logo.value && form.value.publish === 'yes') {
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
		window.scrollTo({ top: 0, behavior: 'smooth' })
	} catch (e) {
		error.value = errorMessage(e)
	} finally {
		saving.value = false
	}
}
</script>

<style scoped>
.dr-public {
	max-width: 700px;
	margin: 0 auto;
	padding: calc(var(--default-grid-baseline) * 6) calc(var(--default-grid-baseline) * 4);
}

.dr-intro {
	margin: calc(var(--default-grid-baseline) * 4) 0;
}

.dr-logo {
	display: flex;
	flex-direction: column;
	gap: var(--default-grid-baseline);
}
</style>
