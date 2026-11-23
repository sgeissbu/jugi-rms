<?php
// login.php
require 'config.php';

use App\Config\BrandConfig;
use App\Service\AuthService;

$message = '';
$username = '';
$authService = new AuthService($pdo);

if (AuthService::isLoggedIn() && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lockoutRemaining = $authService->loginLockoutRemaining();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($lockoutRemaining > 0) {
        $message = 'Zu viele Fehlversuche. Bitte in ' . (int)ceil($lockoutRemaining / 60) . ' Minute(n) erneut versuchen.';
    } elseif (!csrfCheck()) {
        $message = 'Ungültige Anfrage. Bitte Seite neu laden und erneut versuchen.';
    } elseif ($username === '' || $password === '') {
        $message = 'Bitte Benutzername und Passwort eingeben.';
    } elseif ($authService->login($username, $password)) {
        header('Location: index.php');
        exit;
    } else {
        $message = 'Benutzername oder Passwort stimmt nicht.';
    }
}

$pageTitle = 'Anmelden';
$bareLayout = true;
$csrfToken = csrfToken();
include 'templates/header.php';
?>
<div class="login">
    <div class="card">
        <div class="head">
            <?php echo BrandConfig::logoHtml(''); ?>
            <h1>Ergebnisse</h1>
            <p class="sub"><?php echo e(BrandConfig::getAppName()); ?></p>
        </div>
        <?php if ($message): ?>
            <div class="alert err" role="alert"><?php echo icon('info') . e($message); ?></div>
        <?php endif; ?>
        <form method="POST" action="login.php">
            <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
            <div class="field">
                <label for="username">Benutzername</label>
                <input class="inp" id="username" name="username" required autocomplete="username" autocapitalize="off" value="<?php echo e($username); ?>">
            </div>
            <div class="field">
                <label for="password">Passwort</label>
                <input class="inp" id="password" name="password" type="password" required autocomplete="current-password">
            </div>
            <button class="btn primary" type="submit">Anmelden</button>
        </form>
    </div>
</div>
<?php include 'templates/footer.php'; ?>
