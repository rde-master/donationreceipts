<template>
	<div class="dr-page">
		<div class="dr-header">
			<h2>Spender</h2>
			<NcButton variant="primary" :to="{ name: 'donor-new' }">
				<template #icon>
					<NcIconSvgWrapper :path="mdiPlus" />
				</template>
				Neuer Spender
			</NcButton>
		</div>
		<NcTextField v-model="search"
			label="Suchen nach Name, Firma, E-Mail oder Ort"
			trailing-button-icon="close"
			:show-trailing-button="search !== ''"
			class="dr-search"
			@trailing-button-click="search = ''" />

		<NcLoadingIcon v-if="loading" :size="32" />
		<NcEmptyContent v-else-if="!donors.length"
			:name="search ? 'Keine Treffer' : 'Noch keine Spender'"
			:description="search ? 'Kein Spender passt zur Suche.' : 'Lege den ersten Spender an.'">
			<template #icon>
				<NcIconSvgWrapper :path="mdiAccountGroupOutline" />
			</template>
		</NcEmptyContent>
		<table v-else class="dr-table">
			<thead>
				<tr>
					<th>Name</th>
					<th>Anschrift</th>
					<th>E-Mail</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="donor in donors"
					:key="donor.id"
					class="dr-clickable"
					@click="$router.push({ name: 'donor', params: { id: donor.id } })">
					<td>
						<RouterLink :to="{ name: 'donor', params: { id: donor.id } }">
							{{ sortName(donor) }}
						</RouterLink>
					</td>
					<td>{{ [donor.street, [donor.zip, donor.city].filter(Boolean).join(' ')].filter(Boolean).join(', ') }}</td>
					<td>{{ donor.email }}</td>
				</tr>
			</tbody>
		</table>
	</div>
</template>

<script setup>
import { mdiAccountGroupOutline, mdiPlus } from '@mdi/js'
import { showError } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { onMounted, ref, watch } from 'vue'
import { errorMessage, listDonors } from '../api.js'

const donors = ref([])
const loading = ref(true)
const search = ref('')

function sortName(donor) {
	return donor.kind === 'company' ? donor.company : `${donor.lastName}, ${donor.firstName}`
}

async function load() {
	try {
		donors.value = await listDonors(search.value)
	} catch (e) {
		showError(errorMessage(e))
	} finally {
		loading.value = false
	}
}

let timer
watch(search, () => {
	clearTimeout(timer)
	timer = setTimeout(load, 250)
})

onMounted(load)
</script>
