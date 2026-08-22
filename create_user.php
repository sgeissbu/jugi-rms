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
$pageHeading = 'Benutzer anlegen';
include 'templates/header.php';
?>

        <?php if ($message):
            $alert_bg = $isSuccess ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700';
            $alert_icon = $isSuccess ? '✅' : '❌';
        ?>
            <div class="mb-6 p-4 border rounded-lg <?php echo $alert_bg; ?>" role="alert">
                <p class="font-medium"><?php echo $alert_icon; ?> <?php echo htmlspecialchars($message); ?></p>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo htmlspecialchars('create_user.php?secret=' . urlencode($providedSecret)); ?>" class="space-y-6">
            <input type="hidden" name="secret" value="<?php echo htmlspecialchars($providedSecret); ?>">
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Benutzername</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    required
                    placeholder="z.B. mmuster"
                    class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[var(--color-primary)] focus:border-[var(--color-primary)] transition duration-150 shadow-sm"
                >
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Passwort (mind. 8 Zeichen)</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    minlength="8"
                    placeholder="Passwort eingeben"
                    class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[var(--color-primary)] focus:border-[var(--color-primary)] transition duration-150 shadow-sm"
                >
            </div>

            <button
                type="submit"
                class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-lg text-lg font-semibold text-white bg-[var(--color-primary)] hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[var(--color-primary)] transition duration-200 ease-in-out transform hover:scale-[1.01]"
            >
                Benutzer anlegen
            </button>
        </form>

<?php
include 'templates/footer.php';
?>
