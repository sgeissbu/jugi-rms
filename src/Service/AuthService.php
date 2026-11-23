<?php

namespace App\Service;

use PDO;

class AuthService {
    private $pdo;

    /** Brute-Force-Schutz: IP-basiert, Datei-persistiert (nicht in Git, siehe .gitignore-Muster für Dotfiles). */
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOGIN_LOCKOUT_SECONDS = 900; // 15 Minuten
    private const ATTEMPTS_FILE = __DIR__ . '/../../.login_attempts.json';

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Versucht einen Benutzer einzuloggen.
     */
    public function login(string $username, string $password): bool {
        if ($this->loginLockoutRemaining() > 0) {
            return false;
        }

        $stmt = $this->pdo->prepare('SELECT id, password FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $this->clearFailedAttempts();
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $username;
            return true;
        }

        $this->recordFailedAttempt();
        return false;
    }

    /**
     * Verbleibende Sekunden bis zum nächsten erlaubten Loginversuch für die aktuelle IP, 0 = erlaubt.
     */
    public function loginLockoutRemaining(): int {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $attempts = $this->readAttempts();
        if (!isset($attempts[$ip])) {
            return 0;
        }
        [$count, $first] = $attempts[$ip];
        if ($count < self::MAX_LOGIN_ATTEMPTS) {
            return 0;
        }

        return max(0, self::LOGIN_LOCKOUT_SECONDS - (time() - $first));
    }

    private function readAttempts(): array {
        if (!file_exists(self::ATTEMPTS_FILE)) {
            return [];
        }
        $raw = @file_get_contents(self::ATTEMPTS_FILE);

        return $raw ? (json_decode($raw, true) ?: []) : [];
    }

    private function writeAttempts(array $attempts): void {
        $now = time();
        foreach ($attempts as $ip => $entry) {
            if (($now - $entry[1]) > self::LOGIN_LOCKOUT_SECONDS) {
                unset($attempts[$ip]);
            }
        }
        @file_put_contents(self::ATTEMPTS_FILE, json_encode($attempts), LOCK_EX);
    }

    private function recordFailedAttempt(): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $attempts = $this->readAttempts();
        $now = time();
        if (!isset($attempts[$ip]) || ($now - $attempts[$ip][1]) > self::LOGIN_LOCKOUT_SECONDS) {
            $attempts[$ip] = [1, $now];
        } else {
            $attempts[$ip][0]++;
        }
        $this->writeAttempts($attempts);
    }

    private function clearFailedAttempts(): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $attempts = $this->readAttempts();
        unset($attempts[$ip]);
        $this->writeAttempts($attempts);
    }

    /**
     * Loggt den Benutzer aus.
     */
    public function logout(): void {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }

    /**
     * Prüft, ob ein Benutzer eingeloggt ist.
     */
    public static function isLoggedIn(): bool {
        return isset($_SESSION['user_id']);
    }

    /**
     * Erzwingt den Login (Redirect).
     */
    /**
     * Für JSON-Schnittstellen: ohne Login 401 mit JSON-Antwort statt Weiterleitung.
     */
    public static function requireLoginJson(): void {
        if (!self::isLoggedIn()) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Bitte neu anmelden.', 'login' => true]);
            exit;
        }
    }

    public static function requireLogin(): void {
        if (!self::isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }
}
