<?php
// create_user.php
// Browser-basiertes Werkzeug zum Anlegen von Login-Benutzern (Pruefer).
// Geschuetzt durch SETUP_SECRET aus .env, da TablePlus/phpMyAdmin allein
// keinen bequemen Weg bieten, ein bcrypt-Passwort-Hash zu erzeugen.
require 'config.php';

$configuredSecret = trim((string)getenv('SETUP_SECRET'));
$providedSecret = trim((string)($_POST['secret'] ?? $_GET['secret'] ?? ''));

if ($configuredSecret === '' || $providedSecret === '' || !hash_equals($configuredSecret, $providedSecret)) {
    http_response_code(404);
    exit('Nicht gefunden.');
}

$message = '';
$isSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $message = 'Bitte Benutzername und Passwort eingeben.';
    } elseif (strlen($password) < 8) {
        $message = 'Das Passwort muss mindestens 8 Zeichen lang sein.';
    } else {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
        $stmt->execute([$username]);
        if ((int)$stmt->fetchColumn() > 0) {
            $message = 'Dieser Benutzername existiert bereits.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO users (username, password) VALUES (?, ?)');
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
            $isSuccess = true;
            $message = 'Benutzer "' . $username . '" wurde erfolgreich angelegt.';
        }
    }
}

$pageTitle = 'Benutzer anlegen';
$bareLayout = true;
include 'templates/header.php';
?>
<div class="login">
    <div class="card">
        <div class="head">
            <?php echo \App\Config\BrandConfig::logoHtml(''); ?>
            <h1>Benutzer anlegen</h1>
            <p class="sub">Login für Leiterinnen und Leiter (Prüfer)</p>
        </div>
        <?php if ($message): ?>
            <div class="alert <?php echo $isSuccess ? 'ok' : 'err'; ?>" role="alert"><?php echo icon($isSuccess ? 'check' : 'info') . e($message); ?></div>
        <?php endif; ?>
        <form method="POST" action="<?php echo e('create_user.php?secret=' . urlencode($providedSecret)); ?>">
            <input type="hidden" name="secret" value="<?php echo e($providedSecret); ?>">
            <div class="field">
                <label for="username">Benutzername</label>
                <input class="inp" id="username" name="username" required autocomplete="off" autocapitalize="off" placeholder="z.B. mmuster">
            </div>
            <div class="field">
                <label for="password">Passwort (mind. 8 Zeichen)</label>
                <input class="inp" id="password" name="password" type="password" required minlength="8" autocomplete="new-password">
            </div>
            <button class="btn primary" type="submit">Benutzer anlegen</button>
        </form>
        <p class="sub" style="margin-top:14px"><a href="login.php">Zur Anmeldung</a></p>
    </div>
</div>
<?php include 'templates/footer.php'; ?>
