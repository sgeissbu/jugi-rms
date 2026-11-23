-- jugi-rms Migration 001: Symbol, Einheit und gewertete Kategorien pro Disziplin (neues Design, November 2026)
-- Einmalig ausführen (TablePlus: "SQL ausführen", phpMyAdmin: "Importieren").
-- Alle Spalten sind optional: ohne Werte läuft alles wie bisher, Symbole werden dann nach dem Namen erkannt.

ALTER TABLE disziplinen
    ADD COLUMN symbol VARCHAR(40) NULL DEFAULT NULL
        COMMENT 'optional: Symbol-Name aus assets/img/icons.svg (z.B. d-weit); leer = automatisch nach Disziplin-Name'
        AFTER hoch_ist_besser,
    ADD COLUMN einheit_winter VARCHAR(20) NULL DEFAULT NULL
        COMMENT 'optional: Einheit im Wintersemester (z.B. Stufe, m, s); leer = einheit_sommer'
        AFTER symbol,
    ADD COLUMN einheit_sommer VARCHAR(20) NULL DEFAULT NULL
        COMMENT 'optional: Einheit im Sommersemester; leer = einheit_winter. s = Zeit (Eingabe 80 oder 1:20)'
        AFTER einheit_winter,
    ADD COLUMN kategorien VARCHAR(100) NULL DEFAULT NULL
        COMMENT 'optional: zählt nur in diesen Kategorien für die Rangliste, z.B. U8/U10,U12; leer = alle'
        AFTER einheit_sommer;

-- Beispiele:
-- UPDATE disziplinen SET einheit_winter = 'Stufe', einheit_sommer = 'm' WHERE name = 'Weitsprung';
-- UPDATE disziplinen SET einheit_winter = 's' WHERE name = 'Hindernislauf';   -- gilt dann auch im Sommer
-- UPDATE disziplinen SET kategorien = 'U8/U10,U12' WHERE name = 'Ballweitwurf'; -- nur für die Jüngeren gewertet
