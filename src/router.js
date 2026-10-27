import { createRouter, createWebHashHistory } from 'vue-router'
import SettingsView from './views/SettingsView.vue'

export default createRouter({
	history: createWebHashHistory(),
	routes: [
		{ path: '/', redirect: '/einstellungen' },
		{ path: '/einstellungen', name: 'settings', component: SettingsView },
	],
})
