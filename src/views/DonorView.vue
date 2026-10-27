<template>
	<div class="dr-page">
		<NcLoadingIcon v-if="!donor" :size="32" />
		<template v-else>
			<div class="dr-header">
				<h2>{{ donor.displayName }}</h2>
				<div class="dr-actions">
					<NcButton :to="{ name: 'donor-edit', params: { id: donor.id } }">
						<template #icon>
							<NcIconSvgWrapper :path="mdiPencil" />
						</template>
						Bearbeiten
					</NcButton>
					<NcButton v-if="receipts && !receipts.length" variant="error" @click="confirmDelete = true">
						<template #icon>
							<NcIconSvgWrapper :path="mdiDelete" />
						</template>
						Löschen
					</NcButton>
				</div>
			</div>

			<dl class="dr-facts">
				<dt>Art</dt>
				<dd>{{ donor.kind === 'company' ? 'Firma' : 'Person' }}</dd>
				<dt>Anschrift</dt>
				<dd>
					<div v-for="line in addressLines" :key="line">
						{{ line }}
					</div>
				</dd>
				<dt>E-Mail</dt>
				<dd>{{ donor.email || '–' }}</dd>
				<template v-if="donor.notes">
					<dt>Notiz</dt>
					<dd class="dr-pre">
						{{ donor.notes }}
					</dd>
				</template>
			</dl>

			<div class="dr-header">
				<h3>Bescheinigungen</h3>
				<NcButton variant="primary" :to="{ name: 'receipt-new', params: { donorId: donor.id } }">
					<template #icon>
						<NcIconSvgWrapper :path="mdiPlus" />
					</template>
					Neue Bescheinigung
				</NcButton>
			</div>
			<NcLoadingIcon v-if="!receipts" :size="24" />
			<p v-else-if="!receipts.length" class="dr-muted">
				Noch keine Bescheinigungen.
			</p>
			<ReceiptTable v-else :receipts="receipts" :show-donor="false" />
		</template>

		<NcDialog v-if="confirmDelete"
			name="Spender löschen?"
			:message="`${donor.displayName} wird endgültig gelöscht.`"
			:buttons="deleteButtons"
			@closing="confirmDelete = false" />
	</div>
</template>

<script setup>
import { mdiDelete, mdiPencil, mdiPlus } from '@mdi/js'
import { showError, showSuccess } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { deleteDonor, errorMessage, getDonor, listReceipts } from '../api.js'
import ReceiptTable from '../components/ReceiptTable.vue'

const props = defineProps({
	id: { type: [String, Number], required: true },
})

const router = useRouter()
const donor = ref(null)
const receipts = ref(null)
const confirmDelete = ref(false)

const addressLines = computed(() => [
	donor.value.street,
	[donor.value.zip, donor.value.city].filter(Boolean).join(' '),
	donor.value.country,
].filter(Boolean))

const deleteButtons = [
	{ label: 'Abbrechen' },
	{
		label: 'Löschen',
		variant: 'error',
		callback: remove,
	},
]

async function remove() {
	try {
		await deleteDonor(props.id)
		showSuccess('Spender gelöscht')
		router.push({ name: 'donors' })
	} catch (e) {
		showError(errorMessage(e))
	}
}

onMounted(async () => {
	try {
		donor.value = await getDonor(props.id)
		receipts.value = (await listReceipts({ donorId: props.id })).receipts
	} catch (e) {
		showError(errorMessage(e))
	}
})
</script>
