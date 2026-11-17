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
