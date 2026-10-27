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

export async function setAllowedGroup(groupId) {
	return (await axios.put(base('/admin/group'), { groupId })).data
}
