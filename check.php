<?php
// check.php
require_once __DIR__ . '/config.php';
requireDevelopmentEnvironment();

echo "<h1>Diagnose</h1>";

// 1. Prüfe Verzeichnisse
$folders = ['src', 'templates', 'vendor'];
foreach ($folders as $folder) {
    if (is_dir(__DIR__ . '/' . $folder)) {
        echo "✅ Ordner '$folder' gefunden.<br>";
    } else {
        echo "❌ Ordner '$folder' FEHLT!<br>";
    }
}

// 2. Prüfe .env Datei
if (file_exists(__DIR__ . '/.env')) {
    echo "✅ .env Datei gefunden.<br>";
} else {
    echo "❌ .env Datei FEHLT! (Wichtig für Datenbank)<br>";
}

// 3. Prüfe Autoloader
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "✅ vendor/autoload.php gefunden.<br>";
} else {
    echo "❌ vendor/autoload.php FEHLT!<br>";
}

// 4. Teste Klassen-Laden
try {
    echo "✅ config.php geladen.<br>";

    $db = \App\Core\Database::getInstance();
    echo "✅ Datenbank-Klasse erfolgreich geladen.<br>";
    $pdo = $db->getConnection();
    echo "✅ Datenbank-Verbindung steht.<br>";
} catch (Throwable $e) {
    echo "❌ Fehler: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>
