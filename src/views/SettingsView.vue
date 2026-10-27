<template>
	<div class="dr-page">
		<h2>Einstellungen</h2>
		<NcLoadingIcon v-if="loading" :size="32" />
		<form v-else class="dr-form" @submit.prevent="save">
			<NcNoteCard v-if="missing.length" type="warning">
				Für Bescheinigungen fehlen noch: {{ missing.join(', ') }}
			</NcNoteCard>

			<section>
				<h3>Verein (Zuwendungsempfänger)</h3>
				<NcTextField v-model="values.org_name" label="Name" />
				<NcTextField v-model="values.org_street" label="Straße und Hausnummer" />
				<div class="dr-row">
					<NcTextField v-model="values.org_zip" label="PLZ" class="dr-zip" />
					<NcTextField v-model="values.org_city" label="Ort" />
				</div>
			</section>

			<section>
				<h3>Steuerbegünstigung</h3>
				<NcCheckboxRadioSwitch v-model="values.tax_mode"
					type="radio"
					value="exemption"
					name="tax_mode">
					Freistellungsbescheid bzw. Anlage zum Körperschaftsteuerbescheid
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch v-model="values.tax_mode"
					type="radio"
					value="statute"
					name="tax_mode">
					Feststellung der satzungsmäßigen Voraussetzungen nach § 60a AO
				</NcCheckboxRadioSwitch>
				<div class="dr-row">
					<NcTextField v-model="values.tax_office" label="Finanzamt" />
					<NcTextField v-model="values.tax_number" label="Steuernummer" />
				</div>
				<div v-if="values.tax_mode === 'exemption'" class="dr-row">
					<NcTextField v-model="values.exemption_date" type="date" label="Datum des Bescheids" />
					<NcTextField v-model="values.exemption_period"
						label="Letzter Veranlagungszeitraum"
						placeholder="z. B. 2022 bis 2024" />
				</div>
				<NcTextField v-else
					v-model="values.statute_date"
					type="date"
					label="Datum des Feststellungsbescheids" />
				<NcNoteCard v-if="bescheidAgeWarning" type="warning">
					{{ bescheidAgeWarning }}
				</NcNoteCard>
			</section>

			<section>
				<h3>Begünstigte Zwecke</h3>
				<NcTextArea v-model="values.purposes"
					label="Zwecke, wie sie im Bescheid stehen"
					resize="vertical"
					rows="3" />
				<NcCheckboxRadioSwitch v-model="values.membership_fees_not_deductible">
					Mitgliedsbeiträge sind steuerlich nicht abziehbar (z. B. Sportverein) –
					auf Geldzuwendungen wird „kein Mitgliedsbeitrag“ angekreuzt
				</NcCheckboxRadioSwitch>
			</section>

			<section>
				<h3>Unterzeichnung</h3>
				<NcTextField v-model="values.sign_place" label="Ort" />
				<div class="dr-row">
					<NcTextField v-model="values.signer_name" label="Name der unterzeichnenden Person" />
					<NcTextField v-model="values.signer_function" label="Funktion" placeholder="z. B. 1. Vorsitzende" />
				</div>
			</section>

			<div class="dr-actions">
				<NcButton type="submit" variant="primary" :disabled="saving">
					Speichern
				</NcButton>
			</div>
		</form>
	</div>
</template>

<script setup>
import { showError, showSuccess } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { computed, onMounted, ref } from 'vue'
import { errorMessage, getSettings, updateSettings } from '../api.js'

const loading = ref(true)
const saving = ref(false)
const values = ref({})
const missing = ref([])

// § 63 Abs. 5 AO: Freistellungsbescheid max. 5, Feststellung nach § 60a max. 3 Jahre alt.
const bescheidAgeWarning = computed(() => {
	const statute = values.value.tax_mode === 'statute'
	const date = statute ? values.value.statute_date : values.value.exemption_date
	if (!date) {
		return ''
	}
	const limit = new Date(date)
	limit.setFullYear(limit.getFullYear() + (statute ? 3 : 5))
	if (limit > new Date()) {
		return ''
	}
	return `Der Bescheid ist älter als ${statute ? 3 : 5} Jahre. Zuwendungsbestätigungen werden damit steuerlich nicht anerkannt (§ 63 Abs. 5 AO).`
})

function apply(data) {
	values.value = { ...data.values }
	missing.value = data.missing
}

onMounted(async () => {
	try {
		apply(await getSettings())
	} catch (e) {
		showError(errorMessage(e))
	} finally {
		loading.value = false
	}
})

async function save() {
	saving.value = true
	try {
		apply(await updateSettings(values.value))
		showSuccess('Einstellungen gespeichert')
	} catch (e) {
		showError(errorMessage(e))
	} finally {
		saving.value = false
	}
}
</script>

<style scoped>
section {
	margin-bottom: calc(var(--default-grid-baseline) * 6);
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 2);
}
</style>
