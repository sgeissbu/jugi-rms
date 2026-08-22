<?php
// rangliste.php
require 'config.php';

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
        $semester_bounds['end']
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
    }
} catch (Exception $e) {
    $error_message = 'Fehler bei der Ranking-Berechnung: ' . $e->getMessage();
}

$pageTitle = 'Rangliste | ' . $semester_label . ' | ' . $geschlecht_label . ' ' . $selected_category_key;
$pageHeading = 'Ranglisten';
$containerWidthClass = 'max-w-7xl';
$extraStylesheets = ['assets/css/rangliste.css'];
include 'templates/header.php';
?>

        <h1 class="text-3xl font-bold text-center text-[var(--color-primary)] mb-6">Rangliste <?php echo htmlspecialchars($geschlecht_label . ' ' . $selected_category_key); ?></h1>

        <div class="mb-8 p-4 bg-gray-50 rounded-xl shadow-inner">
            <div class="space-y-4">
                <div class="rangliste-semester-bar">
                    <label for="semester_select" class="rangliste-semester-bar__label">Semester</label>
                    <form method="get" action="rangliste.php" class="rangliste-semester-bar__form">
                        <input type="hidden" name="kategorie" value="<?php echo htmlspecialchars($selected_category_key); ?>">
                        <input type="hidden" name="geschlecht" value="<?php echo htmlspecialchars($selected_geschlecht); ?>">
                        <select
                            name="semester"
                            id="semester_select"
                            class="rangliste-semester-bar__select"
                            onchange="this.form.submit()"
                            aria-label="Semester wählen"
                        >
                            <?php foreach ($semester_options as $opt): ?>
                                <option value="<?php echo htmlspecialchars($opt['value']); ?>" <?php echo ($selected_semester_key === $opt['value']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($opt['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <div class="flex flex-wrap gap-2 justify-center">
                    <?php foreach ($categories as $key => $cat):
                        $url = 'rangliste.php' . rangliste_query_params(['kategorie' => $key]);
                    ?>
                        <a href="<?php echo htmlspecialchars($url); ?>" class="toggle-btn <?php echo ($selected_category_key === $key) ? 'toggle-btn-active' : 'toggle-btn-inactive'; ?>"><?php echo $cat['label']; ?></a>
                    <?php endforeach; ?>
                </div>
                <div class="flex gap-4 justify-center pt-4 border-t border-gray-200">
                    <?php foreach (['Buben', 'Mädchen'] as $g):
                        $url = 'rangliste.php' . rangliste_query_params(['geschlecht' => $g]);
                    ?>
                        <a href="<?php echo htmlspecialchars($url); ?>" class="toggle-btn <?php echo ($selected_geschlecht === $g) ? 'toggle-btn-active' : 'toggle-btn-inactive'; ?>"><?php echo $g; ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <?php if ($error_message): ?>
            <p class="text-red-600 font-medium text-center bg-red-100 p-4 rounded-lg"><?php echo $error_message; ?></p>
        <?php endif; ?>

        <?php if ($table_data_available): ?>
            <div class="flex flex-wrap justify-end gap-2 mb-4">
                <a href="<?php echo htmlspecialchars('export_ranglisten_pdf.php' . rangliste_query_params([])); ?>" class="flex items-center px-4 py-2 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-[var(--color-primary)] hover:bg-gray-700 transition duration-150">PDF exportieren (alle)</a>
                <a href="<?php echo htmlspecialchars('export_rangliste.php' . rangliste_query_params([])); ?>" class="flex items-center px-4 py-2 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-[var(--color-primary)] hover:bg-gray-700 transition duration-150">CSV exportieren</a>
            </div>

            <div class="table-container border border-gray-200 rounded-lg shadow-xl">
                <table class="min-w-full divide-y divide-gray-300">
                    <thead class="bg-[var(--color-primary)] text-white sticky-header">
                        <tr>
                            <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold sticky-col sm:pl-6 w-[100px]">Platz</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold">Name</th>
                            <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold">Jg.</th>
                            <?php foreach ($disciplines as $d): ?>
                                <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold border-l border-gray-600">
                                    <?php echo htmlspecialchars($d['name']); ?>
                                    <span class="text-[10px] font-normal opacity-75 block leading-tight">
                                        (<?php echo $d['hoch_ist_besser'] ? '↑' : '↓'; ?>)
                                    </span>
                                </th>
                            <?php endforeach; ?>
                            <th scope="col" class="px-3 py-3.5 text-center text-sm font-bold text-[var(--color-secondary)] border-l border-gray-600">Punkte</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <?php
                        $rank = 0; $last_points = null;
                        foreach ($all_participants_names as $i => $participant_name):
                            $current_points = $results_map[$participant_name]['total_points'];
                            if ($current_points !== $last_points) { $rank = $i + 1; $last_points = $current_points; }
                            $rank_display = ($rank === 1) ? '🥇' : ($rank === 2 ? '🥈' : ($rank === 3 ? '🥉' : $rank));
                            $row_class = ($rank === 1) ? 'bg-yellow-50' : (($rank === 2) ? 'bg-gray-100' : (($rank === 3) ? 'bg-yellow-100' : ''));
                        ?>
                        <tr class="<?php echo $row_class; ?>">
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-bold text-[var(--color-primary)] sm:pl-6 sticky-col border-r border-gray-200"><?php echo $rank_display; ?></td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm font-medium text-gray-900"><?php echo htmlspecialchars($participant_name); ?></td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-center text-gray-700"><?php echo htmlspecialchars($results_map[$participant_name]['jahrgang']); ?></td>
                            <?php foreach ($all_disciplines_names as $discipline_name):
                                $result = $results_map[$participant_name]['results'][$discipline_name] ?? null;
                                $points = $results_map[$participant_name]['discipline_points'][$discipline_name] ?? null;
                                $display_result = $result !== null ? number_format($result, 2, '.', "'") : '-';
                                $is_penalty = $result === null;
                            ?>
                                <td class="whitespace-nowrap px-3 py-4 text-xs text-center border-l border-gray-200">
                                    <div class="font-bold text-sm <?php echo $is_penalty ? 'text-red-600' : 'text-[var(--color-primary)]'; ?>"><?php echo $points; ?></div>
                                    <div class="text-xs <?php echo $is_penalty ? 'text-red-400' : 'text-gray-500 italic'; ?>"><?php echo $display_result; ?></div>
                                </td>
                            <?php endforeach; ?>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-center font-extrabold text-[var(--color-secondary)] rangliste-total-highlight border-l border-gray-200"><?php echo $current_points; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="mt-4 text-xs text-gray-500 text-center">
                (↑ = Hoch ist besser, ↓ = Zeit/Tief ist besser)
            </p>
        <?php endif; ?>

<?php include 'templates/footer.php'; ?>
