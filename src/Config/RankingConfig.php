<?php

namespace App\Config;

use DateTimeImmutable;
use DateTimeInterface;

final class RankingConfig {
    private const DEFAULT_CATEGORY = 'U12';

    /**
     * Zentrale Kategoriedefinition für Ranglisten und Exporte.
     *
     * @return array<string, array{min_age:int,max_age:int,label:string}>
     */
    public static function getCategories(): array {
        return [
            'U8/U10' => ['min_age' => 6, 'max_age' => 9, 'label' => 'U8/U10 (6-9 J.)'],
            'U12' => ['min_age' => 10, 'max_age' => 11, 'label' => 'U12 (10/11 J.)'],
            'U14' => ['min_age' => 12, 'max_age' => 13, 'label' => 'U14 (12/13 J.)'],
            'U16/U18' => ['min_age' => 14, 'max_age' => 17, 'label' => 'U16/U18 (14-17 J.)'],
        ];
    }

    /**
     * Kategorie eines Jahrgangs zum Referenzdatum (wie in den Ranglisten); null = in keiner Kategorie.
     */
    public static function categoryForBirthYear(int $birthYear, ?DateTimeInterface $date = null): ?string {
        $age = self::getReferenceYear($date) - $birthYear;
        foreach (self::getCategories() as $key => $c) {
            if ($age >= $c['min_age'] && $age <= $c['max_age']) {
                return $key;
            }
        }

        return null;
    }

    public static function getReferenceYear(?DateTimeInterface $date = null): int {
        $referenceDate = $date ?? new DateTimeImmutable();
        $year = (int)$referenceDate->format('Y');
        $month = (int)$referenceDate->format('n');

        return $month >= 10 ? $year + 1 : $year;
    }

    public static function normalizeCategory(?string $categoryKey): string {
        $categories = self::getCategories();
        $candidate = $categoryKey ?? self::DEFAULT_CATEGORY;

        return array_key_exists($candidate, $categories) ? $candidate : self::DEFAULT_CATEGORY;
    }

    /**
     * @return array{label:string,filter:string}
     */
    public static function resolveGender(?string $selected): array {
        $label = $selected === 'Mädchen' ? 'Mädchen' : 'Buben';

        return [
            'label' => $label,
            'filter' => $label === 'Mädchen' ? 'w' : 'm',
        ];
    }

    /**
     * @return array{min_birth_year:int,max_birth_year:int}
     */
    public static function getBirthYearRange(string $categoryKey, ?DateTimeInterface $date = null): array {
        $categories = self::getCategories();
        $normalizedCategory = self::normalizeCategory($categoryKey);
        $ageLimits = $categories[$normalizedCategory];
        $referenceYear = self::getReferenceYear($date);

        return [
            'min_birth_year' => $referenceYear - $ageLimits['max_age'],
            'max_birth_year' => $referenceYear - $ageLimits['min_age'],
        ];
    }

    public static function getSemesterTitle(?DateTimeInterface $date = null): string {
        $referenceDate = $date ?? new DateTimeImmutable();
        $month = (int)$referenceDate->format('n');
        $day = (int)$referenceDate->format('j');

        if ($month >= 10 || $month <= 3 || ($month === 4 && $day <= 10)) {
            return 'Ranglisten Wintersemester';
        }

        return 'Ranglisten Sommersemester';
    }

    /**
     * Inklusive Datums-Grenzen des Semesters, das den Referenzzeitpunkt enthält.
     * Logik entspricht {@see self::getSemesterTitle()}: Winter 1.10.–10.4., Sommer 11.4.–30.9.
     *
     * @return array{start:string,end:string} 'Y-m-d'
     */
    /**
     * True, wenn die Grenzen exakt dem aktuellen Semester entsprechen (Vergleich der Y-m-d-Strings).
     */
    public static function isCurrentSemesterDateRange(string $start, string $end): bool {
        $c = self::getCurrentSemesterDateBounds();

        return $c['start'] === $start && $c['end'] === $end;
    }

    public static function getCurrentSemesterDateBounds(?DateTimeInterface $date = null): array {
        $ref = $date ?? new DateTimeImmutable();
        $y = (int)$ref->format('Y');
        $month = (int)$ref->format('n');
        $day = (int)$ref->format('j');

        $isWinter = $month >= 10 || $month <= 3 || ($month === 4 && $day <= 10);

        if ($isWinter) {
            if ($month >= 10) {
                $start = new DateTimeImmutable(sprintf('%d-10-01', $y));
                $end = new DateTimeImmutable(sprintf('%d-04-10', $y + 1));
            } else {
                $start = new DateTimeImmutable(sprintf('%d-10-01', $y - 1));
                $end = new DateTimeImmutable(sprintf('%d-04-10', $y));
            }
        } else {
            $start = new DateTimeImmutable(sprintf('%d-04-11', $y));
            $end = new DateTimeImmutable(sprintf('%d-09-30', $y));
        }

        return [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
        ];
    }

    /**
     * Semester-Schlüssel: w-JJJJ = Winter ab 1.10. JJJJ, s-JJJJ = Sommer ab 11.4. JJJJ.
     */
    public static function getSemesterKeyForDate(?DateTimeInterface $date = null): string {
        $ref = $date ?? new DateTimeImmutable();
        $y = (int)$ref->format('Y');
        $month = (int)$ref->format('n');
        $day = (int)$ref->format('j');

        $isWinter = $month >= 10 || $month <= 3 || ($month === 4 && $day <= 10);

        if ($isWinter) {
            return $month >= 10 ? 'w-' . $y : 'w-' . ($y - 1);
        }

        return 's-' . $y;
    }

    /**
     * @return array{start:string,end:string} 'Y-m-d'
     */
    public static function getSemesterDateBoundsForKey(string $key): array {
        if (preg_match('/^w-(\d{4})$/', $key, $m)) {
            $y = (int)$m[1];
            $start = new DateTimeImmutable(sprintf('%d-10-01', $y));
            $end = new DateTimeImmutable(sprintf('%d-04-10', $y + 1));

            return ['start' => $start->format('Y-m-d'), 'end' => $end->format('Y-m-d')];
        }
        if (preg_match('/^s-(\d{4})$/', $key, $m)) {
            $y = (int)$m[1];
            $start = new DateTimeImmutable(sprintf('%d-04-11', $y));
            $end = new DateTimeImmutable(sprintf('%d-09-30', $y));

            return ['start' => $start->format('Y-m-d'), 'end' => $end->format('Y-m-d')];
        }

        return self::getCurrentSemesterDateBounds();
    }

    public static function normalizeSemesterQuery(?string $semester): string {
        $semester = trim((string)$semester);
        if ($semester !== '' && preg_match('/^[ws]-\d{4}$/', $semester)) {
            return $semester;
        }

        return self::getSemesterKeyForDate();
    }

    public static function getSemesterLabel(string $key): string {
        if (preg_match('/^w-(\d{4})$/', $key, $m)) {
            $y = (int)$m[1];

            return 'WS ' . $y . '/' . substr((string)($y + 1), -2);
        }
        if (preg_match('/^s-(\d{4})$/', $key, $m)) {
            return 'SoSe ' . $m[1];
        }

        return '';
    }

    /**
     * Referenzdatum für Alterskategorien (Jahrgangsgrenzen) an gewähltes Semester koppeln.
     */
    public static function getSemesterReferenceDateForCategories(string $key): DateTimeImmutable {
        $bounds = self::getSemesterDateBoundsForKey($key);

        return new DateTimeImmutable($bounds['end']);
    }

    public static function previousSemesterKey(string $key): string {
        if (preg_match('/^w-(\d{4})$/', $key, $m)) {
            $y = (int)$m[1];

            return 's-' . $y;
        }
        if (preg_match('/^s-(\d{4})$/', $key, $m)) {
            $y = (int)$m[1];

            return 'w-' . ($y - 1);
        }

        return self::getSemesterKeyForDate();
    }

    /**
     * Neuestes Semester zuerst (für Dropdown).
     *
     * @return list<array{value:string,label:string}>
     */
    public static function getSemesterSelectOptions(int $count = 14): array {
        $key = self::getSemesterKeyForDate();
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $out[] = [
                'value' => $key,
                'label' => self::getSemesterLabel($key),
            ];
            $key = self::previousSemesterKey($key);
        }

        return $out;
    }

    /**
     * Semester-Auswahl von aktuell zurück bis zum Semester, das das älteste Resultat-Datum enthält.
     * Keine Einträge in resultate: nur das aktuelle Semester.
     *
     * @return list<array{value:string,label:string}>
     */
    public static function getSemesterSelectOptionsFromOldestResultDate(?string $minDateYmd): array {
        $currentKey = self::getSemesterKeyForDate();

        if ($minDateYmd === null || $minDateYmd === '') {
            return [
                [
                    'value' => $currentKey,
                    'label' => self::getSemesterLabel($currentKey),
                ],
            ];
        }

        try {
            $d = new DateTimeImmutable($minDateYmd);
        } catch (\Exception $e) {
            return [
                [
                    'value' => $currentKey,
                    'label' => self::getSemesterLabel($currentKey),
                ],
            ];
        }

        $oldestKey = self::getSemesterKeyForDate($d);
        $oldestStart = self::getSemesterDateBoundsForKey($oldestKey)['start'];
        $currentStart = self::getSemesterDateBoundsForKey($currentKey)['start'];

        if ($oldestStart > $currentStart) {
            return [
                [
                    'value' => $currentKey,
                    'label' => self::getSemesterLabel($currentKey),
                ],
            ];
        }

        $out = [];
        $key = $currentKey;
        for ($i = 0; $i < 200; $i++) {
            $out[] = [
                'value' => $key,
                'label' => self::getSemesterLabel($key),
            ];
            if ($key === $oldestKey) {
                break;
            }
            $prev = self::previousSemesterKey($key);
            if ($prev === $key) {
                break;
            }
            $key = $prev;
        }

        return $out;
    }

    /**
     * @param list<array{value:string,label:string}> $options
     */
    public static function resolveSemesterFromRequest(?string $requested, array $options): string {
        $allowed = array_column($options, 'value');
        if ($allowed === []) {
            return self::getSemesterKeyForDate();
        }

        $req = trim((string)$requested);
        if ($req !== '' && preg_match('/^[ws]-\d{4}$/', $req) && in_array($req, $allowed, true)) {
            return $req;
        }

        return $allowed[0];
    }
}
