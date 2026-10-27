<template>
	<NcContent app-name="donationreceipts">
		<NcAppNavigation aria-label="Spendenbescheinigungen">
			<template #list>
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
import { mdiCogOutline } from '@mdi/js'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
</script>
