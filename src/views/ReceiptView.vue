<template>
	<div class="dr-page">
		<NcLoadingIcon v-if="!receipt" :size="32" />
		<template v-else>
			<div class="dr-header">
				<h2>Bescheinigung {{ receipt.number }}</h2>
				<div class="dr-actions">
					<NcButton :to="{ name: 'receipt-edit', params: { id: receipt.id } }">
						<template #icon>
							<NcIconSvgWrapper :path="mdiPencil" />
						</template>
						Bearbeiten
					</NcButton>
				</div>
			</div>

			<NcNoteCard v-if="receipt.changedAfterSent" type="warning">
				Nach dem Versand geändert. Der Spender hat noch die alte Fassung.
			</NcNoteCard>

			<div class="dr-files">
				<template v-for="(label, variant) in VARIANTS" :key="variant">
					<NcButton v-if="receipt.files?.[variant]"
						:href="receiptFileUrl(receipt.id, variant)"
						target="_blank">
						<template #icon>
							<NcIconSvgWrapper :path="mdiFilePdfBox" />
						</template>
						{{ label }}
					</NcButton>
				</template>
				<NcButton v-if="receipt.files?.signed"
					variant="tertiary"
					:href="filesAppUrl(receipt.files.signed.id)">
					<template #icon>
						<NcIconSvgWrapper :path="mdiFolderOutline" />
					</template>
					In Dateien zeigen
				</NcButton>
			</div>
			<NcNoteCard v-if="!receipt.files?.unsigned || !receipt.files?.signed" type="warning">
				Mindestens eine PDF-Datei fehlt im Spenderordner. Speichere die Bescheinigung erneut,
				um beide Fassungen neu zu erzeugen.
			</NcNoteCard>

			<dl class="dr-facts">
				<dt>Spender</dt>
				<dd>
					<RouterLink v-if="donor" :to="{ name: 'donor', params: { id: donor.id } }">
						{{ donor.displayName }}
					</RouterLink>
				</dd>
				<dt>Art</dt>
				<dd>{{ receipt.type === 'goods' ? 'Sachzuwendung' : 'Geldzuwendung' }}</dd>
				<dt>{{ receipt.type === 'goods' ? 'Wert' : 'Betrag' }}</dt>
				<dd>{{ formatAmount(receipt.amountCents) }}</dd>
				<dt>Tag der Zuwendung</dt>
				<dd>{{ formatDate(receipt.donationDate) }}</dd>
				<dt>Ausgestellt am</dt>
				<dd>{{ formatDate(receipt.issueDate) }}</dd>
				<template v-if="receipt.type === 'money'">
					<dt>Verzicht auf Aufwendungsersatz</dt>
					<dd>{{ receipt.expenseWaiver ? 'Ja' : 'Nein' }}</dd>
				</template>
				<template v-else>
					<dt>Bezeichnung</dt>
					<dd class="dr-pre">
						{{ receipt.goodsDescription }}
					</dd>
					<dt>Herkunft</dt>
					<dd>{{ ORIGINS[receipt.goodsOrigin] }}</dd>
					<dt>Unterlagen zur Wertermittlung</dt>
					<dd>{{ receipt.goodsValuationDocuments ? 'liegen vor' : '–' }}</dd>
				</template>
				<dt>Versand</dt>
				<dd>
					<template v-if="receipt.sentAt">
						{{ formatDateTime(receipt.sentAt) }} an {{ receipt.sentTo }}
					</template>
					<span v-else class="dr-muted">noch nicht versendet</span>
				</dd>
				<dt>Erstellt</dt>
				<dd>{{ formatDateTime(receipt.createdAt) }} von {{ receipt.createdBy }}</dd>
			</dl>
		</template>
	</div>
</template>

<script setup>
import { mdiFilePdfBox, mdiFolderOutline, mdiPencil } from '@mdi/js'
import { showError } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import { onMounted, ref } from 'vue'
import { errorMessage, getDonor, getReceipt, receiptFileUrl } from '../api.js'
import { formatAmount, formatDate, formatDateTime, ORIGINS } from '../format.js'

const props = defineProps({
	id: { type: [String, Number], required: true },
})

const receipt = ref(null)
const donor = ref(null)

const VARIANTS = {
	signed: 'PDF mit Unterschrift',
	unsigned: 'PDF ohne Unterschrift',
}

function filesAppUrl(fileId) {
	return generateUrl(`/f/${fileId}`)
}

onMounted(async () => {
	try {
		receipt.value = await getReceipt(props.id)
		donor.value = await getDonor(receipt.value.donorId)
	} catch (e) {
		showError(errorMessage(e))
	}
})
</script>

<style scoped>
.dr-files {
	display: flex;
	flex-wrap: wrap;
	gap: calc(var(--default-grid-baseline) * 2);
	margin-bottom: calc(var(--default-grid-baseline) * 4);
}
</style>
