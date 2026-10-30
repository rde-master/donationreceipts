<template>
	<div class="dr-page dr-page--wide">
		<div class="dr-header">
			<h2>Rückmeldungen</h2>
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

		<NcLoadingIcon v-if="loading" :size="32" />
		<NcEmptyContent v-else-if="!filtered.length"
			name="Keine Rückmeldungen"
			description="Rückmeldungen kommen über das Formular oder werden in der Aktion manuell erfasst.">
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
				{{ filtered.length }} Rückmeldungen · zugesagt {{ formatAmount(sum) }} · Freikarten {{ tickets }}
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
import { mdiClipboardTextOutline, mdiDownload } from '@mdi/js'
import { showError } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { computed, onMounted, ref } from 'vue'
import { errorMessage, listResponses } from '../api.js'
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
	const header = ['Name', 'Möglicher Sponsor', 'E-Mail', 'Aktion', 'Rückmeldung am', 'Erfasst', 'Art', 'Betrag/Wert',
		'Sachspende', 'Freikarten', 'Anzahl Freikarten', 'Öffentlich (Logo)', 'Logo liegt vor', 'Hinweis',
		'Straße', 'PLZ', 'Ort', 'Land', 'Status']
	const rows = filtered.value.map((i) => [
		i.response.displayName,
		i.sponsor?.name ?? '',
		i.sponsor?.email ?? '',
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
	link.download = `Rueckmeldungen${campaign.value.id ? '_' + campaign.value.name.replace(/[^\wäöüÄÖÜß-]+/g, '_') : ''}.csv`
	link.click()
	URL.revokeObjectURL(link.href)
}

onMounted(load)
</script>

<style scoped>
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
