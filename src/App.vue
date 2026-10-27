<template>
	<NcContent app-name="donationreceipts">
		<NcAppNavigation aria-label="Spendenbescheinigungen">
			<template #list>
				<NcAppNavigationItem name="Spender" :to="{ name: 'donors' }" :active="route.path.startsWith('/spender')">
					<template #icon>
						<NcIconSvgWrapper :path="mdiAccountGroupOutline" />
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
			<RouterView />
		</NcAppContent>
	</NcContent>
</template>

<script setup>
import { mdiAccountGroupOutline, mdiCogOutline } from '@mdi/js'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { useRoute } from 'vue-router'

const route = useRoute()
</script>
