/**
 * The sponsor response form (public page and manual entry in the app):
 * form state from a response and the payload for the server.
 */
import { amountForInput, parseAmount } from './format.js'

/**
 * @param {object} response response fields as the server sends them
 * @return {object} form state for ResponseFields
 */
export function responseForm(response) {
	return {
		kind: response.kind || 'company',
		firstName: response.firstName ?? '',
		lastName: response.lastName ?? '',
		company: response.company ?? '',
		street: response.street ?? '',
		zip: response.zip ?? '',
		city: response.city ?? '',
		country: response.country ?? '',
		type: response.type || 'money',
		amountText: response.amountCents ? amountForInput(response.amountCents) : '',
		goodsDescription: response.goodsDescription ?? '',
		goodsOrigin: response.goodsOrigin ?? '',
		tickets: response.tickets ?? '',
		publish: response.publish === null || response.publish === undefined ? '' : (response.publish ? 'yes' : 'no'),
		remarks: response.remarks ?? '',
	}
}

/**
 * @param {object} form form state
 * @return {boolean} everything required is chosen (texts are checked by the browser)
 */
export function responseComplete(form) {
	return parseAmount(form.amountText) !== null && form.tickets !== '' && form.publish !== ''
		&& (form.type !== 'goods' || form.goodsOrigin !== '')
}

/**
 * @param {object} form form state
 * @return {string[]} labels of the required fields still empty or invalid
 */
export function responseMissing(form) {
	const blank = (value) => String(value ?? '').trim() === ''
	const missing = []
	if (form.kind === 'company') {
		if (blank(form.company)) {
			missing.push('Firmenname')
		}
	} else {
		if (blank(form.firstName)) {
			missing.push('Vorname')
		}
		if (blank(form.lastName)) {
			missing.push('Nachname')
		}
	}
	if (blank(form.street)) {
		missing.push('Straße')
	}
	if (blank(form.zip)) {
		missing.push('PLZ')
	}
	if (blank(form.city)) {
		missing.push('Ort')
	}
	if (parseAmount(form.amountText) === null) {
		missing.push('Betrag')
	}
	if (form.type === 'goods') {
		if (blank(form.goodsDescription)) {
			missing.push('Beschreibung der Sachspende')
		}
		if (form.goodsOrigin === '') {
			missing.push('Herkunft der Sachspende')
		}
	}
	if (form.tickets === '') {
		missing.push('Freikarten')
	}
	if (form.publish === '') {
		missing.push('Veröffentlichung')
	}
	return missing
}

/**
 * @param {object} form form state
 * @return {object} payload as InquiryService expects it
 */
export function responsePayload(form) {
	const { amountText, publish, ...fields } = form
	return { ...fields, amountCents: parseAmount(amountText), publish: publish === 'yes' }
}
