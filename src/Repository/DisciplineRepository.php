<?php

namespace App\Repository;

use PDO;

class DisciplineRepository {
    private $pdo;

    /** Erlaubte Tabelle/Spalte-Kombinationen für {@see checkValueExists}. */
    private const ALLOWED_COLUMNS = [
        'disziplinen' => ['name'],
    ];

    /** Optionale Spalten aus Migration 001; fehlen sie, werden sie als NULL geliefert. */
    private const OPTIONAL_COLUMNS = ['symbol', 'einheit_winter', 'einheit_sommer', 'kategorien'];
    /** @var array<string,bool>|null Cache: welche optionalen Spalten existieren */
    private static $existing = null;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Überprüft, ob ein gegebener Wert in einer bestimmten Datenbanktabelle und Spalte existiert.
     * Tabelle/Spalte werden gegen eine Allowlist geprüft, da sie direkt interpoliert werden (nicht parametrisierbar in PDO).
     */
    public function checkValueExists(string $table, string $column, string $value): bool {
        if (!isset(self::ALLOWED_COLUMNS[$table]) || !in_array($column, self::ALLOWED_COLUMNS[$table], true)) {
            throw new \InvalidArgumentException('Ungültige Tabelle/Spalte für checkValueExists().');
        }
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
     * Ob die Disziplin existiert und aktiv ist (Erfassung).
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
        $stmt = $this->pdo->query('SELECT ' . $this->columns() . ' FROM disziplinen ORDER BY name ASC');
        return $stmt->fetchAll();
    }

    /**
     * Holt alle aktiven Disziplinen (inkl. Symbol/Einheiten, falls Migration 001 eingespielt ist).
     */
    public function getActive(): array {
        $stmt = $this->pdo->query('SELECT ' . $this->columns() . ' FROM disziplinen WHERE aktiv = 1 ORDER BY name ASC');
        return $stmt->fetchAll();
    }

    /**
     * Eine aktive Disziplin nach Name (für die Erfassung).
     */
    public function getActiveByName(string $name): ?array {
        $stmt = $this->pdo->prepare('SELECT ' . $this->columns() . ' FROM disziplinen WHERE name = ? AND aktiv = 1');
        $stmt->execute([$name]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Metadaten zu einer Liste von Namen (Reihenfolge wie übergeben).
     *
     * @param list<string> $names
     * @return array<string, array{name:string,hoch_ist_besser:int,symbol:?string,einheit_winter:?string,einheit_sommer:?string}>
     */
    public function getMetadataByNames(array $names): array {
        if ($names === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($names), '?'));
        $sql = 'SELECT ' . $this->columns() . " FROM disziplinen WHERE name IN ($placeholders)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($names));
        $byName = [];
        foreach ($stmt->fetchAll() as $row) {
            $byName[$row['name']] = $row;
        }

        return $byName;
    }

    /**
     * Spaltenliste; ohne Migration 001 werden die neuen Felder als NULL geliefert.
     */
    private function columns(): string {
        if (self::$existing === null) {
            self::$existing = [];
            try {
                foreach ($this->pdo->query('SHOW COLUMNS FROM disziplinen')->fetchAll() as $col) {
                    self::$existing[$col['Field']] = true;
                }
            } catch (\PDOException $e) {
                // ohne Rechte für SHOW COLUMNS: optionale Spalten als NULL
            }
        }
        $cols = ['name', 'hoch_ist_besser'];
        foreach (self::OPTIONAL_COLUMNS as $c) {
            $cols[] = isset(self::$existing[$c]) ? $c : "NULL AS $c";
        }

        return implode(', ', $cols);
    }
}
