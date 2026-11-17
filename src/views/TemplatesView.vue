<template>
	<div class="dr-page">
		<div class="dr-header">
			<h2>Vorlagen für Anschreiben</h2>
			<NcButton variant="primary" @click="startNew">
				<template #icon>
					<NcIconSvgWrapper :path="mdiPlus" />
				</template>
				Neue Vorlage
			</NcButton>
		</div>
		<NcLoadingIcon v-if="loading" :size="32" />
		<template v-else>
			<NcEmptyContent v-if="!templates.length && !form"
				name="Noch keine Vorlagen"
				description="Lege eine Vorlage an; sie wird mit einem Mustertext vorbefüllt.">
				<template #icon>
					<NcIconSvgWrapper :path="mdiEmailEditOutline" />
				</template>
			</NcEmptyContent>
			<ul v-else class="dr-template-list">
				<li v-for="template in templates" :key="template.id">
					<NcButton :variant="form?.id === template.id ? 'secondary' : 'tertiary'"
						wide
						alignment="start"
						@click="edit(template)">
						{{ template.name }}
					</NcButton>
				</li>
			</ul>

			<form v-if="form" class="dr-form dr-template-form" @submit.prevent="save">
				<h3>{{ form.id ? 'Vorlage bearbeiten' : 'Neue Vorlage' }}</h3>
				<NcTextField v-model="form.name" label="Name der Vorlage" required />
				<NcTextField v-model="form.subject" label="Betreff" required />
				<HtmlEditor v-model="form.body" label="Text" :placeholders="placeholders" />
				<p class="dr-muted">
					Platzhalter:
					<span v-for="(label, key) in placeholders" :key="key" class="dr-placeholder">
						<code>{{ '{' + key + '}' }}</code> {{ label }}
					</span>
				</p>
				<NcNoteCard v-if="!/\{link(knopf)?\}/.test(form.body)" type="warning">
					Der Text enthält weder <code>{link}</code> noch <code>{linkknopf}</code> – ohne Link können
					Sponsoren nicht antworten.
				</NcNoteCard>
				<div class="dr-actions">
					<NcButton type="submit" variant="primary" :disabled="saving">
						Speichern
					</NcButton>
					<NcButton @click="form = null">
						Abbrechen
					</NcButton>
					<NcButton v-if="form.id" variant="error" @click="remove">
						Löschen
					</NcButton>
				</div>
			</form>

			<section v-if="mailValues" class="dr-sponsor-mails">
				<h2>Automatische Mails an Sponsoren</h2>
				<p class="dr-muted">
					Gehen über den SMTP-Zugang der Anschreiben, jeweils mit dem persönlichen Rückmelde-Link.
				</p>
				<div class="dr-segmented dr-mail-kinds" role="group" aria-label="Mail">
					<NcButton v-for="kind in MAIL_KINDS"
						:key="kind.key"
						:pressed="mailKind.key === kind.key"
						@update:pressed="mailKind = kind">
						{{ kind.label }}
					</NcButton>
				</div>
				<form class="dr-form dr-template-form" @submit.prevent="saveMails">
					<p class="dr-muted">
						{{ mailKind.hint }}
					</p>
					<NcTextField v-model="mailValues[`${mailKind.key}_subject`]" label="Betreff" />
					<HtmlEditor :key="mailKind.key"
						v-model="mailValues[`${mailKind.key}_body`]"
						label="Text"
						:placeholders="mailPlaceholders[mailKind.placeholders]" />
					<p class="dr-muted">
						Platzhalter:
						<span v-for="(label, key) in mailPlaceholders[mailKind.placeholders]" :key="key" class="dr-placeholder">
							<code>{{ '{' + key + '}' }}</code> {{ label }}
						</span>
					</p>

					<h3>Erinnerungen</h3>
					<div class="dr-row">
						<NcTextField v-model="reminderDays"
							type="number"
							min="1"
							max="365"
							label="Fällig nach Tagen"
							helper-text="seit dem Anschreiben bzw. der letzten Erinnerung" />
						<NcTextField v-model="reminderMax"
							type="number"
							min="1"
							max="10"
							label="Höchstens Erinnerungen"
							helper-text="je Sponsor und Aktion" />
					</div>
					<NcCheckboxRadioSwitch v-model="mailValues.reminder_auto" type="switch">
						Fällige Erinnerungen täglich automatisch senden
					</NcCheckboxRadioSwitch>
					<p class="dr-muted">
						Erinnert werden nur Sponsoren ohne Antwort und solche, die zugesagt haben, aber noch Angaben
						schuldig sind – nie nach einer Absage, bei „nicht mehr anschreiben“, bei einer unbearbeiteten
						Antwort-Mail oder in geschlossenen Aktionen. Ohne Automatik siehst du die fälligen in der Aktion
						und sendest sie dort.
					</p>
					<div class="dr-actions">
						<NcButton type="submit" variant="primary" :disabled="savingMails">
							Mails und Erinnerungen speichern
						</NcButton>
					</div>
				</form>
			</section>
		</template>
	</div>
</template>

<script setup>
import { mdiEmailEditOutline, mdiPlus } from '@mdi/js'
import { showError, showSuccess } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { computed, defineAsyncComponent, onMounted, ref } from 'vue'
import { createLetterTemplate, deleteLetterTemplate, errorMessage, getSettings, listLetterTemplates, updateLetterTemplate, updateSettings } from '../api.js'

// automatic mails to sponsors: setting keys <key>_subject / <key>_body
const MAIL_KINDS = [
	{
		key: 'confirm',
		label: 'Bestätigung Zusage',
		hint: 'Geht nach jeder Rückmeldung mit Angaben (auch nach Änderungen) an den Sponsor.',
		placeholders: 'confirm',
	},
	{
		key: 'decline',
		label: 'Bestätigung Absage',
		hint: 'Geht an den Sponsor, wenn er im Rückmeldeformular „diesmal nicht“ oder „bitte keine Anfragen mehr“ wählt.',
		placeholders: 'decline',
	},
	{
		key: 'reminder',
		label: 'Erinnerung ohne Antwort',
		hint: 'Für Sponsoren, die auf das Anschreiben noch nicht geantwortet haben.',
		placeholders: 'sponsor',
	},
	{
		key: 'reminder_incomplete',
		label: 'Erinnerung Angaben fehlen',
		hint: 'Für Sponsoren mit dem Status „Zusage – Angaben fehlen noch“.',
		placeholders: 'sponsor',
	},
]
const MAIL_KEYS = [...MAIL_KINDS.flatMap((k) => [`${k.key}_subject`, `${k.key}_body`]), 'reminder_days', 'reminder_max', 'reminder_auto']

// Tiptap is large: only loaded where a mail text is edited
const HtmlEditor = defineAsyncComponent(() => import('../components/HtmlEditor.vue'))

const loading = ref(true)
const saving = ref(false)
const templates = ref([])
const placeholders = ref({})
const defaults = ref({ subject: '', body: '' })
const form = ref(null)
const mailValues = ref(null)
const mailPlaceholders = ref({})
const mailKind = ref(MAIL_KINDS[0])
const savingMails = ref(false)

// NcTextField type=number yields a string
const reminderDays = computed({
	get: () => String(mailValues.value.reminder_days ?? ''),
	set: (v) => { mailValues.value.reminder_days = parseInt(v, 10) || 0 },
})
const reminderMax = computed({
	get: () => String(mailValues.value.reminder_max ?? ''),
	set: (v) => { mailValues.value.reminder_max = parseInt(v, 10) || 0 },
})

async function loadMails() {
	const data = await getSettings()
	mailValues.value = Object.fromEntries(MAIL_KEYS.map((key) => [key, data.values[key]]))
	mailPlaceholders.value = {
		sponsor: data.sponsorPlaceholders,
		confirm: data.confirmPlaceholders,
		decline: data.declinePlaceholders,
	}
}

async function saveMails() {
	savingMails.value = true
	try {
		await updateSettings({ ...mailValues.value })
		showSuccess('Gespeichert')
	} catch (e) {
		showError(errorMessage(e))
	} finally {
		savingMails.value = false
	}
}

async function load() {
	const data = await listLetterTemplates()
	templates.value = data.templates
	placeholders.value = data.placeholders
	defaults.value = data.defaults
}

function edit(template) {
	form.value = { ...template }
}

function startNew() {
	form.value = { id: null, name: '', ...defaults.value }
}

async function save() {
	saving.value = true
	try {
		const data = { name: form.value.name, subject: form.value.subject, body: form.value.body }
		const saved = form.value.id
			? await updateLetterTemplate(form.value.id, data)
			: await createLetterTemplate(data)
		await load()
		form.value = { ...saved }
		showSuccess('Vorlage gespeichert')
	} catch (e) {
		showError(errorMessage(e))
	} finally {
		saving.value = false
	}
}

async function remove() {
	try {
		await deleteLetterTemplate(form.value.id)
		form.value = null
		await load()
		showSuccess('Vorlage gelöscht')
	} catch (e) {
		showError(errorMessage(e))
	}
}

onMounted(async () => {
	try {
		await Promise.all([load(), loadMails()])
		if (!templates.value.length) {
			startNew()
			form.value.name = 'Anfrage Sponsoring'
		}
	} catch (e) {
		showError(errorMessage(e))
	} finally {
		loading.value = false
	}
})
</script>

<style scoped>
.dr-template-list {
	display: flex;
	flex-direction: column;
	gap: var(--default-grid-baseline);
	max-width: 400px;
	margin-bottom: calc(var(--default-grid-baseline) * 4);
}

.dr-template-form {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 2);
}

.dr-sponsor-mails {
	margin-top: calc(var(--default-grid-baseline) * 10);
	padding-top: calc(var(--default-grid-baseline) * 4);
	border-top: 1px solid var(--color-border);
}

.dr-mail-kinds {
	display: flex;
	flex-wrap: wrap;
	gap: var(--default-grid-baseline);
	margin: calc(var(--default-grid-baseline) * 3) 0;
}

.dr-placeholder {
	display: inline-block;
	margin-inline-end: calc(var(--default-grid-baseline) * 3);
}
</style>
