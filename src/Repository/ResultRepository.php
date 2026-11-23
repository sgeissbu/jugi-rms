<?php

namespace App\Repository;

use App\Config\RankingConfig;
use PDO;

class ResultRepository {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Fügt ein neues Resultat ein und liefert die neue ID. $nameSnapshot entspricht dem aktuellen Anzeigenamen
     * aus teilnehmer (Denormalisierung). Das Datum kommt aus PHP (APP_TIMEZONE), nicht aus der DB-Uhr.
     */
    public function insert(int $teilnehmerId, string $nameSnapshot, string $discipline, float $resultat, string $pruefer): int {
        $sql = 'INSERT INTO resultate (teilnehmer_id, name, disziplin, resultat, pruefer, datum) VALUES (?, ?, ?, ?, ?, ?)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$teilnehmerId, $nameSnapshot, $discipline, $resultat, $pruefer, date('Y-m-d')]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Löscht einen eigenen Eintrag von heute («Rückgängig»). Liefert true, wenn etwas gelöscht wurde.
     */
    public function deleteOwnFromToday(int $id, string $pruefer): bool {
        $stmt = $this->pdo->prepare('DELETE FROM resultate WHERE id = ? AND pruefer = ? AND datum = ?');
        $stmt->execute([$id, $pruefer, date('Y-m-d')]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Alle Einträge von heute (neueste zuerst), für «Heute erfasst» und «heute schon …» in der Erfassung.
     *
     * @return list<array{id:int,teilnehmer_id:?int,name:string,disziplin:string,resultat:string,pruefer:string}>
     */
    public function getToday(): array {
        $stmt = $this->pdo->prepare('SELECT id, teilnehmer_id, name, disziplin, resultat, pruefer FROM resultate WHERE datum = ? ORDER BY id DESC');
        $stmt->execute([date('Y-m-d')]);

        return $stmt->fetchAll();
    }

    /**
     * Bestresultat pro Teilnehmer-ID und Disziplin im Zeitraum (alte Einträge ohne ID über den Namen).
     *
     * @return list<array{teilnehmer_id:int,disziplin:string,best_resultat:string}>
     */
    public function getBestByParticipantInPeriod(string $dateStart, string $dateEnd): array {
        $sql = "
            SELECT t.id AS teilnehmer_id, r.disziplin,
                CASE WHEN d.hoch_ist_besser = 1 THEN MAX(r.resultat) ELSE MIN(r.resultat) END AS best_resultat
            FROM teilnehmer t
            JOIN resultate r ON (
                r.teilnehmer_id = t.id
                OR (r.teilnehmer_id IS NULL AND r.name = CONCAT(t.vorname, ' ', t.nachname))
            )
            JOIN disziplinen d ON r.disziplin = d.name
            WHERE r.datum IS NOT NULL AND DATE(r.datum) BETWEEN ? AND ?
            GROUP BY t.id, r.disziplin, d.hoch_ist_besser
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$dateStart, $dateEnd]);

        return $stmt->fetchAll();
    }

    /**
     * Ältestes Messdatum (für Semester-Dropdown-Grenze). Nur Zeilen mit gesetztem datum.
     */
    public function getMinResultDatum(): ?string {
        $sql = 'SELECT MIN(DATE(datum)) FROM resultate WHERE datum IS NOT NULL';
        $stmt = $this->pdo->query($sql);
        $v = $stmt->fetchColumn(0);
        if ($v === false || $v === null || $v === '') {
            return null;
        }

        return (string)$v;
    }

    /**
     * Holt die besten Resultate pro Teilnehmer und Disziplin (nur aktuelles Semester, siehe RankingConfig).
     */
    public function getBestResultsPerParticipant(): array {
        $bounds = RankingConfig::getCurrentSemesterDateBounds();
        $sql = "
            SELECT
                COALESCE(CONCAT(t.vorname, ' ', t.nachname), r.name) AS teilnehmer_name,
                r.disziplin,
                CASE
                    WHEN d.hoch_ist_besser = 1 THEN MAX(r.resultat)
                    ELSE MIN(r.resultat)
                END AS best_resultat
            FROM
                resultate r
            JOIN
                disziplinen d ON r.disziplin = d.name
            LEFT JOIN
                teilnehmer t ON r.teilnehmer_id = t.id
            WHERE
                r.datum IS NOT NULL
                AND DATE(r.datum) BETWEEN ? AND ?
            GROUP BY
                COALESCE(CONCAT(t.vorname, ' ', t.nachname), r.name), r.disziplin, d.hoch_ist_besser
            ORDER BY
                COALESCE(CONCAT(t.vorname, ' ', t.nachname), r.name) ASC, r.disziplin ASC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$bounds['start'], $bounds['end']]);
        return $stmt->fetchAll();
    }

    /**
     * Eindeutige Disziplin-Namen aus resultate im Zeitraum (für vergangene Semester / Spaltenliste).
     *
     * @return list<string>
     */
    public function getDistinctDisciplinesInDateRange(string $dateStart, string $dateEnd): array {
        $sql = "
            SELECT DISTINCT r.disziplin AS name
            FROM resultate r
            WHERE r.datum IS NOT NULL
              AND DATE(r.datum) BETWEEN ? AND ?
            ORDER BY r.disziplin ASC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$dateStart, $dateEnd]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Holt die besten Resultate für eine bestimmte Gruppe (Geschlecht + Jahrgangsbereich),
     * gefiltert auf den angegebenen Datumsbereich (Semester).
     *
     * @param bool $restrictToActiveDisciplines true = nur disziplinen.aktiv = 1 (laufendes Semester)
     */
    public function getBestResultsForGroup(
        string $gender,
        int $minYear,
        int $maxYear,
        string $dateStart,
        string $dateEnd,
        bool $restrictToActiveDisciplines
    ): array {
        $aktivClause = $restrictToActiveDisciplines ? ' AND d.aktiv = 1' : '';
        $sql = "
            SELECT
                t.id,
                CONCAT(t.vorname, ' ', t.nachname) AS teilnehmer_name,
                r.disziplin,
                d.hoch_ist_besser,
                CASE
                    WHEN d.hoch_ist_besser = 1 THEN MAX(r.resultat)
                    ELSE MIN(r.resultat)
                END AS best_resultat
            FROM
                teilnehmer t
            JOIN
                resultate r ON (
                    r.teilnehmer_id = t.id
                    OR (r.teilnehmer_id IS NULL AND r.name = CONCAT(t.vorname, ' ', t.nachname))
                )
            JOIN
                disziplinen d ON r.disziplin = d.name{$aktivClause}
            WHERE
                t.geschlecht = ?
                AND t.jahrgang BETWEEN ? AND ?
                AND r.datum IS NOT NULL
                AND DATE(r.datum) BETWEEN ? AND ?
            GROUP BY
                t.id, teilnehmer_name, r.disziplin, d.hoch_ist_besser
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$gender, $minYear, $maxYear, $dateStart, $dateEnd]);
        return $stmt->fetchAll();
    }
}
