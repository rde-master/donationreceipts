<template>
	<div class="dr-page dr-page--wide">
		<NcLoadingIcon v-if="!data" :size="32" />
		<template v-else>
			<div class="dr-header">
				<h2>
					{{ campaign.name }}
					<span v-if="campaign.closed" class="dr-muted">(geschlossen)</span>
				</h2>
				<div class="dr-actions">
					<NcButton @click="renameOpen = true">
						<template #icon>
							<NcIconSvgWrapper :path="mdiPencil" />
						</template>
						Umbenennen
					</NcButton>
					<NcButton @click="toggleClosed">
						<template #icon>
							<NcIconSvgWrapper :path="campaign.closed ? mdiLockOpenVariantOutline : mdiLockOutline" />
						</template>
						{{ campaign.closed ? 'Wieder öffnen' : 'Schließen' }}
					</NcButton>
					<NcButton v-if="!rows.some((r) => r.inquiry)" variant="error" @click="remove">
						<template #icon>
							<NcIconSvgWrapper :path="mdiDelete" />
						</template>
						Löschen
					</NcButton>
				</div>
			</div>

			<NcNoteCard v-if="campaign.closed" type="info">
				Die Aktion ist geschlossen: Das Rückmeldeformular nimmt keine Eingaben mehr an, und es werden
				keine Anschreiben verschickt. Eingegangene Spenden lassen sich weiter bestätigen.
			</NcNoteCard>
			<NcNoteCard v-else-if="data.mailMissing.length" type="warning">
				Für den Versand der Anschreiben fehlt in den Einstellungen (SMTP-Zugang für Sponsoren):
				{{ data.mailMissing.join(', ') }}
			</NcNoteCard>

			<div class="dr-tiles">
				<StatTile label="Angeschrieben" :value="`${summary.sent} von ${rows.length}`">
					<span v-if="summary.repliesWithoutStatus" class="dr-warning">
						{{ summary.repliesWithoutStatus }} Antwort-Mails ohne Status
					</span>
				</StatTile>
				<StatTile label="Rückmeldungen" :value="String(summary.responded)">
					{{ summary.received }} Spenden eingegangen · {{ summary.declined }} Absagen
					<template v-if="summary.incomplete">
						· {{ summary.incomplete }} Zusagen ohne Angaben
					</template>
				</StatTile>
				<StatTile label="Zugesagt" :value="formatAmount(summary.pledgedCents)">
					Eingang offen: {{ formatAmount(summary.openCents) }}
				</StatTile>
				<StatTile label="Freikarten" :value="String(summary.tickets)">
					{{ summary.ticketsLater }} melden sich noch · {{ summary.publish }} Logo-Freigaben
				</StatTile>
			</div>

			<div class="dr-row dr-toolbar">
				<NcSelect v-model="filter"
					:options="FILTERS"
					:clearable="false"
					input-label="Anzeigen"
					:label-outside="false" />
				<div class="dr-actions dr-toolbar__actions">
					<NcButton v-if="dueRows.length && !campaign.closed" @click="selectDue">
						<template #icon>
							<NcIconSvgWrapper :path="mdiBellAlertOutline" />
						</template>
						{{ dueRows.length }} {{ dueRows.length === 1 ? 'Erinnerung' : 'Erinnerungen' }} fällig – auswählen
					</NcButton>
					<NcButton :disabled="!remindRows.length || campaign.closed"
						:title="selected.length && !remindRows.length ? 'Unter den ausgewählten ist niemand ohne Antwort bzw. mit fehlenden Angaben' : ''"
						@click="reminderOpen = true">
						<template #icon>
							<NcIconSvgWrapper :path="mdiBellRingOutline" />
						</template>
						Erinnerung an {{ remindRows.length }}
					</NcButton>
					<NcButton variant="primary"
						:disabled="!selected.length || campaign.closed"
						@click="letterOpen = true">
						<template #icon>
							<NcIconSvgWrapper :path="mdiSend" />
						</template>
						Anschreiben an {{ selected.length }} {{ selected.length === 1 ? 'Sponsor' : 'Sponsoren' }}
					</NcButton>
				</div>
			</div>
			<p class="dr-muted dr-reminder-rule">
				Erinnerungen sind fällig {{ data.reminder.days }} Tage nach dem Anschreiben bzw. der letzten
				Erinnerung, höchstens {{ data.reminder.max }}× je Sponsor
				({{ data.reminder.auto ? 'werden täglich automatisch gesendet' : 'Versand von Hand' }}) –
				einstellbar unter
				<RouterLink :to="{ name: 'templates', query: { reiter: 'erinnerungen' } }">
					Vorlagen
				</RouterLink>.
			</p>

			<p v-if="!rows.length" class="dr-muted">
				Es gibt noch keine möglichen Sponsoren. Lege sie unter
				<RouterLink :to="{ name: 'sponsors' }">
					Mögliche Sponsoren
				</RouterLink> an.
			</p>
			<table v-else class="dr-table">
				<thead>
					<tr>
						<th class="dr-check">
							<NcCheckboxRadioSwitch :model-value="allSelected"
								:indeterminate="selected.length > 0 && !allSelected"
								aria-label="Alle angezeigten auswählen"
								@update:model-value="selectAll" />
						</th>
						<th>Möglicher Sponsor</th>
						<th>Status</th>
						<th class="dr-num">
							Spende
						</th>
						<th>Freikarten</th>
						<th>Logo</th>
						<th />
					</tr>
				</thead>
				<tbody>
					<tr v-for="row in visibleRows" :key="row.sponsor.id">
						<td class="dr-check">
							<NcCheckboxRadioSwitch v-model="selected"
								:value="row.sponsor.id"
								:disabled="!selectable(row)"
								:aria-label="`${row.sponsor.name} auswählen`" />
						</td>
						<td>
							<div>{{ row.sponsor.name }}</div>
							<div class="dr-muted">
								{{ [row.sponsor.contact, row.sponsor.email].filter(Boolean).join(' · ') }}
							</div>
						</td>
						<td>
							<span :class="statusClass(row)">{{ statusText(row) }}</span>
							<div v-if="row.inquiry?.outcomeNote" class="dr-muted dr-pre">
								{{ row.inquiry.outcomeNote }}
							</div>
							<div v-if="row.inquiry?.responseBy" class="dr-muted">
								manuell erfasst
							</div>
							<div v-if="row.inquiry?.reminderCount" class="dr-muted">
								erinnert {{ formatDateTime(row.inquiry.remindedAt) }}{{ row.inquiry.reminderCount > 1 ? ` (${row.inquiry.reminderCount}×)` : '' }}
							</div>
							<div v-if="row.inquiry?.reminderDue" class="dr-warning">
								Erinnerung fällig
							</div>
							<div v-if="openReplies[row.sponsor.id]">
								<RouterLink :to="{ name: 'replies' }" class="dr-warning">
									{{ openReplies[row.sponsor.id] === 1 ? '1 Antwort-Mail' : `${openReplies[row.sponsor.id]} Antwort-Mails` }} zu bearbeiten
								</RouterLink>
							</div>
						</td>
						<td class="dr-num">
							<template v-if="row.inquiry?.response">
								{{ formatAmount(row.inquiry.response.amountCents) }}
								<div v-if="row.inquiry.response.type === 'goods'" class="dr-muted">
									Sachspende
								</div>
							</template>
						</td>
						<td>{{ row.inquiry?.response ? data.ticketLabels[row.inquiry.response.tickets] : '' }}</td>
						<td>
							<template v-if="row.inquiry?.response">
								{{ row.inquiry.response.publish ? 'Ja' : 'Nein' }}
								<a v-if="row.inquiry.hasLogo" :href="logoUrl(row.inquiry.id)">(Datei)</a>
							</template>
						</td>
						<td class="dr-num">
							<div class="dr-row-actions">
								<NcButton v-if="row.inquiry?.response"
									variant="tertiary"
									@click="responseRow = row">
									{{ row.inquiry.receiptId ? 'Ansehen' : 'Rückmeldung' }}
								</NcButton>
								<NcButton v-if="!row.inquiry?.receiptId"
									variant="tertiary"
									:aria-label="`Status der Rückmeldung von ${row.sponsor.name} setzen`"
									title="Status der Rückmeldung setzen (z. B. diesmal nicht)"
									@click="outcomeRow = row">
									<template #icon>
										<NcIconSvgWrapper :path="mdiListStatus" />
									</template>
								</NcButton>
								<NcButton v-if="!row.inquiry?.receiptId"
									variant="tertiary"
									:aria-label="row.inquiry?.response ? `Rückmeldung von ${row.sponsor.name} bearbeiten` : `Rückmeldung von ${row.sponsor.name} erfassen`"
									:title="row.inquiry?.response ? 'Rückmeldung bearbeiten' : 'Rückmeldung manuell erfassen'"
									@click="manualRow = row">
									<template #icon>
										<NcIconSvgWrapper :path="row.inquiry?.response ? mdiPencil : mdiClipboardEditOutline" />
									</template>
								</NcButton>
								<NcButton v-if="row.inquiry"
									variant="tertiary"
									:aria-label="`Rückmelde-Link von ${row.sponsor.name} kopieren`"
									title="Rückmelde-Link kopieren"
									@click="copyLink(row.inquiry.link)">
									<template #icon>
										<NcIconSvgWrapper :path="mdiLinkVariant" />
									</template>
								</NcButton>
							</div>
						</td>
					</tr>
				</tbody>
			</table>
		</template>

		<ReminderDialog v-if="reminderOpen"
			:campaign="campaign"
			:rows="remindRows"
			@sent="afterSend"
			@close="reminderOpen = false" />

		<LetterDialog v-if="letterOpen"
			:campaign="campaign"
			:sponsors="rows.filter((r) => selected.includes(r.sponsor.id)).map((r) => r.sponsor)"
			@sent="afterSend"
			@close="letterOpen = false" />

		<ResponseFormDialog v-if="manualRow"
			:campaign-id="Number(id)"
			:sponsor="manualRow.sponsor"
			:inquiry="manualRow.inquiry"
			:ticket-labels="data.ticketLabels"
			@saved="load"
			@close="manualRow = null" />

		<OutcomeDialog v-if="outcomeRow"
			:campaign-id="Number(id)"
			:sponsor="outcomeRow.sponsor"
			:inquiry="outcomeRow.inquiry"
			@saved="load"
			@manual="openManualFromOutcome"
			@close="outcomeRow = null" />

		<ResponseDialog v-if="responseRow"
			:inquiry="responseRow.inquiry"
			:sponsor="responseRow.sponsor"
			:ticket-labels="data.ticketLabels"
			@received="load"
			@close="responseRow = null" />

		<NcDialog v-if="renameOpen"
			name="Aktion umbenennen"
			@closing="renameOpen = false">
			<form id="dr-rename" @submit.prevent="rename">
				<NcTextField v-model="newName" label="Name der Aktion" />
			</form>
			<template #actions>
				<NcButton @click="renameOpen = false">
					Abbrechen
				</NcButton>
				<NcButton type="submit" form="dr-rename" variant="primary">
					Speichern
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>

<script setup>
import { mdiBellAlertOutline, mdiBellRingOutline, mdiClipboardEditOutline, mdiDelete, mdiLinkVariant, mdiListStatus, mdiLockOpenVariantOutline, mdiLockOutline, mdiPencil, mdiSend } from '@mdi/js'
import { showError, showSuccess } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { deleteCampaign, errorMessage, getCampaign, listReplies, logoUrl, updateCampaign } from '../api.js'
import StatTile from '../components/charts/StatTile.vue'
import LetterDialog from '../components/LetterDialog.vue'
import OutcomeDialog from '../components/OutcomeDialog.vue'
import ReminderDialog from '../components/ReminderDialog.vue'
import ResponseDialog from '../components/ResponseDialog.vue'
import ResponseFormDialog from '../components/ResponseFormDialog.vue'
import { formatAmount, formatDateTime } from '../format.js'
import { OUTCOMES, REFUSED } from '../outcome.js'

const props = defineProps({
	id: { type: [String, Number], required: true },
})

const FILTERS = [
	{ id: 'all', label: 'Alle möglichen Sponsoren' },
	{ id: 'new', label: 'Noch nicht angeschrieben' },
	{ id: 'waiting', label: 'Ohne Antwort' },
	{ id: 'replied', label: 'Antwort per Mail – Status offen' },
	{ id: 'later', label: 'Meldet sich noch' },
	{ id: 'incomplete', label: 'Zusage – Angaben fehlen noch' },
	{ id: 'open', label: 'Zusage, Spende offen' },
	{ id: 'received', label: 'Spende eingegangen' },
	{ id: 'declined', label: 'Absage / nicht mehr anschreiben' },
	{ id: 'due', label: 'Erinnerung fällig' },
]

const router = useRouter()
const data = ref(null)
const filter = ref(FILTERS[0])
const selected = ref([])
const letterOpen = ref(false)
const responseRow = ref(null)
const manualRow = ref(null)
const outcomeRow = ref(null)
const reminderOpen = ref(false)
// open reply mails per sponsor id
const openReplies = ref({})
const renameOpen = ref(false)
const newName = ref('')

const campaign = computed(() => data.value.campaign)
const summary = computed(() => data.value.summary)
const rows = computed(() => data.value.rows)

function state(row) {
	const inquiry = row.inquiry
	if (inquiry?.receiptId) {
		return 'received'
	}
	switch (inquiry?.outcome) {
	case 'pledged':
		return 'open'
	case 'incomplete':
	case 'later':
		return inquiry.outcome
	case 'declined':
	case 'no_contact':
		return 'declined'
	}
	if (row.sponsor.noContact) {
		return 'declined'
	}
	if (inquiry?.lastReplyAt) {
		return 'replied'
	}
	return inquiry?.sentAt ? 'waiting' : 'new'
}

// declined (this campaign) or asked not to be written to: no letter
function selectable(row) {
	return state(row) !== 'declined'
}

// "Spende zugesagt – Angaben erfassen" in the status dialog
function openManualFromOutcome() {
	manualRow.value = outcomeRow.value
	outcomeRow.value = null
}

const visibleRows = computed(() => {
	switch (filter.value.id) {
	case 'all':
		return rows.value
	case 'due':
		return dueRows.value
	default:
		return rows.value.filter((row) => state(row) === filter.value.id)
	}
})
// reminder due: no answer (or data missing) for some days
const dueRows = computed(() => rows.value.filter((row) => row.inquiry?.reminderDue))
// the selected ones a reminder can go to
const remindRows = computed(() => rows.value.filter((row) => selected.value.includes(row.sponsor.id) && row.inquiry?.remindable))

function selectDue() {
	filter.value = FILTERS.find((f) => f.id === 'due')
	selected.value = dueRows.value.map((row) => row.sponsor.id)
}

const allSelected = computed(() => visibleRows.value.some(selectable)
	&& visibleRows.value.filter(selectable).every((row) => selected.value.includes(row.sponsor.id)))

// a changed filter only keeps what is still visible
watch(filter, () => {
	const visible = new Set(visibleRows.value.map((row) => row.sponsor.id))
	selected.value = selected.value.filter((id) => visible.has(id))
})

function selectAll(all) {
	selected.value = all ? visibleRows.value.filter(selectable).map((row) => row.sponsor.id) : []
}

function statusText(row) {
	const inquiry = row.inquiry
	switch (state(row)) {
	case 'new':
		return inquiry ? 'Versand fehlgeschlagen' : 'nicht angeschrieben'
	case 'waiting':
		return `angeschrieben ${formatDateTime(inquiry.sentAt)}`
	case 'replied':
		return `Antwort per Mail ${formatDateTime(inquiry.lastReplyAt)} – Status offen`
	case 'later':
	case 'incomplete':
		return OUTCOMES[inquiry.outcome].label
	case 'open':
		return 'Zusage – Spende offen'
	case 'declined':
		return inquiry && REFUSED.includes(inquiry.outcome)
			? OUTCOMES[inquiry.outcome].label
			: 'Möchte nicht mehr angeschrieben werden'
	default:
		return 'Spende eingegangen'
	}
}

function statusClass(row) {
	const s = state(row)
	if (s === 'new' && row.inquiry) {
		return 'dr-warning'
	}
	if (s === 'replied' || s === 'incomplete') {
		return 'dr-warning'
	}
	return ['new', 'waiting', 'declined'].includes(s) ? 'dr-muted' : ''
}

async function copyLink(link) {
	try {
		await navigator.clipboard.writeText(link)
		showSuccess('Link kopiert')
	} catch {
		window.prompt('Rückmelde-Link', link)
	}
}

async function load() {
	try {
		const [campaignData, replies] = await Promise.all([
			getCampaign(props.id),
			listReplies({ status: 'open', campaignId: props.id }),
		])
		openReplies.value = replies.replies.reduce((counts, r) => ({ ...counts, [r.sponsorId]: (counts[r.sponsorId] ?? 0) + 1 }), {})
		data.value = campaignData
		newName.value = data.value.campaign.name
	} catch (e) {
		showError(errorMessage(e))
	}
}

async function afterSend() {
	selected.value = []
	await load()
}

async function rename() {
	try {
		data.value.campaign = await updateCampaign(props.id, { name: newName.value, closed: campaign.value.closed })
		renameOpen.value = false
	} catch (e) {
		showError(errorMessage(e))
	}
}

async function toggleClosed() {
	try {
		data.value.campaign = await updateCampaign(props.id, { name: campaign.value.name, closed: !campaign.value.closed })
	} catch (e) {
		showError(errorMessage(e))
	}
}

async function remove() {
	try {
		await deleteCampaign(props.id)
		showSuccess('Aktion gelöscht')
		router.push({ name: 'campaigns' })
	} catch (e) {
		showError(errorMessage(e))
	}
}

onMounted(load)
</script>

<style scoped>
.dr-tiles {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
	gap: calc(var(--default-grid-baseline) * 3);
	margin: calc(var(--default-grid-baseline) * 4) 0;
}

.dr-toolbar {
	justify-content: space-between;
	margin-bottom: calc(var(--default-grid-baseline) * 2);
}

.dr-toolbar > * {
	flex: 0 0 auto;
	min-width: 240px;
}

.dr-toolbar__actions {
	margin-top: 0;
	flex-wrap: wrap;
}

.dr-reminder-rule {
	margin-bottom: calc(var(--default-grid-baseline) * 2);
}

.dr-check {
	width: 44px;
}

.dr-row-actions {
	display: flex;
	justify-content: flex-end;
}
</style>
