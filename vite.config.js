import { createAppConfig } from '@nextcloud/vite-config'

// Output: js/donationreceipts-<entry>.mjs (committed, so the server needs no Node).
export default createAppConfig({
	main: 'src/main.js',
	admin: 'src/admin.js',
}, {
	// Each entry injects the CSS of its own chunks (admin page has no main.mjs).
	inlineCSS: { relativeCSSInjection: true },
	extractLicenseInformation: false,
	config: {
		build: {
			// The committed bundle needs no maps; they also double build memory.
			sourcemap: false,
		},
	},
})
