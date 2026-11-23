<?php
// export_ranglisten_pdf.php – alle Ranglisten eines Semesters als PDF (A4 quer, Design «Klar»).
// Titelseite mit Übersicht, danach pro Kategorie/Geschlecht eine Rangliste. Ausgelegt für bis zu 10 Disziplinen.
require 'config.php';

use App\Config\BrandConfig;
use App\Config\DisciplineMeta;
use App\Config\RankingConfig;
use App\Repository\ParticipantRepository;
use App\Repository\DisciplineRepository;
use App\Repository\ResultRepository;
use App\Service\AuthService;
use App\Service\RankingService;
use Dompdf\Dompdf;
use Dompdf\Options;

AuthService::requireLogin();

// Dompdf/php-svg-lib melden unter neueren PHP-Versionen «Deprecated»-Hinweise, die das PDF zerstören würden
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

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
$semester_long = strncmp($selected_semester_key, 'w-', 2) === 0
    ? 'Wintersemester ' . substr($selected_semester_key, 2) . '/' . substr((string)((int)substr($selected_semester_key, 2) + 1), -2)
    : 'Sommersemester ' . substr($selected_semester_key, 2);

$C1 = BrandConfig::getPrimaryColor();
$C2 = BrandConfig::getSecondaryColor();
$ON_C2 = BrandConfig::textOn($C2);
$LOGO = BrandConfig::getLogoDataUri();
$APP = BrandConfig::getAppName();

// Disziplin-Metadaten (Symbol, Einheit für dieses Semester)
$META = [];
foreach ($disciplineRepo->getAllWithMetadata() as $dm) {
    $META[$dm['name']] = [
        'unit' => DisciplineMeta::unitFor($dm, $selected_semester_key),
        'symbol' => DisciplineMeta::symbol($dm['name'], $dm['symbol'] ?? null),
    ];
}

/** Symbol aus assets/img/icons.svg als eigenständiges SVG (Data-URI) für Dompdf. */
function pdf_icon(string $name, string $color): string {
    static $sprite = null, $cache = [];
    if ($sprite === null) {
        $sprite = (string)@file_get_contents(__DIR__ . '/assets/img/icons.svg');
    }
    $key = $name . $color;
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    if (!preg_match('/<symbol id="' . preg_quote($name, '/') . '" viewBox="0 0 24 24">(.*?)<\/symbol>/s', $sprite, $m)) {
        return $cache[$key] = '';
    }
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="' . $color
        . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $m[1] . '</svg>';

    return $cache[$key] = 'data:image/svg+xml;base64,' . base64_encode($svg);
}

/** Lange Disziplin-Namen für schmale Spalten trennen («Geschicklichkeits-/challenge»). */
function pdf_hyphenate(string $text, int $max): string {
    $parts = [];
    foreach (preg_split('/\s+/', trim($text)) as $word) {
        if (mb_strlen($word) <= $max) {
            $parts[] = htmlspecialchars($word);
            continue;
        }
        $cut = 0;
        foreach (['challenge', 'lichkeits', 'springen', 'sprung', 'stossen', 'wurf', 'lauf', 'ball', 'hang', 'parcours', 'kampf', 'zeit'] as $suffix) {
            $pos = mb_strrpos(mb_strtolower($word), $suffix);
            if ($pos !== false && $pos >= 3) {
                $cut = $pos;
                break;
            }
        }
        if ($cut === 0) {
            $cut = (int)ceil(mb_strlen($word) / 2);
        }
        $left = mb_substr($word, 0, $cut);
        $right = mb_substr($word, $cut);
        // Teile, die noch zu lang sind, nochmals trennen
        $parts[] = pdf_hyphenate($left, $max) . '-<br>' . pdf_hyphenate($right, $max);
    }

    return implode(' ', $parts);
}

function pdf_ranking_page(string $catKey, array $cat, string $genderLabel, array $data, array $META, array $ctx): string {
    $disciplines = $data['disciplines'];
    $n = max(1, count($disciplines));
    // Spaltenbreiten in Prozent (A4 quer, nutzbar ~273 mm); Dompdf hält sich bei table-layout: fixed an Prozente
    $wRank = 4; $wName = $n > 8 ? 15 : 17; $wJg = 4.5; $wTot = 6.5;
    $wDisc = round((100 - $wRank - $wName - $wJg - $wTot) / $n, 3);
    $small = $n > 7;
    $maxChars = (int)max(8, floor($wDisc * 2.73 / 1.5));

    $rows = '';
    $rank = 0; $last = null;
    foreach ($data['participants'] as $i => $name) {
        $r = $data['results_map'][$name];
        if ($r['total_points'] !== $last) { $rank = $i + 1; $last = $r['total_points']; }
        $badge = $rank <= 3 ? '<span class="medal m' . $rank . '">' . $rank . '</span>' : '<span class="rk">' . $rank . '</span>';
        $rows .= '<tr><td class="c-rank">' . $badge . '</td><td class="c-name">' . htmlspecialchars($name) . '</td><td class="c-jg">' . htmlspecialchars((string)$r['jahrgang']) . '</td>';
        foreach ($data['discipline_names'] as $dn) {
            $res = $r['results'][$dn] ?? null;
            $pts = (int)($r['discipline_points'][$dn] ?? 0);
            $rows .= $res === null
                ? '<td class="c-res miss"><b>–</b><span>fehlt +' . $pts . '</span></td>'
                : '<td class="c-res"><b>' . htmlspecialchars(DisciplineMeta::format($res, $META[$dn]['unit'] ?? '', false)) . '</b><span>Rang ' . $pts . '</span></td>';
        }
        $rows .= '<td class="c-tot">' . (int)$r['total_points'] . '</td></tr>';
    }

    $head = '<tr><th class="c-rank" style="width:' . $wRank . '%">Rang</th><th class="c-name" style="width:' . $wName . '%">Name</th><th class="c-jg" style="width:' . $wJg . '%">Jg.</th>';
    foreach ($disciplines as $d) {
        $m = $META[$d['name']] ?? ['unit' => '', 'symbol' => 'medal'];
        $icon = pdf_icon($m['symbol'], $ctx['C1']);
        $label = DisciplineMeta::unitLabel($m['unit']);
        $head .= '<th class="c-disc" style="width:' . $wDisc . '%">' . ($icon ? '<img src="' . $icon . '" class="ico">' : '')
            . '<div class="dn">' . pdf_hyphenate($d['name'], $maxChars) . '</div>'
            . '<div class="du"><span class="arr">' . ($d['hoch_ist_besser'] ? '↑' : '↓') . '</span>' . ($label !== '' ? ' ' . htmlspecialchars($label) : '') . '</div></th>';
    }
    $head .= '<th class="c-tot" style="width:' . $wTot . '%">Punkte</th></tr>';

    $years = RankingConfig::getBirthYearRange($catKey, $ctx['ref']);

    return '<div class="page">'
        . '<table class="hdr"><tr>'
        . '<td class="hdr-logo">' . ($ctx['logo'] ? '<img src="' . $ctx['logo'] . '">' : '') . '</td>'
        . '<td class="hdr-title"><div class="kick">Rangliste · ' . htmlspecialchars($ctx['semester']) . '</div><h1>' . htmlspecialchars($catKey) . ' · ' . htmlspecialchars($genderLabel) . '</h1></td>'
        . '<td class="hdr-meta">' . count($data['participants']) . ' Kinder<br>Jahrgänge ' . $years['min_birth_year'] . '–' . $years['max_birth_year'] . '</td>'
        . '</tr></table>'
        . '<table class="rank' . ($small ? ' small' : '') . '"><thead>' . $head . '</thead><tbody>' . $rows . '</tbody></table>'
        . '<p class="legend">Punkte = Summe der Ränge, weniger ist besser. Pro Disziplin gibt der Rang die Punkte (gleiches Resultat = gleicher Rang); '
        . 'fehlt ein Resultat, zählt der schlechteste Rang + 1. <span class="arr">↑</span> höher ist besser, <span class="arr">↓</span> tiefer ist besser.</p>'
        . '</div>';
}

$ctx = ['C1' => $C1, 'logo' => $LOGO, 'semester' => $semester_long, 'ref' => $semester_ref_date];
$pages = [];
$overview = [];
foreach (RankingConfig::getCategories() as $catKey => $cat) {
    foreach (['Mädchen', 'Buben'] as $genderLabel) {
        $g = RankingConfig::resolveGender($genderLabel);
        $years = RankingConfig::getBirthYearRange($catKey, $semester_ref_date);
        $data = $rankingService->getRanking($g['filter'], $years['min_birth_year'], $years['max_birth_year'], $semester_bounds['start'], $semester_bounds['end'], $catKey);
        $count = empty($data['participants']) || empty($data['disciplines']) ? 0 : count($data['participants']);
        $overview[] = [$catKey, $genderLabel, $count, $years];
        if ($count > 0) {
            $pages[] = pdf_ranking_page($catKey, $cat, $genderLabel, $data, $META, $ctx);
        }
    }
}

$byCat = [];
foreach ($overview as [$catKey, $genderLabel, $count, $years]) {
    $byCat[$catKey]['years'] = $years;
    $byCat[$catKey][$genderLabel] = $count;
}
$ovRows = '<tr class="ov-h"><td></td><td></td><td class="ov-n">Mädchen</td><td class="ov-n">Buben</td></tr>';
foreach ($byCat as $catKey => $c) {
    $cell = function (int $count): string { return $count ? $count . ' Kinder' : '<span class="muted">–</span>'; };
    $ovRows .= '<tr><td class="ov-cat">' . htmlspecialchars($catKey) . '</td><td class="ov-jg">Jg. ' . $c['years']['min_birth_year'] . '–' . $c['years']['max_birth_year'] . '</td>'
        . '<td class="ov-n">' . $cell($c['Mädchen'] ?? 0) . '</td><td class="ov-n">' . $cell($c['Buben'] ?? 0) . '</td></tr>';
}
$stand = date('d.m.Y');

$css = '
@page { size: A4 landscape; margin: 13mm 12mm 15mm 12mm; }
* { font-family: Helvetica, Arial, sans-serif; }
body { color: #151B2E; font-size: 9pt; }
.arr { font-family: "DejaVu Sans", sans-serif; }
.page { page-break-before: always; }
.cover { text-align: center; padding-top: 8mm; }
.cover img.logo { height: 30mm; }
.cover .kick { margin-top: 8mm; color: #5E667A; font-size: 12pt; letter-spacing: 1px; text-transform: uppercase; }
.cover h1 { margin: 2mm 0 0; color: ' . $C1 . '; font-size: 34pt; }
.cover .bar { width: 40mm; height: 2.2mm; margin: 6mm auto 8mm; background: ' . $C2 . '; }
.ov { margin: 0 auto; border-collapse: collapse; width: 150mm; font-size: 11pt; }
.ov td { padding: 2.2mm 3mm; border-bottom: 0.3mm solid #E1E4EB; text-align: left; }
.ov .ov-cat { font-weight: bold; color: ' . $C1 . '; }
.ov .ov-jg { color: #5E667A; }
.ov .ov-n { text-align: right; width: 32mm; }
.ov .ov-h td { color: #5E667A; font-size: 9pt; border-bottom: 0.3mm solid #C9CED8; }
.muted { color: #A0A7B6; }
.cover .stand { margin-top: 10mm; color: #5E667A; font-size: 9pt; }
.hdr { width: 100%; border-collapse: collapse; border-bottom: 0.8mm solid ' . $C2 . '; margin-bottom: 4mm; }
.hdr td { vertical-align: bottom; padding: 0 0 2.5mm; }
.hdr-logo { width: 26mm; }
.hdr-logo img { height: 14mm; }
.hdr-title .kick { color: #5E667A; font-size: 9pt; }
.hdr-title h1 { margin: 0.5mm 0 0; color: ' . $C1 . '; font-size: 20pt; }
.hdr-meta { text-align: right; color: #5E667A; font-size: 9pt; line-height: 1.35; }
table.rank { width: 100%; border-collapse: collapse; table-layout: fixed; }
.rank thead { display: table-header-group; }
.rank th { background: ' . $C1 . '; color: #fff; font-size: 7.5pt; font-weight: bold; padding: 1.6mm 1mm; text-align: center; vertical-align: bottom; line-height: 1.15; }
.rank th.c-name { text-align: left; padding-left: 2mm; }
.rank th .ico { width: 4.6mm; height: 4.6mm; background: #fff; border-radius: 1.2mm; padding: 0.6mm; margin-bottom: 0.8mm; }
.rank th .du { font-weight: normal; font-size: 6.5pt; color: #D5DAE6; margin-top: 0.4mm; }
.rank th.c-tot { background: ' . $C2 . '; color: ' . $ON_C2 . '; font-size: 8.5pt; }
.rank td { padding: 1.3mm 1mm; border-bottom: 0.25mm solid #E1E4EB; text-align: center; vertical-align: middle; }
.rank tbody tr:nth-child(even) td { background: #F6F7FA; }
.rank tr { page-break-inside: avoid; }
.rank .c-name { text-align: left; padding-left: 2mm; font-weight: bold; font-size: 9pt; }
.rank td.c-jg { color: #5E667A; }
.rank .c-res b { display: block; font-size: 9pt; }
.rank .c-res span { display: block; font-size: 6.5pt; color: #7A8296; }
.rank .c-res.miss b { color: #A0A7B6; font-weight: normal; }
.rank .c-res.miss span { color: #B4232A; }
.rank td.c-tot { font-weight: bold; font-size: 11pt; color: ' . $C1 . '; background: #FFF6DB; }
.rank tbody tr:nth-child(even) td.c-tot { background: #FDEFC4; }
.rank.small .c-name { font-size: 8.5pt; }
.rank.small .c-res b { font-size: 8.5pt; }
.rk { font-weight: bold; color: #5E667A; }
.medal { display: inline-block; width: 5.6mm; height: 4.4mm; padding-top: 1.2mm; line-height: 1; border-radius: 2.8mm; font-weight: bold; font-size: 8pt; text-align: center; }
.medal.m1 { background: #F2C230; color: #3B2A00; }
.medal.m2 { background: #C9D0DB; color: #262C38; }
.medal.m3 { background: #D9995F; color: #3A1F08; }
.legend { margin-top: 3mm; color: #5E667A; font-size: 7.5pt; }
';

$cover = '<div class="cover">'
    . ($LOGO ? '<img class="logo" src="' . $LOGO . '">' : '')
    . '<div class="kick">' . htmlspecialchars($APP) . '</div>'
    . '<h1>Ranglisten ' . htmlspecialchars($semester_long) . '</h1>'
    . '<div class="bar"></div>'
    . '<table class="ov">' . $ovRows . '</table>'
    . '<div class="stand">Stand ' . $stand . '</div>'
    . '</div>';

$html = '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><style>' . $css . '</style></head><body>' . $cover . implode('', $pages) . '</body></html>';

$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('isPhpEnabled', false);
$options->set('defaultFont', 'Helvetica');
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

// Fusszeile auf jeder Seite: Verein · Semester links, Stand + Seite rechts
$canvas = $dompdf->getCanvas();
$font = $dompdf->getFontMetrics()->getFont('Helvetica');
$w = $canvas->get_width();
$h = $canvas->get_height();
$canvas->page_text(34, $h - 26, $APP . ' · Ranglisten ' . $semester_label, $font, 7, [0.37, 0.4, 0.48]);
$canvas->page_text($w - 160, $h - 26, 'Stand ' . $stand . ' · Seite {PAGE_NUM} von {PAGE_COUNT}', $font, 7, [0.37, 0.4, 0.48]);

$dompdf->stream('Ranglisten_' . str_replace(['/', ' '], ['-', '_'], $semester_label) . '_' . date('Ymd') . '.pdf', ['Attachment' => 0]);
