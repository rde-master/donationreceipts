<template>
	<div class="dr-page dr-viz">
		<div class="dr-header">
			<h2>Spender</h2>
			<NcButton variant="primary" :to="{ name: 'donor-new' }">
				<template #icon>
					<NcIconSvgWrapper :path="mdiPlus" />
				</template>
				Neuer Spender
			</NcButton>
		</div>

		<div v-if="!loading && donors.length" class="dr-overview">
			<div class="dr-tiles">
				<StatTile label="Spender"
					:value="String(donors.length)"
					class="dr-tile-link"
					@click="setFilter('all')">
					{{ counts.company }} Firmen · {{ counts.person }} Privatpersonen
				</StatTile>
				<StatTile label="Mit Bescheinigung"
					:value="String(counts.withReceipt)"
					class="dr-tile-link"
					@click="setFilter('withReceipt')">
					zusammen {{ formatAmount(counts.totalCents) }}
				</StatTile>
				<StatTile :label="`Gespendet ${currentYear}`"
					:value="String(counts.thisYear)"
					class="dr-tile-link"
					@click="setFilter('thisYear')">
					davon {{ counts.newThisYear }} zum ersten Mal
				</StatTile>
				<StatTile label="Mehrfach-Spender"
					:value="String(counts.repeat)"
					class="dr-tile-link"
					@click="setFilter('repeat')">
					in mehr als einem Jahr gespendet
				</StatTile>
				<StatTile label="Ohne E-Mail"
					:value="String(counts.noEmail)"
					class="dr-tile-link"
					@click="setFilter('noEmail')">
					Bescheinigungen gehen per Post
				</StatTile>
			</div>
			<div class="dr-charts">
				<section class="dr-chart-card">
					<h3>Firmen und Privatpersonen</h3>
					<SegmentBar label="Spender nach Art"
						:parts="[
							{ key: 'company', label: 'Firmen', value: counts.company, color: '--dr-series-1' },
							{ key: 'person', label: 'Privatpersonen', value: counts.person, color: '--dr-series-2' },
						]"
						selectable
						@select="setFilter" />
					<h3 class="dr-chart-card__second">
						Zuwendungen nach Art
					</h3>
					<SegmentBar label="Summe der Zuwendungen nach Art des Spenders"
						:parts="[
							{ key: 'company', label: 'von Firmen', value: counts.companyCents, color: '--dr-series-1' },
							{ key: 'person', label: 'von Privatpersonen', value: counts.personCents, color: '--dr-series-2' },
						]"
						:format="formatAmount" />
				</section>
				<section class="dr-chart-card">
					<h3>Größte Spender (alle Jahre)</h3>
					<p v-if="!top.length" class="dr-muted">
						Noch keine Bescheinigungen.
					</p>
					<BarList v-else
						label="Die fünf Spender mit der höchsten Summe"
						:items="top"
						:format="formatAmount" />
				</section>
			</div>
		</div>

		<div class="dr-row dr-toolbar">
			<NcTextField v-model="search"
				label="Suchen nach Name, Firma, E-Mail oder Ort"
				trailing-button-icon="close"
				:show-trailing-button="search !== ''"
				class="dr-search"
				@trailing-button-click="search = ''" />
			<NcSelect v-model="filter"
				:options="FILTERS"
				:clearable="false"
				input-label="Anzeigen"
				:label-outside="false"
				class="dr-filter" />
		</div>

		<NcLoadingIcon v-if="loading" :size="32" />
		<NcEmptyContent v-else-if="!visible.length"
			:name="donors.length ? 'Keine Treffer' : 'Noch keine Spender'"
			:description="donors.length ? 'Kein Spender passt zu Suche und Filter.' : 'Lege den ersten Spender an.'">
			<template #icon>
				<NcIconSvgWrapper :path="mdiAccountGroupOutline" />
			</template>
		</NcEmptyContent>
		<table v-else class="dr-table">
			<thead>
				<tr>
					<th>Name</th>
					<th>Anschrift</th>
					<th>Letzte Zuwendung</th>
					<th class="dr-num">
						Bescheinigungen
					</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="donor in visible"
					:key="donor.id"
					class="dr-clickable"
					@click="$router.push({ name: 'donor', params: { id: donor.id } })">
					<td>
						<RouterLink :to="{ name: 'donor', params: { id: donor.id } }">
							{{ sortName(donor) }}
						</RouterLink>
						<div class="dr-muted">
							{{ donor.email || 'keine E-Mail – Versand per Post' }}
							<template v-if="sponsorDonors.has(donor.id)">
								· auch möglicher Sponsor
							</template>
						</div>
					</td>
					<td>{{ [donor.street, [donor.zip, donor.city].filter(Boolean).join(' ')].filter(Boolean).join(', ') }}</td>
					<td>
						<template v-if="stats[donor.id]">
							{{ formatDate(stats[donor.id].lastDate) }}
							<div class="dr-muted">
								{{ formatAmount(stats[donor.id].lastCents) }}
							</div>
						</template>
						<span v-else class="dr-muted">noch keine</span>
					</td>
					<td class="dr-num">
						<template v-if="stats[donor.id]">
							{{ formatAmount(stats[donor.id].totalCents) }}
							<div class="dr-muted">
								{{ stats[donor.id].count }}× · {{ stats[donor.id].years.size === 1 ? [...stats[donor.id].years][0] : `${stats[donor.id].years.size} Jahre` }}
							</div>
						</template>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
</template>

<script setup>
import { mdiAccountGroupOutline, mdiPlus } from '@mdi/js'
import { showError } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { computed, onMounted, ref } from 'vue'
import { errorMessage, listDonors, listReceipts, listSponsors } from '../api.js'
import BarList from '../components/charts/BarList.vue'
import SegmentBar from '../components/charts/SegmentBar.vue'
import StatTile from '../components/charts/StatTile.vue'
import { formatAmount, formatDate } from '../format.js'

const currentYear = String(new Date().getFullYear())

const FILTERS = [
	{ id: 'all', label: 'Alle' },
	{ id: 'company', label: 'Firmen' },
	{ id: 'person', label: 'Privatpersonen' },
	{ id: 'withReceipt', label: 'Mit Bescheinigung' },
	{ id: 'withoutReceipt', label: 'Noch ohne Bescheinigung' },
	{ id: 'thisYear', label: `Gespendet ${currentYear}` },
	{ id: 'repeat', label: 'Mehrfach-Spender (mehrere Jahre)' },
	{ id: 'noEmail', label: 'Ohne E-Mail (Versand per Post)' },
]

const donors = ref([])
const receipts = ref([])
const sponsorDonors = ref(new Set())
const loading = ref(true)
const search = ref('')
const filter = ref(FILTERS[0])

function sortName(donor) {
	return donor.kind === 'company' ? donor.company : `${donor.lastName}, ${donor.firstName}`
}

// per donor: receipts count/sum, years (by receipt number), latest donation
const stats = computed(() => {
	const byDonor = {}
	for (const r of receipts.value) {
		const s = byDonor[r.donorId] ??= { count: 0, totalCents: 0, years: new Set(), lastDate: '', lastCents: 0 }
		s.count++
		s.totalCents += r.amountCents
		s.years.add(r.number.slice(0, 4))
		if (r.donationDate > s.lastDate) {
			s.lastDate = r.donationDate
			s.lastCents = r.amountCents
		}
	}
	return byDonor
})

function matches(donor, id) {
	const s = stats.value[donor.id]
	switch (id) {
	case 'company':
	case 'person':
		return donor.kind === id
	case 'withReceipt':
		return !!s
	case 'withoutReceipt':
		return !s
	case 'thisYear':
		return !!s?.years.has(currentYear)
	case 'repeat':
		return (s?.years.size ?? 0) > 1
	case 'noEmail':
		return !donor.email
	default:
		return true
	}
}

const visible = computed(() => {
	const term = search.value.trim().toLowerCase()
	return donors.value.filter((d) => matches(d, filter.value.id)
		&& (!term || [d.displayName, d.company, d.firstName, d.lastName, d.email, d.city, d.zip, d.street]
			.some((v) => (v ?? '').toLowerCase().includes(term))))
})

const counts = computed(() => {
	const c = { company: 0, person: 0, withReceipt: 0, totalCents: 0, thisYear: 0, newThisYear: 0, repeat: 0, noEmail: 0, companyCents: 0, personCents: 0 }
	for (const d of donors.value) {
		const s = stats.value[d.id]
		c[d.kind === 'company' ? 'company' : 'person']++
		if (!d.email) {
			c.noEmail++
		}
		if (!s) {
			continue
		}
		c.withReceipt++
		c.totalCents += s.totalCents
		c[d.kind === 'company' ? 'companyCents' : 'personCents'] += s.totalCents
		if (s.years.has(currentYear)) {
			c.thisYear++
			if (s.years.size === 1) {
				c.newThisYear++
			}
		}
		if (s.years.size > 1) {
			c.repeat++
		}
	}
	return c
})

const top = computed(() => donors.value
	.filter((d) => stats.value[d.id])
	.map((d) => ({
		key: d.id,
		label: d.displayName,
		value: stats.value[d.id].totalCents,
		to: { name: 'donor', params: { id: d.id } },
		sub: `${stats.value[d.id].count}×`,
	}))
	.sort((a, b) => b.value - a.value)
	.slice(0, 5))

function setFilter(id) {
	filter.value = FILTERS.find((f) => f.id === id)
}

onMounted(async () => {
	try {
		const [donorList, receiptData, sponsors] = await Promise.all([listDonors(), listReceipts(), listSponsors()])
		donors.value = donorList
		receipts.value = receiptData.receipts
		sponsorDonors.value = new Set(sponsors.map((s) => s.donorId).filter(Boolean))
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

.dr-chart-card .dr-chart-card__second {
	margin-top: calc(var(--default-grid-baseline) * 5);
}

.dr-toolbar {
	align-items: flex-end;
	margin-bottom: calc(var(--default-grid-baseline) * 2);
}

.dr-toolbar .dr-search {
	flex: 1 1 280px;
	margin-bottom: 0;
}

.dr-filter {
	flex: 0 1 320px;
	min-width: 240px;
}
</style>
