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

export async function sendTestMail(to, account = '') {
	return (await axios.post(base('/mail/test'), { to, account })).data
}

export async function setAllowedGroup(groupId) {
	return (await axios.put(base('/admin/group'), { groupId })).data
}

export async function listSponsors(search = '') {
	return (await axios.get(base('/sponsors'), { params: { search } })).data
}

export async function getSponsor(id) {
	return (await axios.get(base(`/sponsors/${id}`))).data
}

export async function createSponsor(sponsor) {
	return (await axios.post(base('/sponsors'), { sponsor })).data
}

export async function updateSponsor(id, sponsor) {
	return (await axios.put(base(`/sponsors/${id}`), { sponsor })).data
}

export async function deleteSponsor(id) {
	return (await axios.delete(base(`/sponsors/${id}`))).data
}

export async function importSponsors(donorIds) {
	return (await axios.post(base('/sponsors/import'), { donorIds })).data
}

export async function listLetterTemplates() {
	return (await axios.get(base('/letter-templates'))).data
}

export async function createLetterTemplate(template) {
	return (await axios.post(base('/letter-templates'), template)).data
}

export async function updateLetterTemplate(id, template) {
	return (await axios.put(base(`/letter-templates/${id}`), template)).data
}

export async function deleteLetterTemplate(id) {
	return (await axios.delete(base(`/letter-templates/${id}`))).data
}

export async function listCampaigns() {
	return (await axios.get(base('/campaigns'))).data
}

export async function createCampaign(name) {
	return (await axios.post(base('/campaigns'), { name })).data
}

export async function getCampaign(id) {
	return (await axios.get(base(`/campaigns/${id}`))).data
}

export async function updateCampaign(id, campaign) {
	return (await axios.put(base(`/campaigns/${id}`), campaign)).data
}

export async function deleteCampaign(id) {
	return (await axios.delete(base(`/campaigns/${id}`))).data
}

export async function previewLetter(campaignId, letter) {
	return (await axios.post(base(`/campaigns/${campaignId}/preview`), letter)).data
}

export async function sendLetters(campaignId, letter) {
	return (await axios.post(base(`/campaigns/${campaignId}/send`), letter)).data
}

export async function listOpenResponses() {
	return (await axios.get(base('/inquiries/open'))).data
}

export async function receiveDonation(inquiryId, receipt) {
	return (await axios.post(base(`/inquiries/${inquiryId}/receive`), { receipt })).data
}

export function logoUrl(inquiryId) {
	return base(`/inquiries/${inquiryId}/logo`)
}

export async function renderReceiptMail(id, mail) {
	return (await axios.post(base(`/receipts/${id}/mail/preview`), mail)).data
}

/**
 * @param {File} file image for a mail template
 * @return {Promise<string>} URL to use as src in the template
 */
export async function uploadMailImage(file) {
	const data = new FormData()
	data.append('image', file)
	const { id } = (await axios.post(base('/mail-images'), data)).data
	return base(`/mail-images/${id}`)
}
