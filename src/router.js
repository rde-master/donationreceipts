import { createRouter, createWebHashHistory } from 'vue-router'
import DonorFormView from './views/DonorFormView.vue'
import DonorsView from './views/DonorsView.vue'
import DonorView from './views/DonorView.vue'
import SettingsView from './views/SettingsView.vue'

export default createRouter({
	history: createWebHashHistory(),
	routes: [
		{ path: '/', redirect: '/spender' },
		{ path: '/spender', name: 'donors', component: DonorsView },
		{ path: '/spender/neu', name: 'donor-new', component: DonorFormView },
		{ path: '/spender/:id(\\d+)', name: 'donor', component: DonorView, props: true },
		{ path: '/spender/:id(\\d+)/bearbeiten', name: 'donor-edit', component: DonorFormView, props: true },
		{ path: '/einstellungen', name: 'settings', component: SettingsView },
	],
})
