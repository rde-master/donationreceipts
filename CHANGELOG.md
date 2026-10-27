# Changelog

Alle Änderungen der App, neueste zuerst. Versionen nach [SemVer](https://semver.org/lang/de/):
Fehlerbehebung erhöht die dritte Stelle, neue Funktionen die zweite, mit der Abnahme kommt 1.0.0.
Jede Version hat einen Git-Tag `v<version>`.

## 0.3.0 – 2026-10-27

Phase 3: Bescheinigungen und PDF.

- Tabellen `dr_receipts` und `dr_counters` (Migration).
- Bescheinigungen für Geld- und Sachzuwendungen aus der Spenderansicht heraus; Liste aller
  Bescheinigungen mit Jahresfilter und Summe; Detail- und Bearbeitungsansicht.
- Nummernkreis `JJJJ-NNNN`, je Erstellungsjahr (deutsche Zeit) ab 1, vergeben in einer
  Datenbank-Transaktion mit Zeilensperre; die Nummer ändert sich danach nicht mehr.
- Betrag in Buchstaben (deutsche Schreibweise, Euro und Cent).
- Ausfüllen der amtlichen Vorlagen 034122/034123 an vermessenen Positionen; Schrift wird bei
  langen Texten verkleinert. Nummer klein oben rechts, „Ort, Datum“ und Unterzeichner über
  der Unterschriftslinie.
- Sachzuwendung: Herkunft (Betriebs-/Privatvermögen/keine Angaben) und Kreuz „Unterlagen zur
  Wertermittlung liegen vor“ wie im Muster.
- Bescheinigungen werden erst erstellt, wenn die Einstellungen vollständig sind.
- Spender mit Bescheinigungen können nicht gelöscht werden.
- Warnung beim Ändern einer bereits versendeten Bescheinigung.

## 0.2.0 – 2026-10-27

Phase 2: Spender.

- Tabelle `dr_donors` (Migration), Person oder Firma, Anschrift, E-Mail, Notiz.
- Spenderliste mit Suche (alle Suchwörter müssen passen, z. B. „max mül“), sortiert nach
  Nachname bzw. Firmenname.
- Anlegen, Bearbeiten, Detailansicht und Löschen.
- Fehlerbehebung: Nicht-Mitglieder bekommen bei API-Aufrufen JSON statt einer HTML-Fehlerseite.

## 0.1.0 – 2026-10-27

Phase 1: Grundgerüst.

- App-Gerüst `donationreceipts` für Nextcloud 32 (PHP-Backend, Vue-3-Frontend).
- Eintrag in der oberen Navigationsleiste, nur für Mitglieder der berechtigten Gruppe.
- Admin-Einstellungen: Auswahl der berechtigten Gruppe. Ohne Gruppe hat niemand Zugriff.
- Serverseitige Gruppenprüfung für jeden Aufruf der App (Standard: verweigern).
- Einstellungsbereich in der App: Vereinsdaten, Steuerbegünstigung (Freistellungsbescheid
  oder § 60a AO, jeweils mit Finanzamt und Steuernummer), begünstigte Zwecke,
  Schalter „kein Mitgliedsbeitrag“, Unterzeichnung. Warnung bei zu altem Bescheid.
- Amtliche Vorlagen 034122 und 034123 unter `resources/`, FPDF/FPDI unter `3rdparty/`.
