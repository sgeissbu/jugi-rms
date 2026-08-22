# jugi-rms

Ein schlankes, webbasiertes Resultatverwaltungssystem (RMS) für Jugendriegen und Vereine — entwickelt für die Erfassung und Verwaltung von sportlichen Wettkampfresultaten. Ursprünglich für die Jugi Langenthal gebaut, jetzt so generalisiert, dass jede Jugendriege oder jeder Verein es mit minimalem Aufwand für sich einsetzen kann.

Kein Admin-UI, keine Baustelle: Teilnehmer, Disziplinen und Login-Benutzer werden direkt in der Datenbank gepflegt (z.B. mit [TablePlus](https://tableplus.com/), phpMyAdmin oder einem vergleichbaren Tool). Das System selbst kümmert sich um Resultaterfassung, Ranglistenberechnung und Exporte.

## Funktionen

- **Resultaterfassung** — Angemeldete Prüfer erfassen Wettkampfresultate über ein Formular mit Autovervollständigung für Namen und Disziplinen
- **Ranglisten** — Automatische Ranglistenberechnung mit Punktesystem über 4 Alterskategorien (U8/U10, U12, U14, U16/U18) und 2 Geschlechter, aufgeteilt nach Semestern
- **Exporte** — CSV- und PDF-Export aller Ranglisten
- **Live-Suche** — Durchsuchbare Ergebnisübersicht mit Echtzeit-Filterung
- **Personalisierung** — eigenes Logo und zwei Leitfarben, ohne eine Zeile Code anzufassen

## Tech-Stack

| Technologie | Zweck |
|-------------|-------|
| PHP 7.4+ | Server-seitige Logik |
| MySQL/MariaDB | Datenbank (via PDO) |
| Tailwind CSS | Styling (CDN) |
| jQuery UI | Autovervollständigung |
| Dompdf | PDF-Generierung |

## Installation

Kein CLI-Zugriff, kein SSH, kein Composer nötig — das System ist für klassisches Shared Hosting per FTP ausgelegt. `vendor/` (inkl. Dompdf) ist bereits im Repository enthalten.

1. **Repository herunterladen** (ZIP oder `git clone`)

2. **`.env` erstellen**

   Kopiere `.env.example` zu `.env` und passe die Werte an:
   ```env
   APP_ENV=production
   APP_DEBUG=false

   DB_HOST=localhost
   DB_NAME=meinverein_rms
   DB_USER=meinverein_user
   DB_PASS=ein-sicheres-passwort

   APP_NAME=Mein Verein
   BRAND_COLOR_PRIMARY=#1D284C
   BRAND_COLOR_SECONDARY=#FEB400

   SETUP_SECRET=ein-langer-zufaelliger-wert
   ```

3. **Auf den Webserver hochladen**

   Alle Dateien (inkl. `vendor/`) per FTP ins Webverzeichnis kopieren.

4. **Datenbank einrichten**

   `database/schema.sql` mit einem DB-Tool (TablePlus: "SQL ausführen", phpMyAdmin: "Importieren") in die Datenbank einspielen. Das legt die vier benötigten Tabellen an: `users`, `teilnehmer`, `disziplinen`, `resultate`.

5. **Ersten Benutzer anlegen**

   Im Browser aufrufen:
   ```
   https://deine-domain.ch/create_user.php?secret=DEIN_SETUP_SECRET
   ```
   (Der Wert muss exakt dem `SETUP_SECRET` aus der `.env` entsprechen — ohne korrektes Secret liefert die Seite einen 404-Fehler.) Über das Formular lässt sich jederzeit ein weiterer Prüfer-Login anlegen.

> Alternativ (für technisch versierte Nutzer): `composer install` regeneriert `vendor/`, falls Abhängigkeiten aktualisiert werden sollen.

## Personalisierung

Nur zwei Dinge lassen sich anpassen — mehr braucht es nicht:

1. **Logo**: eigene Datei als `assets/branding/logo.svg` oder `assets/branding/logo.png` ablegen. Ohne eigenes Logo wird automatisch ein neutraler Platzhalter angezeigt.

   > Enthält das Logo Text (Vereinsname, Initialen etc.), sollte dieser beim Export aus dem Design-Tool **in Pfade/Kurven umgewandelt** werden (z.B. "In Pfad umwandeln" in Illustrator/Inkscape/Figma), statt als lebendiger `<text>`-Font-Verweis im SVG zu bleiben. Grund: Der Web-Browser hat Zugriff auf System-Schriften, der PDF-Export (Dompdf) dagegen nur auf seine eigenen mitgelieferten Basisschriften — bei live-Text kann die Schrift daher zwischen Web und PDF abweichen. Reine Vektorpfade sehen dagegen garantiert überall identisch aus.
2. **Farben**: `BRAND_COLOR_PRIMARY` und `BRAND_COLOR_SECONDARY` in der `.env` (Hex-Werte, z.B. `#1D284C`). Wirken auf Oberfläche und PDF-Export.

Zusätzlich kann `APP_NAME` in der `.env` gesetzt werden (erscheint im Browser-Tab-Titel).

## Datentabellen

Es gibt bewusst kein Admin-UI für Stammdaten — diese werden direkt per DB-Tool gepflegt:

| Tabelle | Zweck | Wichtige Spalten |
|---------|-------|------------------|
| `teilnehmer` | Wettkampfteilnehmer | `vorname`, `nachname`, `geschlecht` (`m`/`w`), `jahrgang`, `montag`…`freitag` (Trainingstage — mind. einer = 1 macht den Teilnehmer "aktiv") |
| `disziplinen` | Wettkampfdisziplinen | `name`, `aktiv` (1 = wählbar bei Erfassung), `hoch_ist_besser` (1 = höher ist besser, 0 = tiefer/schneller ist besser) |
| `resultate` | Erfasste Resultate | `teilnehmer_id`, `disziplin`, `resultat`, `pruefer`, `datum` (wird beim Erfassen automatisch gesetzt) |
| `users` | Login-Benutzer (Prüfer) | `username`, `password` (bcrypt-Hash — am einfachsten über `create_user.php` anlegen) |

Details zu den exakten Spaltentypen siehe `database/schema.sql`.

## Verwendung

1. **Anmelden** unter `/login.php`
2. **Resultate erfassen** unter `/index.php` (Autovervollständigung für Namen und Disziplinen)
3. **Ergebnisse ansehen** unter `/resultsoverview.php`
4. **Ranglisten ansehen** unter `/rangliste.php`, gefiltert nach Semester, Kategorie und Geschlecht
5. **Exportieren** als CSV oder PDF direkt von der Ranglisten-Seite

## Alterskategorien & Semesterlogik

Alterskategorien (U8/U10, U12, U14, U16/U18) und die Semestergrenzen (Winter 1.10.–10.4., Sommer 11.4.–30.9.) sind zentral in `src/Config/RankingConfig.php` hinterlegt. Das ist bewusst **nicht** Teil der `.env`-Personalisierung, um das System schlank zu halten — falls eine andere Struktur benötigt wird, kann diese Datei direkt angepasst werden.

## Bekannte Einschränkungen

- Resultate werden über `teilnehmer_id` verknüpft, mit Namens-Fallback für ältere Einträge ohne ID
- Keine automatisierten Tests vorhanden
- Kein Admin-UI für Teilnehmer/Disziplinen/Benutzer — Pflege erfolgt direkt in der Datenbank

## Lizenz

[MIT](LICENSE) — frei nutz- und anpassbar für andere Jugendriegen und Vereine.
