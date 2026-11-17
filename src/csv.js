/**
 * CSV for the import of possible sponsors: reading (Excel-style, any of
 * ; , tab as separator, UTF-8 or Windows-1252) and the sample file.
 */

/** Columns of the import; the first header name is the one in the sample. */
export const SPONSOR_COLUMNS = {
	name: ['Name', 'Firma', 'Firmenname', 'Sponsor', 'Möglicher Sponsor'],
	contact: ['Ansprechpartner', 'Ansprechperson', 'Kontakt', 'Kontaktperson'],
	salutation: ['Briefanrede', 'Anrede'],
	email: ['E-Mail', 'Email', 'Mail', 'E-Mail-Adresse', 'Emailadresse'],
	notes: ['Notiz', 'Notizen', 'Bemerkung', 'Hinweis'],
	noContact: ['Nicht mehr anschreiben', 'Nicht anschreiben', 'Sperre'],
}

const TRUE_VALUES = ['ja', 'x', '1', 'true', 'wahr', 'yes', 'j']

const normalize = (header) => header.replace(/^\uFEFF/, '').trim().toLowerCase().replace(/\s+/g, ' ')

/**
 * Text of an uploaded file: UTF-8, otherwise Windows-1252 (Excel "CSV").
 *
 * @param {File} file the CSV file
 * @return {Promise<string>}
 */
export async function readCsvFile(file) {
	const buffer = await file.arrayBuffer()
	try {
		return new TextDecoder('utf-8', { fatal: true }).decode(buffer)
	} catch {
		return new TextDecoder('windows-1252').decode(buffer)
	}
}

/**
 * @param {string} text CSV text
 * @return {string[][]} rows of cells (empty rows dropped); each row has
 *                      .line, its first line in the file (1-based)
 */
export function parseCsv(text) {
	text = text.replace(/^\uFEFF/, '')
	const firstLine = text.split(/\r?\n/, 1)[0] ?? ''
	const delimiter = [';', ',', '\t']
		.map((d) => [d, firstLine.split(d).length])
		.sort((a, b) => b[1] - a[1])[0][0]

	const rows = []
	let row = []
	let cell = ''
	let quoted = false
	let line = 1
	let rowLine = 1
	const endRow = () => {
		row.push(cell)
		row.line = rowLine
		rows.push(row)
		row = []
		cell = ''
	}
	for (let i = 0; i < text.length; i++) {
		const c = text[i]
		if (quoted) {
			if (c === '"' && text[i + 1] === '"') {
				cell += '"'
				i++
			} else if (c === '"') {
				quoted = false
			} else {
				if (c === '\n') {
					line++
				}
				cell += c
			}
		} else if (c === '"' && cell === '') {
			quoted = true
		} else if (c === delimiter) {
			row.push(cell)
			cell = ''
		} else if (c === '\n' || c === '\r') {
			if (c === '\r' && text[i + 1] === '\n') {
				i++
			}
			endRow()
			line++
			rowLine = line
		} else {
			cell += c
		}
	}
	if (cell !== '' || row.length) {
		endRow()
	}
	return rows.filter((r) => r.some((value) => value.trim() !== ''))
}

/**
 * Sponsor rows from CSV text, by the header names.
 *
 * @param {string} text CSV text
 * @return {{rows: object[], missing: string[], ignored: string[]}} rows with line number; missing required columns; unknown columns
 */
export function sponsorRowsFromCsv(text) {
	const [header = [], ...data] = parseCsv(text)
	const index = {}
	const ignored = []
	header.forEach((name, i) => {
		const field = Object.keys(SPONSOR_COLUMNS)
			.find((key) => SPONSOR_COLUMNS[key].some((alias) => normalize(alias) === normalize(name)))
		if (field && !(field in index)) {
			index[field] = i
		} else if (name.trim() !== '') {
			ignored.push(name.trim())
		}
	})
	const missing = ['name', 'email'].filter((f) => !(f in index)).map((f) => SPONSOR_COLUMNS[f][0])
	const rows = data.map((cells) => {
		const value = (field) => (field in index ? (cells[index[field]] ?? '').trim() : '')
		return {
			// line in the file, as Excel shows it
			line: cells.line,
			name: value('name'),
			contact: value('contact'),
			salutation: value('salutation'),
			email: value('email'),
			notes: value('notes'),
			noContact: TRUE_VALUES.includes(value('noContact').toLowerCase()),
		}
	})
	return { rows, missing, ignored }
}

/**
 * Downloads the sample file (semicolons, UTF-8 with BOM: opens in Excel).
 */
export function downloadSponsorSample() {
	const lines = [
		['Name', 'Ansprechpartner', 'Briefanrede', 'E-Mail', 'Notiz', 'Nicht mehr anschreiben'],
		['Bäckerei Korn GmbH', 'Anna Korn', 'Sehr geehrte Frau Korn', 'info@baeckerei-korn.example', 'Stammkunde seit 2019', 'nein'],
		['Autohaus Muster KG', 'Peter Muster', '', 'kontakt@autohaus-muster.example', '', ''],
		['Max Beispiel', '', 'Lieber Max', 'max.beispiel@example.org', 'Vereinsmitglied', ''],
	]
	const quote = (value) => /[";\r\n]/.test(value) ? `"${value.replace(/"/g, '""')}"` : value
	const csv = '\uFEFF' + lines.map((l) => l.map(quote).join(';')).join('\r\n') + '\r\n'
	const link = document.createElement('a')
	link.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }))
	link.download = 'Muster_moegliche_Sponsoren.csv'
	link.click()
	URL.revokeObjectURL(link.href)
}
