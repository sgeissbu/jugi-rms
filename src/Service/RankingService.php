<?php

namespace App\Service;

use App\Config\RankingConfig;
use App\Repository\ParticipantRepository;
use App\Repository\DisciplineRepository;
use App\Repository\ResultRepository;

class RankingService {
    private $participantRepo;
    private $disciplineRepo;
    private $resultRepo;

    public function __construct(ParticipantRepository $participantRepo, DisciplineRepository $disciplineRepo, ResultRepository $resultRepo) {
        $this->participantRepo = $participantRepo;
        $this->disciplineRepo = $disciplineRepo;
        $this->resultRepo = $resultRepo;
    }

    /**
     * Berechnet die Rangliste für eine bestimmte Gruppe.
     * $dateStart/$dateEnd: Semester-Zeitraum (resultate.datum), inkl. Grenzen.
     */
    public function getRanking(string $gender, int $minYear, int $maxYear, string $dateStart, string $dateEnd): array {
        $isCurrentSemester = RankingConfig::isCurrentSemesterDateRange($dateStart, $dateEnd);
        if ($isCurrentSemester) {
            $allParticipantsData = $this->participantRepo->getByGenderAndYearRange($gender, $minYear, $maxYear);
        } else {
            $allParticipantsData = $this->participantRepo->getByGenderYearRangeWithResultsInPeriod(
                $gender,
                $minYear,
                $maxYear,
                $dateStart,
                $dateEnd
            );
        }

        $resultsMap = [];
        $participantNames = [];
        foreach ($allParticipantsData as $p) {
            $name = $p['teilnehmer_name'];
            $resultsMap[$name] = [
                'results' => [],
                'discipline_points' => [],
                'total_points' => 0,
                'jahrgang' => $p['jahrgang']
            ];
            $participantNames[] = $name;
        }

        if (empty($participantNames)) return [];
        $bestResultsRaw = $this->resultRepo->getBestResultsForGroup(
            $gender,
            $minYear,
            $maxYear,
            $dateStart,
            $dateEnd,
            $isCurrentSemester
        );

        if ($isCurrentSemester) {
            $disciplineRows = $this->disciplineRepo->getActive();
            $disciplineNames = array_column($disciplineRows, 'name');
            $hochByDisc = [];
            foreach ($disciplineRows as $dr) {
                $hochByDisc[$dr['name']] = (int)$dr['hoch_ist_besser'];
            }
        } else {
            $disciplineNames = $this->resultRepo->getDistinctDisciplinesInDateRange($dateStart, $dateEnd);
            $meta = $this->disciplineRepo->getMetadataByNames($disciplineNames);
            $hochByDisc = [];
            foreach ($disciplineNames as $dn) {
                $hochByDisc[$dn] = isset($meta[$dn]) ? (int)$meta[$dn]['hoch_ist_besser'] : 1;
            }
        }

        $disciplineResults = [];

        foreach ($bestResultsRaw as $row) {
            $name = $row['teilnehmer_name'];
            $discName = $row['disziplin'];

            if (isset($resultsMap[$name])) {
                $resultsMap[$name]['results'][$discName] = (float)$row['best_resultat'];
            }

            if (!in_array($discName, $disciplineNames, true)) {
                continue;
            }

            $disciplineResults[$discName][] = [
                'name' => $name,
                'result' => (float)$row['best_resultat'],
                'hoch_ist_besser' => (int)$row['hoch_ist_besser'],
            ];
        }

        // Nur Disziplinen, bei denen mindestens ein Teilnehmer dieser Gruppe ein Resultat hat
        $disciplineNames = array_values(array_filter($disciplineNames, function ($dn) use ($disciplineResults) {
            return !empty($disciplineResults[$dn]);
        }));

        $disciplines = [];
        foreach ($disciplineNames as $dn) {
            $disciplines[] = ['name' => $dn, 'hoch_ist_besser' => $hochByDisc[$dn]];
        }

        $maxRankPointsPerDiscipline = [];
        foreach ($disciplineNames as $discName) {
            $results = $disciplineResults[$discName];
            $hochIstBesser = $results[0]['hoch_ist_besser'];
            usort($results, function($a, $b) use ($hochIstBesser) {
                if ($a['result'] == $b['result']) return 0;
                return ($hochIstBesser == 1) ? (($a['result'] < $b['result']) ? 1 : -1) : (($a['result'] > $b['result']) ? 1 : -1);
            });

            $currentPoints = 0;
            $lastResult = null;
            $maxPointsForDisc = 0;
            foreach ($results as $i => $row) {
                $currentRank = $i + 1;
                if ($row['result'] !== $lastResult) {
                    $currentPoints = $currentRank;
                    $lastResult = $row['result'];
                }
                $resultsMap[$row['name']]['discipline_points'][$discName] = $currentPoints;
                $maxPointsForDisc = max($maxPointsForDisc, $currentPoints);
            }
            $maxRankPointsPerDiscipline[$discName] = $maxPointsForDisc + 1;
        }

        foreach ($participantNames as $name) {
            foreach ($disciplineNames as $discName) {
                $pts = $resultsMap[$name]['discipline_points'][$discName] ?? null;
                if ($pts === null) {
                    $penalty = $maxRankPointsPerDiscipline[$discName] ?? (count($participantNames) + 1);
                    $resultsMap[$name]['discipline_points'][$discName] = $penalty;
                    $resultsMap[$name]['total_points'] += $penalty;
                } else {
                    $resultsMap[$name]['total_points'] += $pts;
                }
            }
        }

        usort($participantNames, function($a, $b) use ($resultsMap) {
            $ptsA = $resultsMap[$a]['total_points'];
            $ptsB = $resultsMap[$b]['total_points'];
            if ($ptsA == $ptsB) return strcmp($a, $b);
            return ($ptsA > $ptsB) ? 1 : -1;
        });

        return [
            'participants' => $participantNames,
            'results_map' => $resultsMap,
            'disciplines' => $disciplines,
            'discipline_names' => $disciplineNames
        ];
    }
}
