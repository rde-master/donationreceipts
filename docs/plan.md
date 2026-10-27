# Nextcloud-App Spendenbescheinigungen – Plan

Oct 27, 2026 · @Daniel

## Ziel und Umfang

Die App läuft in Nextcloud 32. Mit ihr legt man Spender an, erstellt für jeden beliebig viele Zuwendungsbestätigungen nach amtlichem Muster als PDF, versieht sie mit Unterschrift und Stempel, legt sie im Ordner des Spenders ab und verschickt sie nach einer Vorschau per E-Mail.

| Thema | Entscheidung |
| --- | --- |
| Arten | Geldzuwendung und Sachzuwendung, jeweils als Einzelbestätigung |
| Bedienung | Eigener Eintrag in der oberen Navigationsleiste; dort laufen Anlegen, Befüllen und die gesamte Verwaltung |
| Einstellungen | Vereinsdaten des Zuwendungsempfängers, Ablage, Nummernkreis, E-Mail; änderbar von allen Berechtigten |
| Fassungen | Beim Erstellen entstehen immer zwei PDFs: ohne und mit Unterschrift und Stempel |
| Ablage | Bestehende Hauptordner „nicht unterschrieben“ und „unterschrieben“, in beiden ein Unterordner je Spender |
| E-Mail | Versand der unterschriebenen Fassung nach Vorschau und Klick, über einen eigenen SMTP-Zugang |
| Rechte | Nur Mitglieder einer Gruppe; die Gruppe wählt ein Admin |
| Nummerierung | Format `2026-0001`, Zählung beginnt mit jedem Erstellungsjahr neu |
| Änderungen | Bescheinigungen bleiben änderbar; bei schon versendeten warnt die App, erlaubt es aber |
| PDF-Aussehen | Wie das amtliche Original; Grundlage sind die beschreibbaren amtlichen PDFs |
| Server | Nextcloud 32, gehostet, SSH-Zugang, nur PHP verfügbar |
| Verteilung | Nur auf dem eigenen Server, nicht im App Store |

Nicht im Umfang: Mitgliedsbeiträge, Sammelbestätigungen, elektronische Übermittlung an das Finanzamt und Buchhaltung.

## Rechtliche Rahmenbedingungen

Das PDF muss dem amtlichen Muster des BMF für Zuwendungsbestätigungen folgen. Die Punkte unten beruhen auf meinem Wissen und sind nicht frisch nachgeprüft. Vor dem Einsatz sollte sie ein Steuerberater oder das Finanzamt bestätigen.

- **Muster:** Es gibt getrennte Muster für Geld- und Sachzuwendungen. Wortlaut und Reihenfolge dürfen nicht wesentlich verändert werden. Je nach Fall werden Textbausteine gewählt, etwa Freistellungsbescheid oder Feststellung nach § 60a AO.
- **Betrag:** Er steht in Ziffern und in Buchstaben auf der Bescheinigung.
- **Sachzuwendung:** Die Bescheinigung nennt die genaue Bezeichnung, den Wert und die Herkunft (Privat- oder Betriebsvermögen). Die Unterlagen zur Wertermittlung bewahrt der Verein auf.
- **Unterschrift:** Bescheinigungen mit eingefügter statt eigenhändiger Unterschrift gelten als maschinell erstellt. Dieses Verfahren muss vorher dem Finanzamt angezeigt werden. Beim Verein ist das bereits erfolgt und genehmigt.
- **Doppel:** Der Verein bewahrt eine Kopie jeder Bescheinigung auf. Die Ablage in Nextcloud kann das abdecken.

## Funktionen

Die App hat einen eigenen Eintrag in der oberen Navigationsleiste. Dort gibt es drei Bereiche: Spender, Bescheinigungen und Einstellungen.

**Spender**

- Anlegen, bearbeiten, suchen und löschen. Löschen ist nur möglich, solange keine Bescheinigung existiert.
- Felder: Person (Vorname, Nachname) oder Firma (Firmenname), Anschrift, E-Mail, Notiz.
- Die Detailansicht listet alle Bescheinigungen des Spenders mit Nummer, Datum, Betrag und Versandstatus.

**Bescheinigungen**

- Neue Bescheinigung aus der Spenderansicht heraus. Ein Spender kann beliebig viele haben.
- Art wählen: Geld oder Sache. Das Formular zeigt nur die Felder dieser Art.
- Geld: Betrag, Tag der Zuwendung, Verzicht auf Erstattung von Aufwendungen (ja/nein).
- Sache: Bezeichnung, Wert, Herkunft, Hinweis zu den Wertermittlungsunterlagen.
- Der Betrag in Worten wird automatisch erzeugt.
- Beim Speichern wird die nächste Nummer im Format \`2026-0001\` vergeben, gezählt im Erstellungsjahr. Sie ändert sich danach nicht mehr.
- Bescheinigungen bleiben änderbar. Nach einer Änderung werden beide PDFs neu erzeugt und ersetzen die alten Dateien; Nextcloud behält die vorigen Fassungen in der Versionshistorie. Ist die Bescheinigung schon versendet, warnt die App vor dem Speichern und bietet danach einen erneuten Versand an.

**PDF, Unterschrift und Stempel**

- Grundlage sind die beschreibbaren amtlichen PDFs für Geld- und Sachzuwendung. Die App setzt die Daten an die Stellen der Formularfelder, das Ergebnis sieht aus wie das Original.
- Beim Erstellen entstehen immer zwei Fassungen: eine ohne und eine mit Unterschrift und Stempel.
- Unterschrift und Stempel sind Bilddateien (PNG mit transparentem Hintergrund), die Berechtigte in den Einstellungen hochladen.

**Ablage**

- Ohne Unterschrift: `<Ordner nicht unterschrieben>/<Spender>/<Nummer>_<Datum>.pdf`
- Mit Unterschrift: `<Ordner unterschrieben>/<Spender>/<Nummer>_<Datum>.pdf`
- Die beiden Hauptordner liegen schon in einem Team-Ordner, auf den alle Berechtigten Schreibrecht haben; sie werden in den Einstellungen ausgewählt. Der Spenderordner heißt „Vorname Nachname“ oder bei Firmen wie die Firma; Zeichen, die in Dateinamen nicht erlaubt sind, werden ersetzt. Gibt es den Namen schon für einen anderen Spender, hängt die App eine Zahl an, z. B. „Max Müller (2)“.
- Fehlt ein Spenderordner, legt die App ihn an. Benennt man den Spender um, bleiben die bestehenden Ordner zugeordnet.

**E-Mail**

- Nach dem Erstellen öffnet sich eine Vorschau mit Empfänger, Betreff, Text und der unterschriebenen Fassung als Anhang.
- Erst ein Klick auf Senden verschickt die Mail. Man kann auch später aus der Liste senden.
- Versand über einen eigenen SMTP-Zugang mit eigener Absenderadresse, unabhängig vom Mailserver der Nextcloud.
- Versandzeitpunkt und Empfängeradresse werden an der Bescheinigung gespeichert.

## Architektur und Technik

Es ist eine klassische Nextcloud-App mit PHP im Backend, Vue im Frontend und den Daten in der Nextcloud-Datenbank. Vorschlag für die App-ID: `donationreceipts`, Anzeigename „Spendenbescheinigungen“.

&#91;embedded content: Architektur · 4 Schichten\]

Die Oberfläche spricht nur mit den Controllern. Datenbank und Dateien laufen über die Schnittstellen von Nextcloud, nur die Mails gehen über den eigenen SMTP-Zugang. Alles ist reines PHP, damit es beim Hoster ohne zusätzliche Programme läuft.

| Baustein | Wahl | Grund |
| --- | --- | --- |
| Ausgangspunkt | Vorlage `nextcloud/app_template` | Aktuelle Projektstruktur, Build und Tests sind schon eingerichtet |
| Backend | PHP mit dem AppFramework: Controller, Services, QBMapper, Migrationen | Standard für Nextcloud-Apps |
| Frontend | Vue 3, @nextcloud/vue, @nextcloud/axios | Sieht aus und bedient sich wie der Rest von Nextcloud |
| PDF | Amtliches PDF als Vorlage, Text und Bilder per FPDI an die Feldpositionen gesetzt | Reines PHP, sieht aus wie das Original, Ergebnis ist nicht mehr veränderbar |
| Betrag in Worten | Kleine eigene Funktion | Deutsche Schreibweise mit Euro und Cent |
| E-Mail | symfony/mailer mit eigenem SMTP-Zugang | Unabhängig vom Mailserver der Nextcloud |
| SMTP-Passwort | Verschlüsselt gespeichert über ICrypto | Liegt nicht im Klartext in der Datenbank |
| Fremdbibliotheken | Mit Namespace-Präfix gebündelt, z. B. per php-scoper | Verhindert Konflikte mit Bibliotheken der Nextcloud und anderer Apps |
| Unterschrift, Stempel | IAppData | Nicht über die Dateiansicht erreichbar |
| Installation | Fertig gebautes App-Verzeichnis per SSH in den App-Ordner kopieren | Kein App Store, kein Build auf dem Server nötig |
| Tests | PHPUnit, ESLint | Laufen in der Vorlage schon mit |

**Befund zu den PDF-Vorlagen**

Keine der beiden Vorlagen hat Formularfelder. Die App setzt die Texte deshalb über feste Koordinaten auf die Seite und malt die Kreuze in die Kästchen. Die Positionen werden je Vorlage einmal vermessen.

| Vorlage | Herkunft | Technik | Folge |
| --- | --- | --- | --- |
| Geldzuwendung/Mitgliedsbeitrag (034122, 2013) | Formular-Management-System der Bundesfinanzverwaltung | PDF 1.4, klassische Querverweistabelle, Arial eingebettet | Direkt mit freier FPDI-Version nutzbar |
| Sachzuwendung (034123, 2013) | Formular-Management-System der Bundesfinanzverwaltung | PDF 1.4, klassische Querverweistabelle, Arial eingebettet | Direkt mit freier FPDI-Version nutzbar |

Beide Vorlagen stammen aus derselben Quelle und sehen einheitlich aus. Die ältere Anlage 3 als Ghostscript-Ausdruck wird nicht mehr gebraucht.

Beide Vorlagen gehen auf das BMF-Schreiben vom 7. November 2013 zurück, dessen Muster seit 2014 verbindlich sind ([BMF-Schreiben](https://www.finanzamt.bayern.de/Informationen/Formulare/Weitere_Themen_A_bis_Z/Spenden/BMF-Schreiben_7-11-13.pdf), [IWW](https://www.iww.de/astw/rechtsprechung/-10b-estg-aktualisierte-muster-der-zuwendungsbestaetigungen-fuer-den-spendenabzug-f71910)). Die Vorlagen wurden direkt aus dem Formular-Management-System heruntergeladen und sind damit die aktuellen Fassungen.

## Datenmodell

Drei eigene Tabellen reichen; die Vereinsdaten liegen in der App-Konfiguration. Beträge werden in Cent als Ganzzahl gespeichert, damit keine Rundungsfehler entstehen.

**Tabelle `dr_donors` (Spender)**

| Feld | Typ | Hinweis |
| --- | --- | --- |
| id | int | Primärschlüssel |
| kind | string | `person` oder `company` |
| first\_name, last\_name | string | Nur bei Personen |
| company | string | Nur bei Firmen |
| street, zip, city, country | string | Anschrift |
| email | string | Ziel für den Versand, optional |
| folder\_unsigned\_id, folder\_signed\_id | int | Nextcloud-IDs der beiden Spenderordner |
| notes | text | optional |
| created\_by, created\_at, updated\_at | string / datetime | Protokoll |

**Tabelle `dr_receipts` (Bescheinigungen)**

| Feld | Typ | Hinweis |
| --- | --- | --- |
| id | int | Primärschlüssel |
| number | string | `2026-0001`, eindeutig |
| donor\_id | int | Verweis auf den Spender |
| type | string | `money` oder `goods` |
| amount\_cents | int | Betrag bzw. Wert |
| donation\_date | date | Tag der Zuwendung |
| expense\_waiver | bool | Nur Geld: Verzicht auf Aufwendungserstattung |
| goods\_description, goods\_origin, goods\_valuation\_note | text / string | Nur Sache |
| file\_unsigned\_id, file\_signed\_id | int | Nextcloud-IDs der beiden PDFs |
| sent\_at, sent\_to | datetime / string | Letzter Versand |
| changed\_after\_sent | bool | Nach dem Versand geändert |
| created\_by, created\_at, updated\_at | string / datetime | Protokoll |

**Tabelle `dr_counters` (Nummernkreis)**

| Feld | Typ | Hinweis |
| --- | --- | --- |
| year | int | Erstellungsjahr, z. B. 2026 |
| next\_value | int | Nächste freie Nummer in diesem Jahr; ein neues Jahr beginnt bei 1 |

Die Nummer wird in einer Datenbank-Transaktion mit Zeilensperre vergeben. So bekommen zwei gleichzeitige Bescheinigungen nie dieselbe Nummer.

## Abläufe

Nummer, PDF und Ablage entstehen in einem Schritt beim Speichern. Der Versand ist ein eigener Schritt mit Bestätigung.

&#91;embedded content: Ablauf einer Bescheinigung · vom Erfassen bis zum Versand\]

Eine Änderung läuft ab der PDF-Erzeugung durch denselben Weg; die Nummer bleibt, beide Dateien werden ersetzt. Ist die Bescheinigung schon versendet, warnt die App vorher und bietet danach einen erneuten Versand an. Eine nicht versendete Bescheinigung kann man jederzeit aus der Liste heraus senden.

- Schlägt das Ablegen fehl, wird die Bescheinigung nicht gespeichert und die Nummer nicht verbraucht. Datenbank und Datei bleiben so immer im Einklang.
- Schlägt der Versand fehl, bleibt die Bescheinigung erhalten und die Vorschau zeigt den Fehler.

## Einstellungen und Berechtigungen

Die Einstellungen sind ein Bereich in der App selbst und für alle Berechtigten änderbar. In den Nextcloud-Admin-Einstellungen gibt es nur einen kleinen Abschnitt, in dem ein Admin die berechtigte Gruppe wählt.

| Bereich | Einstellungen |
| --- | --- |
| Verein (Zuwendungsempfänger) | Name, Anschrift |
| Steuerbegünstigung | Freistellungsbescheid (Finanzamt, Steuernummer, Datum, Veranlagungszeitraum) oder Feststellungsbescheid nach § 60a AO (Datum) |
| Zwecke | Begünstigte Zwecke, wie sie im Bescheid stehen |
| Unterzeichnung | Ort, Name und Funktion der unterzeichnenden Person |
| Unterschrift und Stempel | Bild-Upload je eins, mit Vorschau |
| Ablage | Hauptordner „nicht unterschrieben“ und Hauptordner „unterschrieben“, per Ordnerauswahl |
| Nummernkreis | Format fest `JJJJ-NNNN`; Anzeige der nächsten Nummer im laufenden Jahr |
| SMTP | Server, Port, Verschlüsselung, Benutzer, Passwort, Absendername, Absenderadresse, Testmail-Knopf |
| E-Mail-Vorlage | Betreff und Text mit Platzhaltern wie `{name}`, `{nummer}`, `{betrag}` |
| Zugriff (nur Admin) | Gruppe, die die App nutzen darf |

**Rechteprüfung**

- Jeder API-Aufruf prüft serverseitig, ob der Nutzer in der berechtigten Gruppe ist. Eine verborgene Navigation allein reicht nicht.
- Nicht-Mitglieder sehen den Navigationseintrag nicht.
- Unterschrift- und Stempelbilder liegen im geschützten App-Datenbereich, nicht in Nutzerordnern. So kann sie niemand über die Dateiansicht herunterladen oder austauschen.

## Umsetzungsphasen

Die Phasen bauen aufeinander auf; nach jeder ist ein testbarer Stand in eurer Nextcloud möglich.

1. **Grundgerüst:** App-Skelett, Eintrag in der oberen Navigationsleiste, Gruppenwahl in den Admin-Einstellungen, Rechteprüfung, Einstellungsbereich in der App mit den Vereinsdaten.
2. **Spender:** Datenbank-Migration, Anlegen, Bearbeiten, Suchen, Detailansicht.
3. **Bescheinigungen und PDF:** Formular für Geld und Sache, Nummernkreis `JJJJ-NNNN`, Betrag in Worten, Ausfüllen der amtlichen PDFs an den Feldpositionen.
4. **Unterschrift, Stempel, Ablage:** Bild-Upload, Erzeugen beider Fassungen, Ablage in den beiden Hauptordnern, Neuerzeugung und Warnung bei Änderungen.
5. **E-Mail:** SMTP-Einstellungen mit Testmail, Vorlage mit Platzhaltern, Vorschau-Dialog, Versand der unterschriebenen Fassung, Versandprotokoll.
6. **Prüfung und Abnahme:** PDFs mit dem Original abgleichen, Probeexemplare vom Steuerberater prüfen lassen, automatische Tests, Installation per SSH auf eurem Server.

## Offene Punkte

Diese Fragen sollten vor Phase 1 geklärt sein, weil sie Datenmodell oder Ablage betreffen.

Alle Fragen sind geklärt; die Umsetzung kann mit Phase 1 starten.

- [x] Basisordner: bestehende Hauptordner „nicht unterschrieben“ und „unterschrieben“ im Team-Ordner, je ein Unterordner pro Spender
- [x] Spenderordner: „Vorname Nachname“ bzw. Firmenname
- [x] Vereinsdaten: alle Berechtigten dürfen sie ändern
- [x] Nummernformat: `2026-0001`, Neustart mit jedem Erstellungsjahr
- [x] Änderung nach Versand: warnen, aber erlauben
- [x] Unterschrift: maschinelles Verfahren angezeigt und genehmigt
- [x] E-Mail: eigener SMTP-Zugang mit eigener Absenderadresse; ausgehendes SMTP beim Hoster möglich
- [x] PDF: amtliche Vorlagen 034122 (Geld) und 034123 (Sache) liegen vor
- [x] Installation: eigene Apps per SSH beim Hoster erlaubt
- [x] Verteilung: nur auf dem eigenen Server
- [x] Muster aktuell: direkt aus dem Formular-Management-System heruntergeladen
