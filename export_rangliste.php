<?php
// export_rangliste.php
require 'config.php';

use App\Config\RankingConfig;
use App\Repository\ParticipantRepository;
use App\Repository\DisciplineRepository;
use App\Repository\ResultRepository;
use App\Service\AuthService;
use App\Service\RankingService;

AuthService::requireLogin();

$participantRepo = new ParticipantRepository($pdo);
$disciplineRepo = new DisciplineRepository($pdo);
$resultRepo = new ResultRepository($pdo);
$rankingService = new RankingService($participantRepo, $disciplineRepo, $resultRepo);

$oldest_result_datum = $resultRepo->getMinResultDatum();
$semester_options = RankingConfig::getSemesterSelectOptionsFromOldestResultDate($oldest_result_datum);
$selected_semester_key = RankingConfig::resolveSemesterFromRequest($_GET['semester'] ?? null, $semester_options);
$semester_bounds = RankingConfig::getSemesterDateBoundsForKey($selected_semester_key);
$semester_ref_date = RankingConfig::getSemesterReferenceDateForCategories($selected_semester_key);

$selected_category_key = RankingConfig::normalizeCategory($_GET['kategorie'] ?? null);
$gender = RankingConfig::resolveGender($_GET['geschlecht'] ?? null);
$birthYearRange = RankingConfig::getBirthYearRange($selected_category_key, $semester_ref_date);

$selected_geschlecht = $gender['label'];
$geschlecht_label = $gender['label'];
$geschlecht_filter = $gender['filter'];
$min_birth_year = $birthYearRange['min_birth_year'];
$max_birth_year = $birthYearRange['max_birth_year'];
$file_title = 'Rangliste_' . $selected_category_key . '_' . $geschlecht_label . '_' . str_replace(['/', ' '], '-', RankingConfig::getSemesterLabel($selected_semester_key));

try {
    $rankingData = $rankingService->getRanking(
        $geschlecht_filter,
        $min_birth_year,
        $max_birth_year,
        $semester_bounds['start'],
        $semester_bounds['end'],
        $selected_category_key
    );
    if (empty($rankingData['participants'])) exit("Keine Teilnehmer gefunden.");

    $all_participants_names = $rankingData['participants'];
    $results_map = $rankingData['results_map'];
    $all_disciplines_names = $rankingData['discipline_names'];

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $file_title . '.csv"');
    $output = fopen('php://output', 'w');
    fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    $header = ['Platz', 'Name', 'Jahrgang'];
    foreach ($all_disciplines_names as $disc_name) {
        $header[] = $disc_name . ' (Resultat)';
        $header[] = $disc_name . ' (Punkte)';
    }
    $header[] = 'Gesamtpunkte';
    fputcsv($output, $header, ';', '"', '\\');

    $rank = 0; $last_points = null;
    foreach ($all_participants_names as $i => $participant_name) {
        $current_points = $results_map[$participant_name]['total_points'];
        if ($current_points !== $last_points) { $rank = $i + 1; $last_points = $current_points; }
        
        $row = [$rank, $participant_name, $results_map[$participant_name]['jahrgang']];
        foreach ($all_disciplines_names as $discipline_name) {
            $result = $results_map[$participant_name]['results'][$discipline_name] ?? 'N/A';
            $points = $results_map[$participant_name]['discipline_points'][$discipline_name] ?? 0;
            $row[] = ($result !== 'N/A') ? number_format($result, 2, '.', '') : 'Fehlend';
            $row[] = $points;
        }
        $row[] = $current_points;
        fputcsv($output, $row, ';', '"', '\\');
    }
    fclose($output);
} catch (Exception $e) {
    exit('Fehler beim Export: ' . $e->getMessage());
}
