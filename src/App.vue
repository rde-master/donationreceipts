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
				<NcAppNavigationItem name="Sponsoren" :to="{ name: 'sponsors' }" :active="route.path.startsWith('/sponsoren')">
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
import { mdiAccountGroupOutline, mdiBullhornOutline, mdiCogOutline, mdiEmailEditOutline, mdiFileDocumentOutline, mdiHandshakeOutline, mdiViewDashboardOutline } from '@mdi/js'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationCaption from '@nextcloud/vue/components/NcAppNavigationCaption'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { useRoute } from 'vue-router'

const route = useRoute()
</script>
