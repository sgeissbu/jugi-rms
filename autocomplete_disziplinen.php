<?php
// autocomplete_disziplinen.php
require 'config.php';

use App\Repository\DisciplineRepository;
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

$disciplineRepo = new DisciplineRepository($pdo);
try {
    echo json_encode($disciplineRepo->search($term, 10, true));
} catch (Exception $e) {
    error_log("Fehler im Autocomplete: " . $e->getMessage());
    echo json_encode(['error' => 'Fehler']);
}
