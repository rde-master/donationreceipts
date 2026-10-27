<template>
	<div class="dr-page">
		<h2>{{ id ? 'Spender bearbeiten' : 'Neuer Spender' }}</h2>
		<NcLoadingIcon v-if="loading" :size="32" />
		<form v-else class="dr-form" @submit.prevent="save">
			<div class="dr-row dr-radios">
				<NcCheckboxRadioSwitch v-model="donor.kind"
					type="radio"
					value="person"
					name="kind">
					Person
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch v-model="donor.kind"
					type="radio"
					value="company"
					name="kind">
					Firma
				</NcCheckboxRadioSwitch>
			</div>
			<div v-if="donor.kind === 'person'" class="dr-row">
				<NcTextField v-model="donor.firstName" label="Vorname" required />
				<NcTextField v-model="donor.lastName" label="Nachname" required />
			</div>
			<NcTextField v-else
				v-model="donor.company"
				label="Firmenname"
				required />
			<NcTextField v-model="donor.street" label="Straße und Hausnummer" />
			<div class="dr-row">
				<NcTextField v-model="donor.zip" label="PLZ" class="dr-zip" />
				<NcTextField v-model="donor.city" label="Ort" />
			</div>
			<NcTextField v-model="donor.country" label="Land (leer = Deutschland)" />
			<NcTextField v-model="donor.email" type="email" label="E-Mail (für den Versand)" />
			<NcTextArea v-model="donor.notes"
				label="Notiz"
				resize="vertical"
				rows="3" />
			<div class="dr-actions">
				<NcButton type="submit" variant="primary" :disabled="saving">
					Speichern
				</NcButton>
				<NcButton :to="id ? { name: 'donor', params: { id } } : { name: 'donors' }">
					Abbrechen
				</NcButton>
			</div>
		</form>
	</div>
</template>

<script setup>
import { showError, showSuccess } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { createDonor, errorMessage, getDonor, updateDonor } from '../api.js'

const props = defineProps({
	id: { type: [String, Number], default: null },
})

const router = useRouter()
const loading = ref(!!props.id)
const saving = ref(false)
const donor = ref({
	kind: 'person',
	firstName: '',
	lastName: '',
	company: '',
	street: '',
	zip: '',
	city: '',
	country: '',
	email: '',
	notes: '',
})

onMounted(async () => {
	if (!props.id) {
		return
	}
	try {
		donor.value = await getDonor(props.id)
	} catch (e) {
		showError(errorMessage(e))
	} finally {
		loading.value = false
	}
})

async function save() {
	saving.value = true
	try {
		const saved = props.id
			? await updateDonor(props.id, donor.value)
			: await createDonor(donor.value)
		showSuccess('Spender gespeichert')
		router.push({ name: 'donor', params: { id: saved.id } })
	} catch (e) {
		showError(errorMessage(e))
	} finally {
		saving.value = false
	}
}
</script>

<style scoped>
.dr-form {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 2);
}

.dr-radios > * {
	flex: 0 0 auto;
}
</style>
