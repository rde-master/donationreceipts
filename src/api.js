import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const base = (path) => generateUrl(`/apps/donationreceipts/api${path}`)

/**
 * Error text for a failed request: the backend's {error} message if present.
 *
 * @param {Error} error axios error
 * @return {string}
 */
export function errorMessage(error) {
	return error?.response?.data?.error ?? error?.message ?? 'Unbekannter Fehler'
}

export async function getStats(year = null) {
	return (await axios.get(base('/stats'), { params: year ? { year } : {} })).data
}

export async function getSettings() {
	return (await axios.get(base('/settings'))).data
}

export async function updateSettings(values) {
	return (await axios.put(base('/settings'), { values })).data
}

export async function listDonors(search = '') {
	return (await axios.get(base('/donors'), { params: { search } })).data
}

export async function getDonor(id) {
	return (await axios.get(base(`/donors/${id}`))).data
}

export async function createDonor(donor) {
	return (await axios.post(base('/donors'), { donor })).data
}

export async function updateDonor(id, donor) {
	return (await axios.put(base(`/donors/${id}`), { donor })).data
}

export async function deleteDonor(id) {
	return (await axios.delete(base(`/donors/${id}`))).data
}

export async function listReceipts(params = {}) {
	return (await axios.get(base('/receipts'), { params })).data
}

export async function getReceipt(id) {
	return (await axios.get(base(`/receipts/${id}`))).data
}

export async function createReceipt(donorId, receipt) {
	return (await axios.post(base(`/donors/${donorId}/receipts`), { receipt })).data
}

export async function updateReceipt(id, receipt) {
	return (await axios.put(base(`/receipts/${id}`), { receipt })).data
}

export function receiptFileUrl(id, variant) {
	return base(`/receipts/${id}/file/${variant}`)
}

export async function setMainFolder(variant, fileId) {
	return (await axios.put(base(`/settings/folders/${variant}`), { fileId })).data
}

export function imageUrl(kind) {
	return base(`/images/${kind}`) + `?t=${Date.now()}`
}

export async function uploadImage(kind, file) {
	const data = new FormData()
	data.append('image', file)
	return (await axios.post(base(`/images/${kind}`), data)).data
}

export async function deleteImage(kind) {
	return (await axios.delete(base(`/images/${kind}`))).data
}

export async function getMailPreview(id) {
	return (await axios.get(base(`/receipts/${id}/mail`))).data
}

export async function sendReceiptMail(id, mail) {
	return (await axios.post(base(`/receipts/${id}/mail`), mail)).data
}

export async function sendTestMail(to) {
	return (await axios.post(base('/mail/test'), { to })).data
}

export async function setAllowedGroup(groupId) {
	return (await axios.put(base('/admin/group'), { groupId })).data
}
