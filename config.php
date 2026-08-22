<?php
// config.php
require_once __DIR__ . '/vendor/autoload.php';

// Fallback-Autoloader fuer App\*, falls Composer-Autoload auf dem Zielsystem nicht aktuell ist.
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = __DIR__ . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

/**
 * Hilfsfunktion zum Laden der .env Datei
 */
function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $name = trim($parts[0]);
            $value = trim($parts[1]);
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
        }
    }
}

// Die .env Datei laden
loadEnv(__DIR__ . '/.env');

/**
 * Liest boolesche ENV-Werte robust aus.
 */
function getEnvBool(string $name, bool $default = false): bool {
    $value = getenv($name);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
}

/**
 * Liefert den aktuellen App-Umgebungsnamen.
 */
function getAppEnvironment(): string {
    return strtolower((string)(getenv('APP_ENV') ?: 'production'));
}

/**
 * Hilfsfunktion für dev-only Endpunkte.
 */
function isDevelopmentEnvironment(): bool {
    return in_array(getAppEnvironment(), ['dev', 'development', 'local', 'test'], true);
}

/**
 * Blockiert Zugriff ausserhalb von Dev/Test.
 */
function requireDevelopmentEnvironment(): void {
    if (!isDevelopmentEnvironment()) {
        http_response_code(404);
        exit('Nicht gefunden.');
    }
}

/**
 * Zentrale PHP-Error-Konfiguration auf Basis von ENV.
 */
function configureErrorHandling(): void {
    $appEnv = getAppEnvironment();
    $debug = getEnvBool('APP_DEBUG', $appEnv !== 'production');

    error_reporting(E_ALL);
    ini_set('display_errors', $debug ? '1' : '0');
    ini_set('log_errors', '1');

    $errorLog = getenv('PHP_ERROR_LOG');
    if ($errorLog !== false && $errorLog !== '') {
        ini_set('error_log', $errorLog);
    }
}

configureErrorHandling();

$secureCookie = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $secureCookie,
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

// Datenbank-Instanz initialisieren
// Database::getInstance() lädt die Umgebungsvariablen intern über getenv()
try {
    $db = \App\Core\Database::getInstance();
    $pdo = $db->getConnection();
} catch (Throwable $e) {
    error_log('Database bootstrap failed: ' . $e->getMessage());
    if (isDevelopmentEnvironment()) {
        throw $e;
    }

    http_response_code(500);
    exit('Interner Serverfehler.');
}
