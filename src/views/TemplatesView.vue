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
		</template>
	</div>
</template>

<script setup>
import { mdiEmailEditOutline, mdiPlus } from '@mdi/js'
import { showError, showSuccess } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { defineAsyncComponent, onMounted, ref } from 'vue'
import { createLetterTemplate, deleteLetterTemplate, errorMessage, listLetterTemplates, updateLetterTemplate } from '../api.js'

// Tiptap is large: only loaded where a mail text is edited
const HtmlEditor = defineAsyncComponent(() => import('../components/HtmlEditor.vue'))

const loading = ref(true)
const saving = ref(false)
const templates = ref([])
const placeholders = ref({})
const defaults = ref({ subject: '', body: '' })
const form = ref(null)

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
		await load()
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
