# Changelog

Alle Änderungen der App, neueste zuerst. Versionen nach [SemVer](https://semver.org/lang/de/):
Fehlerbehebung erhöht die dritte Stelle, neue Funktionen die zweite, mit der Abnahme kommt 1.0.0.
Jede Version hat einen Git-Tag `v<version>`.

## 0.5.2 – 2026-10-28

- Läuft unter Nextcloud 32 bis 35 (bisher nur 32). Geprüft gegen die OCP-API von NC 35 und
  das dort mitgelieferte symfony/mailer 7.4; der STARTTLS-Zwang greift dort unverändert.

## 0.5.1 – 2026-10-27

- ESLint 9 mit Vue-Regeln (`npm run lint`), läuft unter Node 20.
- README: Funktionen, Einrichtung in der richtigen Reihenfolge, Abweichungen vom Plan.

## 0.5.0 – 2026-10-27

Phase 5: E-Mail.

- Eigener SMTP-Zugang (Server, Port, STARTTLS oder SSL/TLS, Benutzer, Passwort, Absender);
  das Passwort wird mit dem Nextcloud-Schlüssel verschlüsselt gespeichert und nie an den
  Browser zurückgegeben.
- Zugangsdaten und Mails gehen nur über eine verschlüsselte Verbindung: bietet der Server
  kein STARTTLS an, wird abgebrochen, bevor etwas gesendet wird.
- Testmail-Knopf (speichert vorher die Einstellungen).
- E-Mail-Vorlage für Betreff und Text mit Platzhaltern {name}, {nummer}, {betrag}, {datum},
  {art}, {verein}.
- Vorschau-Dialog mit Empfänger, Betreff, Text und der unterschriebenen Fassung als Anhang;
  alles im Dialog änderbar, versendet wird erst mit „Senden“. Fehler bleiben im Dialog stehen.
- Nach dem Erstellen öffnet sich die Vorschau automatisch (wenn der Spender eine E-Mail-Adresse
  hat); nach Änderung einer versendeten Bescheinigung wird der erneute Versand angeboten.
- Senden auch später aus den Listen (Briefumschlag-Knopf bei nicht versendeten oder
  geänderten Bescheinigungen).
- Versandzeitpunkt und Empfänger werden an der Bescheinigung gespeichert.
- Darstellung: lange Beschriftungen in Detailansichten überlappen nicht mehr.

## 0.4.0 – 2026-10-27

Phase 4: Unterschrift, Stempel, Ablage.

- Upload von Unterschrift und Stempel (PNG, max. 2 MB) mit Vorschau; gespeichert im
  geschützten App-Datenbereich (nicht über die Dateien-App erreichbar).
- Auswahl der Hauptordner „nicht unterschrieben“ und „unterschrieben“ per Ordnerauswahl;
  gespeichert wird die Datei-ID, geprüft werden Zugriff und Schreibrecht.
- Beim Erstellen entstehen immer beide Fassungen als
  `<Hauptordner>/<Spender>/<Nummer>_<Ausstellungsdatum>.pdf`.
- Spenderordner werden bei Bedarf angelegt; ein gleichnamiger Ordner eines anderen Spenders
  führt zu „Max Müller (2)“. Umbenannte Spender behalten ihre Ordner (Zuordnung über ID).
- Änderungen erzeugen beide PDFs neu und ersetzen die Dateien (alte Fassung in der
  Versionshistorie); ein geändertes Ausstellungsdatum benennt die Dateien um.
- Nummer, Datenbankeintrag und Ablage in einer Transaktion: schlägt das Ablegen fehl, wird
  nichts gespeichert, bereits geschriebene Dateien werden entfernt, die Nummer bleibt frei.
- Detailansicht: beide PDFs öffnen, „In Dateien zeigen“; Hinweis, wenn eine Datei fehlt.
- Einstellungen zeigen die nächste Nummer; Ordner-IDs lassen sich nicht über das allgemeine
  Speichern der Einstellungen setzen.

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
