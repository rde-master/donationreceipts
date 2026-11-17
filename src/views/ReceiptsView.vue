<template>
	<div class="dr-page dr-viz">
		<div class="dr-header">
			<h2>Bescheinigungen</h2>
			<span class="dr-muted">Nächste Nummer: {{ nextNumber }}</span>
		</div>
		<div class="dr-row dr-filter">
			<NcSelect v-model="year"
				:options="years"
				:clearable="false"
				input-label="Jahr"
				:label-outside="false" />
		</div>

		<div v-if="!loading && (filtered.length || open.length)" class="dr-overview">
			<div class="dr-tiles">
				<StatTile label="Bescheinigungen" :value="String(filtered.length)">
					{{ overview.moneyCount }} Geld · {{ overview.goodsCount }} Sach
				</StatTile>
				<StatTile label="Summe" :value="formatAmount(sum)">
					<template v-if="filtered.length">
						Ø {{ formatAmount(Math.round(sum / filtered.length)) }} je Bescheinigung
					</template>
				</StatTile>
				<StatTile label="Spender" :value="String(overview.donors)">
					<template v-if="filtered.length">
						größte Zuwendung {{ formatAmount(overview.max) }}
					</template>
				</StatTile>
				<StatTile label="Noch zu versenden" :value="String(sending.toSend + sending.changed)">
					<span :class="{ 'dr-warning': sending.changed }">{{ sending.changed }} nach Versand geändert</span>
					· {{ sending.noEmail }} per Post
				</StatTile>
				<StatTile label="Vorbereitet" :value="String(open.length)">
					Zusagen über {{ formatAmount(openSum) }}, Spende noch nicht da
				</StatTile>
			</div>
			<div v-if="filtered.length" class="dr-charts">
				<section class="dr-chart-card">
					<h3>Versand</h3>
					<SendStatus :counts="sending" />
				</section>
				<section class="dr-chart-card">
					<h3>{{ year === ALL ? 'Zuwendungen pro Jahr' : `Zuwendungen pro Monat ${year}` }}</h3>
					<ColumnChart :key="year"
						:caption="year === ALL ? 'Summe der Zuwendungen pro Jahr, Geld und Sach' : `Summe der Zuwendungen pro Monat ${year} nach Ausstellungsdatum, Geld und Sach`"
						:labels="periods.labels"
						:titles="periods.titles"
						:series="periods.series"
						:height="180"
						:format="formatAmount"
						:format-tick="formatEuroTick" />
				</section>
			</div>
		</div>

		<section v-if="open.length" class="dr-open">
			<h3>Vorbereitet – Spende noch nicht eingegangen</h3>
			<p class="dr-muted">
				Rückmeldungen von Sponsoren. Sobald die Spende da ist, wird daraus die Bescheinigung
				erstellt und versendet.
			</p>
			<table class="dr-table">
				<thead>
					<tr>
						<th>Sponsor</th>
						<th>Aktion</th>
						<th>Rückmeldung</th>
						<th class="dr-num">
							Zusage
						</th>
						<th />
					</tr>
				</thead>
				<tbody>
					<tr v-for="inquiry in open" :key="inquiry.id">
						<td>{{ inquiry.response.displayName }}</td>
						<td>
							<RouterLink :to="{ name: 'campaign', params: { id: inquiry.campaignId } }">
								{{ inquiry.campaignName }}
							</RouterLink>
						</td>
						<td>{{ formatDateTime(inquiry.respondedAt) }}</td>
						<td class="dr-num">
							{{ formatAmount(inquiry.response.amountCents) }}
							<span v-if="inquiry.response.type === 'goods'" class="dr-muted">(Sache)</span>
						</td>
						<td class="dr-num">
							<NcButton v-if="inquiry.sponsor" variant="secondary" @click="receiving = inquiry">
								Spende eingegangen
							</NcButton>
						</td>
					</tr>
				</tbody>
			</table>
		</section>

		<NcLoadingIcon v-if="loading" :size="32" />
		<NcEmptyContent v-else-if="!filtered.length"
			name="Keine Bescheinigungen"
			description="Neue Bescheinigungen legst du in der Ansicht eines Spenders an.">
			<template #icon>
				<NcIconSvgWrapper :path="mdiFileDocumentOutline" />
			</template>
		</NcEmptyContent>
		<template v-else>
			<ReceiptTable :receipts="filtered" @sent="replace" />
			<p class="dr-muted dr-sum">
				{{ filtered.length }} Bescheinigungen, Summe {{ formatAmount(sum) }}
			</p>
		</template>

		<ResponseDialog v-if="receiving"
			:inquiry="receiving"
			:sponsor="receiving.sponsor"
			:ticket-labels="ticketLabels"
			@received="reload"
			@close="receiving = null" />
	</div>
</template>

<script setup>
import { mdiFileDocumentOutline } from '@mdi/js'
import { showError } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { computed, onMounted, ref } from 'vue'
import { errorMessage, listOpenResponses, listReceipts } from '../api.js'
import ColumnChart from '../components/charts/ColumnChart.vue'
import SendStatus from '../components/charts/SendStatus.vue'
import StatTile from '../components/charts/StatTile.vue'
import ReceiptTable from '../components/ReceiptTable.vue'
import ResponseDialog from '../components/ResponseDialog.vue'
import { formatAmount, formatDateTime } from '../format.js'

const ALL = 'Alle Jahre'

const receipts = ref([])
const nextNumber = ref('')
const loading = ref(true)
const year = ref(ALL)
const open = ref([])
const ticketLabels = ref({})
const receiving = ref(null)

const years = computed(() => [ALL, ...new Set(receipts.value.map((r) => r.number.slice(0, 4)))])
const filtered = computed(() => year.value === ALL
	? receipts.value
	: receipts.value.filter((r) => r.number.startsWith(year.value + '-')))
const sum = computed(() => filtered.value.reduce((total, r) => total + r.amountCents, 0))
const openSum = computed(() => open.value.reduce((total, i) => total + i.response.amountCents, 0))

const MONTHS = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez']
const MONTH_NAMES = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember']

const overview = computed(() => ({
	moneyCount: filtered.value.filter((r) => r.type !== 'goods').length,
	goodsCount: filtered.value.filter((r) => r.type === 'goods').length,
	donors: new Set(filtered.value.map((r) => r.donorId)).size,
	max: Math.max(0, ...filtered.value.map((r) => r.amountCents)),
}))

// as StatsService::sendState
const sending = computed(() => filtered.value.reduce((s, r) => {
	const state = r.sentAt
		? (r.changedAfterSent ? 'changed' : 'sent')
		: (r.donorHasEmail ? 'toSend' : 'noEmail')
	s[state]++
	return s
}, { sent: 0, toSend: 0, changed: 0, noEmail: 0 }))

// columns: months of the chosen year (by date of issue) or all years (by number)
const periods = computed(() => {
	const keys = year.value === ALL
		? years.value.slice(1).sort()
		: MONTHS.map((_, i) => String(i + 1).padStart(2, '0'))
	const periodOf = (r) => year.value === ALL ? r.number.slice(0, 4) : r.issueDate.slice(5, 7)
	const values = (type) => keys.map((k) => filtered.value
		.filter((r) => periodOf(r) === k && (r.type === 'goods') === (type === 'goods'))
		.reduce((total, r) => total + r.amountCents, 0))
	return {
		labels: year.value === ALL ? keys : MONTHS,
		titles: year.value === ALL ? keys : MONTH_NAMES.map((m) => `${m} ${year.value}`),
		series: [
			{ key: 'money', label: 'Geldzuwendungen', color: '--dr-series-1', values: values('money') },
			{ key: 'goods', label: 'Sachzuwendungen', color: '--dr-series-2', values: values('goods') },
		],
	}
})

function formatEuroTick(cents) {
	return new Intl.NumberFormat('de-DE', { maximumFractionDigits: 0 }).format(cents / 100) + ' €'
}

function replace(updated) {
	receipts.value = receipts.value.map((r) => r.id === updated.id ? { ...r, ...updated } : r)
}

async function loadOpen() {
	const data = await listOpenResponses()
	open.value = data.inquiries
	ticketLabels.value = data.ticketLabels
}

async function reload() {
	try {
		const data = await listReceipts()
		receipts.value = data.receipts
		nextNumber.value = data.nextNumber
		await loadOpen()
	} catch (e) {
		showError(errorMessage(e))
	}
}

onMounted(async () => {
	try {
		const data = await listReceipts()
		receipts.value = data.receipts
		nextNumber.value = data.nextNumber
		await loadOpen()
		if (years.value.length > 1) {
			year.value = years.value[1]
		}
	} catch (e) {
		showError(errorMessage(e))
	} finally {
		loading.value = false
	}
})
</script>

<style scoped>
.dr-tiles {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
	gap: calc(var(--default-grid-baseline) * 3);
	margin: calc(var(--default-grid-baseline) * 2) 0 calc(var(--default-grid-baseline) * 4);
}

.dr-charts {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
	gap: calc(var(--default-grid-baseline) * 3);
	margin-bottom: calc(var(--default-grid-baseline) * 6);
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

.dr-open {
	margin-bottom: calc(var(--default-grid-baseline) * 8);
}

.dr-filter {
	max-width: 240px;
	margin-bottom: calc(var(--default-grid-baseline) * 4);
}

.dr-sum {
	margin-top: calc(var(--default-grid-baseline) * 3);
}
</style>
