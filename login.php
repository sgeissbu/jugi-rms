<?php
// login.php
require 'config.php';

use App\Service\AuthService;

$message = '';
$authService = new AuthService($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $message = 'Bitte Benutzername und Passwort eingeben.';
    } else {
        if ($authService->login($username, $password)) {
            header('Location: index.php');
            exit;
        } else {
            $message = 'Ungültiger Benutzername oder Passwort.';
        }
    }
}

$pageTitle = 'Login';
include 'templates/header.php';
?>

        <?php if ($message):
            $alert_bg = 'bg-red-100 border-red-400 text-red-700';
            $alert_icon = '❌';
        ?>
            <div class="mb-6 p-4 border rounded-lg <?php echo $alert_bg; ?>" role="alert">
                <p class="font-medium"><?php echo $alert_icon; ?> <?php echo htmlspecialchars($message); ?></p>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="space-y-6">
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Benutzername</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    required
                    placeholder="Dein Benutzername"
                    class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[var(--color-primary)] focus:border-[var(--color-primary)] transition duration-150 shadow-sm"
                    value="<?php echo htmlspecialchars($username ?? ''); ?>"
                >
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Passwort</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    placeholder="Passwort eingeben"
                    class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[var(--color-primary)] focus:border-[var(--color-primary)] transition duration-150 shadow-sm"
                >
            </div>

            <button
                type="submit"
                class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-lg text-lg font-semibold text-white bg-[var(--color-primary)] hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[var(--color-primary)] transition duration-200 ease-in-out transform hover:scale-[1.01]"
            >
                Einloggen
            </button>
        </form>

<?php
include 'templates/footer.php';
?>
