<?php
// autocomplete_namen.php
require 'config.php';

use App\Repository\ParticipantRepository;
use App\Service\AuthService;

if (!AuthService::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Nicht autorisiert.']);
    exit;
}

header('Content-Type: application/json');

$term = $_GET['term'] ?? '';
if (empty($term)) {
    echo json_encode([]);
    exit;
}

$participantRepo = new ParticipantRepository($pdo);
try {
    $rows = $participantRepo->searchWithIds($term, 10, true);
    $out = [];
    foreach ($rows as $row) {
        $out[] = [
            'id' => (int)$row['id'],
            'label' => $row['label'],
        ];
    }
    echo json_encode($out);
} catch (Exception $e) {
    error_log('Fehler im Autocomplete: ' . $e->getMessage());
    echo json_encode(['error' => 'Fehler']);
}
