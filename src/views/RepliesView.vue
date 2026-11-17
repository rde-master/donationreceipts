<template>
	<div class="dr-page dr-viz">
		<div class="dr-header">
			<h2>E-Mail-Antworten</h2>
			<NcButton :disabled="fetching || !enabled" @click="fetchNow">
				<template #icon>
					<NcLoadingIcon v-if="fetching" :size="20" />
					<NcIconSvgWrapper v-else :path="mdiRefresh" />
				</template>
				Jetzt abrufen
			</NcButton>
		</div>
		<p class="dr-muted">
			Antwort-Mails auf Anschreiben und Mails von möglichen Sponsoren aus dem Postfach der
			Anschreiben. Jede ist eine Aufgabe, bis jemand sie als erledigt markiert.
			<template v-if="lastRun">
				Zuletzt abgerufen: {{ formatDateTime(lastRun) }}.
			</template>
		</p>
		<NcNoteCard v-if="!loading && !enabled" type="info">
			Der Abruf ist nicht eingerichtet. Trage den IMAP-Zugang unter
			<RouterLink :to="{ name: 'settings' }">
				Einstellungen
			</RouterLink>
			ein und schalte den Abruf ein.
		</NcNoteCard>
		<NcNoteCard v-if="lastError" type="error">
			Letzter Abruf fehlgeschlagen: {{ lastError }}
		</NcNoteCard>

		<div v-if="stats && stats.total" class="dr-overview">
			<div class="dr-tiles">
				<StatTile label="Zu bearbeiten"
					:value="String(stats.open)"
					class="dr-tile-link"
					@click="showStatus('open')">
					<template v-if="stats.oldestOpenAt">
						älteste vom {{ formatDay(stats.oldestOpenAt) }}
					</template>
					<template v-else>
						alles erledigt
					</template>
				</StatTile>
				<StatTile label="Rückmeldung ohne Status"
					:value="String(stats.withoutStatus)"
					class="dr-tile-link"
					@click="showStatus('nostatus')">
					<span :class="{ 'dr-warning': stats.withoutStatus }">
						Sponsoren, deren Antwort noch keinen Status hat
					</span>
				</StatTile>
				<StatTile label="Letzte 7 Tage" :value="String(stats.lastWeek)">
					neue Antwort-Mails
				</StatTile>
				<StatTile label="Erledigt"
					:value="String(stats.done)"
					class="dr-tile-link"
					@click="showStatus('done')">
					dazu {{ stats.auto }} automatische Antworten
				</StatTile>
				<StatTile label="Keiner Aktion zugeordnet"
					:value="String(stats.unassigned)"
					class="dr-tile-link"
					@click="showStatus('unassigned')">
					z. B. Mails von einer anderen Adresse
				</StatTile>
			</div>
			<section class="dr-chart-card">
				<h3>Antwort-Mails pro Woche</h3>
				<ColumnChart caption="Antwort-Mails pro Woche, letzte 12 Wochen (ohne automatische Antworten)"
					:labels="stats.weeks.map((w) => shortDay(w.start + 86400))"
					:titles="stats.weeks.map((w) => `${formatDay(w.start + 86400)} – ${formatDay(w.start + 7 * 86400)}`)"
					:series="[{ key: 'replies', label: 'Antwort-Mails', color: '--dr-series-1', values: stats.weeks.map((w) => w.count) }]"
					:height="160"
					:format="formatCount"
					:format-tick="formatCount" />
			</section>
		</div>

		<div class="dr-row dr-filter">
			<NcSelect v-model="status"
				:options="STATUS"
				:clearable="false"
				input-label="Anzeigen"
				:label-outside="false" />
		</div>

		<NcLoadingIcon v-if="loading" :size="32" />
		<NcEmptyContent v-else-if="!replies.length"
			:name="status.id === 'open' ? 'Keine offenen E-Mail-Antworten' : 'Keine E-Mail-Antworten'"
			description="Neue Antworten erscheinen hier nach dem nächsten Abruf (alle 10 Minuten).">
			<template #icon>
				<NcIconSvgWrapper :path="mdiEmailCheckOutline" />
			</template>
		</NcEmptyContent>
		<ul v-else class="dr-replies">
			<li v-for="reply in replies"
				:key="reply.id"
				class="dr-reply"
				:class="{ 'dr-reply--done': reply.status === 'done' }">
				<div class="dr-reply__head">
					<div>
						<strong>{{ reply.sponsorName || reply.fromName || reply.fromEmail }}</strong>
						<span class="dr-muted"> &lt;{{ reply.fromEmail }}&gt;</span>
						<div class="dr-muted">
							{{ formatDateTime(reply.receivedAt) }}
							<template v-if="reply.campaignName">
								·
								<RouterLink :to="{ name: 'campaign', params: { id: reply.campaignId } }">
									{{ reply.campaignName }}
								</RouterLink>
							</template>
						</div>
					</div>
					<div class="dr-reply__state">
						<span :class="reply.status === 'open' ? 'dr-warning' : 'dr-muted'">
							{{ reply.status === 'open' ? 'zu bearbeiten' : `erledigt${reply.doneBy ? ' von ' + reply.doneBy : ''}` }}
						</span>
						<span v-if="reply.campaignId" class="dr-muted">
							Rückmeldung: {{ reply.outcome ? OUTCOMES[reply.outcome].label : 'noch kein Status' }}
						</span>
						<span v-else class="dr-muted">
							keiner Aktion zugeordnet
						</span>
					</div>
				</div>
				<div class="dr-reply__subject">
					{{ reply.subject || '(ohne Betreff)' }}
				</div>
				<div class="dr-pre dr-reply__body">
					{{ expanded[reply.id] ? reply.body : shortBody(reply.body) }}
				</div>
				<NcButton v-if="shortBody(reply.body) !== reply.body"
					variant="tertiary"
					size="small"
					@click="expanded[reply.id] = !expanded[reply.id]">
					{{ expanded[reply.id] ? 'Zitat ausblenden' : 'Ganze Mail anzeigen' }}
				</NcButton>
				<NcTextField v-model="notes[reply.id]" label="Notiz (z. B. was erledigt wurde)" />
				<div class="dr-actions">
					<NcButton v-if="reply.status === 'open'" variant="primary" @click="setStatus(reply, 'done')">
						<template #icon>
							<NcIconSvgWrapper :path="mdiCheck" />
						</template>
						Erledigt
					</NcButton>
					<NcButton v-else @click="setStatus(reply, 'open')">
						Wieder öffnen
					</NcButton>
					<NcButton v-if="(notes[reply.id] ?? '') !== reply.note" @click="setStatus(reply, reply.status)">
						Notiz speichern
					</NcButton>
					<NcButton v-if="reply.campaignId && reply.sponsorId"
						:variant="reply.status === 'open' ? 'secondary' : 'tertiary'"
						@click="openFor(reply)">
						<template #icon>
							<NcIconSvgWrapper :path="mdiListStatus" />
						</template>
						Status / Rückmeldung
					</NcButton>
					<NcButton variant="tertiary" :href="`mailto:${reply.fromEmail}?subject=${encodeURIComponent('Re: ' + reply.subject)}`">
						<template #icon>
							<NcIconSvgWrapper :path="mdiReply" />
						</template>
						Antworten
					</NcButton>
				</div>
			</li>
		</ul>

		<OutcomeDialog v-if="target && !target.manual"
			:campaign-id="target.campaignId"
			:sponsor="target.sponsor"
			:inquiry="target.inquiry"
			:reply-id="target.reply.id"
			@saved="changed"
			@manual="target.manual = true"
			@close="target?.manual || (target = null)" />

		<ResponseFormDialog v-if="target?.manual"
			:campaign-id="target.campaignId"
			:sponsor="target.sponsor"
			:inquiry="target.inquiry"
			:ticket-labels="target.ticketLabels"
			@saved="manualSaved"
			@close="target = null" />
	</div>
</template>

<script setup>
import { mdiCheck, mdiEmailCheckOutline, mdiListStatus, mdiRefresh, mdiReply } from '@mdi/js'
import { showError, showSuccess } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { onMounted, ref, watch } from 'vue'
import { errorMessage, fetchReplies, getCampaign, listReplies, updateReply } from '../api.js'
import OutcomeDialog from '../components/OutcomeDialog.vue'
import ColumnChart from '../components/charts/ColumnChart.vue'
import StatTile from '../components/charts/StatTile.vue'
import ResponseFormDialog from '../components/ResponseFormDialog.vue'
import { formatDateTime } from '../format.js'
import { OUTCOMES } from '../outcome.js'

const STATUS = [
	{ id: 'open', label: 'Zu bearbeiten' },
	{ id: 'done', label: 'Erledigt' },
	{ id: '', label: 'Alle' },
	// filtered here, from all replies
	{ id: 'nostatus', label: 'Rückmeldung ohne Status' },
	{ id: 'unassigned', label: 'Keiner Aktion zugeordnet' },
]
const LOCAL_FILTERS = {
	nostatus: (r) => r.campaignId && !r.outcome && r.note !== AUTO_NOTE,
	unassigned: (r) => !r.campaignId,
}
// as Reply::AUTO_NOTE
const AUTO_NOTE = 'Automatische Antwort (z. B. Abwesenheitsnotiz)'
// the navigation shows the number of open replies (App.vue)
const REPLIES_CHANGED = 'donationreceipts:replies-changed'
// where the quoted earlier mail starts (German and English mail programs)
const QUOTE_START = /^(Am .+ schrieb .+:|On .+ wrote:|-{2,} ?(Ursprüngliche Nachricht|Original Message) ?-{2,}|Von: .+|From: .+|>.*)$/m

const loading = ref(true)
const stats = ref(null)

function showStatus(id) {
	status.value = STATUS.find((s) => s.id === id)
}

function formatDay(timestamp) {
	return new Intl.DateTimeFormat('de-DE', { dateStyle: 'medium' }).format(new Date(timestamp * 1000))
}

function shortDay(timestamp) {
	return new Intl.DateTimeFormat('de-DE', { day: 'numeric', month: 'numeric' }).format(new Date(timestamp * 1000))
}

function formatCount(n) {
	return new Intl.NumberFormat('de-DE').format(n)
}
const fetching = ref(false)
const replies = ref([])
const enabled = ref(true)
const lastRun = ref(0)
const lastError = ref('')
const status = ref(STATUS[0])
const expanded = ref({})
const notes = ref({})
// reply whose answer status / response is being entered
const target = ref(null)

function shortBody(body) {
	const match = QUOTE_START.exec(body)
	return match && match.index > 0 ? body.slice(0, match.index).trim() : body
}

async function load() {
	try {
		const local = LOCAL_FILTERS[status.value.id]
		const data = await listReplies({ status: local ? '' : status.value.id })
		replies.value = local ? data.replies.filter(local) : data.replies
		stats.value = data.stats
		enabled.value = data.enabled
		lastRun.value = data.lastRun
		lastError.value = data.lastError
		notes.value = Object.fromEntries(data.replies.map((r) => [r.id, r.note]))
	} catch (e) {
		showError(errorMessage(e))
	} finally {
		loading.value = false
	}
}

async function fetchNow() {
	fetching.value = true
	try {
		const { new: count } = await fetchReplies()
		showSuccess(count ? `${count} neue Antworten` : 'Keine neuen Antworten')
		await load()
		window.dispatchEvent(new CustomEvent(REPLIES_CHANGED))
	} catch (e) {
		showError(errorMessage(e))
		await load()
	} finally {
		fetching.value = false
	}
}

async function setStatus(reply, newStatus) {
	try {
		await updateReply(reply.id, newStatus, notes.value[reply.id] ?? '')
		await load()
		window.dispatchEvent(new CustomEvent(REPLIES_CHANGED))
	} catch (e) {
		showError(errorMessage(e))
	}
}

async function openFor(reply) {
	try {
		const data = await getCampaign(reply.campaignId)
		const row = data.rows.find((r) => r.sponsor.id === reply.sponsorId)
		if (!row) {
			return
		}
		target.value = { reply, campaignId: reply.campaignId, sponsor: row.sponsor, inquiry: row.inquiry, ticketLabels: data.ticketLabels, manual: false }
	} catch (e) {
		showError(errorMessage(e))
	}
}

async function changed() {
	await load()
	window.dispatchEvent(new CustomEvent(REPLIES_CHANGED))
}

// the response answers the mail: mark it done
async function manualSaved() {
	const reply = target.value.reply
	try {
		if (reply.status === 'open') {
			await updateReply(reply.id, 'done', notes.value[reply.id] || 'Rückmeldung erfasst')
		}
	} catch (e) {
		showError(errorMessage(e))
	}
	await changed()
}

watch(status, load)
onMounted(load)
</script>

<style scoped>
.dr-tiles {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
	gap: calc(var(--default-grid-baseline) * 3);
	margin: calc(var(--default-grid-baseline) * 4) 0;
}

.dr-tile-link {
	cursor: pointer;
}

.dr-tile-link:hover {
	border-color: var(--color-primary-element);
}

.dr-chart-card {
	padding: calc(var(--default-grid-baseline) * 4);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
}

.dr-chart-card h3 {
	margin: 0 0 calc(var(--default-grid-baseline) * 2);
	font-size: 16px;
}

.dr-filter {
	max-width: 240px;
	margin: calc(var(--default-grid-baseline) * 4) 0;
}

.dr-replies {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 4);
}

.dr-reply {
	border: 1px solid var(--color-border);
	border-inline-start: 4px solid var(--color-warning);
	border-radius: var(--border-radius-large);
	padding: calc(var(--default-grid-baseline) * 3);
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 2);
}

.dr-reply--done {
	border-inline-start-color: var(--color-border);
}

.dr-reply__head {
	display: flex;
	justify-content: space-between;
	gap: calc(var(--default-grid-baseline) * 2);
}

.dr-reply__state {
	display: flex;
	flex-direction: column;
	align-items: flex-end;
	text-align: end;
}

.dr-reply__subject {
	font-weight: bold;
}

.dr-reply__body {
	max-height: 50vh;
	overflow-y: auto;
}

.dr-actions {
	flex-wrap: wrap;
	margin-top: 0;
}
</style>
