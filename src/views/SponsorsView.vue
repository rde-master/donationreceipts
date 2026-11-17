<template>
	<div class="dr-page">
		<div class="dr-header">
			<h2>Mögliche Sponsoren</h2>
			<div class="dr-actions">
				<NcButton @click="csvOpen = true">
					<template #icon>
						<NcIconSvgWrapper :path="mdiFileDelimitedOutline" />
					</template>
					CSV importieren
				</NcButton>
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
		<div v-if="!loading && sponsors.length" class="dr-tiles">
			<StatTile label="Mögliche Sponsoren"
				:value="String(sponsors.length)"
				class="dr-tile-link"
				@click="setFilter('all')">
				{{ counts.never }} noch nie angeschrieben · {{ counts.no_contact }} nicht mehr anschreiben
			</StatTile>
			<StatTile label="Haben schon gespendet"
				:value="String(counts.donated)"
				class="dr-tile-link"
				@click="setFilter('donated')">
				zusammen {{ formatAmount(counts.donatedCents) }}
			</StatTile>
			<StatTile label="Zusage, Spende offen"
				:value="String(counts.pledged + counts.incomplete)"
				class="dr-tile-link"
				@click="setFilter('pledged')">
				<span :class="{ 'dr-warning': counts.incomplete }">{{ counts.incomplete }} davon Angaben fehlen</span>
			</StatTile>
			<StatTile label="Ohne Antwort"
				:value="String(counts.waiting + counts.replied + counts.later)"
				class="dr-tile-link"
				@click="setFilter('waiting')">
				<span :class="{ 'dr-warning': counts.replied }">{{ counts.replied }} Antwort-Mails ohne Status</span>
				· {{ counts.later }} melden sich noch
			</StatTile>
			<StatTile label="Zuletzt abgesagt"
				:value="String(counts.declined + counts.lastNoContact)"
				class="dr-tile-link"
				@click="setFilter('declined')" />
		</div>

		<div class="dr-row dr-toolbar">
			<NcTextField v-model="search"
				label="Suchen nach Name, Ansprechpartner oder E-Mail"
				trailing-button-icon="close"
				:show-trailing-button="search !== ''"
				class="dr-search"
				@trailing-button-click="search = ''" />
			<NcSelect v-model="filter"
				:options="FILTERS"
				:clearable="false"
				input-label="Letzte Rückmeldung"
				:label-outside="false"
				class="dr-filter" />
		</div>

		<NcLoadingIcon v-if="loading" :size="32" />
		<NcEmptyContent v-else-if="!visible.length"
			:name="sponsors.length ? 'Keine Treffer' : 'Noch keine möglichen Sponsoren'"
			:description="sponsors.length ? 'Kein Eintrag passt zu Suche und Filter.' : 'Lege mögliche Sponsoren an oder übernimm bestehende Spender.'">
			<template #icon>
				<NcIconSvgWrapper :path="mdiHandshakeOutline" />
			</template>
		</NcEmptyContent>
		<table v-else class="dr-table">
			<thead>
				<tr>
					<th>Name</th>
					<th>Letzte Rückmeldung</th>
					<th class="dr-num">
						Spenden
					</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="sponsor in visible"
					:key="sponsor.id"
					class="dr-clickable"
					@click="$router.push({ name: 'sponsor-edit', params: { id: sponsor.id } })">
					<td>
						<RouterLink :to="{ name: 'sponsor-edit', params: { id: sponsor.id } }">
							{{ sponsor.name }}
						</RouterLink>
						<div class="dr-muted">
							{{ [sponsor.contact, sponsor.email].filter(Boolean).join(' · ') }}
						</div>
						<div v-if="sponsor.noContact" class="dr-muted">
							möchte nicht mehr angeschrieben werden
						</div>
					</td>
					<td>
						<template v-if="last(sponsor)">
							<span :class="stateClass(last(sponsor).state)">{{ STATES[last(sponsor).state] }}</span>
							<template v-if="last(sponsor).amountCents">
								· {{ formatAmount(last(sponsor).amountCents) }}
							</template>
							<div class="dr-muted">
								<RouterLink :to="{ name: 'campaign', params: { id: last(sponsor).campaignId } }" @click.stop>
									{{ last(sponsor).campaignName }}
								</RouterLink>
								· {{ formatDay(last(sponsor).at) }}
							</div>
							<div v-if="last(sponsor).note" class="dr-muted dr-note">
								„{{ last(sponsor).note }}“
							</div>
						</template>
						<span v-else class="dr-muted">noch nie angeschrieben</span>
					</td>
					<td class="dr-num">
						<template v-if="history[sponsor.id]?.donations">
							{{ formatAmount(history[sponsor.id].donatedCents) }}
							<div class="dr-muted">
								{{ history[sponsor.id].donations }}× von {{ history[sponsor.id].asked }} Anfragen
							</div>
						</template>
						<span v-else-if="history[sponsor.id]?.asked" class="dr-muted">
							0 von {{ history[sponsor.id].asked }} Anfragen
						</span>
					</td>
				</tr>
			</tbody>
		</table>

		<SponsorCsvDialog v-if="csvOpen"
			@imported="load"
			@close="csvOpen = false" />

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
import { mdiAccountArrowRightOutline, mdiFileDelimitedOutline, mdiHandshakeOutline, mdiPlus } from '@mdi/js'
import { showError, showSuccess } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { computed, onMounted, ref } from 'vue'
import { errorMessage, getSponsorOverview, importSponsors, listDonors, listSponsors } from '../api.js'
import StatTile from '../components/charts/StatTile.vue'
import SponsorCsvDialog from '../components/SponsorCsvDialog.vue'
import { formatAmount } from '../format.js'
import { STATES } from '../outcome.js'

// filters on the latest inquiry (state) and the sponsor
const FILTERS = [
	{ id: 'all', label: 'Alle' },
	{ id: 'never', label: 'Noch nie angeschrieben' },
	{ id: 'waiting', label: 'Ohne Antwort (inkl. Antwort-Mail, meldet sich noch)' },
	{ id: 'replied', label: 'Antwort per Mail – Status offen' },
	{ id: 'pledged', label: 'Zusage – Spende offen (inkl. Angaben fehlen)' },
	{ id: 'incomplete', label: 'Zusage – Angaben fehlen noch' },
	{ id: 'received', label: 'Zuletzt gespendet' },
	{ id: 'declined', label: 'Zuletzt abgesagt' },
	{ id: 'no_contact', label: 'Nicht mehr anschreiben' },
	{ id: 'donated', label: 'Hat schon einmal gespendet' },
	{ id: 'neverDonated', label: 'Angeschrieben, noch nie gespendet' },
	{ id: 'failed', label: 'Versand fehlgeschlagen' },
]

const sponsors = ref([])
const history = ref({})
const filter = ref(FILTERS[0])
const loading = ref(true)
const search = ref('')
const importOpen = ref(false)
const csvOpen = ref(false)
const importable = ref(null)
const selected = ref([])
const importing = ref(false)

function formatDay(timestamp) {
	return new Intl.DateTimeFormat('de-DE', { dateStyle: 'medium' }).format(new Date(timestamp * 1000))
}

function last(sponsor) {
	return history.value[sponsor.id]?.last ?? null
}

function matches(sponsor, id) {
	const h = history.value[sponsor.id]
	const state = h?.last?.state
	switch (id) {
	case 'all':
		return true
	case 'never':
		return !h
	case 'waiting':
		return ['waiting', 'replied', 'later'].includes(state)
	case 'pledged':
		return ['pledged', 'incomplete'].includes(state)
	case 'declined':
		return ['declined', 'no_contact'].includes(state)
	case 'no_contact':
		return sponsor.noContact
	case 'donated':
		return (h?.donations ?? 0) > 0
	case 'neverDonated':
		return (h?.asked ?? 0) > 0 && !h.donations
	default:
		return state === id
	}
}

const visible = computed(() => {
	const term = search.value.trim().toLowerCase()
	return sponsors.value.filter((s) => matches(s, filter.value.id)
		&& (!term || [s.name, s.contact, s.email].some((v) => (v ?? '').toLowerCase().includes(term))))
})

const counts = computed(() => {
	const c = { never: 0, no_contact: 0, donated: 0, donatedCents: 0, pledged: 0, incomplete: 0, waiting: 0, replied: 0, later: 0, declined: 0, lastNoContact: 0 }
	for (const s of sponsors.value) {
		const h = history.value[s.id]
		if (s.noContact) {
			c.no_contact++
		}
		if (!h) {
			c.never++
			continue
		}
		if (h.donations) {
			c.donated++
			c.donatedCents += h.donatedCents
		}
		const state = h.last?.state
		if (state === 'no_contact') {
			c.lastNoContact++
		} else if (state in c) {
			c[state]++
		}
	}
	return c
})

function setFilter(id) {
	filter.value = FILTERS.find((f) => f.id === id)
}

function stateClass(state) {
	if (['replied', 'incomplete', 'failed'].includes(state)) {
		return 'dr-warning'
	}
	return ['waiting', 'declined', 'no_contact', 'later'].includes(state) ? 'dr-muted' : ''
}

async function load() {
	try {
		[sponsors.value, history.value] = await Promise.all([listSponsors(), getSponsorOverview()])
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

onMounted(load)
</script>

<style scoped>
.dr-tiles {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
	gap: calc(var(--default-grid-baseline) * 3);
	margin: calc(var(--default-grid-baseline) * 4) 0;
}

.dr-tile-link {
	cursor: pointer;
}

.dr-tile-link:hover {
	border-color: var(--color-primary-element);
}

.dr-toolbar {
	align-items: flex-end;
	margin-bottom: calc(var(--default-grid-baseline) * 2);
}

.dr-toolbar .dr-search {
	flex: 1 1 280px;
	margin-bottom: 0;
}

.dr-filter {
	flex: 0 1 320px;
	min-width: 240px;
}

.dr-note {
	font-style: italic;
}

.dr-import {
	max-height: 50vh;
	overflow-y: auto;
}
</style>
