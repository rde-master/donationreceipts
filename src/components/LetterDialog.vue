<template>
	<NcDialog :name="`Anschreiben – ${campaign.name}`"
		size="large"
		@closing="$emit('close')">
		<NcLoadingIcon v-if="loading" :size="32" />

		<div v-else-if="results" class="dr-letter">
			<NcNoteCard :type="failed.length ? 'warning' : 'success'">
				{{ results.length - failed.length }} von {{ results.length }} Anschreiben versendet.
			</NcNoteCard>
			<template v-if="failed.length">
				<p>Nicht versendet:</p>
				<ul class="dr-failed">
					<li v-for="r in failed" :key="r.sponsorId">
						<strong>{{ r.name }}</strong> ({{ r.email }}): {{ r.error }}
					</li>
				</ul>
			</template>
		</div>

		<form v-else
			id="dr-letter-form"
			class="dr-letter"
			@submit.prevent="send">
			<p>
				An {{ sponsors.length === 1 ? sponsors[0].name : `${sponsors.length} Sponsoren` }}.
				Jeder Sponsor bekommt eine eigene Mail mit seinem persönlichen Rückmelde-Link.
			</p>
			<NcSelect v-model="template"
				:options="templates"
				label="name"
				input-label="Vorlage"
				placeholder="Vorlage wählen"
				:label-outside="false"
				@update:model-value="applyTemplate" />
			<NcTextField v-model="subject" label="Betreff" required />
			<NcTextArea v-model="body"
				label="Text"
				resize="vertical"
				rows="10" />
			<p class="dr-muted">
				Platzhalter:
				<span v-for="(label, key) in placeholders" :key="key" class="dr-placeholder">
					<code>{{ '{' + key + '}' }}</code> {{ label }}
				</span>
			</p>

			<h3>Vorschau</h3>
			<NcSelect v-if="sponsors.length > 1"
				v-model="previewSponsor"
				:options="sponsors"
				label="name"
				input-label="Vorschau für"
				:clearable="false"
				:label-outside="false" />
			<div v-if="preview" class="dr-preview">
				<div><span class="dr-muted">An:</span> {{ preview.to }}</div>
				<div><span class="dr-muted">Betreff:</span> {{ preview.subject }}</div>
				<div class="dr-pre dr-preview__body">
					{{ preview.body }}
				</div>
			</div>
			<NcNoteCard v-if="error" type="error">
				{{ error }}
			</NcNoteCard>
		</form>

		<template #actions>
			<NcButton @click="$emit('close')">
				{{ results ? 'Schließen' : 'Abbrechen' }}
			</NcButton>
			<NcButton v-if="!results"
				type="submit"
				form="dr-letter-form"
				variant="primary"
				:disabled="loading || sending || !subject.trim()">
				<template #icon>
					<NcLoadingIcon v-if="sending" :size="20" />
					<NcIconSvgWrapper v-else :path="mdiSend" />
				</template>
				{{ sponsors.length === 1 ? 'Senden' : `${sponsors.length} Mails senden` }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script setup>
import { mdiSend } from '@mdi/js'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { computed, onMounted, ref, watch } from 'vue'
import { errorMessage, listLetterTemplates, previewLetter, sendLetters } from '../api.js'

const props = defineProps({
	campaign: { type: Object, required: true },
	sponsors: { type: Array, required: true },
})

const emit = defineEmits(['close', 'sent'])

const loading = ref(true)
const sending = ref(false)
const error = ref('')
const templates = ref([])
const placeholders = ref({})
const template = ref(null)
const subject = ref('')
const body = ref('')
const previewSponsor = ref(props.sponsors[0])
const preview = ref(null)
const results = ref(null)

const failed = computed(() => (results.value ?? []).filter((r) => r.error))

function applyTemplate(value) {
	if (value) {
		subject.value = value.subject
		body.value = value.body
	}
}

let timer
async function updatePreview() {
	clearTimeout(timer)
	timer = setTimeout(async () => {
		if (!previewSponsor.value) {
			return
		}
		try {
			preview.value = await previewLetter(props.campaign.id, {
				sponsorId: previewSponsor.value.id,
				subject: subject.value,
				body: body.value,
			})
		} catch (e) {
			error.value = errorMessage(e)
		}
	}, 300)
}

watch([subject, body, previewSponsor], updatePreview)

onMounted(async () => {
	try {
		const data = await listLetterTemplates()
		templates.value = data.templates
		placeholders.value = data.placeholders
		template.value = templates.value[0] ?? null
		applyTemplate(template.value ?? data.defaults)
	} catch (e) {
		error.value = errorMessage(e)
	} finally {
		loading.value = false
	}
})

async function send() {
	sending.value = true
	error.value = ''
	try {
		results.value = (await sendLetters(props.campaign.id, {
			sponsorIds: props.sponsors.map((s) => s.id),
			subject: subject.value,
			body: body.value,
		})).results
		emit('sent')
	} catch (e) {
		// stays open with the error, so nothing typed gets lost
		error.value = errorMessage(e)
	} finally {
		sending.value = false
	}
}
</script>

<style scoped>
.dr-letter {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 2);
}

.dr-placeholder {
	display: inline-block;
	margin-inline-end: calc(var(--default-grid-baseline) * 3);
}

.dr-preview {
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	padding: calc(var(--default-grid-baseline) * 3);
	display: flex;
	flex-direction: column;
	gap: var(--default-grid-baseline);
}

.dr-preview__body {
	margin-top: calc(var(--default-grid-baseline) * 2);
}

.dr-failed {
	list-style: disc;
	padding-inline-start: calc(var(--default-grid-baseline) * 5);
}
</style>
