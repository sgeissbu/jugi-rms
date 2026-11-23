# jugi-rms

Ein schlankes, webbasiertes Resultatverwaltungssystem (RMS) für Jugendriegen und Vereine — entwickelt für die Erfassung und Verwaltung von sportlichen Wettkampfresultaten. Ursprünglich für die Jugi Langenthal gebaut, jetzt so generalisiert, dass jede Jugendriege oder jeder Verein es mit minimalem Aufwand für sich einsetzen kann.

Kein Admin-UI, keine Baustelle: Teilnehmer, Disziplinen und Login-Benutzer werden direkt in der Datenbank gepflegt (z.B. mit [TablePlus](https://tableplus.com/), phpMyAdmin oder einem vergleichbaren Tool). Das System selbst kümmert sich um Resultaterfassung, Ranglistenberechnung und Exporte.

## Funktionen

- **Resultaterfassung fürs Handy** — 2 Buchstaben tippen (Vor- oder Nachname, auch Initialen), Kind antippen (bei nur einem Treffer automatisch), Resultat eintippen, speichern. Die Tastatur bleibt offen, die Seite lädt nicht neu, Kinder vom heutigen Trainingstag stehen zuoberst. Mit «Rückgängig», Hinweis bei unplausiblen Werten («Komma vergessen?») und Zwischenspeicher, falls in der Halle das Netz fehlt.
- **Zeiten** — Eingabe in Sekunden (`80`, `8.12`) oder Minuten:Sekunden (`1:20`); gespeichert wird immer in Sekunden, angezeigt ab 60 s als `1:20`.
- **Einheiten und Symbole pro Disziplin** — z.B. Weitsprung im Winter in Stufen, im Sommer in Metern; jede Disziplin mit eigenem Symbol.
- **Übersicht** — Bestresultate aller aktiven Kinder, Suche, Filter nach Trainingstag und «nur mit Lücken», pro Disziplin «erfasst x von y».
- **Ranglisten** — Automatische Ranglistenberechnung mit Punktesystem über 4 Alterskategorien (U8/U10, U12, U14, U16/U18) und 2 Geschlechter, aufgeteilt nach Semestern; auf dem Handy als aufklappbare Liste.
- **Exporte** — CSV- und PDF-Export aller Ranglisten, Druckansicht
- **Personalisierung** — eigenes Logo und zwei Leitfarben, ohne eine Zeile Code anzufassen; Hell- und Dunkelmodus folgen dem Gerät

## Tech-Stack

| Technologie | Zweck |
|-------------|-------|
| PHP 7.4+ | Server-seitige Logik |
| MySQL/MariaDB | Datenbank (via PDO) |
| eigenes CSS/JS | Oberfläche (keine externen Dateien, kein CDN, keine Webfonts) |
| Dompdf | PDF-Generierung |

Symbole: [Lucide](https://lucide.dev) (ISC-Lizenz) plus eigene Disziplin-Symbole im gleichen Stil (`assets/img/icons.svg`).

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

## Update einer bestehenden Installation

1. Neue Dateien per FTP hochladen (`.env` und eigenes Logo bleiben unverändert).
2. Einmalig `database/migrations/001_disziplin_symbol_einheit.sql` in der Datenbank ausführen (neue, optionale Spalten `symbol`, `einheit_winter`, `einheit_sommer`, `kategorien` in `disziplinen`). Ohne Migration läuft alles weiter, nur Einheiten können dann nicht gesetzt werden.
3. Die alten Dateien `autocomplete_namen.php`, `autocomplete_disziplinen.php`, `assets/css/table-layout.css`, `assets/css/rangliste.css` und `assets/js/resultsoverview.js` werden nicht mehr gebraucht und können gelöscht werden.

## Personalisierung

Nur zwei Dinge lassen sich anpassen — mehr braucht es nicht:

1. **Logo**: eigene Datei als `assets/branding/logo.svg` oder `assets/branding/logo.png` ablegen. Ohne eigenes Logo wird automatisch ein neutraler Platzhalter angezeigt.

   Optional: ein zweites Logo für den Dunkelmodus als `assets/branding/logo-dark.svg` (oder `.png`), z.B. mit heller Schrift. Ein eigenes Tab-Symbol als `favicon.png` (im Hauptordner oder in `assets/branding/`).

   > Enthält das Logo Text (Vereinsname, Initialen etc.), sollte dieser beim Export aus dem Design-Tool **in Pfade/Kurven umgewandelt** werden (z.B. "In Pfad umwandeln" in Illustrator/Inkscape/Figma), statt als lebendiger `<text>`-Font-Verweis im SVG zu bleiben. Grund: Der Web-Browser hat Zugriff auf System-Schriften, der PDF-Export (Dompdf) dagegen nur auf seine eigenen mitgelieferten Basisschriften — bei live-Text kann die Schrift daher zwischen Web und PDF abweichen. Reine Vektorpfade sehen dagegen garantiert überall identisch aus.
2. **Farben**: `BRAND_COLOR_PRIMARY` und `BRAND_COLOR_SECONDARY` in der `.env` (Hex-Werte, z.B. `#1D284C`). Wirken auf Oberfläche und PDF-Export.

Zusätzlich kann `APP_NAME` in der `.env` gesetzt werden (erscheint im Kopf und im Browser-Tab-Titel). `APP_TIMEZONE` (Vorgabe `Europe/Zurich`) bestimmt, was «heute» ist – wichtig bei Hostern, deren PHP auf UTC steht.

## Datentabellen

Es gibt bewusst kein Admin-UI für Stammdaten — diese werden direkt per DB-Tool gepflegt:

| Tabelle | Zweck | Wichtige Spalten |
|---------|-------|------------------|
| `teilnehmer` | Wettkampfteilnehmer | `vorname`, `nachname`, `geschlecht` (`m`/`w`), `jahrgang`, `montag`…`freitag` (Trainingstage — mind. einer = 1 macht den Teilnehmer "aktiv") |
| `disziplinen` | Wettkampfdisziplinen | `name`, `aktiv` (1 = wählbar bei Erfassung), `hoch_ist_besser` (1 = höher ist besser, 0 = tiefer/schneller ist besser), optional `einheit_winter` / `einheit_sommer`, `kategorien` und `symbol` (siehe unten) |
| `resultate` | Erfasste Resultate | `teilnehmer_id`, `disziplin`, `resultat`, `pruefer`, `datum` (wird beim Erfassen automatisch gesetzt) |
| `users` | Login-Benutzer (Prüfer) | `username`, `password` (bcrypt-Hash — am einfachsten über `create_user.php` anlegen) |

Details zu den exakten Spaltentypen siehe `database/schema.sql`.

### Einheiten und Symbole der Disziplinen

`einheit_winter` und `einheit_sommer` sind optional. Ist nur eine gesetzt, gilt sie für beide Semester.

| Einheit | Bedeutung | Eingabe / Anzeige |
|---------|-----------|-------------------|
| `s` | Zeit in Sekunden | `80` oder `1:20` → angezeigt als `1:20`, unter 60 s als `8.12 s`. Bei langen Zeiten gibt es im Eingabefeld eine «:»-Taste (die iPhone-Zahlentastatur hat keinen Doppelpunkt). |
| `m` | Meter | `3.45 m` |
| `Stufe` | Stufenwertung | `Stufe 3.5` (halbe Stufen möglich) |
| anderer Text | z.B. `Sprünge`, `Punkte` | wird hinter die Zahl gesetzt |

`kategorien` (optional): Die Disziplin zählt nur in diesen Kategorien für die Rangliste, z.B. `U8/U10,U12` für einen Ballweitwurf der Jüngeren und `U14,U16/U18` für Kugelstossen der Älteren. Erfassen bleibt für alle möglich (mit Hinweis «zählt für U12 nicht»); nicht gewertete Resultate erscheinen in der Übersicht grau und in der Rangliste gar nicht – ohne Punkte, aber auch ohne Strafpunkte. Leer = zählt in allen Kategorien.

`symbol` ist ebenfalls optional: Ohne Eintrag wird das Symbol aus dem Namen erkannt (Weitsprung, Sprint, Hindernislauf, Schleuderball, Ballwurf, Seilspringen, Kugelstossen, Beugehang, Biathlon, Balancieren, Klettern, Geschicklichkeit/Slalom, Ausdauer-/Minutenlauf, Pendellauf). Wer ein anderes Symbol will, trägt einen Namen aus `assets/img/icons.svg` ein (z.B. `d-weit`, `d-sprint60`, `d-hindernis`, `medal`).

## Verwendung

1. **Anmelden** unter `/login.php`
2. **Resultate erfassen** unter `/index.php`: Disziplin einmal wählen (bleibt auf dem Gerät gespeichert), dann pro Kind 2 Buchstaben → Kind → Resultat → Speichern
3. **Ergebnisse ansehen** unter `/resultsoverview.php`
4. **Ranglisten ansehen** unter `/rangliste.php`, gefiltert nach Semester, Kategorie und Geschlecht
5. **Exportieren** als CSV oder PDF direkt von der Ranglisten-Seite

## Alterskategorien & Semesterlogik

Alterskategorien (U8/U10, U12, U14, U16/U18) und die Semestergrenzen (Winter 1.10.–10.4., Sommer 11.4.–30.9.) sind zentral in `src/Config/RankingConfig.php` hinterlegt. Das ist bewusst **nicht** Teil der `.env`-Personalisierung, um das System schlank zu halten — falls eine andere Struktur benötigt wird, kann diese Datei direkt angepasst werden.

## Bekannte Einschränkungen

- Resultate werden über `teilnehmer_id` verknüpft, mit Namens-Fallback für ältere Einträge ohne ID
- Keine automatisierten Tests vorhanden (für die lokale Entwicklung legt `php tools/seed-demo.php` erfundene Demo-Daten an)
- «Rückgängig» geht nur für eigene Einträge vom selben Tag; ältere Korrekturen weiterhin per DB-Tool
- Kein Admin-UI für Teilnehmer/Disziplinen/Benutzer — Pflege erfolgt direkt in der Datenbank

## Lizenz

[MIT](LICENSE) — frei nutz- und anpassbar für andere Jugendriegen und Vereine.
