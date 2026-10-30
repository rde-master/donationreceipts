/**
 * German formatting helpers shared by the views.
 */

/**
 * @param {number} cents amount in cents
 * @return {string} "1.234,56 €"
 */
export function formatAmount(cents) {
	return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(cents / 100)
}

/**
 * @param {string} iso "2026-03-01"
 * @return {string} "01.03.2026"
 */
export function formatDate(iso) {
	if (!iso) {
		return ''
	}
	const [y, m, d] = iso.split('-')
	return `${d}.${m}.${y}`
}

/**
 * @param {number} timestamp unix seconds
 * @return {string} "01.03.2026, 14:05"
 */
export function formatDateTime(timestamp) {
	return new Intl.DateTimeFormat('de-DE', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(timestamp * 1000))
}

/**
 * Parses a German amount ("1.234,56", "1234,5", "50") into cents.
 * A dot followed by one or two digits at the end ("12.50") counts as
 * decimal separator; otherwise dots are thousands separators.
 *
 * @param {string} text user input
 * @return {number|null} cents or null if not a valid amount
 */
export function parseAmount(text) {
	let value = String(text ?? '').replace(/[\s€]/g, '')
	if (value.includes(',')) {
		value = value.replace(/\./g, '').replace(',', '.')
	} else if (!/^\d+\.\d{1,2}$/.test(value)) {
		value = value.replace(/\./g, '')
	}
	const match = /^(\d+)(?:\.(\d{1,2}))?$/.exec(value)
	if (!match) {
		return null
	}
	return parseInt(match[1], 10) * 100 + parseInt((match[2] ?? '0').padEnd(2, '0'), 10)
}

/**
 * @param {number} cents amount in cents
 * @return {string} "1234,56" for editing
 */
export function amountForInput(cents) {
	return (cents / 100).toFixed(2).replace('.', ',')
}

export const ORIGINS = {
	business: 'Betriebsvermögen',
	private: 'Privatvermögen',
	unknown: 'keine Angaben zur Herkunft',
}

/**
 * @return {string} today as "2026-03-01" (local time)
 */
export function todayIso() {
	const now = new Date()
	return [now.getFullYear(), String(now.getMonth() + 1).padStart(2, '0'), String(now.getDate()).padStart(2, '0')].join('-')
}
