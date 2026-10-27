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

		<NcLoadingIcon v-if="loading" :size="32" />
		<NcEmptyContent v-else-if="!filtered.length"
			name="Keine Bescheinigungen"
			description="Neue Bescheinigungen legst du in der Ansicht eines Spenders an.">
			<template #icon>
				<NcIconSvgWrapper :path="mdiFileDocumentOutline" />
			</template>
		</NcEmptyContent>
		<template v-else>
			<ReceiptTable :receipts="filtered" />
			<p class="dr-muted dr-sum">
				{{ filtered.length }} Bescheinigungen, Summe {{ formatAmount(sum) }}
			</p>
		</template>
	</div>
</template>

<script setup>
import { mdiFileDocumentOutline } from '@mdi/js'
import { showError } from '@nextcloud/dialogs'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { computed, onMounted, ref } from 'vue'
import { errorMessage, listReceipts } from '../api.js'
import ReceiptTable from '../components/ReceiptTable.vue'
import { formatAmount } from '../format.js'

const ALL = 'Alle Jahre'

const receipts = ref([])
const nextNumber = ref('')
const loading = ref(true)
const year = ref(ALL)

const years = computed(() => [ALL, ...new Set(receipts.value.map((r) => r.number.slice(0, 4)))])
const filtered = computed(() => year.value === ALL
	? receipts.value
	: receipts.value.filter((r) => r.number.startsWith(year.value + '-')))
const sum = computed(() => filtered.value.reduce((total, r) => total + r.amountCents, 0))

onMounted(async () => {
	try {
		const data = await listReceipts()
		receipts.value = data.receipts
		nextNumber.value = data.nextNumber
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
.dr-filter {
	max-width: 240px;
	margin-bottom: calc(var(--default-grid-baseline) * 4);
}

.dr-sum {
	margin-top: calc(var(--default-grid-baseline) * 3);
}
</style>
