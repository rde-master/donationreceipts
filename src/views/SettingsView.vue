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

			<section>
				<h3>Unterschrift und Stempel</h3>
				<p class="dr-muted">
					PNG mit transparentem Hintergrund. Die Bilder liegen im geschützten Datenbereich der App
					und werden sofort gespeichert.
				</p>
				<ImageSetting kind="signature"
					label="Unterschrift"
					:present="images.signature"
					@changed="reload" />
				<ImageSetting kind="stamp"
					label="Stempel"
					:present="images.stamp"
					@changed="reload" />
			</section>

			<section>
				<h3>Ablage</h3>
				<p class="dr-muted">
					In beiden Hauptordnern legt die App je Spender einen Unterordner an. Alle Berechtigten
					brauchen dort Schreibrecht, z. B. über einen Team-Ordner.
				</p>
				<div v-for="(label, variant) in FOLDER_LABELS" :key="variant" class="dr-folder">
					<div>
						<strong>{{ label }}</strong>
						<div :class="folders[variant] ? '' : 'dr-muted'">
							{{ folders[variant] || 'nicht gewählt' }}
						</div>
					</div>
					<NcButton @click="pickFolder(variant, label)">
						Ordner wählen
					</NcButton>
				</div>
			</section>

			<section>
				<h3>Nummernkreis</h3>
				<p>Format <code>JJJJ-NNNN</code>, jedes Jahr ab 0001. Nächste Nummer: <strong>{{ nextNumber }}</strong></p>
			</section>
		</form>
	</div>
</template>

<script setup>
import { getFilePickerBuilder, showError, showSuccess } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { computed, onMounted, ref } from 'vue'
import { errorMessage, getSettings, setMainFolder, updateSettings } from '../api.js'
import ImageSetting from '../components/ImageSetting.vue'

const FOLDER_LABELS = {
	unsigned: 'Hauptordner „nicht unterschrieben“',
	signed: 'Hauptordner „unterschrieben“',
}

const loading = ref(true)
const saving = ref(false)
const values = ref({})
const missing = ref([])
const folders = ref({})
const images = ref({})
const nextNumber = ref('')

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
	folders.value = data.folders
	images.value = data.images
	nextNumber.value = data.nextNumber
}

async function reload() {
	try {
		const data = await getSettings()
		// keep unsaved form input, refresh the rest
		missing.value = data.missing
		folders.value = data.folders
		images.value = data.images
		nextNumber.value = data.nextNumber
	} catch (e) {
		showError(errorMessage(e))
	}
}

async function pickFolder(variant, label) {
	let picked = null
	const picker = getFilePickerBuilder(label + ' wählen')
		.setMultiSelect(false)
		.allowDirectories(true)
		.setMimeTypeFilter(['httpd/unix-directory'])
		// Without a selection the picker hands over the folder currently open.
		.setButtonFactory((nodes) => [{
			label: nodes[0]?.basename ? `„${nodes[0].basename}“ wählen` : 'Wählen',
			variant: 'primary',
			disabled: !nodes.length,
			callback: (selected) => { picked = selected[0] },
		}])
		.build()
	try {
		await picker.pickNodes()
	} catch {
		return // closed without picking
	}
	if (!picked) {
		return
	}
	try {
		const data = await setMainFolder(variant, picked.fileid)
		folders.value = data.folders
		missing.value = data.missing
		showSuccess('Ordner gespeichert')
	} catch (e) {
		showError(errorMessage(e))
	}
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
.dr-folder {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: calc(var(--default-grid-baseline) * 4);
}

section {
	margin-bottom: calc(var(--default-grid-baseline) * 6);
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 2);
}
</style>
