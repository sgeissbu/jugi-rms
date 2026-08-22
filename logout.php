<?php
// logout.php
require 'config.php';

use App\Service\AuthService;

$authService = new AuthService($pdo);
$authService->logout();

header('Location: login.php');
exit;
