<?php
// db_check.php
require 'config.php';
requireDevelopmentEnvironment();

try {
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<h1>Struktur der Tabelle 'users':</h1><pre>";
    print_r($columns);
    echo "</pre>";
} catch (Exception $e) {
    echo "Fehler: " . $e->getMessage();
}
?>
