<template>
	<div class="dr-page dr-viz">
		<div class="dr-header">
			<h2>Aktionen</h2>
		</div>

		<div v-if="!loading && campaigns.length" class="dr-overview">
			<div class="dr-tiles">
				<StatTile label="Aktionen" :value="String(campaigns.length)">
					{{ openCampaigns }} offen · {{ campaigns.length - openCampaigns }} geschlossen
				</StatTile>
				<StatTile label="Angeschrieben" :value="String(totals.sent)">
					Antwortquote {{ totals.sent ? percent((totals.sent - totals.counts.open) / totals.sent) : '–' }}
				</StatTile>
				<StatTile label="Zugesagt" :value="formatAmount(totals.pledgedCents)">
					Eingang offen {{ formatAmount(totals.openCents) }}
				</StatTile>
				<StatTile label="Absagen" :value="String(totals.counts.declined)">
					{{ totals.counts.received + totals.counts.pledged + totals.counts.incomplete }} Zusagen
				</StatTile>
				<StatTile label="Zu erledigen" :value="String(totals.todo)">
					<span :class="{ 'dr-warning': totals.todo }">
						{{ totals.repliesWithoutStatus }} E-Mails ohne Status · {{ totals.reminderDue }} Erinnerungen fällig ·
						{{ totals.counts.incomplete }} Angaben fehlen
					</span>
				</StatTile>
			</div>
			<div class="dr-charts">
				<section class="dr-chart-card">
					<h3>Antworten aller Aktionen</h3>
					<AnswerBar :counts="totals.counts" label="Antworten aller Aktionen" />
				</section>
				<section v-if="campaigns.length > 1" class="dr-chart-card">
					<h3>Zugesagt je Aktion</h3>
					<BarList label="Zugesagter Betrag je Aktion" :items="byCampaign" :format="formatAmount" />
				</section>
			</div>
		</div>
		<form class="dr-row dr-new" @submit.prevent="create">
			<NcTextField v-model="newName" label="Neue Aktion, z. B. Sommerfest 2027" />
			<NcButton type="submit" variant="primary" :disabled="!newName.trim() || creating">
				<template #icon>
					<NcIconSvgWrapper :path="mdiPlus" />
				</template>
				Anlegen
			</NcButton>
		</form>

		<NcLoadingIcon v-if="loading" :size="32" />
		<NcEmptyContent v-else-if="!campaigns.length"
			name="Noch keine Aktionen"
			description="Eine Aktion fasst die Anschreiben an Sponsoren und ihre Rückmeldungen zusammen.">
			<template #icon>
				<NcIconSvgWrapper :path="mdiBullhornOutline" />
			</template>
		</NcEmptyContent>
		<table v-else class="dr-table">
			<thead>
				<tr>
					<th>Aktion</th>
					<th class="dr-num">
						Angeschrieben
					</th>
					<th class="dr-answers">
						Antworten
					</th>
					<th class="dr-num">
						Rückmeldungen
					</th>
					<th class="dr-num">
						Zugesagt
					</th>
					<th class="dr-num">
						Eingang offen
					</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="campaign in campaigns"
					:key="campaign.id"
					class="dr-clickable"
					@click="$router.push({ name: 'campaign', params: { id: campaign.id } })">
					<td>
						<RouterLink :to="{ name: 'campaign', params: { id: campaign.id } }">
							{{ campaign.name }}
						</RouterLink>
						<span v-if="campaign.closed" class="dr-muted"> (geschlossen)</span>
						<div v-if="campaign.summary.repliesWithoutStatus || campaign.summary.reminderDue" class="dr-warning">
							<template v-if="campaign.summary.repliesWithoutStatus">
								{{ campaign.summary.repliesWithoutStatus }} Antwort-Mails ohne Status
							</template>
							<template v-if="campaign.summary.repliesWithoutStatus && campaign.summary.reminderDue">
								·
							</template>
							<template v-if="campaign.summary.reminderDue">
								{{ campaign.summary.reminderDue }} Erinnerungen fällig
							</template>
						</div>
					</td>
					<td class="dr-num">
						{{ campaign.summary.sent }}
					</td>
					<td class="dr-answers">
						<AnswerBar :counts="answerCountsFromSummary(campaign.summary)"
							:label="`Antworten zu ${campaign.name}`"
							compact />
					</td>
					<td class="dr-num">
						{{ campaign.summary.responded }}
						<div v-if="campaign.summary.declined" class="dr-muted">
							davon {{ campaign.summary.declined }} Absagen
						</div>
					</td>
					<td class="dr-num">
						{{ formatAmount(campaign.summary.pledgedCents) }}
					</td>
					<td class="dr-num">
						{{ formatAmount(campaign.summary.openCents) }}
					</td>
				</tr>
			</tbody>
		</table>
	</div>
</template>

<script setup>
import { mdiBullhornOutline, mdiPlus } from '@mdi/js'
import { showError } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { createCampaign, errorMessage, listCampaigns } from '../api.js'
import AnswerBar from '../components/charts/AnswerBar.vue'
import BarList from '../components/charts/BarList.vue'
import StatTile from '../components/charts/StatTile.vue'
import { formatAmount } from '../format.js'
import { answerCountsFromSummary } from '../outcome.js'

const router = useRouter()
const campaigns = ref([])
const loading = ref(true)
const newName = ref('')
const creating = ref(false)

const openCampaigns = computed(() => campaigns.value.filter((c) => !c.closed).length)
const totals = computed(() => {
	const t = { sent: 0, pledgedCents: 0, openCents: 0, repliesWithoutStatus: 0, reminderDue: 0, counts: { received: 0, pledged: 0, incomplete: 0, open: 0, declined: 0 } }
	for (const c of campaigns.value) {
		t.sent += c.summary.sent
		t.pledgedCents += c.summary.pledgedCents
		t.openCents += c.summary.openCents
		t.repliesWithoutStatus += c.summary.repliesWithoutStatus
		t.reminderDue += c.summary.reminderDue
		for (const [key, value] of Object.entries(answerCountsFromSummary(c.summary))) {
			t.counts[key] += value
		}
	}
	t.todo = t.repliesWithoutStatus + t.reminderDue + t.counts.incomplete
	return t
})
const byCampaign = computed(() => campaigns.value
	.filter((c) => c.summary.pledgedCents > 0)
	.map((c) => ({
		key: c.id,
		label: c.name,
		value: c.summary.pledgedCents,
		to: { name: 'campaign', params: { id: c.id } },
		sub: `${formatAmount(c.summary.pledgedCents - c.summary.openCents)} eingegangen`,
	}))
	.sort((a, b) => b.value - a.value))

function percent(value) {
	return new Intl.NumberFormat('de-DE', { style: 'percent', maximumFractionDigits: 0 }).format(value)
}

async function create() {
	creating.value = true
	try {
		const campaign = await createCampaign(newName.value)
		router.push({ name: 'campaign', params: { id: campaign.id } })
	} catch (e) {
		showError(errorMessage(e))
	} finally {
		creating.value = false
	}
}

onMounted(async () => {
	try {
		campaigns.value = await listCampaigns()
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

.dr-answers {
	width: 22%;
	min-width: 120px;
}

.dr-new {
	max-width: 600px;
	margin-bottom: calc(var(--default-grid-baseline) * 6);
}

.dr-new > button {
	flex: 0 0 auto;
}
</style>
