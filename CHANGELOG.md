# Changelog

Alle Änderungen der App, neueste zuerst. Versionen nach [SemVer](https://semver.org/lang/de/):
Fehlerbehebung erhöht die dritte Stelle, neue Funktionen die zweite, mit der Abnahme kommt 1.0.0.
Jede Version hat einen Git-Tag `v<version>`.

## 0.14.0 – 2026-11-17

- **Mögliche Sponsoren**: Die Liste zeigt je Sponsor die **letzte Rückmeldung** (Status, Aktion mit
  Link, Datum, zugesagter Betrag, Nachricht bei einer Absage) und die **Spenden** (Summe, wie oft
  gespendet von wie vielen Anfragen).
- **Filter** nach letzter Rückmeldung: noch nie angeschrieben, ohne Antwort, Antwort per Mail,
  Zusage offen, Angaben fehlen, zuletzt gespendet, zuletzt abgesagt, nicht mehr anschreiben,
  hat schon gespendet, angeschrieben aber noch nie gespendet, Versand fehlgeschlagen. Die Suche
  wirkt zusammen mit dem Filter.
- **Kleine Übersicht** über der Liste: Sponsoren gesamt, haben schon gespendet (mit Summe), Zusagen
  offen, ohne Antwort, zuletzt abgesagt – ein Klick auf eine Kachel setzt den passenden Filter.

## 0.13.0 – 2026-11-17

- **Absage im Rückmeldeformular**: Sponsoren wählen oben „Ja, wir unterstützen …“, „Diesmal leider
  nicht“ oder „Bitte keine Anfragen mehr“ – eine Absage geht ohne Pflichtfelder, mit optionaler
  Nachricht. Der Status wird gesetzt („bitte keine Anfragen mehr“ auch am Sponsor), die Gruppe
  benachrichtigt und der Sponsor bekommt eine Bestätigung. Die Antwort lässt sich über denselben
  Link ändern; eine spätere Zusage hebt „nicht mehr anschreiben“ wieder auf.
- **Erinnerungen**: zwei Vorlagen („ohne Antwort“ und „Zusage – Angaben fehlen noch“), fällig nach
  einstellbaren Tagen (Standard 14) seit Anschreiben bzw. letzter Erinnerung, höchstens N-mal
  (Standard 2). In der Aktion: Hinweis „Erinnerung fällig“, Filter, „fällige auswählen“ und
  „Erinnerung an …“ mit Vorschau; je Sponsor sichtbar, wann zuletzt erinnert wurde. Wahlweise
  täglich automatischer Versand (Hintergrundjob, standardmäßig aus). Nie bei Absagen, „nicht mehr
  anschreiben“, unbearbeiteten Antwort-Mails oder geschlossenen Aktionen.
- **Vorlagen**: Unter „Vorlagen“ stehen jetzt alle automatischen Mails an Sponsoren – Bestätigung
  Zusage (bisher in den Einstellungen), Bestätigung Absage, beide Erinnerungen – und die
  Erinnerungs-Einstellungen.
- **Übersicht**: neue Karte „Sponsoring“ mit jeder offenen Aktion (angeschrieben, Zusagen und
  Betrag, eingegangen, Absagen, ohne Antwort, Antwort-Mails ohne Status, Zusagen ohne Angaben,
  fällige Erinnerungen, Antwortquote) und den offenen Antwort-Mails. Die Aktionsliste zeigt Absagen
  und offene Aufgaben.
- Neue Spalten `dr_inquiries.reminded_at`, `reminder_count` (`occ upgrade`).

## 0.12.0 – 2026-11-17

- **Antwort-Mails werden in der Aktion erkannt**: Eine zugeordnete Antwort auf ein Anschreiben
  markiert den Sponsor als „Antwort per Mail – Status offen“ (eigener Filter, Hinweis in der
  Kachel „Angeschrieben“). Bisher erschien sie nur auf der Seite Antworten.
- **Status der Rückmeldung**: Zusage · Zusage – Angaben fehlen noch · Meldet sich noch ·
  Diesmal nicht · Bitte nicht mehr anschreiben, jeweils mit Notiz. Setzbar in der Aktion (neuer
  Button je Sponsor) und direkt an der Antwort-Mail („Status / Rückmeldung“); die Mail wird dabei
  als erledigt markiert. Aus demselben Dialog lässt sich die Zusage mit allen Angaben erfassen.
  Eine Rückmeldung über das Formular setzt den Status automatisch auf „Zusage“.
- **„Bitte nicht mehr anschreiben“** wird beim möglichen Sponsor hinterlegt (auch im Formular
  des Sponsors änderbar, Hinweis in der Liste). Solche Sponsoren und Absagen der Aktion lassen
  sich nicht auswählen und werden beim Anschreiben übersprungen.
- Aktion: neue Filter (Ohne Antwort, Antwort per Mail, Meldet sich noch, Angaben fehlen, Absage),
  Absagen und Zusagen ohne Angaben in der Kachel „Rückmeldungen“. Zugesagte Beträge zählen nur
  noch für Zusagen mit Angaben.
- Behoben: Beim allerersten Abruf des Postfachs wurden alle schon vorhandenen Mails übersprungen –
  eine Antwort, die vor dem Einrichten des Abrufs kam, ging so verloren. Jetzt werden die Mails der
  letzten 30 Tage berücksichtigt.
- Neue Spalten `dr_inquiries.outcome`, `outcome_note`, `outcome_by`, `outcome_at`,
  `last_reply_at` und `dr_sponsors.no_contact` (`occ upgrade`).

## 0.11.1 – 2026-11-17

- Rückmeldeformular: Die Meldung nach dem Speichern („Vielen Dank! Ihre Angaben sind
  gespeichert …“) sowie Fehler- und Logo-Hinweise erscheinen jetzt unten direkt über dem
  Absenden-Button statt oben auf der Seite. Nach einer weiteren Änderung verschwindet die
  Erfolgsmeldung wieder.

## 0.11.0 – 2026-11-17

- Neues Feld **„Spendenbescheinigung senden an (E-Mail)“** im Rückmeldeformular und in der
  manuellen Erfassung. Vorbelegt mit der Adresse des verknüpften Spenders bzw. des möglichen
  Sponsors, änderbar. Die Zuwendungsbestätigung geht nach dem Spendeneingang an diese Adresse;
  ein neu angelegter (oder aktualisierter) Spender erhält sie als E-Mail-Adresse.
- Die Adresse steht in der Rückmeldung, in der Bestätigungsmail an den Sponsor und im CSV-Export
  („Bescheinigung an“).
- Neue Spalte `dr_inquiries.receipt_email` (`occ upgrade`).

## 0.10.2 – 2026-11-17

- Behoben: Die Absenden-Leiste des Rückmeldeformulars verdeckte das letzte Eingabefeld. Sie hat
  jetzt einen eigenen Bereich unter dem scrollbaren Formular.
- Das Rückmeldeformular steht mittig auf der Seite.

## 0.10.1 – 2026-11-17

- **Rückmeldeformular für Sponsoren überarbeitet**: übersichtliches Karten-Layout (Kopfkarte mit
  Verein, Aktion und Einleitung, je Abschnitt eine Karte), ruhiger Hintergrund statt des
  Nextcloud-Hintergrundbilds, Kopfzeile in der Theming-Farbe.
- Behoben: Die Seite ließ sich nicht scrollen, dadurch war der Absenden-Button nicht erreichbar.
  Der Button sitzt jetzt in einer festen Leiste am unteren Rand und zeigt an, welche Angaben noch
  fehlen.
- Die Nextcloud-Werbezeile unter dem Formular ist ausgeblendet.

## 0.10.0 – 2026-10-31

- „Sponsoren“ heißen jetzt **„Mögliche Sponsoren“**.
- Neue Seite **Rückmeldungen**: alle Rückmeldungen über alle Aktionen, Filter nach Aktion und
  Status (Spende offen / eingegangen), Summe und Freikarten, **Export als CSV** (Excel-tauglich,
  mit Anschrift, Freikarten, Logo-Freigabe und Hinweis).
- Rückmeldungen lassen sich **manuell erfassen und bearbeiten** (z. B. nach einem Telefonat),
  auch ohne vorheriges Anschreiben und in geschlossenen Aktionen; wahlweise mit
  Bestätigungsmail an den Sponsor. Sie sind als „manuell erfasst“ gekennzeichnet.
- **Antworten auf Anschreiben**: Die App liest alle 10 Minuten das Postfach der Anschreiben
  (IMAP, nur lesend, nur verschlüsselt) und übernimmt Antworten auf Anschreiben (erkannt an der
  Message-ID) und Mails von Adressen möglicher Sponsoren. Jede ist eine **Aufgabe** auf der neuen
  Seite **Antworten** („zu bearbeiten“ → „erledigt“, mit Notiz und Name), mit Zähler in der
  Navigation und Hinweis in der Aktion. Abwesenheitsnotizen werden ohne Aufgabe abgelegt.
  „Jetzt abrufen“ und „Verbindung testen“ für den Abruf von Hand.
- **Info an die Gruppe** bei jeder Rückmeldung über das Formular und jeder neuen Antwort-Mail:
  Nextcloud-Benachrichtigung und (abschaltbar) eine E-Mail an alle Gruppenmitglieder.
- Behoben: Der Texteditor der Mail-Vorlagen war nur eine schmale Spalte – Nextcloud gibt
  bearbeitbaren Feldern global eine feste Breite.
- Neue Datenbanktabelle `dr_replies`, neue Spalte `dr_inquiries.response_by` (`occ upgrade`).

## 0.9.0 – 2026-10-30

- **HTML-Mails** für die Bescheinigungs-Mail, die Sponsoren-Anschreiben und die
  Bestätigungsmail an Sponsoren, jeweils mit automatisch erzeugter Textfassung als Fallback
  (multipart/alternative).
- **WYSIWYG-Editor** (Tiptap) in den Einstellungen, bei den Vorlagen und in beiden
  Senden-Dialogen: fett, kursiv, unterstrichen, Überschriften, Listen, Zitat, Ausrichtung,
  Links, Rückgängig, Platzhalter-Menü.
- **Bilder** (PNG, JPG, GIF bis 5 MB) lassen sich in die Vorlagen einfügen; sie liegen im
  geschützten Datenbereich der App und werden beim Versand in die Mail eingebettet.
- Neue Platzhalter `{linkknopf}` und `{ordnerknopf}`: der Link als Knopf. `{link}` und
  `{ordnerlink}` werden in HTML zu klickbaren Links und lassen sich auch als Linkziel
  verwenden (Text markieren → Link → `{link}`).
- Vorschau als gerenderte HTML-Mail. Der Senden-Dialog der Bescheinigung bearbeitet jetzt
  die Vorlage mit Platzhaltern; gefüllt wird sie beim Senden.
- Das HTML wird beim Speichern und beim Senden serverseitig auf die Elemente des Editors
  beschränkt (keine Skripte, keine fremden Bilder, nur http(s)-/mailto-Links).
- Bestehende Text-Vorlagen werden beim Laden automatisch in HTML umgewandelt.

## 0.8.0 – 2026-10-30

- Neu: **Sponsoren anschreiben** (Navigation „Aktionen“, „Sponsoren“, „Vorlagen“).
  - Sponsorenliste mit Name, Ansprechpartner, Briefanrede, E-Mail und Notiz; bestehende
    Spender mit E-Mail-Adresse lassen sich übernehmen.
  - Anschreiben-Vorlagen mit Platzhaltern, in der App verwaltbar.
  - Aktionen mit Übersicht (angeschrieben, Rückmeldungen, zugesagte und noch offene Beträge,
    Freikarten, Logo-Freigaben) und Filter nach Status. Versand einzeln oder als Serie nach
    Vorschau, jeder Sponsor mit persönlichem Rückmelde-Link (auch zum Kopieren).
  - Öffentliches Rückmeldeformular: konfigurierbarer Text, Anschrift, Geld- oder Sachspende,
    Freikarten, Zustimmung „Logo auf Plakaten“ mit optionalem Logo-Upload, Hinweis. Änderbar,
    bis die Spende eingegangen ist; geschlossene Aktionen nehmen nichts mehr an.
  - Nach jeder Rückmeldung eine Nextcloud-Benachrichtigung an die Gruppe und eine
    Bestätigungsmail an den Sponsor.
  - „Spende eingegangen“ erzeugt Spender, Bescheinigung und PDFs und versendet sie sofort;
    schlägt nur der Versand fehl, bleibt die Bescheinigung erhalten und der Fehler wird
    angezeigt. Offene Zusagen stehen auch oben unter „Bescheinigungen“.
- Einstellungen: zweiter SMTP-Zugang für Sponsoren-Anschreiben (mit Testmail), Text des
  Rückmeldeformulars, Bestätigungsmail, optionaler Logo-Ordner.
- Neu in der Bescheinigungs-Mail: Platzhalter **`{ordnerlink}`** – öffentlicher Link (nur lesen)
  auf den Ordner „unterschrieben“ des Spenders, damit er seine Bescheinigungen jederzeit wieder
  herunterladen kann. Ein Link je Spender, beim ersten Versand angelegt und danach
  wiederverwendet; ist er gelöscht oder abgelaufen, entsteht ein neuer. Verlangt die Nextcloud
  für öffentliche Links ein Passwort, meldet die Vorschau das, statt ohne Link zu senden.
- Neue Datenbanktabellen (`occ upgrade` nötig): `dr_sponsors`, `dr_campaigns`,
  `dr_letter_templates`, `dr_inquiries`; neue Spalte `dr_donors.share_token`.

## 0.7.0 – 2026-10-28

- Neue Startseite **Übersicht** (Dashboard) für ein wählbares Jahr:
  - Kennzahlen: Summe der Zuwendungen (davon Geld/Sach), Bescheinigungen, Spender (davon
    zum ersten Mal), Durchschnitt, größte Einzelzuwendung – mit Veränderung zum Vorjahr; im
    laufenden Jahr wird mit dem Vorjahr bis zum gleichen Tag verglichen.
  - Diagramm „Ausgestellt pro Monat“, gestapelt nach Geld- und Sachzuwendung, umschaltbar
    zwischen Betrag und Anzahl.
  - Versandstatus (versendet, noch zu versenden, nach Versand geändert, Spender ohne E-Mail)
    mit Liste der offenen Bescheinigungen zum direkten Öffnen.
  - Größte Zuwendungen des Jahres je Spender.
  - Jahresvergleich; ein Klick auf ein Jahr stellt die ganze Übersicht darauf um.
  - Hinweise: fehlende Einstellungen und Gültigkeit des Freistellungs- bzw.
    Feststellungsbescheids (§ 63 Abs. 5 AO) – Warnung ein halbes Jahr vor Ablauf.
  - Diagramme mit Tooltip (auch per Tastatur) und Tabellenansicht; helles und dunkles Theme.

## 0.6.0 – 2026-10-28

- Einstellungen, Verein (Zuwendungsempfänger): neue optionale **Funktionszeile** unter dem
  Namen (z. B. Abteilung). Sie steht im Aussteller-Feld beider Muster zwischen Name und
  Anschrift; mit vier Zeilen wird die Schrift dort kleiner, damit alles ins Feld passt.

## 0.5.3 – 2026-10-28

- Behoben: Die Knöpfe „Neuer Spender“, „Neue Bescheinigung“, „Bearbeiten“ und „Abbrechen“
  taten beim Klicken nichts. Die App hatte vue-router 4, @nextcloud/vue 9.14 bringt
  vue-router 5 mit; mit zwei Kopien fanden die Knöpfe den Router der App nicht. Die App
  nutzt jetzt ebenfalls vue-router 5.

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
