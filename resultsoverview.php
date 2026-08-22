<?php
// resultsoverview.php
require 'config.php';

use App\Repository\ParticipantRepository;
use App\Repository\DisciplineRepository;
use App\Repository\ResultRepository;
use App\Service\AuthService;

// Prüfung, ob der Benutzer eingeloggt ist
AuthService::requireLogin();

$participantRepo = new ParticipantRepository($pdo);
$disciplineRepo = new DisciplineRepository($pdo);
$resultRepo = new ResultRepository($pdo);

$error_message = '';
$table_data_available = false;

try {
    $disciplines = $disciplineRepo->getActive();
    $best_results_raw = $resultRepo->getBestResultsPerParticipant();

    $results_map = [];
    foreach ($best_results_raw as $row) {
        $results_map[$row['teilnehmer_name']][$row['disziplin']] = $row['best_resultat'];
    }

    $all_participants = $participantRepo->getAllFullNames(true);

    if (empty($all_participants)) {
        $error_message = 'Keine aktiven Teilnehmer in der Datenbank gefunden.';
    } elseif (empty($disciplines)) {
        $error_message = 'Keine Disziplinen in der Datenbank gefunden.';
    } else {
        $table_data_available = true;
    }

} catch (PDOException $e) {
    $error_message = 'Datenbankfehler beim Abrufen der Übersicht: ' . $e->getMessage();
}

$pageTitle = 'Ergebnisübersicht';
$pageHeading = 'Ergebnisübersicht';
$containerWidthClass = 'max-w-screen-2xl';
include 'templates/header.php';
?>

        <?php if ($error_message): ?>
            <div class="mb-6 p-4 border rounded-lg bg-red-100 border-red-400 text-red-700" role="alert">
                <p class="font-medium">❌ <?php echo htmlspecialchars($error_message); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($table_data_available): ?>
          <div class="mb-4">
              <div class="relative">
                  <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                      <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                  </span>
                  <input type="text" id="participantSearch" placeholder="Teilnehmer suchen..." 
                         class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg bg-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] focus:border-[var(--color-primary)] sm:text-sm transition duration-150 shadow-sm">
              </div>
          </div>

          <div class="table-container table-container--overview shadow-xl rounded-lg border border-gray-200">
              <table class="min-w-full divide-y divide-gray-300 ranking-table">
                  <thead class="sticky-header text-white">
                      <tr>
                          <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold sticky-col">
                              Name
                          </th>
                          <?php foreach ($disciplines as $d): ?>
                              <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold">
                                  <?php echo htmlspecialchars($d['name']); ?>
                                  <span class="text-[10px] font-normal opacity-75 block leading-tight">
                                      (<?php echo $d['hoch_ist_besser'] ? '↑' : '↓'; ?>)
                                  </span>
                              </th>
                          <?php endforeach; ?>
                      </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-200 bg-white">
                      <?php foreach ($all_participants as $participant_name): ?>
                      <tr class="hover:bg-blue-50 transition-colors duration-75">
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 sticky-col bg-inherit border-r border-gray-200">
                              <?php echo htmlspecialchars($participant_name); ?>
                          </td>
                          <?php foreach ($disciplines as $d):
                              $discipline_name = $d['name'];
                              $result = $results_map[$participant_name][$discipline_name] ?? null;
                              $display_result = $result !== null ? number_format($result, 2, '.', "'") : '-';
                              $class = $result !== null ? 'font-semibold text-gray-800' : 'text-gray-400';
                          ?>
                              <td class="whitespace-nowrap px-3 py-4 text-sm text-center <?php echo $class; ?>">
                                  <?php echo $display_result; ?>
                              </td>
                          <?php endforeach; ?>
                      </tr>
                      <?php endforeach; ?>
                  </tbody>
              </table>
          </div>
          <p class="mt-4 text-xs text-gray-500 text-center">
              Nutze das Suchfeld, um schnell nach Teilnehmern zu filtern. (↑ = Hoch ist besser, ↓ = Zeit/Tief ist besser)
          </p>
        <?php endif; ?>

<?php 
$extraScripts = '<script src="assets/js/resultsoverview.js"></script>';
include 'templates/footer.php'; 
?>
