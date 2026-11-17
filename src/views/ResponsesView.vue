<template>
	<div class="dr-page dr-viz">
		<div class="dr-header">
			<h2>Zusagen</h2>
			<NcButton :disabled="!filtered.length" @click="exportCsv">
				<template #icon>
					<NcIconSvgWrapper :path="mdiDownload" />
				</template>
				Als CSV exportieren
			</NcButton>
		</div>
		<div class="dr-row dr-filters">
			<NcSelect v-model="campaign"
				:options="campaignOptions"
				label="name"
				:clearable="false"
				input-label="Aktion"
				:label-outside="false" />
			<NcSelect v-model="status"
				:options="STATUS"
				:clearable="false"
				input-label="Status"
				:label-outside="false" />
		</div>

		<div v-if="!loading && inCampaign.length" class="dr-overview">
			<div class="dr-tiles">
				<StatTile label="Zusagen" :value="String(inCampaign.length)">
					{{ overview.moneyCount }} Geld · {{ overview.goodsCount }} Sach
				</StatTile>
				<StatTile label="Zugesagt" :value="formatAmount(overview.total)">
					Ø {{ formatAmount(Math.round(overview.total / inCampaign.length)) }} je Zusage
				</StatTile>
				<StatTile label="Eingegangen"
					:value="formatAmount(overview.received)"
					class="dr-tile-link"
					@click="showStatus('received')">
					{{ overview.receivedCount }} von {{ inCampaign.length }} Spenden
				</StatTile>
				<StatTile label="Eingang offen"
					:value="formatAmount(overview.total - overview.received)"
					class="dr-tile-link"
					@click="showStatus('open')">
					{{ inCampaign.length - overview.receivedCount }} Zusagen
				</StatTile>
				<StatTile label="Freikarten" :value="String(overview.tickets)">
					{{ overview.ticketsLater }} melden sich noch · {{ overview.logos }} Logo-Freigaben
				</StatTile>
			</div>
			<div class="dr-charts">
				<section class="dr-chart-card">
					<h3>Eingang der zugesagten Beträge</h3>
					<SegmentBar label="Eingang der zugesagten Beträge"
						:parts="[
							{ key: 'received', label: 'eingegangen', value: overview.received, color: '--dr-good', icon: mdiCheckCircle },
							{ key: 'open', label: 'noch offen', value: overview.total - overview.received, color: '--dr-series-1', icon: mdiHandshakeOutline },
						]"
						:format="formatAmount"
						selectable
						@select="showStatus" />
				</section>
				<section class="dr-chart-card">
					<h3>{{ campaign.id === null && byCampaign.length > 1 ? 'Zugesagt je Aktion' : 'Größte Zusagen' }}</h3>
					<BarList v-if="campaign.id === null && byCampaign.length > 1"
						label="Zugesagter Betrag je Aktion"
						:items="byCampaign"
						:format="formatAmount" />
					<BarList v-else
						label="Die fünf größten Zusagen"
						:items="topPledges"
						:format="formatAmount" />
				</section>
			</div>
		</div>

		<NcLoadingIcon v-if="loading" :size="32" />
		<NcEmptyContent v-else-if="!filtered.length"
			name="Keine Zusagen"
			description="Zusagen kommen über das Rückmeldeformular oder werden in der Aktion manuell erfasst. Absagen und offene Antworten stehen in der Aktion.">
			<template #icon>
				<NcIconSvgWrapper :path="mdiClipboardTextOutline" />
			</template>
		</NcEmptyContent>
		<template v-else>
			<table class="dr-table">
				<thead>
					<tr>
						<th>Name</th>
						<th>Aktion</th>
						<th>Eingegangen</th>
						<th class="dr-num">
							Spende
						</th>
						<th>Freikarten</th>
						<th>Logo</th>
						<th>Hinweis</th>
						<th>Status</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="inquiry in filtered"
						:key="inquiry.id"
						class="dr-clickable"
						@click="selected = inquiry">
						<td>
							<div>{{ inquiry.response.displayName }}</div>
							<div v-if="inquiry.sponsor && inquiry.sponsor.name !== inquiry.response.displayName" class="dr-muted">
								{{ inquiry.sponsor.name }}
							</div>
						</td>
						<td>
							<RouterLink :to="{ name: 'campaign', params: { id: inquiry.campaignId } }" @click.stop>
								{{ inquiry.campaignName }}
							</RouterLink>
						</td>
						<td>
							{{ formatDateTime(inquiry.respondedAt) }}
							<div class="dr-muted">
								{{ inquiry.responseBy ? `manuell (${inquiry.responseBy})` : 'Formular' }}
							</div>
						</td>
						<td class="dr-num">
							{{ formatAmount(inquiry.response.amountCents) }}
							<div v-if="inquiry.response.type === 'goods'" class="dr-muted">
								Sachspende
							</div>
						</td>
						<td>{{ ticketLabels[inquiry.response.tickets] }}</td>
						<td>{{ inquiry.response.publish ? (inquiry.hasLogo ? 'Ja, Logo liegt vor' : 'Ja') : 'Nein' }}</td>
						<td class="dr-remarks">
							{{ inquiry.response.remarks }}
						</td>
						<td>
							<span v-if="inquiry.receiptId">Spende eingegangen</span>
							<span v-else class="dr-warning">Spende offen</span>
						</td>
					</tr>
				</tbody>
			</table>
			<p class="dr-muted dr-sum">
				{{ filtered.length }} Zusagen · zugesagt {{ formatAmount(sum) }} · Freikarten {{ tickets }}
			</p>
		</template>

		<ResponseDialog v-if="selected && selected.sponsor"
			:inquiry="selected"
			:sponsor="selected.sponsor"
			:ticket-labels="ticketLabels"
			@received="load"
			@close="selected = null" />
	</div>
</template>

<script setup>
import { mdiCheckCircle, mdiClipboardTextOutline, mdiDownload, mdiHandshakeOutline } from '@mdi/js'
import { showError } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { computed, onMounted, ref } from 'vue'
import { errorMessage, listResponses } from '../api.js'
import BarList from '../components/charts/BarList.vue'
import SegmentBar from '../components/charts/SegmentBar.vue'
import StatTile from '../components/charts/StatTile.vue'
import ResponseDialog from '../components/ResponseDialog.vue'
import { formatAmount, formatDateTime } from '../format.js'

const ALL_CAMPAIGNS = { id: null, name: 'Alle Aktionen' }
const STATUS = [
	{ id: 'all', label: 'Alle' },
	{ id: 'open', label: 'Spende offen' },
	{ id: 'received', label: 'Spende eingegangen' },
]
const TICKET_COUNT = { two: 2, one: 1 }

const loading = ref(true)
const inquiries = ref([])
const campaigns = ref([])
const ticketLabels = ref({})
const campaign = ref(ALL_CAMPAIGNS)
const status = ref(STATUS[0])
const selected = ref(null)

const campaignOptions = computed(() => [ALL_CAMPAIGNS, ...campaigns.value])
const filtered = computed(() => inquiries.value.filter((i) => (campaign.value.id === null || i.campaignId === campaign.value.id)
	&& (status.value.id === 'all' || (status.value.id === 'received') === (i.receiptId !== null))))
// the overview follows the campaign filter, not the status filter
const inCampaign = computed(() => inquiries.value.filter((i) => campaign.value.id === null || i.campaignId === campaign.value.id))
const overview = computed(() => inCampaign.value.reduce((o, i) => {
	const amount = i.response.amountCents
	o.total += amount
	if (i.receiptId) {
		o.received += amount
		o.receivedCount++
	}
	o[i.response.type === 'goods' ? 'goodsCount' : 'moneyCount']++
	o.tickets += TICKET_COUNT[i.response.tickets] ?? 0
	o.ticketsLater += i.response.tickets === 'later' ? 1 : 0
	o.logos += i.response.publish ? 1 : 0
	return o
}, { total: 0, received: 0, receivedCount: 0, moneyCount: 0, goodsCount: 0, tickets: 0, ticketsLater: 0, logos: 0 }))
const byCampaign = computed(() => {
	const sums = {}
	for (const i of inCampaign.value) {
		const s = sums[i.campaignId] ??= { key: i.campaignId, label: i.campaignName, value: 0, received: 0, to: { name: 'campaign', params: { id: i.campaignId } } }
		s.value += i.response.amountCents
		s.received += i.receiptId ? i.response.amountCents : 0
	}
	return Object.values(sums)
		.map((s) => ({ ...s, sub: `davon ${formatAmount(s.received)} eingegangen` }))
		.sort((a, b) => b.value - a.value)
})
const topPledges = computed(() => [...inCampaign.value]
	.sort((a, b) => b.response.amountCents - a.response.amountCents)
	.slice(0, 5)
	.map((i) => ({ key: i.id, label: i.response.displayName, value: i.response.amountCents, sub: i.receiptId ? 'eingegangen' : 'offen' })))

function showStatus(id) {
	status.value = STATUS.find((s) => s.id === id)
}

const sum = computed(() => filtered.value.reduce((total, i) => total + i.response.amountCents, 0))
const tickets = computed(() => filtered.value.reduce((total, i) => total + (TICKET_COUNT[i.response.tickets] ?? 0), 0))

async function load() {
	try {
		const data = await listResponses()
		inquiries.value = data.inquiries
		campaigns.value = data.campaigns
		ticketLabels.value = data.ticketLabels
		if (selected.value) {
			selected.value = data.inquiries.find((i) => i.id === selected.value.id) ?? null
		}
	} catch (e) {
		showError(errorMessage(e))
	} finally {
		loading.value = false
	}
}

/**
 * Semicolon-separated with BOM, so Excel opens it with umlauts and German decimals.
 */
function exportCsv() {
	const header = ['Name', 'Möglicher Sponsor', 'E-Mail', 'Bescheinigung an', 'Aktion', 'Rückmeldung am', 'Erfasst', 'Art', 'Betrag/Wert',
		'Sachspende', 'Freikarten', 'Anzahl Freikarten', 'Öffentlich (Logo)', 'Logo liegt vor', 'Hinweis',
		'Straße', 'PLZ', 'Ort', 'Land', 'Status']
	const rows = filtered.value.map((i) => [
		i.response.displayName,
		i.sponsor?.name ?? '',
		i.sponsor?.email ?? '',
		i.response.receiptEmail || (i.sponsor?.email ?? ''),
		i.campaignName,
		formatDateTime(i.respondedAt),
		i.responseBy ? `manuell (${i.responseBy})` : 'Formular',
		i.response.type === 'goods' ? 'Sachspende' : 'Geldspende',
		(i.response.amountCents / 100).toFixed(2).replace('.', ','),
		i.response.goodsDescription,
		ticketLabels.value[i.response.tickets] ?? '',
		TICKET_COUNT[i.response.tickets] ?? 0,
		i.response.publish ? 'Ja' : 'Nein',
		i.hasLogo ? 'Ja' : 'Nein',
		i.response.remarks,
		i.response.street,
		i.response.zip,
		i.response.city,
		i.response.country,
		i.receiptId ? 'Spende eingegangen' : 'Spende offen',
	])
	const quote = (value) => {
		const text = String(value ?? '')
		return /[";\r\n]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text
	}
	const csv = '﻿' + [header, ...rows].map((row) => row.map(quote).join(';')).join('\r\n')
	const link = document.createElement('a')
	link.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }))
	link.download = `Zusagen${campaign.value.id ? '_' + campaign.value.name.replace(/[^\wäöüÄÖÜß-]+/g, '_') : ''}.csv`
	link.click()
	URL.revokeObjectURL(link.href)
}

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

.dr-charts {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
	gap: calc(var(--default-grid-baseline) * 3);
	margin-bottom: calc(var(--default-grid-baseline) * 4);
}

.dr-chart-card {
	padding: calc(var(--default-grid-baseline) * 4);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	min-width: 0;
}

.dr-chart-card h3 {
	margin: 0 0 calc(var(--default-grid-baseline) * 3);
	font-size: 16px;
}

.dr-filters {
	max-width: 600px;
	margin-bottom: calc(var(--default-grid-baseline) * 4);
}

.dr-remarks {
	max-width: 250px;
	white-space: pre-wrap;
}

.dr-sum {
	margin-top: calc(var(--default-grid-baseline) * 2);
}
</style>
