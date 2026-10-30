<template>
	<div class="dr-page dr-page--wide">
		<NcLoadingIcon v-if="!data" :size="32" />
		<template v-else>
			<div class="dr-header">
				<h2>
					{{ campaign.name }}
					<span v-if="campaign.closed" class="dr-muted">(geschlossen)</span>
				</h2>
				<div class="dr-actions">
					<NcButton @click="renameOpen = true">
						<template #icon>
							<NcIconSvgWrapper :path="mdiPencil" />
						</template>
						Umbenennen
					</NcButton>
					<NcButton @click="toggleClosed">
						<template #icon>
							<NcIconSvgWrapper :path="campaign.closed ? mdiLockOpenVariantOutline : mdiLockOutline" />
						</template>
						{{ campaign.closed ? 'Wieder öffnen' : 'Schließen' }}
					</NcButton>
					<NcButton v-if="!rows.some((r) => r.inquiry)" variant="error" @click="remove">
						<template #icon>
							<NcIconSvgWrapper :path="mdiDelete" />
						</template>
						Löschen
					</NcButton>
				</div>
			</div>

			<NcNoteCard v-if="campaign.closed" type="info">
				Die Aktion ist geschlossen: Das Rückmeldeformular nimmt keine Eingaben mehr an, und es werden
				keine Anschreiben verschickt. Eingegangene Spenden lassen sich weiter bestätigen.
			</NcNoteCard>
			<NcNoteCard v-else-if="data.mailMissing.length" type="warning">
				Für den Versand der Anschreiben fehlt in den Einstellungen (SMTP-Zugang für Sponsoren):
				{{ data.mailMissing.join(', ') }}
			</NcNoteCard>

			<div class="dr-tiles">
				<StatTile label="Angeschrieben" :value="`${summary.sent} von ${rows.length}`" />
				<StatTile label="Rückmeldungen" :value="String(summary.responded)">
					davon {{ summary.received }} Spenden eingegangen
				</StatTile>
				<StatTile label="Zugesagt" :value="formatAmount(summary.pledgedCents)">
					Eingang offen: {{ formatAmount(summary.openCents) }}
				</StatTile>
				<StatTile label="Freikarten" :value="String(summary.tickets)">
					{{ summary.ticketsLater }} melden sich noch · {{ summary.publish }} Logo-Freigaben
				</StatTile>
			</div>

			<div class="dr-row dr-toolbar">
				<NcSelect v-model="filter"
					:options="FILTERS"
					:clearable="false"
					input-label="Anzeigen"
					:label-outside="false" />
				<NcButton variant="primary"
					:disabled="!selected.length || campaign.closed"
					@click="letterOpen = true">
					<template #icon>
						<NcIconSvgWrapper :path="mdiSend" />
					</template>
					Anschreiben an {{ selected.length }} {{ selected.length === 1 ? 'Sponsor' : 'Sponsoren' }}
				</NcButton>
			</div>

			<p v-if="!rows.length" class="dr-muted">
				Es gibt noch keine Sponsoren. Lege sie unter
				<RouterLink :to="{ name: 'sponsors' }">
					Sponsoren
				</RouterLink> an.
			</p>
			<table v-else class="dr-table">
				<thead>
					<tr>
						<th class="dr-check">
							<NcCheckboxRadioSwitch :model-value="allSelected"
								:indeterminate="selected.length > 0 && !allSelected"
								aria-label="Alle angezeigten auswählen"
								@update:model-value="selectAll" />
						</th>
						<th>Sponsor</th>
						<th>Status</th>
						<th class="dr-num">
							Spende
						</th>
						<th>Freikarten</th>
						<th>Logo</th>
						<th />
					</tr>
				</thead>
				<tbody>
					<tr v-for="row in visibleRows" :key="row.sponsor.id">
						<td class="dr-check">
							<NcCheckboxRadioSwitch v-model="selected"
								:value="row.sponsor.id"
								:aria-label="`${row.sponsor.name} auswählen`" />
						</td>
						<td>
							<div>{{ row.sponsor.name }}</div>
							<div class="dr-muted">
								{{ [row.sponsor.contact, row.sponsor.email].filter(Boolean).join(' · ') }}
							</div>
						</td>
						<td>
							<span :class="statusClass(row)">{{ statusText(row) }}</span>
						</td>
						<td class="dr-num">
							<template v-if="row.inquiry?.response">
								{{ formatAmount(row.inquiry.response.amountCents) }}
								<div v-if="row.inquiry.response.type === 'goods'" class="dr-muted">
									Sachspende
								</div>
							</template>
						</td>
						<td>{{ row.inquiry?.response ? data.ticketLabels[row.inquiry.response.tickets] : '' }}</td>
						<td>
							<template v-if="row.inquiry?.response">
								{{ row.inquiry.response.publish ? 'Ja' : 'Nein' }}
								<a v-if="row.inquiry.hasLogo" :href="logoUrl(row.inquiry.id)">(Datei)</a>
							</template>
						</td>
						<td class="dr-num">
							<div class="dr-row-actions">
								<NcButton v-if="row.inquiry?.response"
									variant="tertiary"
									@click="responseRow = row">
									{{ row.inquiry.receiptId ? 'Ansehen' : 'Rückmeldung' }}
								</NcButton>
								<NcButton v-if="row.inquiry"
									variant="tertiary"
									:aria-label="`Rückmelde-Link von ${row.sponsor.name} kopieren`"
									title="Rückmelde-Link kopieren"
									@click="copyLink(row.inquiry.link)">
									<template #icon>
										<NcIconSvgWrapper :path="mdiLinkVariant" />
									</template>
								</NcButton>
							</div>
						</td>
					</tr>
				</tbody>
			</table>
		</template>

		<LetterDialog v-if="letterOpen"
			:campaign="campaign"
			:sponsors="rows.filter((r) => selected.includes(r.sponsor.id)).map((r) => r.sponsor)"
			@sent="afterSend"
			@close="letterOpen = false" />

		<ResponseDialog v-if="responseRow"
			:inquiry="responseRow.inquiry"
			:sponsor="responseRow.sponsor"
			:ticket-labels="data.ticketLabels"
			@received="load"
			@close="responseRow = null" />

		<NcDialog v-if="renameOpen"
			name="Aktion umbenennen"
			@closing="renameOpen = false">
			<form id="dr-rename" @submit.prevent="rename">
				<NcTextField v-model="newName" label="Name der Aktion" />
			</form>
			<template #actions>
				<NcButton @click="renameOpen = false">
					Abbrechen
				</NcButton>
				<NcButton type="submit" form="dr-rename" variant="primary">
					Speichern
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>

<script setup>
import { mdiDelete, mdiLinkVariant, mdiLockOpenVariantOutline, mdiLockOutline, mdiPencil, mdiSend } from '@mdi/js'
import { showError, showSuccess } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { deleteCampaign, errorMessage, getCampaign, logoUrl, updateCampaign } from '../api.js'
import StatTile from '../components/charts/StatTile.vue'
import LetterDialog from '../components/LetterDialog.vue'
import ResponseDialog from '../components/ResponseDialog.vue'
import { formatAmount, formatDateTime } from '../format.js'

const props = defineProps({
	id: { type: [String, Number], required: true },
})

const FILTERS = [
	{ id: 'all', label: 'Alle Sponsoren' },
	{ id: 'new', label: 'Noch nicht angeschrieben' },
	{ id: 'waiting', label: 'Ohne Rückmeldung' },
	{ id: 'open', label: 'Zusage, Spende offen' },
	{ id: 'received', label: 'Spende eingegangen' },
]

const router = useRouter()
const data = ref(null)
const filter = ref(FILTERS[0])
const selected = ref([])
const letterOpen = ref(false)
const responseRow = ref(null)
const renameOpen = ref(false)
const newName = ref('')

const campaign = computed(() => data.value.campaign)
const summary = computed(() => data.value.summary)
const rows = computed(() => data.value.rows)

function state(row) {
	const inquiry = row.inquiry
	if (!inquiry?.sentAt && !inquiry?.response) {
		return 'new'
	}
	if (!inquiry.response) {
		return 'waiting'
	}
	return inquiry.receiptId ? 'received' : 'open'
}

const visibleRows = computed(() => filter.value.id === 'all'
	? rows.value
	: rows.value.filter((row) => state(row) === filter.value.id))

const allSelected = computed(() => visibleRows.value.length > 0
	&& visibleRows.value.every((row) => selected.value.includes(row.sponsor.id)))

// a changed filter only keeps what is still visible
watch(filter, () => {
	const visible = new Set(visibleRows.value.map((row) => row.sponsor.id))
	selected.value = selected.value.filter((id) => visible.has(id))
})

function selectAll(all) {
	selected.value = all ? visibleRows.value.map((row) => row.sponsor.id) : []
}

function statusText(row) {
	const inquiry = row.inquiry
	switch (state(row)) {
	case 'new':
		return inquiry ? 'Versand fehlgeschlagen' : 'nicht angeschrieben'
	case 'waiting':
		return `angeschrieben ${formatDateTime(inquiry.sentAt)}`
	case 'open':
		return 'Zusage – Spende offen'
	default:
		return 'Spende eingegangen'
	}
}

function statusClass(row) {
	const s = state(row)
	if (s === 'new' && row.inquiry) {
		return 'dr-warning'
	}
	return s === 'new' || s === 'waiting' ? 'dr-muted' : ''
}

async function copyLink(link) {
	try {
		await navigator.clipboard.writeText(link)
		showSuccess('Link kopiert')
	} catch {
		window.prompt('Rückmelde-Link', link)
	}
}

async function load() {
	try {
		data.value = await getCampaign(props.id)
		newName.value = data.value.campaign.name
	} catch (e) {
		showError(errorMessage(e))
	}
}

async function afterSend() {
	selected.value = []
	await load()
}

async function rename() {
	try {
		data.value.campaign = await updateCampaign(props.id, { name: newName.value, closed: campaign.value.closed })
		renameOpen.value = false
	} catch (e) {
		showError(errorMessage(e))
	}
}

async function toggleClosed() {
	try {
		data.value.campaign = await updateCampaign(props.id, { name: campaign.value.name, closed: !campaign.value.closed })
	} catch (e) {
		showError(errorMessage(e))
	}
}

async function remove() {
	try {
		await deleteCampaign(props.id)
		showSuccess('Aktion gelöscht')
		router.push({ name: 'campaigns' })
	} catch (e) {
		showError(errorMessage(e))
	}
}

onMounted(load)
</script>

<style scoped>
.dr-tiles {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
	gap: calc(var(--default-grid-baseline) * 3);
	margin: calc(var(--default-grid-baseline) * 4) 0;
}

.dr-toolbar {
	justify-content: space-between;
	margin-bottom: calc(var(--default-grid-baseline) * 2);
}

.dr-toolbar > * {
	flex: 0 0 auto;
	min-width: 240px;
}

.dr-check {
	width: 44px;
}

.dr-row-actions {
	display: flex;
	justify-content: flex-end;
}
</style>
