# Spendenbescheinigungen (donationreceipts)

Nextcloud-App für Zuwendungsbestätigungen nach amtlichem Muster: Spender verwalten,
Bescheinigungen für Geld- und Sachzuwendungen als PDF erzeugen (ohne und mit
Unterschrift und Stempel), im Spenderordner ablegen und per E-Mail verschicken.

Der vollständige Plan steht in [docs/plan.md](docs/plan.md), die Änderungen je Version in
[CHANGELOG.md](CHANGELOG.md).

## Voraussetzungen

- Nextcloud 32, PHP ≥ 8.1
- SSH-Zugang zum Server (`occ`)
- Auf dem Server wird weder Node noch Composer gebraucht: das gebaute Frontend (`js/`)
  und die PHP-Bibliotheken (`3rdparty/`) sind im Repository enthalten.

## Installation

```bash
# ins Verzeichnis für eigene Apps klonen (Name muss donationreceipts sein)
git clone <repo-url> custom_apps/donationreceipts

# aktivieren (legt die Datenbanktabellen an)
php occ app:enable donationreceipts
```

Danach in **Verwaltung → Spendenbescheinigungen** die berechtigte Gruppe wählen. Solange
keine Gruppe gewählt ist, hat niemand Zugriff – auch Admins nicht, sofern sie nicht in der
Gruppe sind.

## Update

```bash
cd custom_apps/donationreceipts
git pull
php occ upgrade   # führt neue Datenbank-Migrationen aus, wenn die Version gestiegen ist
```

## Entwicklung

```bash
npm ci && npm run build          # Frontend nach js/ bauen (Ergebnis wird eingecheckt)
composer install                 # PHPUnit und OCP-Schnittstellen (nur Entwicklung)
vendor/bin/phpunit               # Tests
```

PHP-Bibliotheken für die Laufzeit (FPDF, FPDI) werden in `build/3rdparty/composer.json`
gepflegt und mit `composer install --no-dev` im Ordner `build/3rdparty` nach `3rdparty/`
installiert.

## Versionierung

Jede Änderung bekommt eine neue Version (SemVer) – in `appinfo/info.xml`, `package.json`,
einem Eintrag in `CHANGELOG.md` und einem Git-Tag:

```bash
scripts/bump-version.sh minor    # oder patch / major
# CHANGELOG.md ergänzen, committen
git tag v$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' appinfo/info.xml)
```

## Abweichungen vom Plan

- **Fremdbibliotheken ohne Namespace-Präfix.** E-Mails laufen über das symfony/mailer, das
  Nextcloud selbst mitbringt (NC 32: 6.4); gebündelt werden nur FPDF und FPDI. Damit ist
  kein Präfix-Werkzeug (php-scoper) nötig, und es gibt keinen Konflikt mit der
  Mailer-Version der Nextcloud.
- **§ 60a AO** braucht laut Muster auch Finanzamt und Steuernummer, nicht nur das Datum.
- **„Kein Mitgliedsbeitrag“**: Das Geldmuster hat dafür ein eigenes Ankreuzfeld; es wird
  über einen Schalter in den Einstellungen gesetzt.
