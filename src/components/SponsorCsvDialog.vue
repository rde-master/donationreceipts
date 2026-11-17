<template>
	<NcDialog name="Mögliche Sponsoren aus CSV importieren"
		size="large"
		@closing="$emit('close')">
		<div class="dr-csv">
			<!-- 1: choose file -->
			<template v-if="!preview">
				<p>
					Eine Zeile je Sponsor, die erste Zeile enthält die Spaltennamen. Pflicht sind
					<strong>Name</strong> und <strong>E-Mail</strong>; optional Ansprechpartner, Briefanrede,
					Notiz und „Nicht mehr anschreiben“ (ja/x). Getrennt durch Semikolon oder Komma – eine aus
					Excel als „CSV (Trennzeichen-getrennt)“ gespeicherte Datei passt.
				</p>
				<div class="dr-actions">
					<NcButton @click="downloadSponsorSample">
						<template #icon>
							<NcIconSvgWrapper :path="mdiDownload" />
						</template>
						Muster-CSV herunterladen
					</NcButton>
					<NcButton variant="primary" @click="fileInput.click()">
						<template #icon>
							<NcIconSvgWrapper :path="mdiFileUploadOutline" />
						</template>
						CSV-Datei wählen
					</NcButton>
					<input ref="fileInput"
						type="file"
						accept=".csv,text/csv,text/plain"
						class="hidden-visually"
						@change="pick">
				</div>
				<NcLoadingIcon v-if="checking" :size="32" />
			</template>

			<!-- 2: preview / 3: result -->
			<template v-else>
				<NcNoteCard :type="done ? (counts.created ? 'success' : 'info') : 'info'">
					<template v-if="done">
						{{ counts.created }} {{ counts.created === 1 ? 'Sponsor' : 'Sponsoren' }} angelegt.
					</template>
					<template v-else>
						{{ fileName }}: {{ preview.length }} Zeilen –
						<strong>{{ counts.new }} neu</strong>
					</template>
					<template v-if="counts.duplicate">
						· {{ counts.duplicate }} schon vorhanden (gleiche E-Mail), werden übersprungen
					</template>
					<template v-if="counts.error">
						· {{ counts.error }} mit Fehler, werden übersprungen
					</template>
				</NcNoteCard>
				<p v-if="ignored.length" class="dr-muted">
					Nicht verwendete Spalten: {{ ignored.join(', ') }}
				</p>
				<NcCheckboxRadioSwitch v-if="!done && (counts.duplicate || counts.error)" v-model="onlyProblems">
					Nur übersprungene Zeilen anzeigen
				</NcCheckboxRadioSwitch>
				<div class="dr-csv__table">
					<table class="dr-table">
						<thead>
							<tr>
								<th class="dr-num">
									Zeile
								</th>
								<th>Name</th>
								<th>E-Mail</th>
								<th>Ergebnis</th>
							</tr>
						</thead>
						<tbody>
							<tr v-for="r in shown" :key="r.line">
								<td class="dr-num">
									{{ r.line }}
								</td>
								<td>{{ r.name }}</td>
								<td>{{ r.email }}</td>
								<td :class="r.status === 'error' ? 'dr-error-text' : r.status === 'duplicate' ? 'dr-muted' : ''">
									{{ STATUS[r.status] }}<template v-if="r.error">
										: {{ r.error }}
									</template>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</template>

			<NcNoteCard v-if="error" type="error">
				{{ error }}
			</NcNoteCard>
		</div>

		<template #actions>
			<NcButton v-if="preview && !done" @click="reset">
				Andere Datei
			</NcButton>
			<NcButton @click="$emit('close')">
				{{ done ? 'Schließen' : 'Abbrechen' }}
			</NcButton>
			<NcButton v-if="preview && !done"
				variant="primary"
				:disabled="importing || !counts.new"
				@click="runImport">
				<template #icon>
					<NcLoadingIcon v-if="importing" :size="20" />
				</template>
				{{ counts.new }} {{ counts.new === 1 ? 'Sponsor' : 'Sponsoren' }} importieren
			</NcButton>
		</template>
	</NcDialog>
</template>

<script setup>
import { mdiDownload, mdiFileUploadOutline } from '@mdi/js'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import { computed, ref } from 'vue'
import { errorMessage, importSponsorRows } from '../api.js'
import { downloadSponsorSample, readCsvFile, sponsorRowsFromCsv } from '../csv.js'

const emit = defineEmits(['close', 'imported'])

const STATUS = {
	new: 'wird angelegt',
	created: 'angelegt',
	duplicate: 'schon vorhanden',
	error: 'Fehler',
}

const fileInput = ref(null)
const fileName = ref('')
const rows = ref([])
const ignored = ref([])
const preview = ref(null)
const checking = ref(false)
const importing = ref(false)
const done = ref(false)
const onlyProblems = ref(false)
const error = ref('')

const counts = computed(() => (preview.value ?? []).reduce(
	(c, r) => ({ ...c, [r.status]: c[r.status] + 1 }),
	{ new: 0, created: 0, duplicate: 0, error: 0 },
))
const shown = computed(() => onlyProblems.value
	? preview.value.filter((r) => r.status === 'duplicate' || r.status === 'error')
	: preview.value)

async function pick(event) {
	const file = event.target.files?.[0]
	event.target.value = ''
	if (!file) {
		return
	}
	error.value = ''
	checking.value = true
	try {
		const parsed = sponsorRowsFromCsv(await readCsvFile(file))
		if (parsed.missing.length) {
			error.value = `In der ersten Zeile fehlen die Spalten: ${parsed.missing.join(', ')}. Am besten die Muster-CSV als Vorlage nehmen.`
			return
		}
		if (!parsed.rows.length) {
			error.value = 'Die Datei enthält keine Zeilen unter der Kopfzeile.'
			return
		}
		fileName.value = file.name
		rows.value = parsed.rows
		ignored.value = parsed.ignored
		preview.value = await importSponsorRows(parsed.rows, true)
	} catch (e) {
		error.value = errorMessage(e)
	} finally {
		checking.value = false
	}
}

async function runImport() {
	importing.value = true
	error.value = ''
	try {
		preview.value = await importSponsorRows(rows.value, false)
		done.value = true
		onlyProblems.value = false
		emit('imported')
	} catch (e) {
		error.value = errorMessage(e)
	} finally {
		importing.value = false
	}
}

function reset() {
	preview.value = null
	rows.value = []
	ignored.value = []
	error.value = ''
	onlyProblems.value = false
}
</script>

<style scoped>
.dr-csv {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 2);
}

.dr-csv__table {
	max-height: 50vh;
	overflow-y: auto;
}

.dr-error-text {
	color: var(--color-error-text);
}
</style>
