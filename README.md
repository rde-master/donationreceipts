# Spendenbescheinigungen (donationreceipts)

Nextcloud-App für Zuwendungsbestätigungen nach amtlichem Muster: Spender verwalten,
Bescheinigungen für Geld- und Sachzuwendungen als PDF erzeugen (ohne und mit
Unterschrift und Stempel), im Spenderordner ablegen und per E-Mail verschicken.

Der vollständige Plan steht in [docs/plan.md](docs/plan.md), die Änderungen je Version in
[CHANGELOG.md](CHANGELOG.md).

## Funktionen

- **Übersicht** (Startseite): Summe, Anzahl, Spender und Durchschnitt des gewählten Jahres
  mit Vergleich zum Vorjahr (im laufenden Jahr bis zum gleichen Tag), Ausstellungen pro
  Monat (Geld/Sach, Betrag oder Anzahl), Jahresvergleich, Versandstatus mit offenen
  Bescheinigungen, größte Zuwendungen, Warnung vor Ablauf des Freistellungsbescheids.
  Jedes Diagramm lässt sich auch als Tabelle anzeigen.
- **Spender**: Person oder Firma, Anschrift, E-Mail, Notiz; Suche; Löschen nur ohne
  Bescheinigungen.
- **Bescheinigungen**: Geld- oder Sachzuwendung, beliebig viele je Spender. Nummer
  `JJJJ-NNNN`, je Erstellungsjahr ab 0001, vergeben in einer Datenbank-Transaktion. Betrag
  in Buchstaben wird automatisch erzeugt. Bescheinigungen bleiben änderbar (mit Warnung,
  wenn schon versendet); löschen lassen sie sich nicht, damit der Nummernkreis lückenlos bleibt.
- **PDF**: die amtlichen Vorlagen 034122 (Geld) und 034123 (Sache) aus `resources/`,
  befüllt an vermessenen Positionen. Es entstehen immer zwei Fassungen: ohne und mit
  eingefügter Unterschrift und Stempel.
- **Ablage**: `<Hauptordner>/<Spender>/<Nummer>_<Ausstellungsdatum>.pdf` in den Hauptordnern
  „nicht unterschrieben“ und „unterschrieben“. Ordner und Dateien sind über ihre Datei-ID
  zugeordnet; bei Änderungen bleibt die alte Fassung in der Versionshistorie.
- **E-Mail**: Versand der unterschriebenen Fassung über einen eigenen SMTP-Zugang nach einer
  Vorschau; Vorlage mit Platzhaltern; Versandzeitpunkt und Empfänger werden gespeichert. Mit
  `{ordnerlink}` enthält die Mail einen öffentlichen Link (nur lesen) auf den Ordner
  „unterschrieben“ des Spenders, über den er alle seine Bescheinigungen jederzeit wieder
  herunterladen kann. Der Link wird beim ersten Gebrauch als Nextcloud-Freigabe angelegt und
  danach wiederverwendet; die Fassungen ohne Unterschrift werden nie freigegeben.
- **Sponsoren anschreiben**:
  - **Sponsoren**: eigene Liste mit Name, Ansprechpartner, Briefanrede und E-Mail; bestehende
    Spender lassen sich übernehmen und bleiben mit ihnen verknüpft.
  - **Vorlagen**: beliebig viele Anschreiben-Vorlagen mit Platzhaltern (`{anrede}`, `{name}`,
    `{ansprechpartner}`, `{aktion}`, `{link}`, `{verein}`).
  - **Aktionen** (z. B. „Sommerfest 2027“): Sponsoren auswählen, Vorlage wählen, Vorschau, dann
    einzeln oder als Serie versenden – über einen **eigenen SMTP-Zugang**, getrennt vom Zugang
    für Bescheinigungen. Jeder Sponsor bekommt einen persönlichen Rückmelde-Link.
  - **Rückmeldeformular** (öffentlich, ohne Login): frei konfigurierbarer Text mit Platzhaltern,
    Anschrift für die Bescheinigung, Geld- oder Sachspende, Freikarten (2 / 1 / nein / melde mich
    noch), Zustimmung zur öffentlichen Nennung (Logo auf Plakaten) mit optionalem Logo-Upload und
    ein Hinweisfeld. Änderbar über denselben Link, bis die Spende eingegangen ist.
  - Nach einer Rückmeldung: Benachrichtigung an alle Gruppenmitglieder und Bestätigungsmail an
    den Sponsor (eigene Vorlage, `{angaben}` fasst die Rückmeldung zusammen).
  - **Spende eingegangen**: In der Aktion oder unter „Bescheinigungen“ bestätigt ein
    Gruppenmitglied Eingangsdatum und Betrag. Dann wird der Spender angelegt bzw. aktualisiert,
    die Bescheinigung mit Nummer und PDFs erzeugt und sofort per E-Mail versendet.
- **Rechte**: Nur Mitglieder einer vom Admin gewählten Gruppe sehen und nutzen die App; jeder
  API-Aufruf wird serverseitig geprüft. Einzige Ausnahme ist das Rückmeldeformular: Es ist nur
  über den 32-stelligen Link erreichbar; falsche Links werden gebremst (Brute-Force-Schutz).

## Voraussetzungen

- Nextcloud 32 bis 35, PHP ≥ 8.1 (Nextcloud 35 selbst verlangt PHP ≥ 8.3)
- SSH-Zugang zum Server (`occ`)
- ausgehende SMTP-Verbindung zum Mailserver des Vereins (Port 587 oder 465)
- Auf dem Server wird weder Node noch Composer gebraucht: das gebaute Frontend (`js/`)
  und die PHP-Bibliotheken (`3rdparty/`) sind im Repository enthalten.

## Installation

```bash
# ins Verzeichnis für eigene Apps klonen (Verzeichnisname muss donationreceipts sein)
git clone <repo-url> custom_apps/donationreceipts

# aktivieren (legt die Datenbanktabellen an)
php occ app:enable donationreceipts
```

## Einrichtung

1. **Verwaltung → Spendenbescheinigungen**: berechtigte Gruppe wählen. Solange keine Gruppe
   gewählt ist, hat niemand Zugriff – auch Admins nicht, sofern sie nicht in der Gruppe sind.
2. In der App unter **Einstellungen**:
   - Vereinsdaten, Steuerbegünstigung (Freistellungsbescheid oder § 60a AO, jeweils mit
     Finanzamt und Steuernummer), begünstigte Zwecke, Unterzeichnung
   - Unterschrift und Stempel als PNG mit transparentem Hintergrund
   - die beiden Hauptordner (alle Gruppenmitglieder brauchen dort Schreibrecht, z. B. über
     einen Team-Ordner der Gruppe)
   - SMTP-Zugang und E-Mail-Vorlage, dann „Testmail senden“
   - für Sponsoren-Anschreiben: zweiter SMTP-Zugang, Text des Rückmeldeformulars,
     Bestätigungsmail und optional ein Logo-Ordner (Logos landen in den Dateien des Mitglieds,
     das das Anschreiben verschickt hat; es braucht dort Schreibrecht)

Solange etwas fehlt, zeigt die Einstellungsseite oben eine Liste, und das Erstellen von
Bescheinigungen wird abgelehnt.

## Update

```bash
cd custom_apps/donationreceipts
git pull
php occ upgrade   # führt neue Datenbank-Migrationen aus, wenn die Version gestiegen ist
```

## Entwicklung

```bash
npm ci && npm run build          # Frontend nach js/ bauen (Ergebnis wird eingecheckt)
npm run lint                     # ESLint
composer install                 # PHPUnit und OCP-Schnittstellen (nur Entwicklung)
vendor/bin/phpunit               # Tests
```

PHP-Bibliotheken für die Laufzeit (FPDF, FPDI) werden in `build/3rdparty/composer.json`
gepflegt und mit `composer install --no-dev` im Ordner `build/3rdparty` nach `3rdparty/`
installiert. Die Feldpositionen auf den Vorlagen stehen in `lib/Pdf/ReceiptLayout.php`.

## Versionierung

Jede Änderung bekommt eine neue Version (SemVer) – in `appinfo/info.xml`, `package.json`,
einem Eintrag in `CHANGELOG.md` und einem Git-Tag:

```bash
scripts/bump-version.sh minor    # oder patch / major
npm run build                    # Version steckt auch im Frontend-Bundle
# CHANGELOG.md ergänzen, committen
git tag v$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' appinfo/info.xml)
```

Mit der Abnahme (Phase 6) kommt Version 1.0.0.

## Abweichungen vom Plan

- **Fremdbibliotheken ohne Namespace-Präfix.** E-Mails laufen über das symfony/mailer, das
  Nextcloud selbst mitbringt (NC 32: 6.4, NC 35: 7.4); gebündelt werden nur FPDF und FPDI. Damit ist
  kein Präfix-Werkzeug (php-scoper) nötig, und es gibt keinen Konflikt mit der
  Mailer-Version der Nextcloud.
- **§ 60a AO** braucht laut Muster auch Finanzamt und Steuernummer, nicht nur das Datum.
- **„Kein Mitgliedsbeitrag“**: Das Geldmuster hat dafür ein eigenes Ankreuzfeld; es wird
  über einen Schalter in den Einstellungen gesetzt.
- **Sachzuwendung**: statt eines Hinweistexts zu den Wertermittlungsunterlagen gibt es – wie im
  Muster – das Kreuz „Geeignete Unterlagen … liegen vor“; die Herkunft hat drei Optionen
  (Betriebsvermögen, Privatvermögen, keine Angaben).
- **Ausstellungsdatum**: eigenes Feld (vorbelegt mit heute); es steht neben dem Ort über der
  Unterschriftslinie und im Dateinamen.
- **Nummer auf dem PDF**: klein oben rechts außerhalb der Felder des Musters.
- **SMTP**: nur verschlüsselte Verbindungen (STARTTLS Pflicht oder SSL/TLS); ein Mailserver
  ohne Verschlüsselung wird abgelehnt.
- **Zeichensatz im PDF**: Windows-1252 (alle deutschen Umlaute, ß und €). Zeichen außerhalb,
  z. B. in osteuropäischen Namen, werden angenähert (ł → l) oder als „?“ dargestellt.

## Lizenz

[GNU Affero General Public License v3.0](LICENSE) (AGPL-3.0). Die mitgelieferten Bibliotheken
unter `3rdparty/` (FPDF, FPDI) behalten ihre eigenen Lizenzen.
