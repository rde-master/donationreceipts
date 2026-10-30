<template>
	<div class="dr-page dr-page--wide">
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
