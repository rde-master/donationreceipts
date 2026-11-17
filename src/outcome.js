import { mdiAlert, mdiCheckCircle, mdiCloseCircle, mdiEmailOutline, mdiHandshakeOutline } from '@mdi/js'

/**
 * Status of a sponsor's answer (as InquiryService::OUTCOME_LABELS).
 */
export const OUTCOMES = {
	pledged: {
		label: 'Zusage',
		description: 'Die Angaben für die Bescheinigung liegen vor.',
	},
	incomplete: {
		label: 'Zusage – Angaben fehlen noch',
		description: 'Möchte spenden, Anschrift oder Betrag fehlen noch. Ein erneutes Anschreiben schickt den Rückmelde-Link noch einmal.',
	},
	later: {
		label: 'Meldet sich noch',
		description: 'Noch unentschieden oder Rückfrage offen.',
	},
	declined: {
		label: 'Diesmal nicht',
		description: 'Wird bei weiteren Anschreiben dieser Aktion ausgelassen.',
	},
	no_contact: {
		label: 'Bitte nicht mehr anschreiben',
		description: 'Wird auch beim möglichen Sponsor hinterlegt: Er wird bei allen künftigen Anschreiben ausgelassen.',
	},
}

/** No further letter for this campaign. */
export const REFUSED = ['declined', 'no_contact']

/** State of an inquiry (InquiryService::stateOf) as the lists show it. */
export const STATES = {
	received: 'Spende eingegangen',
	pledged: 'Zusage – Spende offen',
	incomplete: OUTCOMES.incomplete.label,
	later: OUTCOMES.later.label,
	declined: OUTCOMES.declined.label,
	no_contact: OUTCOMES.no_contact.label,
	replied: 'Antwort per Mail – Status offen',
	waiting: 'Angeschrieben, ohne Antwort',
	failed: 'Versand fehlgeschlagen',
}

/**
 * The few groups the answer bars show (more would not be told apart);
 * colors validated for color-vision deficiency, always with icon + legend.
 */
export const ANSWER_GROUPS = [
	{ key: 'received', label: 'Spende eingegangen', color: '--dr-good', icon: mdiCheckCircle },
	{ key: 'pledged', label: 'Zusage, Spende offen', color: '--dr-series-1', icon: mdiHandshakeOutline },
	{ key: 'incomplete', label: 'Zusage, Angaben fehlen', color: '--dr-warning', icon: mdiAlert },
	{ key: 'open', label: 'Ohne Antwort / unentschieden', color: '--dr-dim', icon: mdiEmailOutline },
	{ key: 'declined', label: 'Absage', color: '--dr-critical', icon: mdiCloseCircle },
]

/**
 * Answer-group counts from a campaign summary (InquiryService::summary).
 *
 * @param {object} s summary
 * @return {{received: number, pledged: number, incomplete: number, open: number, declined: number}}
 */
export function answerCountsFromSummary(s) {
	const pledged = Math.max(0, s.responded - s.declined - s.later - s.incomplete - s.received)
	const answered = s.received + pledged + s.incomplete + s.declined
	return {
		received: s.received,
		pledged,
		incomplete: s.incomplete,
		open: Math.max(0, s.sent - answered),
		declined: s.declined,
	}
}

/**
 * Answer group of a state (InquiryService::stateOf / campaign row state).
 *
 * @param {string} state state
 * @return {string|null} group key; null = not written to
 */
export function answerGroupOf(state) {
	switch (state) {
	case 'received':
	case 'incomplete':
	case 'declined':
		return state
	case 'pledged':
	case 'open':
		return 'pledged'
	case 'no_contact':
		return 'declined'
	case 'waiting':
	case 'replied':
	case 'later':
		return 'open'
	default:
		return null
	}
}
