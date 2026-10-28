import { createRouter, createWebHashHistory } from 'vue-router'
import DashboardView from './views/DashboardView.vue'
import DonorFormView from './views/DonorFormView.vue'
import DonorsView from './views/DonorsView.vue'
import DonorView from './views/DonorView.vue'
import ReceiptFormView from './views/ReceiptFormView.vue'
import ReceiptsView from './views/ReceiptsView.vue'
import ReceiptView from './views/ReceiptView.vue'
import SettingsView from './views/SettingsView.vue'

export default createRouter({
	history: createWebHashHistory(),
	routes: [
		{ path: '/', redirect: '/uebersicht' },
		{ path: '/uebersicht', name: 'dashboard', component: DashboardView },
		{ path: '/spender', name: 'donors', component: DonorsView },
		{ path: '/spender/neu', name: 'donor-new', component: DonorFormView },
		{ path: '/spender/:id(\\d+)', name: 'donor', component: DonorView, props: true },
		{ path: '/spender/:id(\\d+)/bearbeiten', name: 'donor-edit', component: DonorFormView, props: true },
		{ path: '/spender/:donorId(\\d+)/bescheinigung/neu', name: 'receipt-new', component: ReceiptFormView, props: true },
		{ path: '/bescheinigungen', name: 'receipts', component: ReceiptsView },
		{ path: '/bescheinigungen/:id(\\d+)', name: 'receipt', component: ReceiptView, props: true },
		{ path: '/bescheinigungen/:id(\\d+)/bearbeiten', name: 'receipt-edit', component: ReceiptFormView, props: true },
		{ path: '/einstellungen', name: 'settings', component: SettingsView },
	],
})
