<?php
// rangliste.php
require 'config.php';

use App\Config\DisciplineMeta;
use App\Config\RankingConfig;
use App\Repository\ParticipantRepository;
use App\Repository\DisciplineRepository;
use App\Repository\ResultRepository;
use App\Service\AuthService;
use App\Service\RankingService;

// Prüfung, ob der Benutzer eingeloggt ist
AuthService::requireLogin();

$participantRepo = new ParticipantRepository($pdo);
$disciplineRepo = new DisciplineRepository($pdo);
$resultRepo = new ResultRepository($pdo);
$rankingService = new RankingService($participantRepo, $disciplineRepo, $resultRepo);

$error_message = '';
$table_data_available = false;

$categories = RankingConfig::getCategories();

$oldest_result_datum = $resultRepo->getMinResultDatum();
$semester_options = RankingConfig::getSemesterSelectOptionsFromOldestResultDate($oldest_result_datum);
$selected_semester_key = RankingConfig::resolveSemesterFromRequest($_GET['semester'] ?? null, $semester_options);

$semester_bounds = RankingConfig::getSemesterDateBoundsForKey($selected_semester_key);
$semester_label = RankingConfig::getSemesterLabel($selected_semester_key);
$semester_ref_date = RankingConfig::getSemesterReferenceDateForCategories($selected_semester_key);

$selected_category_key = RankingConfig::normalizeCategory($_GET['kategorie'] ?? null);
$gender = RankingConfig::resolveGender($_GET['geschlecht'] ?? null);
$birthYearRange = RankingConfig::getBirthYearRange($selected_category_key, $semester_ref_date);

$selected_geschlecht = $gender['label'];
$geschlecht_label = $gender['label'];
$geschlecht_filter = $gender['filter'];
$min_birth_year = $birthYearRange['min_birth_year'];
$max_birth_year = $birthYearRange['max_birth_year'];

function rangliste_query_params(array $overrides): string {
    global $selected_semester_key, $selected_category_key, $selected_geschlecht;
    $params = array_merge([
        'semester' => $selected_semester_key,
        'kategorie' => $selected_category_key,
        'geschlecht' => $selected_geschlecht,
    ], $overrides);

    return '?' . http_build_query($params);
}

try {
    $rankingData = $rankingService->getRanking(
        $geschlecht_filter,
        $min_birth_year,
        $max_birth_year,
        $semester_bounds['start'],
        $semester_bounds['end'],
        $selected_category_key
    );

    if (empty($rankingData['participants'])) {
        $error_message = 'Keine Teilnehmer für die Gruppe ' . $geschlecht_label . ' ' . $selected_category_key . ' gefunden.';
    } elseif (empty($rankingData['disciplines'])) {
        $error_message = 'Keine aktiven Disziplinen mit Resultaten für diese Gruppe gefunden.';
    } else {
        $table_data_available = true;
        $all_participants_names = $rankingData['participants'];
        $results_map = $rankingData['results_map'];
        $disciplines = $rankingData['disciplines'];
        $all_disciplines_names = $rankingData['discipline_names'];
        // Symbol und Einheit (für das gewählte Semester) je Disziplin
        $meta = $disciplineRepo->getMetadataByNames($all_disciplines_names);
        foreach ($disciplines as &$d) {
            $m = $meta[$d['name']] ?? [];
            $d['symbol'] = DisciplineMeta::symbol($d['name'], $m['symbol'] ?? null);
            $d['unit'] = DisciplineMeta::unitFor($m, $selected_semester_key);
        }
        unset($d);
    }
} catch (Exception $e) {
    error_log('rangliste: ' . $e->getMessage());
    $error_message = 'Fehler bei der Berechnung der Rangliste.';
}

$pageTitle = 'Rangliste ' . $geschlecht_label . ' ' . $selected_category_key;
$activeNav = 'rangliste';
$wideLayout = true;
include 'templates/header.php';

function rang_medal(int $rank): string {
    return '<span class="medal' . ($rank <= 3 ? ' m' . $rank : '') . '">' . $rank . '</span>';
}
?>
<h1>Rangliste</h1>
<p class="sub">Punkte = Summe der Ränge · weniger ist besser</p>

<div class="filters">
    <form method="get" action="rangliste.php">
        <input type="hidden" name="kategorie" value="<?php echo e($selected_category_key); ?>">
        <input type="hidden" name="geschlecht" value="<?php echo e($selected_geschlecht); ?>">
        <select class="sel" name="semester" aria-label="Semester" onchange="this.form.submit()">
            <?php foreach ($semester_options as $opt): ?>
                <option value="<?php echo e($opt['value']); ?>"<?php echo $selected_semester_key === $opt['value'] ? ' selected' : ''; ?>><?php echo e($opt['label']); ?></option>
            <?php endforeach; ?>
        </select>
        <noscript><button class="btn" type="submit">Anzeigen</button></noscript>
    </form>
    <nav class="seg" aria-label="Kategorie">
        <?php foreach ($categories as $key => $cat):
            $sub = preg_match('/\((.*)\)/', $cat['label'], $m) ? $m[1] : ''; ?>
            <a href="<?php echo e('rangliste.php' . rangliste_query_params(['kategorie' => $key])); ?>"<?php echo $selected_category_key === $key ? ' aria-current="true"' : ''; ?>><?php echo e($key); ?><?php if ($sub !== ''): ?><small><?php echo e($sub); ?></small><?php endif; ?></a>
        <?php endforeach; ?>
    </nav>
    <nav class="seg" aria-label="Geschlecht">
        <?php foreach (['Mädchen', 'Buben'] as $g): ?>
            <a href="<?php echo e('rangliste.php' . rangliste_query_params(['geschlecht' => $g])); ?>"<?php echo $selected_geschlecht === $g ? ' aria-current="true"' : ''; ?>><?php echo e($g); ?></a>
        <?php endforeach; ?>
    </nav>
</div>

<div class="card">
    <div class="rk-head">
        <h2><?php echo e($selected_category_key . ' · ' . $geschlecht_label); ?>
            <small><?php echo e($semester_label . ($table_data_available ? ' · ' . count($all_participants_names) . ' Kinder' : '') . ' · Jahrgänge ' . $min_birth_year . '–' . $max_birth_year); ?></small></h2>
        <?php if ($table_data_available): ?>
            <div class="act">
                <a class="btn" href="<?php echo e('export_ranglisten_pdf.php' . rangliste_query_params([])); ?>"><?php echo icon('download'); ?>PDF<span class="long">(alle Kategorien)</span></a>
                <a class="btn" href="<?php echo e('export_rangliste.php' . rangliste_query_params([])); ?>">CSV</a>
                <button class="btn" type="button" onclick="window.print()" title="Drucken"><?php echo icon('printer'); ?></button>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($error_message): ?>
        <p class="empty"><?php echo e($error_message); ?></p>
    <?php endif; ?>

    <?php if ($table_data_available):
        $ranks = [];
        $rank = 0; $last = null;
        foreach ($all_participants_names as $i => $pname) {
            $pts = $results_map[$pname]['total_points'];
            if ($pts !== $last) { $rank = $i + 1; $last = $pts; }
            $ranks[$pname] = $rank;
        }
    ?>
        <div class="tbl-wrap rk-table" style="border-radius: 0 0 var(--r) var(--r)">
            <table class="grid">
                <thead>
                    <tr>
                        <th>Rang</th>
                        <th class="name">Name</th>
                        <th>Jg.</th>
                        <?php foreach ($disciplines as $d): ?>
                            <th><?php echo icon($d['symbol']) . e($d['name']); ?><span class="dir"><?php echo ($d['hoch_ist_besser'] ? '↑' : '↓') . (DisciplineMeta::unitLabel($d['unit']) !== '' ? ' ' . e(DisciplineMeta::unitLabel($d['unit'])) : ''); ?></span></th>
                        <?php endforeach; ?>
                        <th class="tot">Punkte</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_participants_names as $pname):
                        $row = $results_map[$pname]; ?>
                        <tr>
                            <td><?php echo rang_medal($ranks[$pname]); ?></td>
                            <td class="name"><b><?php echo e($pname); ?></b></td>
                            <td><?php echo e($row['jahrgang']); ?></td>
                            <?php foreach ($disciplines as $d):
                                $res = $row['results'][$d['name']] ?? null;
                                $pts = $row['discipline_points'][$d['name']] ?? null;
                                if ($res === null): ?>
                                    <td class="res pen"><b>fehlt</b><small>+<?php echo (int)$pts; ?></small></td>
                                <?php else: ?>
                                    <td class="res"><b><?php echo e(DisciplineMeta::format($res, $d['unit'], false)); ?></b><small>Rang <?php echo (int)$pts; ?></small></td>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <td class="tot"><?php echo (int)$row['total_points']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <ul class="rk-list">
            <?php foreach ($all_participants_names as $i => $pname):
                $row = $results_map[$pname];
                $missing = 0;
                foreach ($disciplines as $d) { if (!isset($row['results'][$d['name']])) $missing++; } ?>
                <li><details<?php echo $i === 0 ? ' open' : ''; ?>>
                    <summary><?php echo rang_medal($ranks[$pname]); ?>
                        <span><b><?php echo e($pname); ?></b><small><?php echo e($row['jahrgang']) . ($missing ? ' · ' . $missing . ' fehlt' : ''); ?></small></span>
                        <span class="pts"><?php echo (int)$row['total_points']; ?><small>Punkte</small></span>
                        <?php echo icon('chev-down', 'ico chev'); ?>
                    </summary>
                    <div class="rk-detail">
                        <?php foreach ($disciplines as $d):
                            $res = $row['results'][$d['name']] ?? null;
                            $pts = (int)($row['discipline_points'][$d['name']] ?? 0); ?>
                            <div<?php echo $res === null ? ' class="pen"' : ''; ?>><span><?php echo icon($d['symbol']) . e($d['name']); ?></span>
                                <b><?php echo $res === null ? 'fehlt' : e(DisciplineMeta::format($res, $d['unit'])); ?></b><em><?php echo ($res === null ? '+' : 'Rang ') . $pts; ?></em></div>
                        <?php endforeach; ?>
                    </div>
                </details></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
<?php if ($table_data_available): ?>
    <p class="legend">So wird gerechnet: Pro Disziplin gibt der Rang die Punkte (1. = 1 Punkt, gleiches Resultat = gleicher Rang). Fehlt ein Resultat, zählt der schlechteste Rang + 1. Die Summe entscheidet – weniger Punkte = besser.</p>
<?php endif; ?>
<?php include 'templates/footer.php'; ?>
