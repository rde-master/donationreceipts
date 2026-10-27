<template>
	<NcContent app-name="donationreceipts">
		<NcAppNavigation aria-label="Spendenbescheinigungen">
			<template #list>
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
import { mdiAccountGroupOutline, mdiCogOutline, mdiFileDocumentOutline } from '@mdi/js'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { useRoute } from 'vue-router'

const route = useRoute()
</script>
