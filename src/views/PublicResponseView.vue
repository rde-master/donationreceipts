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

		<form class="dr-public__form" @submit.prevent="submit">
			<fieldset :disabled="!state.editable">
				<section>
					<h3>Angaben für die Zuwendungsbestätigung</h3>
					<div class="dr-row dr-radios">
						<NcCheckboxRadioSwitch v-model="form.kind"
							type="radio"
							value="company"
							name="kind">
							Firma
						</NcCheckboxRadioSwitch>
						<NcCheckboxRadioSwitch v-model="form.kind"
							type="radio"
							value="person"
							name="kind">
							Privatperson
						</NcCheckboxRadioSwitch>
					</div>
					<NcTextField v-if="form.kind === 'company'"
						v-model="form.company"
						label="Firmenname"
						required />
					<div v-else class="dr-row">
						<NcTextField v-model="form.firstName" label="Vorname" required />
						<NcTextField v-model="form.lastName" label="Nachname" required />
					</div>
					<NcTextField v-model="form.street" label="Straße und Hausnummer" required />
					<div class="dr-row">
						<NcTextField v-model="form.zip" label="PLZ" class="dr-zip" required />
						<NcTextField v-model="form.city" label="Ort" required />
					</div>
					<NcTextField v-model="form.country" label="Land (leer = Deutschland)" />
				</section>

				<section>
					<h3>Ihre Spende</h3>
					<div class="dr-row dr-radios">
						<NcCheckboxRadioSwitch v-model="form.type"
							type="radio"
							value="money"
							name="type">
							Geldspende
						</NcCheckboxRadioSwitch>
						<NcCheckboxRadioSwitch v-model="form.type"
							type="radio"
							value="goods"
							name="type">
							Sachspende
						</NcCheckboxRadioSwitch>
					</div>
					<NcTextField v-model="amountText"
						:label="form.type === 'goods' ? 'Wert der Sachspende in Euro' : 'Betrag in Euro'"
						placeholder="z. B. 250,00"
						:error="amountText !== '' && amountCents === null"
						:helper-text="amountText !== '' && amountCents === null ? 'Bitte einen gültigen Betrag eingeben' : ''"
						required />
					<template v-if="form.type === 'goods'">
						<NcTextArea v-model="form.goodsDescription"
							label="Was spenden Sie? (genaue Bezeichnung, ggf. Alter und Zustand)"
							resize="vertical"
							rows="3"
							required />
						<fieldset>
							<legend>Die Sachspende stammt aus dem</legend>
							<NcCheckboxRadioSwitch v-for="(label, value) in ORIGINS"
								:key="value"
								v-model="form.goodsOrigin"
								type="radio"
								:value="value"
								name="origin">
								{{ label }}
							</NcCheckboxRadioSwitch>
						</fieldset>
					</template>
				</section>

				<section>
					<h3>Sind Freikarten erwünscht?</h3>
					<NcCheckboxRadioSwitch v-for="(label, value) in state.tickets"
						:key="value"
						v-model="form.tickets"
						type="radio"
						:value="value"
						name="tickets">
						{{ label }}
					</NcCheckboxRadioSwitch>
				</section>

				<section>
					<h3>Dürfen wir Ihre Unterstützung öffentlich bekannt geben (Logo auf Plakaten)?</h3>
					<div class="dr-row dr-radios">
						<NcCheckboxRadioSwitch v-model="publish"
							type="radio"
							value="yes"
							name="publish">
							Ja
						</NcCheckboxRadioSwitch>
						<NcCheckboxRadioSwitch v-model="publish"
							type="radio"
							value="no"
							name="publish">
							Nein
						</NcCheckboxRadioSwitch>
					</div>
					<div v-if="publish === 'yes' && state.logoUpload" class="dr-logo">
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
				</section>

				<section>
					<h3>Hinweis</h3>
					<NcTextArea v-model="form.remarks"
						label="Möchten Sie uns noch etwas mitteilen? (optional)"
						resize="vertical"
						rows="3" />
				</section>
			</fieldset>

			<NcNoteCard v-if="error" type="error">
				{{ error }}
			</NcNoteCard>
			<div v-if="state.editable" class="dr-actions">
				<NcButton type="submit"
					variant="primary"
					:disabled="saving || !complete">
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
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { computed, ref } from 'vue'
import { errorMessage } from '../api.js'
import { amountForInput, formatDateTime, ORIGINS, parseAmount } from '../format.js'

const MAX_LOGO_BYTES = 10 * 1024 * 1024

const state = ref(loadState('donationreceipts', 'response'))
const initial = state.value.response
const form = ref({
	kind: initial.kind || 'company',
	firstName: initial.firstName,
	lastName: initial.lastName,
	company: initial.company,
	street: initial.street,
	zip: initial.zip,
	city: initial.city,
	country: initial.country,
	type: initial.type || 'money',
	goodsDescription: initial.goodsDescription,
	goodsOrigin: initial.goodsOrigin,
	tickets: initial.tickets,
	remarks: initial.remarks,
})
const amountText = ref(initial.amountCents ? amountForInput(initial.amountCents) : '')
const publish = ref(initial.publish === null ? '' : (initial.publish ? 'yes' : 'no'))
const logo = ref(null)
const logoInput = ref(null)
const saving = ref(false)
const saved = ref(false)
const error = ref('')
const logoWarning = ref('')

const amountCents = computed(() => parseAmount(amountText.value))
const complete = computed(() => amountCents.value !== null && form.value.tickets !== '' && publish.value !== ''
	&& (form.value.type !== 'goods' || form.value.goodsOrigin !== ''))

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
	data.append('data', JSON.stringify({
		...form.value,
		amountCents: amountCents.value,
		publish: publish.value === 'yes',
	}))
	if (logo.value && publish.value === 'yes') {
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

.dr-public__form section {
	margin-bottom: calc(var(--default-grid-baseline) * 6);
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 2);
}

.dr-public__form fieldset {
	border: none;
	padding: 0;
	margin: 0;
}

.dr-radios > * {
	flex: 0 0 auto;
}

.dr-logo {
	display: flex;
	flex-direction: column;
	gap: var(--default-grid-baseline);
}
</style>
