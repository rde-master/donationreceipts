<template>
	<NcDialog :name="`Erinnerung – ${campaign.name}`"
		size="large"
		@closing="$emit('close')">
		<div v-if="results" class="dr-reminder">
			<NcNoteCard :type="failed.length ? 'warning' : 'success'">
				{{ results.length - failed.length }} von {{ results.length }} Erinnerungen versendet.
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

		<div v-else class="dr-reminder">
			<p>
				An {{ sponsors.length === 1 ? sponsors[0].name : `${sponsors.length} Sponsoren` }}, jeweils mit dem
				persönlichen Rückmelde-Link.
				<template v-if="incompleteCount && incompleteCount < sponsors.length">
					{{ incompleteCount }} davon haben schon zugesagt und bekommen die Vorlage „Angaben fehlen noch“.
				</template>
				<template v-else-if="incompleteCount">
					Alle haben schon zugesagt und bekommen die Vorlage „Angaben fehlen noch“.
				</template>
			</p>
			<p class="dr-muted">
				Die Texte stehen unter
				<RouterLink :to="{ name: 'templates' }" @click="$emit('close')">
					Vorlagen
				</RouterLink>.
			</p>
			<NcSelect v-if="sponsors.length > 1"
				v-model="previewSponsor"
				:options="sponsors"
				label="name"
				input-label="Vorschau für"
				:clearable="false"
				:label-outside="false" />
			<NcLoadingIcon v-if="!preview && !error" :size="32" />
			<MailPreview v-if="preview"
				:to="preview.to"
				:subject="preview.subject"
				:html="preview.html" />
			<NcNoteCard v-if="error" type="error">
				{{ error }}
			</NcNoteCard>
		</div>

		<template #actions>
			<NcButton @click="$emit('close')">
				{{ results ? 'Schließen' : 'Abbrechen' }}
			</NcButton>
			<NcButton v-if="!results"
				variant="primary"
				:disabled="sending"
				@click="send">
				<template #icon>
					<NcLoadingIcon v-if="sending" :size="20" />
					<NcIconSvgWrapper v-else :path="mdiBellRingOutline" />
				</template>
				{{ sponsors.length === 1 ? 'Erinnerung senden' : `${sponsors.length} Erinnerungen senden` }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script setup>
import { mdiBellRingOutline } from '@mdi/js'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { computed, onMounted, ref, watch } from 'vue'
import { errorMessage, previewReminder, sendReminders } from '../api.js'
import MailPreview from './MailPreview.vue'

const props = defineProps({
	campaign: { type: Object, required: true },
	// rows of the campaign view ({sponsor, inquiry}) that can be reminded
	rows: { type: Array, required: true },
})

const emit = defineEmits(['close', 'sent'])

const sponsors = computed(() => props.rows.map((r) => r.sponsor))
const incompleteCount = computed(() => props.rows.filter((r) => r.inquiry?.outcome === 'incomplete').length)
const previewSponsor = ref(sponsors.value[0])
const preview = ref(null)
const error = ref('')
const sending = ref(false)
const results = ref(null)
const failed = computed(() => (results.value ?? []).filter((r) => r.error))

async function loadPreview() {
	preview.value = null
	error.value = ''
	try {
		preview.value = await previewReminder(props.campaign.id, previewSponsor.value.id)
	} catch (e) {
		error.value = errorMessage(e)
	}
}

async function send() {
	sending.value = true
	error.value = ''
	try {
		results.value = (await sendReminders(props.campaign.id, sponsors.value.map((s) => s.id))).results
		emit('sent')
	} catch (e) {
		error.value = errorMessage(e)
	} finally {
		sending.value = false
	}
}

watch(previewSponsor, loadPreview)
onMounted(loadPreview)
</script>

<style scoped>
.dr-reminder {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 2);
}

.dr-failed {
	list-style: disc;
	padding-inline-start: calc(var(--default-grid-baseline) * 5);
}
</style>
