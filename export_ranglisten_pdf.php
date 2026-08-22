<?php
// export_ranglisten_pdf.php
require 'config.php';

use App\Config\BrandConfig;
use App\Config\RankingConfig;
use App\Repository\ParticipantRepository;
use App\Repository\DisciplineRepository;
use App\Repository\ResultRepository;
use App\Service\AuthService;
use App\Service\RankingService;
use Dompdf\Dompdf;
use Dompdf\Options;

AuthService::requireLogin();

$participantRepo = new ParticipantRepository($pdo);
$disciplineRepo = new DisciplineRepository($pdo);
$resultRepo = new ResultRepository($pdo);
$rankingService = new RankingService($participantRepo, $disciplineRepo, $resultRepo);

$oldest_result_datum = $resultRepo->getMinResultDatum();
$semester_options = RankingConfig::getSemesterSelectOptionsFromOldestResultDate($oldest_result_datum);
$selected_semester_key = RankingConfig::resolveSemesterFromRequest($_GET['semester'] ?? null, $semester_options);
$semester_bounds = RankingConfig::getSemesterDateBoundsForKey($selected_semester_key);
$semester_label = RankingConfig::getSemesterLabel($selected_semester_key);
$semester_ref_date = RankingConfig::getSemesterReferenceDateForCategories($selected_semester_key);
$semester_title_display = 'Ranglisten ' . $semester_label;

$COLOR_PRIMARY = BrandConfig::getPrimaryColor();
$COLOR_SECONDARY = BrandConfig::getSecondaryColor();
$LOGO_SRC = BrandConfig::getLogoDataUri();

$categories = RankingConfig::getCategories();
$genders = ['Buben', 'Mädchen'];

$css = "<style>
    @page { margin-top: 100px; margin-bottom: 50px; }
    @page :first { margin-top: -60px; margin-bottom: -100px; }
    #pdf-header:first, #pdf-footer:first { display: none; }
    #pdf-header { position: fixed; top: -90px; left: 0; right: 0; height: 80px; font-family: sans-serif; }
    #pdf-header h1 { color: $COLOR_PRIMARY; text-align: right; font-size: 20px; margin-top: 20px; }
    #pdf-footer { position: fixed; bottom: -40px; left: 0; right: 0; height: 30px; border-top: 1px solid #ccc; text-align: right; font-size: 8px; color: #555; padding: 5px 20px; font-family: sans-serif; }
    .ranking-table { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 9px; font-family: sans-serif; table-layout: fixed; }
    .ranking-table th, .ranking-table td { padding: 1px; border: 1px solid #ddd; text-align: center; }
    .ranking-table th { word-wrap: break-word; overflow-wrap: anywhere; }
    .ranking-table thead tr { background-color: $COLOR_PRIMARY; color: white; }
    .ranking-table tbody tr:nth-child(even) { background-color: #f7f7f7; }
    .col-name { text-align: left !important; }
    .col-total { background-color: $COLOR_SECONDARY; color: $COLOR_PRIMARY; font-weight: bold; }
    .points { font-weight: bold; font-size: 10px; color: $COLOR_PRIMARY; }
    .result { font-size: 8px; color: #555; }
    .penalty-points { color: red; font-weight: bold; }
    .penalty-result { color: red; font-style: italic; }
    .page-break { page-break-before: always; }
</style>";

$header_html = "<div id='pdf-header'><div style='display: inline-block; width: 100%;'>
    <img src='$LOGO_SRC' alt='Logo' style='height: 70px; width: auto; float: left; margin-top: 5px;'>
    <h1>$semester_title_display</h1>
</div></div>";

$footer_html = "<div id='pdf-footer'><p style='margin: 0;'>PDF-Ranglisten generiert am: " . date('d.m.Y H:i') . "</p></div>";

function generate_ranking_html($category_label, $geschlecht_label, $rankingData, $COLOR_PRIMARY, $COLOR_SECONDARY) {
    $html = '<div class="page-break">';
    $html .= '<h2 style="font-family: sans-serif; color: ' . $COLOR_PRIMARY . '; border-bottom: 2px solid ' . $COLOR_SECONDARY . '; margin-top: 0px; padding-bottom: 5px;">Rangliste ' . $geschlecht_label . ' ' . $category_label . '</h2>';
    
    if (empty($rankingData['participants'])) {
        $html .= '<p>Keine Daten verfügbar.</p></div>';
        return $html;
    }

    $html .= '<table class="ranking-table"><thead><tr><th style="width: 3%;">Rang</th><th class="col-name" style="width: 13%;">Name</th><th style="width: 5%;">Jg.</th>';
    foreach ($rankingData['disciplines'] as $disc) $html .= '<th>' . htmlspecialchars($disc['name']) . '</th>';
    $html .= '<th class="col-total" style="width: 10%;">Punkte</th></tr></thead><tbody>';

    $rank = 0; $last_pts = null;
    foreach ($rankingData['participants'] as $i => $name) {
        $data = $rankingData['results_map'][$name];
        if ($data['total_points'] !== $last_pts) { $rank = $i + 1; $last_pts = $data['total_points']; }
        $html .= '<tr><td>' . $rank . '</td><td class="col-name">' . htmlspecialchars($name) . '</td><td>' . $data['jahrgang'] . '</td>';
        foreach ($rankingData['discipline_names'] as $discName) {
            $pts = $data['discipline_points'][$discName] ?? null;
            $res = $data['results'][$discName] ?? null;
            $is_pen = $res === null;
            $html .= '<td><div class="' . ($is_pen ? 'penalty-points' : 'points') . '">' . $pts . '</div><div class="' . ($is_pen ? 'penalty-result' : 'result') . '">' . ($is_pen ? 'n.R.' : number_format($res, 2)) . '</div></td>';
        }
        $html .= '<td class="col-total">' . $data['total_points'] . '</td></tr>';
    }
    $html .= '</tbody></table></div>';
    return $html;
}

$full_html = "<div style='text-align: center; margin-top: 100px; font-family: sans-serif;'>
    <img src='$LOGO_SRC' alt='Logo' style='height: 150px; width: auto; margin-bottom: 30px; display: block; margin-left: auto; margin-right: auto;'>
    <h1 style='color: $COLOR_PRIMARY; font-size: 48px; border-bottom: 6px solid $COLOR_SECONDARY; padding-bottom: 20px;'>$semester_title_display</h1>
    <h2 style='color: #555; font-size: 24px; margin-top: 50px;'>Alle Kategorien</h2>
</div><div style='page-break-after: always;'></div>" . $header_html . $footer_html;

foreach ($categories as $catKey => $age) {
    foreach ($genders as $gender) {
        $genderConfig = RankingConfig::resolveGender($gender);
        $birthYearRange = RankingConfig::getBirthYearRange($catKey, $semester_ref_date);

        $gFilter = $genderConfig['filter'];
        $minY = $birthYearRange['min_birth_year'];
        $maxY = $birthYearRange['max_birth_year'];
        $rankingData = $rankingService->getRanking(
            $gFilter,
            $minY,
            $maxY,
            $semester_bounds['start'],
            $semester_bounds['end']
        );
        $full_html .= generate_ranking_html($catKey, $gender, $rankingData, $COLOR_PRIMARY, $COLOR_SECONDARY);
    }
}

$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('defaultPaperOrientation', 'portrait');
$options->set('defaultPaperSize', 'A4');
$dompdf = new Dompdf($options);
$dompdf->loadHtml($css . $full_html);
$dompdf->render();
$dompdf->stream('Gesamtranglisten_' . $selected_semester_key . '_' . date('Ymd') . '.pdf', ['Attachment' => 0]);
