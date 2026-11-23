<?php
// index.php – Resultate erfassen (Handy zuerst).
// Ablauf: 2 Buchstaben → Kind antippen (eindeutig = automatisch) → Resultat → Speichern.
// Gespeichert wird per fetch (erfassen_api.php) ohne Neuladen, damit die Tastatur offen bleibt; Logik in assets/js/erfassen.js.
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
$pruefer = (string)$_SESSION['username'];

$disciplines = [];
foreach ($disciplineRepo->getActive() as $d) {
    $unit = DisciplineMeta::unitFor($d, $semesterKey);
    $disciplines[] = [
        'name' => $d['name'],
        'hoch' => (int)$d['hoch_ist_besser'] === 1,
        'symbol' => DisciplineMeta::symbol($d['name'], $d['symbol'] ?? null),
        'unit' => $unit,
        'kind' => DisciplineMeta::unitKind($unit),
        'label' => DisciplineMeta::unitLabel($unit),
        'cats' => DisciplineMeta::countedCategories($d), // null = zählt in allen Kategorien
    ];
}
$activeNames = array_column($disciplines, 'name');

$refDate = RankingConfig::getSemesterReferenceDateForCategories($semesterKey);
$kids = array_map(function (array $k) use ($refDate): array {
    return ['id' => $k['id'], 'v' => $k['vorname'], 'n' => $k['nachname'], 'jg' => $k['jahrgang'], 'd' => $k['days'],
        'c' => RankingConfig::categoryForBirthYear($k['jahrgang'], $refDate)];
}, $participantRepo->getActiveWithDays());

$best = [];
foreach ($resultRepo->getBestByParticipantInPeriod($bounds['start'], $bounds['end']) as $row) {
    if (in_array($row['disziplin'], $activeNames, true)) {
        $best[$row['teilnehmer_id']][$row['disziplin']] = (float)$row['best_resultat'];
    }
}

$today = [];
foreach ($resultRepo->getToday() as $row) {
    $today[] = [
        'id' => (int)$row['id'],
        'pid' => $row['teilnehmer_id'] !== null ? (int)$row['teilnehmer_id'] : null,
        'name' => $row['name'],
        'disz' => $row['disziplin'],
        'v' => (float)$row['resultat'],
        'own' => $row['pruefer'] === $pruefer,
    ];
}

$weekdayKeys = [1 => 'montag', 2 => 'dienstag', 3 => 'mittwoch', 4 => 'donnerstag', 5 => 'freitag', 6 => 'samstag', 7 => 'sonntag'];
$weekdayNames = [1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag', 5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag'];
$months = [1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
$dow = (int)date('N');

$data = [
    'csrf' => csrfToken(),
    'me' => $pruefer,
    'todayKey' => $weekdayKeys[$dow],
    'date' => date('Y-m-d'),
    'semester' => RankingConfig::getSemesterLabel($semesterKey),
    'kids' => $kids,
    'disciplines' => $disciplines,
    'best' => $best,
    'today' => $today,
];

$pageTitle = 'Erfassen';
$activeNav = 'erfassen';
include 'templates/header.php';
?>
<div class="narrow">
    <h1>Erfassen</h1>
    <p class="sub"><?php echo e($weekdayNames[$dow] . ', ' . (int)date('j') . '. ' . $months[(int)date('n')] . ' · Prüfer ' . $pruefer); ?></p>
</div>
<?php if ($disciplines === [] || $kids === []): ?>
    <div class="narrow"><div class="alert info"><?php echo icon('info'); ?>
        <?php echo $disciplines === [] ? 'Es ist keine Disziplin aktiv (Tabelle disziplinen, Spalte aktiv = 1).' : 'Es ist kein Kind aktiv (Tabelle teilnehmer, mindestens ein Trainingstag = 1).'; ?>
    </div></div>
<?php else: ?>
    <div id="erf" class="e"></div>
    <noscript><div class="narrow alert err">Zum Erfassen muss JavaScript eingeschaltet sein.</div></noscript>
    <script type="application/json" id="erf-data"><?php echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?></script>
<?php endif; ?>
<?php
$extraScripts = '<script src="' . e(asset('assets/js/erfassen.js')) . '" data-icons="' . e(asset('assets/img/icons.svg')) . '"></script>';
include 'templates/footer.php';
