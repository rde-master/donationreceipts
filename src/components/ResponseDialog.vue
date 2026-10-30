<template>
	<NcDialog :name="`Rückmeldung von ${sponsor.name}`"
		size="large"
		@closing="$emit('close')">
		<div class="dr-response">
			<dl class="dr-facts">
				<dt>Eingegangen</dt>
				<dd>{{ formatDateTime(inquiry.respondedAt) }}</dd>
				<dt>Name und Anschrift</dt>
				<dd>
					<div v-for="line in addressLines" :key="line">
						{{ line }}
					</div>
				</dd>
				<dt>{{ response.type === 'goods' ? 'Sachspende' : 'Geldspende' }}</dt>
				<dd>
					{{ formatAmount(response.amountCents) }}
					<template v-if="response.type === 'goods'">
						<div class="dr-pre">
							{{ response.goodsDescription }}
						</div>
						<div class="dr-muted">
							{{ ORIGINS[response.goodsOrigin] }}
						</div>
					</template>
				</dd>
				<dt>Freikarten</dt>
				<dd>{{ ticketLabels[response.tickets] }}</dd>
				<dt>Öffentlich bekannt geben</dt>
				<dd>
					{{ response.publish ? 'Ja (Logo auf Plakaten)' : 'Nein' }}
					<a v-if="inquiry.hasLogo" :href="logoUrl(inquiry.id)">Logo herunterladen</a>
				</dd>
				<template v-if="response.remarks">
					<dt>Hinweis</dt>
					<dd class="dr-pre">
						{{ response.remarks }}
					</dd>
				</template>
			</dl>

			<NcNoteCard v-if="receipt" :type="mailError ? 'warning' : 'success'">
				Bescheinigung {{ receipt.number }} erstellt
				<template v-if="mailError">
					– der Versand ist aber fehlgeschlagen: {{ mailError }}. Sende sie aus der Bescheinigung erneut.
				</template>
				<template v-else>
					und an {{ receipt.sentTo }} gesendet.
				</template>
				<RouterLink :to="{ name: 'receipt', params: { id: receipt.id } }">
					Bescheinigung öffnen
				</RouterLink>
			</NcNoteCard>
			<NcNoteCard v-else-if="inquiry.receiptId" type="success">
				Die Spende ist eingegangen.
				<RouterLink :to="{ name: 'receipt', params: { id: inquiry.receiptId } }">
					Bescheinigung öffnen
				</RouterLink>
			</NcNoteCard>

			<form v-else
				id="dr-receive-form"
				class="dr-receive"
				@submit.prevent="submit">
				<h3>Spende eingegangen</h3>
				<p class="dr-muted">
					Erstellt die Bescheinigung aus der Rückmeldung und sendet sie sofort per E-Mail.
				</p>
				<NcLoadingIcon v-if="!donors" :size="24" />
				<template v-else>
					<NcSelect v-model="donor"
						:options="donorOptions"
						label="displayName"
						input-label="Spender"
						:clearable="false"
						:label-outside="false" />
					<NcCheckboxRadioSwitch v-if="donor.id !== null && addressDiffers" v-model="updateDonor">
						Name und Anschrift des Spenders aus der Rückmeldung übernehmen
						<span class="dr-muted">(bisher: {{ donor.displayName }}, {{ [donor.street, [donor.zip, donor.city].filter(Boolean).join(' ')].filter(Boolean).join(', ') }})</span>
					</NcCheckboxRadioSwitch>
				</template>

				<div class="dr-row dr-radios">
					<NcCheckboxRadioSwitch v-model="form.type"
						type="radio"
						value="money"
						name="type">
						Geldzuwendung
					</NcCheckboxRadioSwitch>
					<NcCheckboxRadioSwitch v-model="form.type"
						type="radio"
						value="goods"
						name="type">
						Sachzuwendung
					</NcCheckboxRadioSwitch>
				</div>
				<div class="dr-row">
					<NcTextField v-model="amountText"
						:label="form.type === 'goods' ? 'Wert in Euro' : 'Eingegangener Betrag in Euro'"
						:error="amountCents === null"
						:helper-text="amountCents === null ? 'Ungültiger Betrag' : ''"
						required />
					<NcTextField v-model="form.donationDate"
						type="date"
						label="Tag der Zuwendung"
						required />
					<NcTextField v-model="form.issueDate"
						type="date"
						label="Ausstellungsdatum"
						required />
				</div>
				<NcCheckboxRadioSwitch v-if="form.type === 'money'" v-model="form.expenseWaiver">
					Es handelt sich um den Verzicht auf Erstattung von Aufwendungen
				</NcCheckboxRadioSwitch>
				<template v-else>
					<NcTextArea v-model="form.goodsDescription"
						label="Genaue Bezeichnung mit Alter, Zustand, Kaufpreis usw."
						resize="vertical"
						rows="3"
						required />
					<fieldset>
						<legend>Herkunft nach Angaben des Zuwendenden</legend>
						<NcCheckboxRadioSwitch v-for="(label, value) in ORIGINS"
							:key="value"
							v-model="form.goodsOrigin"
							type="radio"
							:value="value"
							name="origin">
							{{ label }}
						</NcCheckboxRadioSwitch>
					</fieldset>
					<NcCheckboxRadioSwitch v-model="form.goodsValuationDocuments">
						Unterlagen zur Wertermittlung (z. B. Rechnung, Gutachten) liegen vor
					</NcCheckboxRadioSwitch>
				</template>
				<NcNoteCard v-if="error" type="error">
					{{ error }}
				</NcNoteCard>
			</form>
		</div>

		<template #actions>
			<NcButton @click="$emit('close')">
				Schließen
			</NcButton>
			<NcButton v-if="!inquiry.receiptId && !receipt"
				type="submit"
				form="dr-receive-form"
				variant="primary"
				:disabled="saving || !donors || amountCents === null">
				<template #icon>
					<NcLoadingIcon v-if="saving" :size="20" />
					<NcIconSvgWrapper v-else :path="mdiCheck" />
				</template>
				Bescheinigung erstellen und senden
			</NcButton>
		</template>
	</NcDialog>
</template>

<script setup>
import { mdiCheck } from '@mdi/js'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { computed, onMounted, ref, watch } from 'vue'
import { errorMessage, listDonors, logoUrl, receiveDonation } from '../api.js'
import { amountForInput, formatAmount, formatDateTime, ORIGINS, parseAmount, todayIso } from '../format.js'

const props = defineProps({
	inquiry: { type: Object, required: true },
	sponsor: { type: Object, required: true },
	ticketLabels: { type: Object, required: true },
})

const emit = defineEmits(['close', 'received'])

const NEW_DONOR = { id: null, displayName: 'Neuen Spender aus der Rückmeldung anlegen' }
const ADDRESS_FIELDS = ['kind', 'firstName', 'lastName', 'company', 'street', 'zip', 'city', 'country']

const response = computed(() => props.inquiry.response)
const donors = ref(null)
const donor = ref(NEW_DONOR)
const updateDonor = ref(true)
const saving = ref(false)
const error = ref('')
const receipt = ref(null)
const mailError = ref(null)
const amountText = ref(amountForInput(response.value.amountCents))
const form = ref({
	type: response.value.type,
	donationDate: todayIso(),
	issueDate: todayIso(),
	expenseWaiver: false,
	goodsDescription: response.value.goodsDescription,
	goodsOrigin: response.value.goodsOrigin || 'unknown',
	goodsValuationDocuments: false,
})

const amountCents = computed(() => parseAmount(amountText.value))
const donorOptions = computed(() => [NEW_DONOR, ...(donors.value ?? [])])
const addressLines = computed(() => [
	response.value.displayName,
	response.value.street,
	[response.value.zip, response.value.city].filter(Boolean).join(' '),
	response.value.country,
].filter(Boolean))
const addressDiffers = computed(() => ADDRESS_FIELDS.some((f) => (donor.value[f] ?? '') !== (response.value[f] ?? '')))

// the receipt date can't lie before the donation
watch(() => form.value.donationDate, (date) => {
	if (date > form.value.issueDate) {
		form.value.issueDate = date
	}
})

onMounted(async () => {
	if (props.inquiry.receiptId) {
		return
	}
	try {
		donors.value = await listDonors()
		donor.value = donors.value.find((d) => d.id === props.sponsor.donorId)
			?? donors.value.find((d) => d.email && d.email.toLowerCase() === props.sponsor.email.toLowerCase())
			?? NEW_DONOR
	} catch (e) {
		error.value = errorMessage(e)
	}
})

async function submit() {
	saving.value = true
	error.value = ''
	try {
		const result = await receiveDonation(props.inquiry.id, {
			...form.value,
			amountCents: amountCents.value,
			donorId: donor.value.id,
			updateDonor: donor.value.id !== null && addressDiffers.value && updateDonor.value,
		})
		receipt.value = result.receipt
		mailError.value = result.mailError
		emit('received', result)
	} catch (e) {
		error.value = errorMessage(e)
	} finally {
		saving.value = false
	}
}
</script>

<style scoped>
.dr-response,
.dr-receive {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 2);
}

.dr-radios > * {
	flex: 0 0 auto;
}
</style>
