# Changelog

Alle Änderungen der App, neueste zuerst. Versionen nach [SemVer](https://semver.org/lang/de/):
Fehlerbehebung erhöht die dritte Stelle, neue Funktionen die zweite, mit der Abnahme kommt 1.0.0.
Jede Version hat einen Git-Tag `v<version>`.

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
