<?php
// resultsoverview.php – Bestresultate aller aktiven Kinder im laufenden Semester.
// Suche, Trainingstag-Filter, «nur mit Lücken» und «erfasst x von y» pro Disziplin laufen im Browser (assets/js/uebersicht.js).
require 'config.php';

use App\Config\DisciplineMeta;
use App\Config\RankingConfig;
use App\Repository\DisciplineRepository;
use App\Repository\ParticipantRepository;
use App\Repository\ResultRepository;
use App\Service\AuthService;

AuthService::requireLogin();

$participantRepo = new ParticipantRepository($pdo);
$disciplineRepo = new DisciplineRepository($pdo);
$resultRepo = new ResultRepository($pdo);

$semesterKey = RankingConfig::getSemesterKeyForDate();
$bounds = RankingConfig::getCurrentSemesterDateBounds();
$refDate = RankingConfig::getSemesterReferenceDateForCategories($semesterKey);
$error_message = '';
$kids = [];
$disciplines = [];
$best = [];

try {
    $disciplines = $disciplineRepo->getActive();
    $kids = $participantRepo->getActiveWithDays();
    foreach ($resultRepo->getBestByParticipantInPeriod($bounds['start'], $bounds['end']) as $row) {
        $best[$row['teilnehmer_id']][$row['disziplin']] = (float)$row['best_resultat'];
    }
    if ($kids === []) {
        $error_message = 'Keine aktiven Kinder gefunden (mindestens ein Trainingstag = 1).';
    } elseif ($disciplines === []) {
        $error_message = 'Keine aktiven Disziplinen gefunden.';
    }
} catch (PDOException $e) {
    error_log('resultsoverview: ' . $e->getMessage());
    $error_message = 'Datenbankfehler beim Laden der Übersicht.';
}

// Nur Trainingstage anzeigen, an denen auch Kinder trainieren
$usedDays = [];
foreach ($kids as $k) {
    foreach ($k['days'] as $day) {
        $usedDays[$day] = true;
    }
}

$pageTitle = 'Übersicht';
$activeNav = 'uebersicht';
$wideLayout = true;
include 'templates/header.php';
?>
<h1>Übersicht</h1>
<p class="sub">Bestresultate aller aktiven Kinder · <?php echo e(RankingConfig::getSemesterLabel($semesterKey)); ?></p>

<?php if ($error_message): ?>
    <div class="alert err" role="alert"><?php echo icon('info') . e($error_message); ?></div>
<?php else: ?>
    <div class="toolbar">
        <div class="search"><?php echo icon('search'); ?><input class="inp" id="q" type="search" placeholder="Kind suchen …" autocomplete="off" aria-label="Kind suchen"></div>
        <?php if (count($usedDays) > 1): ?>
            <div class="seg" id="days" role="group" aria-label="Trainingstag">
                <button type="button" data-day="" aria-pressed="true">Alle</button>
                <?php foreach (ParticipantRepository::WEEKDAYS as $day => $short): if (isset($usedDays[$day])): ?>
                    <button type="button" data-day="<?php echo $day; ?>" aria-pressed="false"><?php echo $short; ?></button>
                <?php endif; endforeach; ?>
            </div>
        <?php endif; ?>
        <label class="switch"><input type="checkbox" id="gaps"> nur mit Lücken</label>
    </div>

    <div class="progress" id="prog">
        <?php foreach ($disciplines as $i => $d): ?>
            <div class="card prog" data-d="<?php echo $i; ?>">
                <div class="h"><?php echo icon(DisciplineMeta::symbol($d['name'], $d['symbol'])) . e($d['name']); ?></div>
                <div class="c"></div>
                <div class="bar"><i></i></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card tbl-wrap">
        <table class="grid" id="ov">
            <thead>
                <tr>
                    <th class="name">Kind</th>
                    <?php foreach ($disciplines as $d):
                        $unit = DisciplineMeta::unitFor($d, $semesterKey); ?>
                        <th><?php echo icon(DisciplineMeta::symbol($d['name'], $d['symbol'])) . e($d['name']); ?>
                            <span class="dir"><?php echo ((int)$d['hoch_ist_besser'] ? '↑ höher besser' : '↓ tiefer besser') . (DisciplineMeta::unitLabel($unit) !== '' ? ' · ' . e(DisciplineMeta::unitLabel($unit)) : ''); ?></span></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($kids as $k):
                    $name = $k['vorname'] . ' ' . $k['nachname'];
                    $cat = RankingConfig::categoryForBirthYear($k['jahrgang'], $refDate); ?>
                    <tr data-name="<?php echo e(mb_strtolower($name, 'UTF-8')); ?>" data-days="<?php echo e(implode(' ', $k['days'])); ?>">
                        <td class="name"><b><?php echo e($name); ?></b><small><?php echo (int)$k['jahrgang'] . ($cat ? ' · ' . e($cat) : '') . ' · ' . e(implode('/', array_map(function ($day) { return ParticipantRepository::WEEKDAYS[$day]; }, $k['days']))); ?></small></td>
                        <?php foreach ($disciplines as $d):
                            $v = $best[$k['id']][$d['name']] ?? null;
                            $unit = DisciplineMeta::unitFor($d, $semesterKey);
                            $counts = $cat === null || DisciplineMeta::countsFor($d, $cat);
                            if ($v === null && !$counts): ?>
                                <td class="na" title="zählt für <?php echo e($cat); ?> nicht"><span>·</span></td>
                            <?php elseif ($v === null): ?>
                                <td class="miss"><span>fehlt</span></td>
                            <?php elseif (!$counts): ?>
                                <td class="ng" title="nicht gewertet (zählt für <?php echo e($cat); ?> nicht in der Rangliste)"><?php echo e(DisciplineMeta::format($v, $unit)); ?><small>nicht gewertet</small></td>
                            <?php else:
                                $kind = DisciplineMeta::unitKind($unit);
                                $suffix = ($kind === 'time' && $v >= 60) || $kind === 'stufe' || $kind === 'none' ? '' : DisciplineMeta::unitLabel($unit); ?>
                                <td><?php echo e(DisciplineMeta::format($v, $unit, false)); ?><?php if ($suffix !== ''): ?><span class="u"><?php echo e($suffix); ?></span><?php endif; ?></td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="empty" id="none" hidden>Kein Kind passt zum Filter.</p>
    </div>
    <p class="legend">Oben pro Disziplin: wie viele der angezeigten Kinder schon ein Resultat haben (nur Kinder, für deren Kategorie die Disziplin zählt). Grau = erfasst, aber in dieser Kategorie nicht gewertet. Die Tabelle scrollt seitlich, die Namen bleiben stehen.</p>
<?php endif; ?>
<?php
$extraScripts = '<script src="' . e(asset('assets/js/uebersicht.js')) . '"></script>';
include 'templates/footer.php';
