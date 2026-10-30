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
				<NcTextField v-model="values.org_function" label="Funktionszeile (optional)" />
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

			<section>
				<h3>E-Mail-Versand (SMTP)</h3>
				<p class="dr-muted">
					Eigener Zugang, unabhängig vom Mailserver der Nextcloud. Zugangsdaten werden nur über
					eine verschlüsselte Verbindung gesendet.
				</p>
				<div class="dr-row">
					<NcTextField v-model="values.smtp_host" label="Server" placeholder="smtp.example.org" />
					<NcTextField v-model="smtpPort"
						type="number"
						label="Port"
						class="dr-zip" />
				</div>
				<div class="dr-row dr-radios">
					<NcCheckboxRadioSwitch v-model="values.smtp_security"
						type="radio"
						value="starttls"
						name="smtp_security"
						@update:model-value="values.smtp_port = 587">
						STARTTLS (Port 587)
					</NcCheckboxRadioSwitch>
					<NcCheckboxRadioSwitch v-model="values.smtp_security"
						type="radio"
						value="ssl"
						name="smtp_security"
						@update:model-value="values.smtp_port = 465">
						SSL/TLS (Port 465)
					</NcCheckboxRadioSwitch>
				</div>
				<div class="dr-row">
					<NcTextField v-model="values.smtp_user" label="Benutzer" autocomplete="off" />
					<NcPasswordField v-model="smtpPassword"
						label="Passwort"
						autocomplete="new-password"
						:placeholder="smtpPasswordSet ? 'gespeichert – leer lassen zum Behalten' : ''" />
				</div>
				<div class="dr-row">
					<NcTextField v-model="values.smtp_from_name" label="Absendername" />
					<NcTextField v-model="values.smtp_from_email" type="email" label="Absenderadresse" />
				</div>
				<div class="dr-row">
					<NcTextField v-model="testAddress" type="email" label="Testmail an" />
					<NcButton :disabled="testing || !testAddress" @click="sendTest('')">
						Speichern und Testmail senden
					</NcButton>
				</div>
			</section>

			<section>
				<h3>E-Mail-Vorlage</h3>
				<p class="dr-muted">
					Versendet wird HTML mit einer automatisch erzeugten Textfassung für Mailprogramme ohne HTML.
					Eingefügte Bilder werden in die Mail eingebettet.
				</p>
				<NcTextField v-model="values.mail_subject" label="Betreff" />
				<HtmlEditor v-model="values.mail_body" label="Text" :placeholders="placeholders" />
				<p class="dr-muted">
					Platzhalter:
					<span v-for="(label, key) in placeholders" :key="key" class="dr-placeholder">
						<code>{{ '{' + key + '}' }}</code> {{ label }}
					</span>
				</p>
			</section>

			<section>
				<h3>Sponsoren-Anschreiben: E-Mail-Versand (SMTP)</h3>
				<p class="dr-muted">
					Zweiter Zugang für Anschreiben an Sponsoren und die Bestätigung nach einer Rückmeldung –
					z. B. ein anderes Postfach als für die Bescheinigungen.
				</p>
				<NcNoteCard v-if="sponsorMailMissing.length" type="info">
					Noch nicht eingerichtet: {{ sponsorMailMissing.join(', ') }}
				</NcNoteCard>
				<div class="dr-row">
					<NcTextField v-model="values.sponsor_smtp_host" label="Server" placeholder="smtp.example.org" />
					<NcTextField v-model="sponsorSmtpPort"
						type="number"
						label="Port"
						class="dr-zip" />
				</div>
				<div class="dr-row dr-radios">
					<NcCheckboxRadioSwitch v-model="values.sponsor_smtp_security"
						type="radio"
						value="starttls"
						name="sponsor_smtp_security"
						@update:model-value="values.sponsor_smtp_port = 587">
						STARTTLS (Port 587)
					</NcCheckboxRadioSwitch>
					<NcCheckboxRadioSwitch v-model="values.sponsor_smtp_security"
						type="radio"
						value="ssl"
						name="sponsor_smtp_security"
						@update:model-value="values.sponsor_smtp_port = 465">
						SSL/TLS (Port 465)
					</NcCheckboxRadioSwitch>
				</div>
				<div class="dr-row">
					<NcTextField v-model="values.sponsor_smtp_user" label="Benutzer" autocomplete="off" />
					<NcPasswordField v-model="sponsorSmtpPassword"
						label="Passwort"
						autocomplete="new-password"
						:placeholder="sponsorSmtpPasswordSet ? 'gespeichert – leer lassen zum Behalten' : ''" />
				</div>
				<div class="dr-row">
					<NcTextField v-model="values.sponsor_smtp_from_name" label="Absendername" />
					<NcTextField v-model="values.sponsor_smtp_from_email" type="email" label="Absenderadresse" />
				</div>
				<div class="dr-row">
					<NcTextField v-model="sponsorTestAddress" type="email" label="Testmail an" />
					<NcButton :disabled="testing || !sponsorTestAddress" @click="sendTest('sponsor_')">
						Speichern und Testmail senden
					</NcButton>
				</div>
			</section>

			<section>
				<h3>Rückmeldeformular</h3>
				<NcTextArea v-model="values.response_intro"
					label="Text über dem Formular"
					resize="vertical"
					rows="6" />
				<p class="dr-muted">
					Platzhalter:
					<span v-for="(label, key) in sponsorPlaceholders" :key="key" class="dr-placeholder">
						<code>{{ '{' + key + '}' }}</code> {{ label }}
					</span>
				</p>
			</section>

			<section>
				<h3>Bestätigung an den Sponsor</h3>
				<p class="dr-muted">
					Geht nach jeder Rückmeldung (auch nach Änderungen) an die E-Mail-Adresse des Sponsors.
				</p>
				<NcTextField v-model="values.confirm_subject" label="Betreff" />
				<HtmlEditor v-model="values.confirm_body" label="Text" :placeholders="confirmPlaceholders" />
				<p class="dr-muted">
					Platzhalter:
					<span v-for="(label, key) in confirmPlaceholders" :key="key" class="dr-placeholder">
						<code>{{ '{' + key + '}' }}</code> {{ label }}
					</span>
				</p>
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
					brauchen dort Schreibrecht, z. B. über einen Team-Ordner. Im Logo-Ordner landen die
					Logos aus Rückmeldungen je Aktion; ohne Logo-Ordner bietet das Formular keinen Upload an.
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
import { getCurrentUser } from '@nextcloud/auth'
import { getFilePickerBuilder, showError, showSuccess } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { computed, defineAsyncComponent, onMounted, ref } from 'vue'
import { errorMessage, getSettings, sendTestMail, setMainFolder, updateSettings } from '../api.js'
import ImageSetting from '../components/ImageSetting.vue'

// Tiptap is large: only loaded where a mail text is edited
const HtmlEditor = defineAsyncComponent(() => import('../components/HtmlEditor.vue'))

const FOLDER_LABELS = {
	unsigned: 'Hauptordner „nicht unterschrieben“',
	signed: 'Hauptordner „unterschrieben“',
	logos: 'Logo-Ordner (optional)',
}

const loading = ref(true)
const saving = ref(false)
const values = ref({})
const missing = ref([])
const folders = ref({})
const images = ref({})
const nextNumber = ref('')
const smtpPassword = ref('')
const smtpPasswordSet = ref(false)
const placeholders = ref({})
const testAddress = ref(getCurrentUser()?.email ?? '')
const testing = ref(false)
const sponsorSmtpPassword = ref('')
const sponsorSmtpPasswordSet = ref(false)
const sponsorMailMissing = ref([])
const sponsorPlaceholders = ref({})
const confirmPlaceholders = ref({})
const sponsorTestAddress = ref(getCurrentUser()?.email ?? '')

// NcTextField type=number still yields a string
const smtpPort = computed({
	get: () => String(values.value.smtp_port ?? ''),
	set: (v) => { values.value.smtp_port = parseInt(v, 10) || 0 },
})
const sponsorSmtpPort = computed({
	get: () => String(values.value.sponsor_smtp_port ?? ''),
	set: (v) => { values.value.sponsor_smtp_port = parseInt(v, 10) || 0 },
})

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
	smtpPasswordSet.value = data.smtpPasswordSet
	placeholders.value = data.placeholders
	smtpPassword.value = ''
	sponsorSmtpPasswordSet.value = data.sponsorSmtpPasswordSet
	sponsorMailMissing.value = data.sponsorMailMissing
	sponsorPlaceholders.value = data.sponsorPlaceholders
	confirmPlaceholders.value = data.confirmPlaceholders
	sponsorSmtpPassword.value = ''
}

/**
 * @param {string} account '' (receipts) or 'sponsor_'
 */
async function sendTest(account) {
	testing.value = true
	const to = account ? sponsorTestAddress.value : testAddress.value
	try {
		if (await save()) {
			await sendTestMail(to, account)
			showSuccess(`Testmail an ${to} gesendet`)
		}
	} catch (e) {
		showError(errorMessage(e))
	} finally {
		testing.value = false
	}
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

/**
 * @return {Promise<boolean>} whether saving worked
 */
async function save() {
	saving.value = true
	try {
		apply(await updateSettings({
			...values.value,
			smtp_password: smtpPassword.value,
			sponsor_smtp_password: sponsorSmtpPassword.value,
		}))
		showSuccess('Einstellungen gespeichert')
		return true
	} catch (e) {
		showError(errorMessage(e))
		return false
	} finally {
		saving.value = false
	}
}
</script>

<style scoped>
.dr-radios > * {
	flex: 0 0 auto;
}

.dr-placeholder {
	display: inline-block;
	margin-inline-end: calc(var(--default-grid-baseline) * 3);
}

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
