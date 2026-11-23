<?php
// erfassen_api.php – JSON-Schnittstelle der Erfassung (Speichern ohne Neuladen, Rückgängig).
// POST, Body JSON, Header X-CSRF-Token. Antwort immer JSON: { ok: true, ... } oder { ok: false, error: "…" }.
require 'config.php';

use App\Config\DisciplineMeta;
use App\Config\RankingConfig;
use App\Repository\DisciplineRepository;
use App\Repository\ParticipantRepository;
use App\Repository\ResultRepository;
use App\Service\AuthService;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
AuthService::requireLoginJson();

function respond(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['ok' => false, 'error' => 'Nur POST erlaubt.'], 405);
}
if (!csrfCheck()) {
    respond(['ok' => false, 'error' => 'Sitzung abgelaufen – bitte Seite neu laden.', 'reload' => true], 403);
}
$in = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($in)) {
    respond(['ok' => false, 'error' => 'Ungültige Anfrage.'], 400);
}

$pruefer = (string)$_SESSION['username'];
$resultRepo = new ResultRepository($pdo);
$action = $in['action'] ?? '';

try {
    if ($action === 'save') {
        $teilnehmerId = (int)($in['teilnehmer_id'] ?? 0);
        $disziplin = trim((string)($in['disziplin'] ?? ''));
        $participantRepo = new ParticipantRepository($pdo);
        $discipline = (new DisciplineRepository($pdo))->getActiveByName($disziplin);

        if ($teilnehmerId <= 0 || !$participantRepo->isActiveParticipantId($teilnehmerId)) {
            respond(['ok' => false, 'error' => 'Kind nicht gefunden oder nicht mehr aktiv.'], 422);
        }
        if ($discipline === null) {
            respond(['ok' => false, 'error' => 'Disziplin «' . $disziplin . '» ist nicht aktiv.'], 422);
        }
        $unit = DisciplineMeta::unitFor($discipline, RankingConfig::getSemesterKeyForDate());
        $value = DisciplineMeta::parse($in['resultat'] ?? '', $unit);
        if ($value === null) {
            respond(['ok' => false, 'error' => 'Resultat ist keine gültige Zahl' . (DisciplineMeta::unitKind($unit) === 'time' ? ' oder Zeit (80 / 1:20)' : '') . '.'], 422);
        }
        $name = (string)$participantRepo->getFullNameById($teilnehmerId);
        $id = $resultRepo->insert($teilnehmerId, $name, $discipline['name'], $value, $pruefer);

        respond(['ok' => true, 'id' => $id, 'resultat' => $value, 'name' => $name, 'disziplin' => $discipline['name']]);
    }

    if ($action === 'delete') {
        $id = (int)($in['id'] ?? 0);
        if ($id <= 0 || !$resultRepo->deleteOwnFromToday($id, $pruefer)) {
            respond(['ok' => false, 'error' => 'Nur eigene Einträge von heute können zurückgenommen werden.'], 422);
        }
        respond(['ok' => true]);
    }

    respond(['ok' => false, 'error' => 'Unbekannte Aktion.'], 400);
} catch (Throwable $e) {
    error_log('erfassen_api: ' . $e->getMessage());
    respond(['ok' => false, 'error' => 'Speichern fehlgeschlagen (Serverfehler).'], 500);
}
