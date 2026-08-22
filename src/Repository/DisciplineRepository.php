<?php

namespace App\Repository;

use PDO;

class DisciplineRepository {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Überprüft, ob ein gegebener Wert in einer bestimmten Datenbanktabelle und Spalte existiert.
     */
    public function checkValueExists(string $table, string $column, string $value): bool {
        // Hinweis: Tabellen- und Spaltennamen sollten validiert sein, um SQL-Injection zu vermeiden
        // Hier gehen wir davon aus, dass sie intern festgelegt werden.
        $sql = "SELECT COUNT(*) FROM $table WHERE $column = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$value]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Spezifischer Check für Disziplinen
     */
    public function exists(string $name): bool {
        return $this->checkValueExists('disziplinen', 'name', $name);
    }

    /**
     * Ob die Disziplin existiert und aktiv ist (Erfassung / Autocomplete).
     */
    public function existsActive(string $name): bool {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM disziplinen WHERE name = ? AND aktiv = 1');
        $stmt->execute([$name]);

        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Holt alle Disziplinen mit ihren Metadaten.
     */
    public function getAllWithMetadata(): array {
        $stmt = $this->pdo->query("SELECT name, hoch_ist_besser FROM disziplinen ORDER BY name ASC");
        return $stmt->fetchAll();
    }

    /**
     * Sucht Disziplinen nach einem Begriff.
     *
     * @param bool $activeOnly nur Zeilen mit aktiv = 1 (z. B. Autocomplete bei neuer Resultat-Erfassung)
     */
    public function search(string $term, int $limit = 10, bool $activeOnly = false): array {
        $searchTerm = '%' . $term . '%';
        $sql = 'SELECT name FROM disziplinen WHERE name LIKE ?';
        if ($activeOnly) {
            $sql .= ' AND aktiv = 1';
        }
        $sql .= ' ORDER BY name ASC LIMIT ' . (int)$limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$searchTerm]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Holt alle aktiven Disziplinen.
     */
    public function getActive(): array {
        $stmt = $this->pdo->query("SELECT name, hoch_ist_besser FROM disziplinen WHERE aktiv = 1 ORDER BY name ASC");
        return $stmt->fetchAll();
    }

    /**
     * Metadaten zu einer Liste von Namen (Reihenfolge wie übergeben).
     *
     * @param list<string> $names
     * @return array<string, array{name:string,hoch_ist_besser:int}>
     */
    public function getMetadataByNames(array $names): array {
        if ($names === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($names), '?'));
        $sql = "SELECT name, hoch_ist_besser FROM disziplinen WHERE name IN ($placeholders)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($names));
        $byName = [];
        foreach ($stmt->fetchAll() as $row) {
            $byName[$row['name']] = $row;
        }

        return $byName;
    }
}
