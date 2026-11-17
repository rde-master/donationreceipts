<template>
	<div class="dr-page">
		<div class="dr-header">
			<h2>Vorlagen</h2>
			<NcButton v-if="tab === 'anschreiben'" variant="primary" @click="startNew">
				<template #icon>
					<NcIconSvgWrapper :path="mdiPlus" />
				</template>
				Neue Vorlage
			</NcButton>
		</div>
		<TabBar v-model="tab" :tabs="TABS" label="Arten von Vorlagen" />
		<NcLoadingIcon v-if="loading" :size="32" />

		<!-- Anschreiben: several templates to choose from when sending -->
		<div v-else-if="tab === 'anschreiben'" role="tabpanel">
			<p class="dr-muted">
				Texte für das Anschreiben an mögliche Sponsoren; beim Versand wählst du eine davon und kannst
				sie noch anpassen.
			</p>
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
		</div>

		<!-- one automatic mail -->
		<form v-else-if="mailKind && mailValues"
			class="dr-form dr-template-form"
			role="tabpanel"
			@submit.prevent="saveMails">
			<p class="dr-muted">
				{{ mailKind.hint }} Geht über den SMTP-Zugang der Anschreiben.
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
			<div class="dr-actions">
				<NcButton type="submit" variant="primary" :disabled="savingMails">
					Speichern
				</NcButton>
			</div>
		</form>

		<!-- reminder rules -->
		<form v-else-if="tab === 'erinnerungen' && mailValues"
			class="dr-form dr-template-form"
			role="tabpanel"
			@submit.prevent="saveMails">
			<p class="dr-muted">
				Wann Sponsoren erinnert werden. Die Texte stehen in den Reitern „Erinnerung ohne Antwort“ und
				„Erinnerung Angaben fehlen“.
			</p>
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
					Speichern
				</NcButton>
			</div>
		</form>
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
import TabBar from '../components/TabBar.vue'
import { useTab } from '../useTab.js'

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
const TABS = [
	{ id: 'anschreiben', label: 'Anschreiben' },
	...MAIL_KINDS.map((k) => ({ id: k.key, label: k.label })),
	{ id: 'erinnerungen', label: 'Regeln für Erinnerungen' },
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
const tab = useTab(TABS.map((t) => t.id))
const mailKind = computed(() => MAIL_KINDS.find((k) => k.key === tab.value) ?? null)
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

.dr-placeholder {
	display: inline-block;
	margin-inline-end: calc(var(--default-grid-baseline) * 3);
}
</style>
