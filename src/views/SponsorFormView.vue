<template>
	<div class="dr-page">
		<div class="dr-header">
			<h2>{{ id ? 'Sponsor bearbeiten' : 'Neuer Sponsor' }}</h2>
			<NcButton v-if="id && !loading" variant="error" @click="confirmDelete = true">
				<template #icon>
					<NcIconSvgWrapper :path="mdiDelete" />
				</template>
				Löschen
			</NcButton>
		</div>
		<NcLoadingIcon v-if="loading" :size="32" />
		<form v-else class="dr-form" @submit.prevent="save">
			<NcTextField v-model="sponsor.name"
				label="Name (Firma oder Person)"
				required />
			<NcTextField v-model="sponsor.contact" label="Ansprechpartner" />
			<NcTextField v-model="sponsor.salutation"
				label="Briefanrede (optional)"
				placeholder="z. B. Sehr geehrte Frau Müller"
				:helper-text="`Leer: „${fallbackSalutation}“`" />
			<NcTextField v-model="sponsor.email"
				type="email"
				label="E-Mail"
				required />
			<NcSelect v-model="donor"
				:options="donors"
				label="displayName"
				input-label="Verknüpfter Spender (für die Bescheinigungen)"
				placeholder="noch keiner – wird bei der ersten Spende angelegt"
				:label-outside="false" />
			<NcTextArea v-model="sponsor.notes"
				label="Notiz"
				resize="vertical"
				rows="3" />
			<div class="dr-actions">
				<NcButton type="submit" variant="primary" :disabled="saving">
					Speichern
				</NcButton>
				<NcButton :to="{ name: 'sponsors' }">
					Abbrechen
				</NcButton>
			</div>
		</form>

		<NcDialog v-if="confirmDelete"
			name="Sponsor löschen?"
			:message="`${sponsor.name} wird endgültig gelöscht.`"
			:buttons="deleteButtons"
			@closing="confirmDelete = false" />
	</div>
</template>

<script setup>
import { mdiDelete } from '@mdi/js'
import { showError, showSuccess } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { createSponsor, deleteSponsor, errorMessage, getSponsor, listDonors, updateSponsor } from '../api.js'

const props = defineProps({
	id: { type: [String, Number], default: null },
})

const router = useRouter()
const loading = ref(true)
const saving = ref(false)
const confirmDelete = ref(false)
const donors = ref([])
const donor = ref(null)
const sponsor = ref({
	name: '',
	contact: '',
	salutation: '',
	email: '',
	donorId: null,
	notes: '',
})

// same rule as SponsorTemplate::salutation()
const fallbackSalutation = computed(() => sponsor.value.contact.trim()
	? `Guten Tag ${sponsor.value.contact.trim()}`
	: 'Sehr geehrte Damen und Herren')

const deleteButtons = [
	{ label: 'Abbrechen' },
	{ label: 'Löschen', variant: 'error', callback: remove },
]

onMounted(async () => {
	try {
		donors.value = await listDonors()
		if (props.id) {
			sponsor.value = await getSponsor(props.id)
			donor.value = donors.value.find((d) => d.id === sponsor.value.donorId) ?? null
		}
	} catch (e) {
		showError(errorMessage(e))
	} finally {
		loading.value = false
	}
})

async function save() {
	saving.value = true
	try {
		const data = { ...sponsor.value, donorId: donor.value?.id ?? null }
		if (props.id) {
			await updateSponsor(props.id, data)
		} else {
			await createSponsor(data)
		}
		showSuccess('Sponsor gespeichert')
		router.push({ name: 'sponsors' })
	} catch (e) {
		showError(errorMessage(e))
	} finally {
		saving.value = false
	}
}

async function remove() {
	try {
		await deleteSponsor(props.id)
		showSuccess('Sponsor gelöscht')
		router.push({ name: 'sponsors' })
	} catch (e) {
		showError(errorMessage(e))
	}
}
</script>

<style scoped>
.dr-form {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 2);
}
</style>
