-- jugi-rms: Datenbankschema
-- Import z.B. per TablePlus ("SQL ausfuehren" / "Run SQL File") oder phpMyAdmin.
-- Zeichensatz: utf8mb4 (Umlaute, Emojis in Namen etc.)

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL COMMENT 'bcrypt-Hash, siehe create_user.php',
    UNIQUE KEY uniq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS teilnehmer (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vorname VARCHAR(100) NOT NULL,
    nachname VARCHAR(100) NOT NULL,
    geschlecht CHAR(1) NOT NULL COMMENT "'m' oder 'w'",
    jahrgang SMALLINT UNSIGNED NOT NULL,
    montag TINYINT(1) NOT NULL DEFAULT 0,
    dienstag TINYINT(1) NOT NULL DEFAULT 0,
    mittwoch TINYINT(1) NOT NULL DEFAULT 0,
    donnerstag TINYINT(1) NOT NULL DEFAULT 0,
    freitag TINYINT(1) NOT NULL DEFAULT 0,
    KEY idx_teilnehmer_geschlecht_jahrgang (geschlecht, jahrgang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS disziplinen (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    aktiv TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'nur aktive Disziplinen erscheinen bei Erfassung/Autocomplete',
    hoch_ist_besser TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = hoeherer Wert ist besser (z.B. Weitsprung), 0 = tieferer Wert ist besser (z.B. Laufzeit)',
    UNIQUE KEY uniq_disziplinen_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS resultate (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teilnehmer_id INT UNSIGNED NOT NULL,
    name VARCHAR(200) NOT NULL COMMENT 'Namens-Snapshot zum Erfassungszeitpunkt (Denormalisierung)',
    disziplin VARCHAR(150) NOT NULL,
    resultat DECIMAL(10,2) NOT NULL,
    pruefer VARCHAR(100) NOT NULL,
    datum DATE NOT NULL,
    KEY idx_resultate_teilnehmer_id (teilnehmer_id),
    KEY idx_resultate_disziplin (disziplin),
    KEY idx_resultate_datum (datum),
    CONSTRAINT fk_resultate_teilnehmer FOREIGN KEY (teilnehmer_id) REFERENCES teilnehmer (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
