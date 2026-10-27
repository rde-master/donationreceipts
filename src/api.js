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

export async function setAllowedGroup(groupId) {
	return (await axios.put(base('/admin/group'), { groupId })).data
}
