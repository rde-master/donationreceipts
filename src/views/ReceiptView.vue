<template>
	<div class="dr-page">
		<NcLoadingIcon v-if="!receipt" :size="32" />
		<template v-else>
			<div class="dr-header">
				<h2>Bescheinigung {{ receipt.number }}</h2>
				<div class="dr-actions">
					<NcButton variant="primary" @click="sendOpen = true">
						<template #icon>
							<NcIconSvgWrapper :path="mdiEmailOutline" />
						</template>
						{{ receipt.sentAt ? 'Erneut senden' : 'Per E-Mail senden' }}
					</NcButton>
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

			<div class="dr-receipt">
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

				<section v-if="previewVariants.length" class="dr-preview">
					<div class="dr-preview__head">
						<h3>Vorschau</h3>
						<div v-if="previewVariants.length > 1" class="dr-preview__switch" role="group" aria-label="Fassung">
							<NcButton v-for="variant in previewVariants"
								:key="variant"
								size="small"
								:pressed="preview === variant"
								@update:pressed="preview = variant">
								{{ variant === 'signed' ? 'mit Unterschrift' : 'ohne Unterschrift' }}
							</NcButton>
						</div>
					</div>
					<iframe :key="previewUrl"
						class="dr-preview__frame"
						:src="previewUrl"
						:title="`Vorschau der Bescheinigung ${receipt.number}`" />
					<p class="dr-muted">
						Wird keine Vorschau angezeigt, öffne die PDF über die Knöpfe oben.
					</p>
				</section>
			</div>
		</template>

		<SendDialog v-if="sendOpen && receipt"
			:receipt="receipt"
			:note="sendNote"
			@sent="receipt = { ...receipt, ...$event }"
			@close="sendOpen = false" />
	</div>
</template>

<script setup>
import { mdiEmailOutline, mdiFilePdfBox, mdiFolderOutline, mdiPencil } from '@mdi/js'
import { showError } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { errorMessage, getDonor, getReceipt, receiptFileUrl } from '../api.js'
import SendDialog from '../components/SendDialog.vue'
import { formatAmount, formatDate, formatDateTime, ORIGINS } from '../format.js'

const props = defineProps({
	id: { type: [String, Number], required: true },
})

const route = useRoute()
const receipt = ref(null)
const donor = ref(null)
const sendOpen = ref(false)
const sendNote = ref('')

const VARIANTS = {
	signed: 'PDF mit Unterschrift',
	unsigned: 'PDF ohne Unterschrift',
}

// signed first: that is what the donor gets
const previewVariants = computed(() => ['signed', 'unsigned'].filter((v) => receipt.value?.files?.[v]))
const preview = ref('signed')
// updatedAt: a changed receipt is not shown from the browser cache; fit the page width
const previewUrl = computed(() => {
	const variant = previewVariants.value.includes(preview.value) ? preview.value : previewVariants.value[0]
	return `${receiptFileUrl(receipt.value.id, variant)}?v=${receipt.value.updatedAt}#view=FitH`
})

function filesAppUrl(fileId) {
	return generateUrl(`/f/${fileId}`)
}

onMounted(async () => {
	try {
		receipt.value = await getReceipt(props.id)
		donor.value = await getDonor(receipt.value.donorId)
		// right after creating / after changing a sent receipt: offer sending
		if (route.query.senden && !receipt.value.sentAt && donor.value.email) {
			sendOpen.value = true
		} else if (route.query.erneut && receipt.value.changedAfterSent) {
			sendNote.value = 'Die Bescheinigung wurde nach dem Versand geändert. Soll der Spender die neue Fassung bekommen?'
			sendOpen.value = true
		}
	} catch (e) {
		showError(errorMessage(e))
	}
})
</script>

<style scoped>
.dr-receipt {
	display: grid;
	grid-template-columns: minmax(280px, 1fr) minmax(320px, 1.4fr);
	gap: calc(var(--default-grid-baseline) * 6);
	align-items: start;
}

.dr-preview__head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	flex-wrap: wrap;
	gap: calc(var(--default-grid-baseline) * 2);
	margin-bottom: calc(var(--default-grid-baseline) * 2);
}

.dr-preview__head h3 {
	margin: 0;
}

.dr-preview__switch {
	display: flex;
	gap: var(--default-grid-baseline);
}

.dr-preview__frame {
	display: block;
	width: 100%;
	/* A4 portrait */
	aspect-ratio: 210 / 297;
	max-height: 85vh;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-dark);
}

.dr-preview p {
	margin-top: var(--default-grid-baseline);
	font-size: 13px;
}

@media (max-width: 1000px) {
	.dr-receipt {
		grid-template-columns: 1fr;
	}
}

.dr-files {
	display: flex;
	flex-wrap: wrap;
	gap: calc(var(--default-grid-baseline) * 2);
	margin-bottom: calc(var(--default-grid-baseline) * 4);
}
</style>
