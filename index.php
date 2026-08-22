<?php
// index.php
require 'config.php';

use App\Repository\ParticipantRepository;
use App\Repository\DisciplineRepository;
use App\Repository\ResultRepository;
use App\Service\AuthService;

// Prüfung, ob der Benutzer eingeloggt ist
AuthService::requireLogin();

// Repositories initialisieren
$participantRepo = new ParticipantRepository($pdo);
$disciplineRepo = new DisciplineRepository($pdo);
$resultRepo = new ResultRepository($pdo);

$message = '';
$pruefer_username = $_SESSION['username'];
$name = '';
$teilnehmer_id = 0;
$resultat = '';
$disziplin = $_SESSION['last_disziplin'] ?? '';
$focusNameAfterSave = false;

if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    $focusNameAfterSave = true;
    unset($_SESSION['success_message']);
}

// ----------------------------------------------------
// 1. Verarbeitung des Formulars (Speichern der Daten)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_resultat'])) {
    // Daten aus dem Formular abrufen und bereinigen
    $name = trim($_POST['name'] ?? '');
    $teilnehmer_id = isset($_POST['teilnehmer_id']) ? (int)$_POST['teilnehmer_id'] : 0;
    $disziplin = trim($_POST['disziplin'] ?? '');
    $resultat = trim($_POST['resultat'] ?? '');

    if (empty($name) || empty($disziplin) || empty($resultat)) {
        $message = 'Bitte alle erforderlichen Felder ausfüllen.';
    } elseif (!is_numeric($resultat)) {
        $message = 'Das Resultat muss eine Zahl sein.';
    } elseif ($teilnehmer_id <= 0) {
        $message = 'Bitte einen Teilnehmer aus den Vorschlägen wählen (Name antippen/anklicken).';
    } elseif (!$participantRepo->isActiveParticipantId($teilnehmer_id)) {
        $message = 'Ungültiger oder inaktiver Teilnehmer. Bitte erneut aus der Liste wählen.';
    }
    // VALIDIERUNGSCHECK für die Disziplin
    elseif (!$disciplineRepo->existsActive($disziplin)) {
        $message = 'Die eingegebene Disziplin ist nicht aktiv oder unbekannt. Bitte wählen Sie eine vorgeschlagene Disziplin.';
    }
    else {
        $canonicalName = $participantRepo->getFullNameById($teilnehmer_id);
        if ($canonicalName === null) {
            $message = 'Teilnehmer nicht gefunden.';
        } elseif ($name !== $canonicalName) {
            $message = 'Name und gewählter Teilnehmer passen nicht zusammen. Bitte Namen erneut aus der Liste wählen.';
        } else {
        try {
            if ($resultRepo->insert($teilnehmer_id, $canonicalName, $disziplin, (float)$resultat, $pruefer_username)) {
                // Speichere die erfolgreich verwendete Disziplin in der Session
                $_SESSION['last_disziplin'] = $disziplin;
                // Setze die Erfolgsmeldung in die Session (für den Redirect)
                $_SESSION['success_message'] = '✅ Neuer Eintrag erfolgreich erstellt! Prüfer: ' . htmlspecialchars($pruefer_username);

                // Führe den Redirect durch (P-R-G Pattern)
                header('Location: index.php');
                exit;
            }
        } catch (PDOException $e) {
            $message = 'Ein Fehler ist aufgetreten: ' . $e->getMessage();
        }
        }
    }
}
// ... (nach der POST-Verarbeitung und dem Setzen von $message, $name, $resultat, $disziplin)

$pageTitle = 'Ergebnisse erfassen';
$pageHeading = 'Ergebnisse erfassen';

include 'templates/header.php';
?>

        <?php if ($message):
            $is_success = strpos($message, 'erfolgreich') !== false;
            $alert_bg = $is_success ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700';
            $alert_icon = $is_success ? '✅' : '❌';
        ?>
            <div class="mb-6 p-4 border rounded-lg <?php echo $alert_bg; ?>" role="alert">
                <p class="font-medium"><?php echo $alert_icon; ?> <?php echo htmlspecialchars($message); ?></p>
            </div>
        <?php endif; ?>

        <form method="POST" action="index.php" class="space-y-6">

            <div>
                <label for="teilnehmer_name" class="block text-sm font-medium text-gray-700 mb-1">Name (Auto-Complete)</label>
                <input
                    type="text"
                    id="teilnehmer_name"
                    name="name"
                    value="<?php echo htmlspecialchars($name ?? ''); ?>"
                    required
                    autocomplete="off"
                    placeholder="Namen eingeben..."
                    class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[var(--color-primary)] focus:border-[var(--color-primary)] transition duration-150 shadow-sm"
                >
                <input type="hidden" name="teilnehmer_id" id="teilnehmer_id" value="<?php echo $teilnehmer_id > 0 ? (int)$teilnehmer_id : ''; ?>">
            </div>

            <div>
                <label for="disziplin" class="block text-sm font-medium text-gray-700 mb-1">Disziplin</label>
                <input
                    type="text"
                    id="disziplin"
                    name="disziplin"
                    value="<?php echo htmlspecialchars($disziplin ?? ''); ?>"
                    required
                    placeholder="Disziplin (z.B. Weitsprung)"
                    class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[var(--color-primary)] focus:border-[var(--color-primary)] transition duration-150 shadow-sm"
                >
            </div>

            <div>
                <label for="resultat" class="block text-sm font-medium text-gray-700 mb-1">Resultat (Zahl)</label>
                <input
                    type="number"
                    step="0.01"
                    id="resultat"
                    name="resultat"
                    value="<?php echo htmlspecialchars($resultat ?? ''); ?>"
                    required
                    placeholder="Zahlwert eingeben (z.B. 3.45)"
                    class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[var(--color-primary)] focus:border-[var(--color-primary)] transition duration-150 shadow-sm"
                >
            </div>

            <button
                type="submit"
                name="submit_resultat"
                class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-lg text-lg font-semibold text-white bg-[var(--color-primary)] hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[var(--color-primary)] transition duration-200 ease-in-out transform hover:scale-[1.01]"
            >
                Ergebnis Speichern
            </button>
        </form>

<?php
$extraScripts = '
<script>
$( function() {
    var teilnehmerNameFromList = false;
    $( "#teilnehmer_name" ).autocomplete({
        minLength: 2,
        source: function(request, response) {
            $.getJSON("autocomplete_namen.php", { term: request.term })
                .done(function(data) {
                    if (data && data.error) {
                        response([]);
                        return;
                    }
                    response($.map(data, function(item) {
                        return { label: item.label, id: item.id, value: item.label };
                    }));
                })
                .fail(function() { response([]); });
        },
        select: function(event, ui) {
            teilnehmerNameFromList = true;
            $("#teilnehmer_id").val(ui.item.id);
            $(this).val(ui.item.label);
            setTimeout(function() { teilnehmerNameFromList = false; }, 0);
            return false;
        }
    });
    $("#teilnehmer_name").on("input", function() {
        if (!teilnehmerNameFromList) {
            $("#teilnehmer_id").val("");
        }
    });
    $( "#disziplin" ).autocomplete({
        source: "autocomplete_disziplinen.php",
        minLength: 2
    });
    ' . ($focusNameAfterSave ? '$("#teilnehmer_name").trigger("focus");' : '') . '
} );
</script>
';
include 'templates/footer.php';
?>
