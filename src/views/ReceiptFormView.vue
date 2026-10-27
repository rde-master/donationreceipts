<template>
	<div class="dr-page">
		<h2>{{ id ? `Bescheinigung ${original?.number ?? ''} bearbeiten` : 'Neue Bescheinigung' }}</h2>
		<p v-if="donor" class="dr-muted">
			für {{ donor.displayName }}
		</p>
		<NcLoadingIcon v-if="loading" :size="32" />
		<form v-else class="dr-form" @submit.prevent="submit">
			<NcNoteCard v-if="original?.sentAt" type="warning">
				Diese Bescheinigung wurde am {{ formatDateTime(original.sentAt) }} an {{ original.sentTo }} versendet.
				Änderungen ersetzen die abgelegten PDFs; der Spender hat dann eine veraltete Fassung.
			</NcNoteCard>

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
					:label="form.type === 'goods' ? 'Wert in Euro' : 'Betrag in Euro'"
					placeholder="z. B. 150,00"
					:error="amountText !== '' && amountCents === null"
					:helper-text="amountText !== '' && amountCents === null ? 'Ungültiger Betrag' : ''"
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

			<template v-if="form.type === 'money'">
				<NcCheckboxRadioSwitch v-model="form.expenseWaiver">
					Es handelt sich um den Verzicht auf Erstattung von Aufwendungen
				</NcCheckboxRadioSwitch>
			</template>

			<template v-else>
				<NcTextArea v-model="form.goodsDescription"
					label="Genaue Bezeichnung mit Alter, Zustand, Kaufpreis usw."
					resize="vertical"
					rows="4"
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
				<NcNoteCard v-if="form.goodsOrigin === 'business'" type="info">
					Bei Betriebsvermögen ist der Entnahmewert (ggf. mit dem niedrigeren gemeinen Wert)
					zuzüglich Umsatzsteuer anzugeben.
				</NcNoteCard>
				<NcCheckboxRadioSwitch v-model="form.goodsValuationDocuments">
					Unterlagen zur Wertermittlung (z. B. Rechnung, Gutachten) liegen vor
				</NcCheckboxRadioSwitch>
			</template>

			<div class="dr-actions">
				<NcButton type="submit" variant="primary" :disabled="saving || amountCents === null">
					{{ id ? 'Speichern' : 'Bescheinigung erstellen' }}
				</NcButton>
				<NcButton @click="$router.back()">
					Abbrechen
				</NcButton>
			</div>
		</form>

		<NcDialog v-if="confirmSent"
			name="Bereits versendet"
			message="Diese Bescheinigung wurde schon versendet. Trotzdem ändern?"
			:buttons="confirmButtons"
			@closing="confirmSent = false" />
	</div>
</template>

<script setup>
import { showError, showSuccess } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { createReceipt, errorMessage, getDonor, getReceipt, listReceipts, updateReceipt } from '../api.js'
import { amountForInput, formatDateTime, ORIGINS, parseAmount } from '../format.js'

const props = defineProps({
	// edit an existing receipt ...
	id: { type: [String, Number], default: null },
	// ... or create one for this donor
	donorId: { type: [String, Number], default: null },
})

const router = useRouter()
const loading = ref(true)
const saving = ref(false)
const confirmSent = ref(false)
const donor = ref(null)
const original = ref(null)
const amountText = ref('')
const form = ref({
	type: 'money',
	donationDate: '',
	issueDate: '',
	expenseWaiver: false,
	goodsDescription: '',
	goodsOrigin: 'private',
	goodsValuationDocuments: false,
})

const amountCents = computed(() => parseAmount(amountText.value))

const confirmButtons = [
	{ label: 'Abbrechen' },
	{ label: 'Trotzdem ändern', variant: 'primary', callback: save },
]

onMounted(async () => {
	try {
		if (props.id) {
			original.value = await getReceipt(props.id)
			form.value = { ...form.value, ...original.value, goodsOrigin: original.value.goodsOrigin || 'private' }
			amountText.value = amountForInput(original.value.amountCents)
			donor.value = await getDonor(original.value.donorId)
		} else {
			donor.value = await getDonor(props.donorId)
			form.value.issueDate = (await listReceipts({ donorId: props.donorId })).today
		}
	} catch (e) {
		showError(errorMessage(e))
	} finally {
		loading.value = false
	}
})

function submit() {
	if (original.value?.sentAt) {
		confirmSent.value = true
		return
	}
	save()
}

async function save() {
	saving.value = true
	const payload = { ...form.value, amountCents: amountCents.value }
	try {
		const saved = props.id
			? await updateReceipt(props.id, payload)
			: await createReceipt(props.donorId, payload)
		showSuccess(props.id ? 'Bescheinigung gespeichert' : `Bescheinigung ${saved.number} erstellt`)
		router.push({ name: 'receipt', params: { id: saved.id } })
	} catch (e) {
		showError(errorMessage(e))
	} finally {
		saving.value = false
	}
}
</script>

<style scoped>
.dr-form {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 2);
}

.dr-radios > * {
	flex: 0 0 auto;
}

fieldset legend {
	color: var(--color-text-maxcontrast);
	margin-bottom: var(--default-grid-baseline);
}
</style>
