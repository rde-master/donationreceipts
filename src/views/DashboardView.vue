<template>
	<div class="dr-page dr-page--wide dr-viz">
		<div class="dr-header">
			<h2>Übersicht</h2>
			<NcSelect v-if="stats"
				:model-value="stats.year"
				:options="yearOptions"
				:clearable="false"
				input-label="Jahr"
				class="dr-dash__year"
				@update:model-value="load" />
		</div>

		<NcLoadingIcon v-if="!stats" :size="32" />
		<template v-else>
			<NcNoteCard v-if="stats.missing.length" type="warning">
				Für Bescheinigungen fehlen noch: {{ stats.missing.join(', ') }}.
				<RouterLink :to="{ name: 'settings' }">
					Zu den Einstellungen
				</RouterLink>
			</NcNoteCard>
			<NcNoteCard v-if="bescheidState && bescheidState.type !== 'success'" :type="bescheidState.type">
				{{ bescheidState.text }}
			</NcNoteCard>

			<NcEmptyContent v-if="!hasAnyReceipt"
				name="Noch keine Bescheinigungen"
				description="Sobald Bescheinigungen erstellt sind, siehst du hier Summen, Verläufe und offene Aufgaben.">
				<template #icon>
					<NcIconSvgWrapper :path="mdiViewDashboardOutline" />
				</template>
				<template #action>
					<NcButton variant="primary" :to="{ name: 'donors' }">
						Zu den Spendern
					</NcButton>
				</template>
			</NcEmptyContent>

			<div v-else class="dr-dash" :class="{ 'dr-dash--refreshing': refreshing }">
				<div class="dr-dash__tiles">
					<StatTile hero
						class="dr-dash__hero"
						:label="`Zuwendungen ${stats.year}`"
						:value="formatAmount(totals.amountCents)"
						:delta="delta('amountCents')"
						:delta-label="deltaLabel">
						Geldzuwendungen {{ formatAmount(totals.moneyCents) }} · Sachzuwendungen {{ formatAmount(totals.goodsCents) }}
					</StatTile>
					<StatTile label="Bescheinigungen"
						:value="formatCount(totals.count)"
						:delta="delta('count')"
						:delta-label="deltaLabel">
						{{ totals.moneyCount }} Geld · {{ totals.goodsCount }} Sach
					</StatTile>
					<StatTile label="Spender mit Bescheinigung"
						:value="formatCount(totals.donors)"
						:delta="delta('donors')"
						:delta-label="deltaLabel">
						davon {{ totals.newDonors }} zum ersten Mal · {{ stats.donorsTotal }} Spender insgesamt
					</StatTile>
					<StatTile label="Durchschnitt je Bescheinigung"
						:value="totals.count ? formatAmount(Math.round(totals.amountCents / totals.count)) : '–'" />
					<StatTile label="Größte Einzelzuwendung"
						:value="totals.count ? formatAmount(totals.maxCents) : '–'" />
				</div>

				<section class="dr-card dr-dash__wide">
					<div class="dr-card__head">
						<h3>Ausgestellt pro Monat {{ stats.year }}</h3>
						<div class="dr-segmented" role="group" aria-label="Messgröße">
							<NcButton size="small"
								:pressed="measure === 'amount'"
								@update:pressed="measure = 'amount'">
								Betrag
							</NcButton>
							<NcButton size="small"
								:pressed="measure === 'count'"
								@update:pressed="measure = 'count'">
								Anzahl
							</NcButton>
						</div>
					</div>
					<ColumnChart :key="measure"
						:caption="`${measure === 'amount' ? 'Betrag' : 'Anzahl'} der Bescheinigungen pro Monat ${stats.year}`"
						:labels="MONTHS"
						:titles="MONTH_NAMES.map((m) => `${m} ${stats.year}`)"
						:series="monthSeries"
						:format="measure === 'amount' ? formatAmount : formatCount"
						:format-tick="measure === 'amount' ? formatEuroTick : formatCount" />
				</section>

				<section class="dr-card">
					<div class="dr-card__head">
						<h3>Versand {{ stats.year }}</h3>
					</div>
					<SendStatus :counts="stats.sending" />
					<template v-if="stats.openTasks.items.length">
						<h4>Offen</h4>
						<ul class="dr-tasks">
							<li v-for="t in stats.openTasks.items" :key="t.id">
								<RouterLink :to="{ name: 'receipt', params: { id: t.id } }">
									<NcIconSvgWrapper :path="t.state === 'changed' ? mdiAlertCircle : mdiEmailArrowRight"
										:size="18"
										inline
										:style="{ color: `var(${t.state === 'changed' ? '--dr-serious' : '--dr-warning'})` }" />
									<span class="dr-tasks__number">{{ t.number }}</span>
									<span class="dr-tasks__name">{{ t.donorName }}</span>
									<span class="dr-muted">{{ t.state === 'changed' ? 'erneut senden' : formatAmount(t.amountCents) }}</span>
								</RouterLink>
							</li>
						</ul>
						<p v-if="stats.openTasks.more" class="dr-muted">
							und {{ stats.openTasks.more }} weitere –
							<RouterLink :to="{ name: 'receipts' }">
								alle Bescheinigungen
							</RouterLink>
						</p>
					</template>
					<p v-else-if="totals.count" class="dr-muted dr-tasks__done">
						<NcIconSvgWrapper :path="mdiCheckCircle" :size="18" inline :style="{ color: 'var(--dr-good)' }" />
						Nichts mehr zu versenden.
					</p>
				</section>

				<section class="dr-card">
					<div class="dr-card__head">
						<h3>Größte Zuwendungen {{ stats.year }}</h3>
					</div>
					<p v-if="!stats.topDonors.items.length" class="dr-muted">
						In diesem Jahr noch keine Bescheinigungen.
					</p>
					<ol v-else class="dr-top">
						<li v-for="d in stats.topDonors.items" :key="d.id">
							<RouterLink :to="{ name: 'donor', params: { id: d.id } }" class="dr-top__name">
								{{ d.name }}
							</RouterLink>
							<div class="dr-top__track">
								<div class="dr-top__bar" :style="{ width: `calc((100% - 7em) * ${d.amountCents / topMax})` }" />
								<span class="dr-top__value">{{ formatAmount(d.amountCents) }}</span>
							</div>
							<span class="dr-muted dr-top__count">{{ d.count }}×</span>
						</li>
					</ol>
					<p v-if="stats.topDonors.more" class="dr-muted">
						und {{ stats.topDonors.more }} weitere Spender
					</p>
				</section>

				<section class="dr-card">
					<div class="dr-card__head">
						<h3>Zuwendungen pro Jahr</h3>
					</div>
					<ColumnChart caption="Summe der Zuwendungen pro Jahr"
						:labels="stats.years.map((y) => String(y.year))"
						:series="[{ key: 'amount', label: 'Zuwendungen', color: '--dr-series-1', values: stats.years.map((y) => y.amountCents) }]"
						:highlight="stats.years.findIndex((y) => y.year === stats.year)"
						selectable
						:height="200"
						:format="formatAmount"
						:format-tick="formatEuroTick"
						@select="(i) => load(stats.years[i].year)" />
					<p class="dr-muted dr-hint">
						Klick auf ein Jahr zeigt es in der ganzen Übersicht.
					</p>
				</section>

				<section class="dr-card">
					<div class="dr-card__head">
						<h3>Auf einen Blick</h3>
					</div>
					<ul class="dr-facts-list">
						<li v-if="bescheidState">
							<NcIconSvgWrapper :path="bescheidState.icon" :size="18" inline :style="{ color: `var(${bescheidState.color})` }" />
							<span>{{ bescheidState.short }}</span>
						</li>
						<li>
							<NcIconSvgWrapper :path="mdiPound" :size="18" inline />
							<span>Nächste Nummer <strong>{{ stats.nextNumber }}</strong></span>
						</li>
						<li>
							<NcIconSvgWrapper :path="mdiEmailOff" :size="18" inline />
							<span>
								{{ stats.donorsWithoutEmail }} von {{ stats.donorsTotal }} Spendern ohne E-Mail-Adresse
								<template v-if="stats.donorsWithoutEmail"> – deren Bescheinigungen gehen per Post</template>
							</span>
						</li>
						<li>
							<NcIconSvgWrapper :path="mdiCalendarRange" :size="18" inline />
							<span>Insgesamt {{ formatCount(allYears.count) }} Bescheinigungen über {{ formatAmount(allYears.amountCents) }}</span>
						</li>
					</ul>
				</section>
			</div>
		</template>
	</div>
</template>

<script setup>
import {
	mdiAlertCircle,
	mdiCalendarRange,
	mdiCheckCircle,
	mdiCloseCircle,
	mdiEmailArrowRight,
	mdiEmailOff,
	mdiPound,
	mdiViewDashboardOutline,
} from '@mdi/js'
import { showError } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { computed, onMounted, ref } from 'vue'
import { errorMessage, getStats } from '../api.js'
import ColumnChart from '../components/charts/ColumnChart.vue'
import SendStatus from '../components/charts/SendStatus.vue'
import StatTile from '../components/charts/StatTile.vue'
import { formatAmount, formatDate } from '../format.js'

const MONTHS = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez']
const MONTH_NAMES = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember']
// warn half a year before the notice runs out
const WARN_DAYS = 183

const stats = ref(null)
const refreshing = ref(false)
const measure = ref('amount')

const totals = computed(() => stats.value.totals)
const yearOptions = computed(() => stats.value.years.map((y) => y.year).reverse())
const allYears = computed(() => stats.value.years.reduce((sum, y) => ({
	count: sum.count + y.count,
	amountCents: sum.amountCents + y.amountCents,
}), { count: 0, amountCents: 0 }))
const hasAnyReceipt = computed(() => allYears.value.count > 0)
const topMax = computed(() => Math.max(...stats.value.topDonors.items.map((d) => d.amountCents), 1))

const monthSeries = computed(() => {
	const unit = measure.value === 'amount' ? 'Cents' : 'Count'
	return [
		{ key: 'money', label: 'Geldzuwendungen', color: '--dr-series-1', values: stats.value.months.map((m) => m['money' + unit]) },
		{ key: 'goods', label: 'Sachzuwendungen', color: '--dr-series-2', values: stats.value.months.map((m) => m['goods' + unit]) },
	]
})

const deltaLabel = computed(() => {
	if (!stats.value.partialYear) {
		return `ggü. ${stats.value.year - 1}`
	}
	const [, m, d] = stats.value.today.split('-')
	return `ggü. 1.1.–${Number(d)}.${Number(m)}.${stats.value.year - 1}`
})

/**
 * @param {string} key field of totals
 * @return {number|null} relative change against the previous year; null if there is nothing to compare with
 */
function delta(key) {
	const before = stats.value.previous[key]
	return before ? (totals.value[key] - before) / before : null
}

const bescheidState = computed(() => {
	const b = stats.value.bescheid
	if (!b) {
		return null
	}
	const what = b.mode === 'statute' ? 'Feststellungsbescheid (§ 60a AO)' : 'Freistellungsbescheid'
	const until = formatDate(b.validUntil)
	const days = (new Date(b.validUntil) - new Date(stats.value.today)) / 86400000
	if (days <= 0) {
		return {
			type: 'error',
			icon: mdiCloseCircle,
			color: '--dr-critical',
			short: `${what} abgelaufen seit ${until}`,
			text: `Der ${what} vom ${formatDate(b.date)} ist seit ${until} zu alt. Zuwendungsbestätigungen werden damit steuerlich nicht anerkannt (§ 63 Abs. 5 AO). Bitte einen aktuellen Bescheid beim Finanzamt anfordern und in den Einstellungen eintragen.`,
		}
	}
	if (days <= WARN_DAYS) {
		return {
			type: 'warning',
			icon: mdiAlertCircle,
			color: '--dr-warning',
			short: `${what} gilt nur noch bis ${until}`,
			text: `Der ${what} vom ${formatDate(b.date)} reicht für Zuwendungsbestätigungen nur noch bis ${until} (§ 63 Abs. 5 AO). Rechtzeitig einen neuen Bescheid anfordern.`,
		}
	}
	return {
		type: 'success',
		icon: mdiCheckCircle,
		color: '--dr-good',
		short: `${what} gilt bis ${until}`,
	}
})

function formatCount(n) {
	return new Intl.NumberFormat('de-DE').format(n)
}

function formatEuroTick(cents) {
	const euros = cents / 100
	if (euros >= 1_000_000) {
		return new Intl.NumberFormat('de-DE', { maximumFractionDigits: 1 }).format(euros / 1_000_000) + ' Mio. €'
	}
	return new Intl.NumberFormat('de-DE', { maximumFractionDigits: 0 }).format(euros) + ' €'
}

async function load(year = null) {
	refreshing.value = stats.value !== null
	try {
		stats.value = await getStats(year)
	} catch (e) {
		showError(errorMessage(e))
	} finally {
		refreshing.value = false
	}
}

onMounted(() => load())
</script>

<style scoped>
.dr-dash__year {
	min-width: 140px;
}

.dr-dash {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: calc(var(--default-grid-baseline) * 4);
	transition: opacity 0.15s;
}

.dr-dash--refreshing {
	opacity: 0.55;
	pointer-events: none;
}

.dr-dash__tiles {
	grid-column: 1 / -1;
	display: grid;
	grid-template-columns: repeat(4, minmax(0, 1fr));
	gap: calc(var(--default-grid-baseline) * 4);
}

.dr-dash__hero {
	grid-column: span 2;
	grid-row: span 2;
	justify-content: center;
}

.dr-dash__wide {
	grid-column: 1 / -1;
}

.dr-card {
	padding: calc(var(--default-grid-baseline) * 4);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	min-width: 0;
}

.dr-card h3 {
	margin: 0;
	font-size: 16px;
}

.dr-card h4 {
	margin: calc(var(--default-grid-baseline) * 4) 0 calc(var(--default-grid-baseline) * 1);
	font-size: 14px;
	color: var(--color-text-maxcontrast);
	font-weight: normal;
}

.dr-card__head {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	justify-content: space-between;
	gap: calc(var(--default-grid-baseline) * 2);
	margin-bottom: calc(var(--default-grid-baseline) * 3);
}

.dr-segmented {
	display: flex;
	gap: 4px;
}

.dr-card p a,
:deep(.notecard) a {
	text-decoration: underline;
}

.dr-hint {
	margin-top: calc(var(--default-grid-baseline) * 2);
	font-size: 13px;
}

.dr-tasks li a {
	display: grid;
	grid-template-columns: 18px max-content minmax(0, 1fr) max-content;
	align-items: center;
	gap: calc(var(--default-grid-baseline) * 2);
	padding: 6px calc(var(--default-grid-baseline) * 1);
	border-radius: var(--border-radius);
}

.dr-tasks li a:hover,
.dr-tasks li a:focus-visible {
	background: var(--color-background-hover);
}

.dr-tasks__number {
	font-variant-numeric: tabular-nums;
}

.dr-tasks__name {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.dr-tasks__done {
	display: flex;
	align-items: center;
	gap: 6px;
	margin-top: calc(var(--default-grid-baseline) * 3);
}

.dr-top li {
	display: grid;
	grid-template-columns: minmax(0, 9em) minmax(0, 1fr) 2.5em;
	align-items: center;
	gap: calc(var(--default-grid-baseline) * 2);
	padding: 4px 0;
}

.dr-top__name {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.dr-top__name:hover {
	text-decoration: underline;
}

.dr-top__track {
	display: flex;
	align-items: center;
	gap: 8px;
	min-width: 0;
}

.dr-top__bar {
	flex: none;
	min-width: 2px;
	height: 12px;
	background: var(--dr-series-1);
	border-start-end-radius: 4px;
	border-end-end-radius: 4px;
}

.dr-top__value {
	white-space: nowrap;
	font-size: 13px;
	font-variant-numeric: tabular-nums;
}

.dr-top__count {
	text-align: end;
	font-size: 13px;
}

.dr-facts-list li {
	display: flex;
	align-items: flex-start;
	gap: calc(var(--default-grid-baseline) * 2);
	padding: 6px 0;
}

@media (max-width: 900px) {
	.dr-dash__tiles {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}
}

@media (max-width: 700px) {
	.dr-dash,
	.dr-dash__tiles {
		grid-template-columns: minmax(0, 1fr);
	}

	.dr-dash__hero {
		grid-column: auto;
		grid-row: auto;
	}
}
</style>
