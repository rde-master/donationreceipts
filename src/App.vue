<template>
	<NcContent app-name="donationreceipts">
		<NcAppNavigation aria-label="Spendenbescheinigungen">
			<template #list>
				<NcAppNavigationItem name="Übersicht" :to="{ name: 'dashboard' }">
					<template #icon>
						<NcIconSvgWrapper :path="mdiViewDashboardOutline" />
					</template>
				</NcAppNavigationItem>
				<NcAppNavigationItem name="Spender" :to="{ name: 'donors' }" :active="route.path.startsWith('/spender')">
					<template #icon>
						<NcIconSvgWrapper :path="mdiAccountGroupOutline" />
					</template>
				</NcAppNavigationItem>
				<NcAppNavigationItem name="Bescheinigungen" :to="{ name: 'receipts' }" :active="route.path.startsWith('/bescheinigungen')">
					<template #icon>
						<NcIconSvgWrapper :path="mdiFileDocumentOutline" />
					</template>
				</NcAppNavigationItem>
				<NcAppNavigationCaption name="Sponsoren anschreiben" />
				<NcAppNavigationItem name="Aktionen" :to="{ name: 'campaigns' }" :active="route.path.startsWith('/aktionen')">
					<template #icon>
						<NcIconSvgWrapper :path="mdiBullhornOutline" />
					</template>
				</NcAppNavigationItem>
				<NcAppNavigationItem name="Rückmeldungen" :to="{ name: 'responses' }">
					<template #icon>
						<NcIconSvgWrapper :path="mdiClipboardTextOutline" />
					</template>
				</NcAppNavigationItem>
				<NcAppNavigationItem name="Antworten" :to="{ name: 'replies' }">
					<template #icon>
						<NcIconSvgWrapper :path="mdiEmailArrowLeftOutline" />
					</template>
					<template v-if="openReplies" #counter>
						<NcCounterBubble :count="openReplies" type="highlighted" />
					</template>
				</NcAppNavigationItem>
				<NcAppNavigationItem name="Mögliche Sponsoren" :to="{ name: 'sponsors' }" :active="route.path.startsWith('/sponsoren')">
					<template #icon>
						<NcIconSvgWrapper :path="mdiHandshakeOutline" />
					</template>
				</NcAppNavigationItem>
				<NcAppNavigationItem name="Vorlagen" :to="{ name: 'templates' }">
					<template #icon>
						<NcIconSvgWrapper :path="mdiEmailEditOutline" />
					</template>
				</NcAppNavigationItem>
			</template>
			<template #footer>
				<NcAppNavigationItem name="Einstellungen" :to="{ name: 'settings' }">
					<template #icon>
						<NcIconSvgWrapper :path="mdiCogOutline" />
					</template>
				</NcAppNavigationItem>
			</template>
		</NcAppNavigation>
		<NcAppContent>
			<!-- keyed: views load their data on mount, so a new route needs a new instance -->
			<RouterView :key="route.fullPath" />
		</NcAppContent>
	</NcContent>
</template>

<script setup>
import { mdiAccountGroupOutline, mdiBullhornOutline, mdiClipboardTextOutline, mdiCogOutline, mdiEmailArrowLeftOutline, mdiEmailEditOutline, mdiFileDocumentOutline, mdiHandshakeOutline, mdiViewDashboardOutline } from '@mdi/js'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationCaption from '@nextcloud/vue/components/NcAppNavigationCaption'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcCounterBubble from '@nextcloud/vue/components/NcCounterBubble'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { countOpenReplies } from './api.js'

const route = useRoute()

// open reply mails (tasks), refreshed on navigation, every 2 minutes and when RepliesView changes one
const openReplies = ref(0)

async function refreshOpenReplies() {
	try {
		openReplies.value = await countOpenReplies()
	} catch {
		// the counter is a convenience; errors show up on the pages
	}
}

let timer
onMounted(() => {
	refreshOpenReplies()
	timer = setInterval(refreshOpenReplies, 120000)
	window.addEventListener('donationreceipts:replies-changed', refreshOpenReplies)
})
onBeforeUnmount(() => {
	clearInterval(timer)
	window.removeEventListener('donationreceipts:replies-changed', refreshOpenReplies)
})
watch(() => route.path, refreshOpenReplies)
</script>
