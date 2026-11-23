<?php

namespace App\Config;

/**
 * Disziplin-Darstellung: Symbol, Einheit je Semester (Winter/Sommer) und Zahlenformat.
 *
 * Einheiten (Spalten einheit_winter / einheit_sommer, optional):
 *  - «s» (auch «sek», «Sekunden») = Zeit: Eingabe als 80 oder 1:20, gespeichert in Sekunden, Anzeige ab 60 s als m:ss
 *  - «m» = Meter (2 Nachkommastellen)
 *  - «Stufe» = Stufenwertung (halbe Stufen möglich)
 *  - alles andere wird als Text hinter die Zahl gesetzt (z. B. «Sprünge», «Punkte»)
 */
final class DisciplineMeta {
    /** Symbol nach Stichwort im Disziplin-Namen (Reihenfolge = Priorität). */
    private const SYMBOL_KEYWORDS = [
        'hindernis' => 'd-hindernis',
        'schleuder' => 'd-schleuder',
        'kugel' => 'd-kugel',
        'seil' => 'd-seil',
        'beugehang' => 'd-beugehang',
        'klimm' => 'd-beugehang',
        'hang' => 'd-beugehang',
        'biathlon' => 'd-biathlon',
        'ziel' => 'd-biathlon',
        'balanc' => 'd-balance',
        'kletter' => 'd-klettern',
        'geschick' => 'd-geschick',
        'slalom' => 'd-geschick',
        'parcours' => 'd-geschick',
        'pendel' => 'shuttle',
        'ausdauer' => 'd-lauf10',
        'cooper' => 'd-lauf10',
        'min' => 'd-lauf10',
        'wurf' => 'd-ballwurf',
        'werfen' => 'd-ballwurf',
        'sprung' => 'd-weit',
        'springen' => 'd-weit',
        'sprint' => 'd-sprint',
        'lauf' => 'd-sprint',
    ];
    private const DEFAULT_SYMBOL = 'medal';

    public static function symbol(string $name, ?string $configured = null): string {
        $configured = trim((string)$configured);
        if ($configured !== '' && preg_match('/^[a-z0-9-]{1,40}$/', $configured)) {
            return $configured;
        }
        $n = self::normalize($name);
        // «60m Sprint», «Sprint 80 m»: gemessene Sprintzeit → Stoppuhr
        if (strpos($n, 'sprint') !== false && preg_match('/\d+\s*m\b/', $n)) {
            return 'd-sprint60';
        }
        foreach (self::SYMBOL_KEYWORDS as $keyword => $symbol) {
            if (strpos($n, $keyword) !== false) {
                return $symbol;
            }
        }

        return self::DEFAULT_SYMBOL;
    }

    /**
     * Einheit für ein Semester: w-JJJJ → Winter, s-JJJJ → Sommer; fehlt sie, gilt die andere Saison.
     */
    public static function unitFor(array $discipline, string $semesterKey): string {
        $winter = trim((string)($discipline['einheit_winter'] ?? ''));
        $summer = trim((string)($discipline['einheit_sommer'] ?? ''));
        $isWinter = strncmp($semesterKey, 'w-', 2) === 0;

        return $isWinter ? ($winter !== '' ? $winter : $summer) : ($summer !== '' ? $summer : $winter);
    }

    /**
     * Kategorien, in denen die Disziplin für die Rangliste zählt (Spalte kategorien, z.B. «U8/U10, U12»).
     * null = alle Kategorien. Unbekannte Einträge werden ignoriert.
     *
     * @return list<string>|null
     */
    public static function countedCategories(array $discipline): ?array {
        $raw = trim((string)($discipline['kategorien'] ?? ''));
        if ($raw === '') {
            return null;
        }
        $known = array_keys(RankingConfig::getCategories());
        $out = [];
        foreach (preg_split('/[,;]+/', $raw) as $part) {
            foreach ($known as $key) {
                if (strcasecmp(trim($part), $key) === 0) {
                    $out[] = $key;
                }
            }
        }

        return $out === [] ? null : array_values(array_unique($out));
    }

    /** Zählt die Disziplin in dieser Kategorie für die Rangliste? */
    public static function countsFor(array $discipline, string $categoryKey): bool {
        $cats = self::countedCategories($discipline);

        return $cats === null || in_array($categoryKey, $cats, true);
    }

    /** time | m | stufe | none | other */
    public static function unitKind(string $unit): string {
        $u = strtolower(trim($unit));
        if (in_array($u, ['s', 'sek', 'sek.', 'sekunde', 'sekunden'], true)) {
            return 'time';
        }
        if (in_array($u, ['m', 'meter'], true)) {
            return 'm';
        }
        if (in_array($u, ['stufe', 'stufen'], true)) {
            return 'stufe';
        }

        return $u === '' ? 'none' : 'other';
    }

    /** Kurze Einheit für Tabellenköpfe («s», «m», «Stufe», «Sprünge»). */
    public static function unitLabel(string $unit): string {
        switch (self::unitKind($unit)) {
            case 'time': return 's';
            case 'm': return 'm';
            case 'stufe': return 'Stufe';
            default: return trim($unit);
        }
    }

    /**
     * Wert für die Anzeige. $withUnit = false für Tabellen mit Einheit im Kopf.
     */
    public static function format($value, string $unit, bool $withUnit = true): string {
        if ($value === null || $value === '') {
            return '–';
        }
        $v = (float)$value;
        $kind = self::unitKind($unit);
        if ($kind === 'time') {
            if ($v >= 60) {
                $min = (int)floor($v / 60);
                $sec = round($v - $min * 60, 2);
                if ($sec >= 60) {
                    $min++;
                    $sec = 0.0;
                }
                $secText = self::trim($sec);
                if ($sec < 10) {
                    $secText = '0' . $secText;
                }

                return $min . ':' . $secText;
            }

            return self::trim($v) . ($withUnit ? ' s' : '');
        }
        if ($kind === 'm') {
            // ab 100 m (z.B. Ausdauerlauf) ohne Nachkommastellen
            return ($v >= 100 ? self::trim(round($v)) : number_format($v, 2, '.', '')) . ($withUnit ? ' m' : '');
        }
        if ($kind === 'stufe') {
            return ($withUnit ? 'Stufe ' : '') . self::trim($v);
        }
        if ($kind === 'other') {
            return self::trim($v) . ($withUnit ? ' ' . trim($unit) : '');
        }

        return self::trim($v);
    }

    /**
     * Eingabe in Zahl umwandeln: Komma oder Punkt; bei Zeit-Einheit auch Minuten:Sekunden (1:20, 1:20.5).
     * Liefert null bei ungültiger Eingabe. Gerundet auf 2 Nachkommastellen (DB: DECIMAL(10,2)).
     */
    public static function parse($raw, string $unit): ?float {
        if (is_int($raw) || is_float($raw)) {
            $v = (float)$raw;
        } else {
            $s = str_replace(',', '.', trim((string)$raw));
            if (self::unitKind($unit) === 'time' && preg_match('/^(\d{1,2}):(\d{1,2}(?:\.\d*)?)$/', $s, $m)) {
                if ((float)$m[2] >= 60) {
                    return null;
                }
                $v = (int)$m[1] * 60 + (float)$m[2];
            } elseif (preg_match('/^(\d+\.?\d*|\.\d+)$/', $s)) {
                $v = (float)$s;
            } else {
                return null;
            }
        }
        if (!is_finite($v) || $v < 0 || $v >= 100000000) {
            return null;
        }

        return round($v, 2);
    }

    /** Zahl ohne überflüssige Nullen: 3.50 → 3.5, 4.00 → 4. */
    public static function trim(float $v): string {
        $s = number_format($v, 2, '.', '');

        return strpos($s, '.') !== false ? rtrim(rtrim($s, '0'), '.') : $s;
    }

    private static function normalize(string $s): string {
        $s = mb_strtolower($s, 'UTF-8');

        return strtr($s, ['ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'é' => 'e', 'è' => 'e']);
    }
}
