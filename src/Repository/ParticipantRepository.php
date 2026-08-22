<?php

namespace App\Repository;

use PDO;

class ParticipantRepository {
    private $pdo;

    /** Mindestens ein Trainingstag = 1 → für Erfassung / Ranglisten „aktiv“. */
    private const SQL_ACTIVE_TRAINING_DAY = '(montag = 1 OR dienstag = 1 OR mittwoch = 1 OR donnerstag = 1 OR freitag = 1)';

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Überprüft, ob der übermittelte Name ('Vorname Nachname') in der Tabelle 'teilnehmer' existiert.
     */
    public function checkFullNameExists(string $fullName): bool {
        $sql = "SELECT COUNT(*) FROM teilnehmer WHERE CONCAT(vorname, ' ', nachname) = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$fullName]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Wie {@see checkFullNameExists}, aber nur Teilnehmer mit mindestens einem Trainingstag = 1.
     */
    public function checkFullNameExistsActive(string $fullName): bool {
        $sql = 'SELECT COUNT(*) FROM teilnehmer WHERE CONCAT(vorname, \' \', nachname) = ? AND ' . self::SQL_ACTIVE_TRAINING_DAY;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$fullName]);

        return $stmt->fetchColumn() > 0;
    }

    /**
     * Holt alle Teilnehmer als vollständige Namen.
     *
     * @param bool $activeOnly nur Teilnehmer mit mindestens einem Trainingstag = 1
     */
    public function getAllFullNames(bool $activeOnly = false): array {
        $sql = '
            SELECT CONCAT(vorname, \' \', nachname) AS full_name
            FROM teilnehmer';
        if ($activeOnly) {
            $sql .= ' WHERE ' . self::SQL_ACTIVE_TRAINING_DAY;
        }
        $sql .= ' ORDER BY vorname ASC, nachname ASC';
        $stmt = $this->pdo->query($sql);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Sucht Teilnehmer nach einem Begriff (für Autocomplete).
     *
     * @param bool $activeOnly nur Teilnehmer mit mindestens einem Trainingstag = 1
     */
    public function search(string $term, int $limit = 10, bool $activeOnly = false): array {
        $searchTerm = '%' . $term . '%';
        $sql = '
            SELECT CONCAT(vorname, \' \', nachname) AS full_name
            FROM teilnehmer
            WHERE (vorname LIKE ? OR nachname LIKE ? OR CONCAT(vorname, \' \', nachname) LIKE ?)';
        if ($activeOnly) {
            $sql .= ' AND ' . self::SQL_ACTIVE_TRAINING_DAY;
        }
        $sql .= '
            ORDER BY nachname ASC, vorname ASC
            LIMIT ' . (int)$limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Wie {@see search}, liefert aber id und Anzeigename (für Autocomplete mit Teilnehmer-ID).
     *
     * @return list<array{id:int,label:string}>
     */
    public function searchWithIds(string $term, int $limit = 10, bool $activeOnly = false): array {
        $searchTerm = '%' . $term . '%';
        $sql = '
            SELECT id, CONCAT(vorname, \' \', nachname) AS label
            FROM teilnehmer
            WHERE (vorname LIKE ? OR nachname LIKE ? OR CONCAT(vorname, \' \', nachname) LIKE ?)';
        if ($activeOnly) {
            $sql .= ' AND ' . self::SQL_ACTIVE_TRAINING_DAY;
        }
        $sql .= '
            ORDER BY nachname ASC, vorname ASC
            LIMIT ' . (int)$limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Voller Name zum Zeitpunkt der Abfrage (Snapshot für resultate.name).
     */
    public function getFullNameById(int $id): ?string {
        $sql = 'SELECT CONCAT(vorname, \' \', nachname) FROM teilnehmer WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $v = $stmt->fetchColumn(0);
        if ($v === false || $v === null || $v === '') {
            return null;
        }

        return (string)$v;
    }

    /**
     * Existiert die ID und ist der Teilnehmer „aktiv“ (mind. ein Trainingstag)?
     */
    public function isActiveParticipantId(int $id): bool {
        $sql = 'SELECT COUNT(*) FROM teilnehmer WHERE id = ? AND ' . self::SQL_ACTIVE_TRAINING_DAY;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);

        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Holt Teilnehmer nach Geschlecht und Jahrgangsbereich (für Ranglisten).
     */
    public function getByGenderAndYearRange(string $gender, int $minYear, int $maxYear): array {
        $sql = "
            SELECT CONCAT(vorname, ' ', nachname) AS teilnehmer_name, jahrgang
            FROM teilnehmer
            WHERE geschlecht = ?
            AND jahrgang BETWEEN ? AND ?
            AND " . self::SQL_ACTIVE_TRAINING_DAY . "
            ORDER BY nachname ASC, vorname ASC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$gender, $minYear, $maxYear]);
        return $stmt->fetchAll();
    }

    /**
     * Gleiche Gruppe wie getByGenderAndYearRange, aber ohne Trainingstage-Filter:
     * nur Teilnehmer, zu denen im Zeitraum mindestens ein Resultat existiert (für vergangene Semester).
     *
     * @return list<array{teilnehmer_name:string,jahrgang:int|string}>
     */
    public function getByGenderYearRangeWithResultsInPeriod(
        string $gender,
        int $minYear,
        int $maxYear,
        string $dateStart,
        string $dateEnd
    ): array {
        $sql = "
            SELECT DISTINCT CONCAT(t.vorname, ' ', t.nachname) AS teilnehmer_name, t.jahrgang
            FROM teilnehmer t
            INNER JOIN resultate r ON (
                r.teilnehmer_id = t.id
                OR (r.teilnehmer_id IS NULL AND r.name = CONCAT(t.vorname, ' ', t.nachname))
            )
            WHERE t.geschlecht = ?
            AND t.jahrgang BETWEEN ? AND ?
            AND r.datum IS NOT NULL
            AND DATE(r.datum) BETWEEN ? AND ?
            ORDER BY t.nachname ASC, t.vorname ASC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$gender, $minYear, $maxYear, $dateStart, $dateEnd]);
        return $stmt->fetchAll();
    }
}
