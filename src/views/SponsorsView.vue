<template>
	<div class="dr-page">
		<div class="dr-header">
			<h2>Mögliche Sponsoren</h2>
			<div class="dr-actions">
				<NcButton @click="openImport">
					<template #icon>
						<NcIconSvgWrapper :path="mdiAccountArrowRightOutline" />
					</template>
					Aus Spendern übernehmen
				</NcButton>
				<NcButton variant="primary" :to="{ name: 'sponsor-new' }">
					<template #icon>
						<NcIconSvgWrapper :path="mdiPlus" />
					</template>
					Neuer Eintrag
				</NcButton>
			</div>
		</div>
		<NcTextField v-model="search"
			label="Suchen nach Name, Ansprechpartner oder E-Mail"
			trailing-button-icon="close"
			:show-trailing-button="search !== ''"
			class="dr-search"
			@trailing-button-click="search = ''" />

		<NcLoadingIcon v-if="loading" :size="32" />
		<NcEmptyContent v-else-if="!sponsors.length"
			:name="search ? 'Keine Treffer' : 'Noch keine möglichen Sponsoren'"
			:description="search ? 'Kein Eintrag passt zur Suche.' : 'Lege mögliche Sponsoren an oder übernimm bestehende Spender.'">
			<template #icon>
				<NcIconSvgWrapper :path="mdiHandshakeOutline" />
			</template>
		</NcEmptyContent>
		<table v-else class="dr-table">
			<thead>
				<tr>
					<th>Name</th>
					<th>Ansprechpartner</th>
					<th>E-Mail</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="sponsor in sponsors"
					:key="sponsor.id"
					class="dr-clickable"
					@click="$router.push({ name: 'sponsor-edit', params: { id: sponsor.id } })">
					<td>
						<RouterLink :to="{ name: 'sponsor-edit', params: { id: sponsor.id } }">
							{{ sponsor.name }}
						</RouterLink>
					</td>
					<td>{{ sponsor.contact }}</td>
					<td>{{ sponsor.email }}</td>
				</tr>
			</tbody>
		</table>

		<NcDialog v-if="importOpen"
			name="Spender als mögliche Sponsoren übernehmen"
			size="normal"
			@closing="importOpen = false">
			<NcLoadingIcon v-if="!importable" :size="32" />
			<p v-else-if="!importable.length" class="dr-muted">
				Alle Spender mit E-Mail-Adresse stehen schon in der Liste.
			</p>
			<template v-else>
				<p class="dr-muted">
					Übernommen werden Spender mit E-Mail-Adresse. Sie bleiben mit dem Spender verknüpft, so
					landen spätere Bescheinigungen beim selben Spender.
				</p>
				<NcCheckboxRadioSwitch :model-value="selected.length === importable.length"
					:indeterminate="selected.length > 0 && selected.length < importable.length"
					@update:model-value="(all) => selected = all ? importable.map((d) => d.id) : []">
					Alle auswählen
				</NcCheckboxRadioSwitch>
				<ul class="dr-import">
					<li v-for="donor in importable" :key="donor.id">
						<NcCheckboxRadioSwitch v-model="selected" :value="donor.id">
							{{ donor.displayName }} <span class="dr-muted">{{ donor.email }}</span>
						</NcCheckboxRadioSwitch>
					</li>
				</ul>
			</template>
			<template #actions>
				<NcButton @click="importOpen = false">
					Abbrechen
				</NcButton>
				<NcButton variant="primary" :disabled="!selected.length || importing" @click="runImport">
					{{ selected.length }} übernehmen
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>

<script setup>
import { mdiAccountArrowRightOutline, mdiHandshakeOutline, mdiPlus } from '@mdi/js'
import { showError, showSuccess } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { onMounted, ref, watch } from 'vue'
import { errorMessage, importSponsors, listDonors, listSponsors } from '../api.js'

const sponsors = ref([])
const loading = ref(true)
const search = ref('')
const importOpen = ref(false)
const importable = ref(null)
const selected = ref([])
const importing = ref(false)

async function load() {
	try {
		sponsors.value = await listSponsors(search.value)
	} catch (e) {
		showError(errorMessage(e))
	} finally {
		loading.value = false
	}
}

async function openImport() {
	importOpen.value = true
	importable.value = null
	selected.value = []
	try {
		const [donors, all] = await Promise.all([listDonors(), listSponsors()])
		const linked = new Set(all.map((s) => s.donorId).filter(Boolean))
		importable.value = donors.filter((d) => d.email && !linked.has(d.id))
	} catch (e) {
		showError(errorMessage(e))
		importOpen.value = false
	}
}

async function runImport() {
	importing.value = true
	try {
		const created = await importSponsors(selected.value)
		showSuccess(`${created.length} Spender übernommen`)
		importOpen.value = false
		await load()
	} catch (e) {
		showError(errorMessage(e))
	} finally {
		importing.value = false
	}
}

let timer
watch(search, () => {
	clearTimeout(timer)
	timer = setTimeout(load, 250)
})

onMounted(load)
</script>

<style scoped>
.dr-import {
	max-height: 50vh;
	overflow-y: auto;
}
</style>
