# Spendenbescheinigungen (donationreceipts)

Nextcloud-App für Zuwendungsbestätigungen nach amtlichem Muster: Spender verwalten,
Bescheinigungen für Geld- und Sachzuwendungen als PDF erzeugen (ohne und mit
Unterschrift und Stempel), im Spenderordner ablegen und per E-Mail verschicken.

Der vollständige Plan steht in [docs/plan.md](docs/plan.md), die Änderungen je Version in
[CHANGELOG.md](CHANGELOG.md).

## Funktionen

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
  Vorschau; Vorlage mit Platzhaltern; Versandzeitpunkt und Empfänger werden gespeichert.
- **Rechte**: Nur Mitglieder einer vom Admin gewählten Gruppe sehen und nutzen die App; jeder
  API-Aufruf wird serverseitig geprüft.

## Voraussetzungen

- Nextcloud 32, PHP ≥ 8.1
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
  Nextcloud selbst mitbringt (NC 32: 6.4); gebündelt werden nur FPDF und FPDI. Damit ist
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
