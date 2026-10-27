<template>
	<div class="dr-page">
		<NcLoadingIcon v-if="!donor" :size="32" />
		<template v-else>
			<div class="dr-header">
				<h2>{{ donor.displayName }}</h2>
				<div class="dr-actions">
					<NcButton :to="{ name: 'donor-edit', params: { id: donor.id } }">
						<template #icon>
							<NcIconSvgWrapper :path="mdiPencil" />
						</template>
						Bearbeiten
					</NcButton>
					<NcButton variant="error" @click="confirmDelete = true">
						<template #icon>
							<NcIconSvgWrapper :path="mdiDelete" />
						</template>
						Löschen
					</NcButton>
				</div>
			</div>

			<dl class="dr-facts">
				<dt>Art</dt>
				<dd>{{ donor.kind === 'company' ? 'Firma' : 'Person' }}</dd>
				<dt>Anschrift</dt>
				<dd>
					<div v-for="line in addressLines" :key="line">
						{{ line }}
					</div>
				</dd>
				<dt>E-Mail</dt>
				<dd>{{ donor.email || '–' }}</dd>
				<template v-if="donor.notes">
					<dt>Notiz</dt>
					<dd class="dr-pre">
						{{ donor.notes }}
					</dd>
				</template>
			</dl>
		</template>

		<NcDialog v-if="confirmDelete"
			name="Spender löschen?"
			:message="`${donor.displayName} wird endgültig gelöscht.`"
			:buttons="deleteButtons"
			@closing="confirmDelete = false" />
	</div>
</template>

<script setup>
import { mdiDelete, mdiPencil } from '@mdi/js'
import { showError, showSuccess } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { deleteDonor, errorMessage, getDonor } from '../api.js'

const props = defineProps({
	id: { type: [String, Number], required: true },
})

const router = useRouter()
const donor = ref(null)
const confirmDelete = ref(false)

const addressLines = computed(() => [
	donor.value.street,
	[donor.value.zip, donor.value.city].filter(Boolean).join(' '),
	donor.value.country,
].filter(Boolean))

const deleteButtons = [
	{ label: 'Abbrechen' },
	{
		label: 'Löschen',
		variant: 'error',
		callback: remove,
	},
]

async function remove() {
	try {
		await deleteDonor(props.id)
		showSuccess('Spender gelöscht')
		router.push({ name: 'donors' })
	} catch (e) {
		showError(errorMessage(e))
	}
}

onMounted(async () => {
	try {
		donor.value = await getDonor(props.id)
	} catch (e) {
		showError(errorMessage(e))
	}
})
</script>
