<template>
	<NcSettingsSection name="Spendenbescheinigungen"
		description="Nur Mitglieder dieser Gruppe sehen die App und dürfen Spender, Bescheinigungen und die Vereinseinstellungen bearbeiten. Ohne gewählte Gruppe hat niemand Zugriff.">
		<NcSettingsSelectGroup v-model="groups"
			label="Berechtigte Gruppe"
			placeholder="Gruppe wählen"
			@update:model-value="save" />
	</NcSettingsSection>
</template>

<script setup>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcSettingsSelectGroup from '@nextcloud/vue/components/NcSettingsSelectGroup'
import { ref } from 'vue'
import { errorMessage, setAllowedGroup } from '../api.js'

const initial = loadState('donationreceipts', 'allowed-group', '')
const groups = ref(initial ? [initial] : [])

async function save(value) {
	// Single group only: keep the most recently picked one.
	const groupId = value.length ? value[value.length - 1] : ''
	groups.value = groupId ? [groupId] : []
	try {
		await setAllowedGroup(groupId)
		showSuccess(groupId ? `Gruppe „${groupId}“ gespeichert` : 'Keine Gruppe mehr berechtigt')
	} catch (e) {
		showError(errorMessage(e))
	}
}
</script>
