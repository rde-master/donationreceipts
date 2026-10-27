<template>
	<table class="dr-table">
		<thead>
			<tr>
				<th>Nummer</th>
				<th v-if="showDonor">
					Spender
				</th>
				<th>Art</th>
				<th>Tag der Zuwendung</th>
				<th class="dr-num">
					Betrag
				</th>
				<th>Versand</th>
				<th />
			</tr>
		</thead>
		<tbody>
			<tr v-for="receipt in receipts"
				:key="receipt.id"
				class="dr-clickable"
				@click="$router.push({ name: 'receipt', params: { id: receipt.id } })">
				<td>
					<RouterLink :to="{ name: 'receipt', params: { id: receipt.id } }">
						{{ receipt.number }}
					</RouterLink>
				</td>
				<td v-if="showDonor">
					{{ receipt.donorName }}
				</td>
				<td>{{ receipt.type === 'goods' ? 'Sache' : 'Geld' }}</td>
				<td>{{ formatDate(receipt.donationDate) }}</td>
				<td class="dr-num">
					{{ formatAmount(receipt.amountCents) }}
				</td>
				<td>
					<span v-if="receipt.sentAt" :class="{ 'dr-warning': receipt.changedAfterSent }">
						{{ receipt.changedAfterSent ? 'nach Versand geändert' : 'versendet' }}
					</span>
					<span v-else class="dr-muted">nicht versendet</span>
				</td>
				<td class="dr-num">
					<NcButton v-if="!receipt.sentAt || receipt.changedAfterSent"
						variant="tertiary"
						:aria-label="`Bescheinigung ${receipt.number} senden`"
						title="Per E-Mail senden"
						@click.stop="sending = receipt">
						<template #icon>
							<NcIconSvgWrapper :path="mdiEmailOutline" />
						</template>
					</NcButton>
				</td>
			</tr>
		</tbody>
	</table>
	<SendDialog v-if="sending"
		:receipt="sending"
		@sent="$emit('sent', $event)"
		@close="sending = null" />
</template>

<script setup>
import { mdiEmailOutline } from '@mdi/js'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { ref } from 'vue'
import { formatAmount, formatDate } from '../format.js'
import SendDialog from './SendDialog.vue'

defineEmits(['sent'])

const sending = ref(null)

defineProps({
	receipts: { type: Array, required: true },
	showDonor: { type: Boolean, default: true },
})
</script>
