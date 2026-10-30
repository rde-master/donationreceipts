<template>
	<div class="dr-page">
		<div class="dr-header">
			<h2>Aktionen</h2>
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
					</td>
					<td class="dr-num">
						{{ campaign.summary.sent }}
					</td>
					<td class="dr-num">
						{{ campaign.summary.responded }}
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
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { createCampaign, errorMessage, listCampaigns } from '../api.js'
import { formatAmount } from '../format.js'

const router = useRouter()
const campaigns = ref([])
const loading = ref(true)
const newName = ref('')
const creating = ref(false)

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
.dr-new {
	max-width: 600px;
	margin-bottom: calc(var(--default-grid-baseline) * 6);
}

.dr-new > button {
	flex: 0 0 auto;
}
</style>
