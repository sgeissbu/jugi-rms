<?php
// templates/header.php – gemeinsamer Seitenkopf.
// Variablen der Seite: $pageTitle, $activeNav ('erfassen' | 'uebersicht' | 'rangliste'), $bareLayout (true = ohne Navigation, z. B. Login),
// $wideLayout (true = volle Breite für grosse Tabellen).
use App\Config\BrandConfig;

$appName = BrandConfig::getAppName();
$brand = BrandConfig::getPrimaryColor();
$accent = BrandConfig::getSecondaryColor();
$activeNav = $activeNav ?? '';
$bareLayout = $bareLayout ?? false;
$navItems = [
    'erfassen' => ['index.php', 'pen', 'Erfassen'],
    'uebersicht' => ['resultsoverview.php', 'table', 'Übersicht'],
    'rangliste' => ['rangliste.php', 'trophy', 'Rangliste'],
];
$username = $_SESSION['username'] ?? '';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?php echo e(isset($pageTitle) ? $pageTitle . ' · ' . $appName : $appName); ?></title>
    <meta name="theme-color" content="<?php echo e($brand); ?>">
    <link rel="icon" href="<?php echo e(BrandConfig::getFaviconWebPath()); ?>">
    <link rel="stylesheet" href="<?php echo e(asset('assets/css/app.css')); ?>">
    <style>:root { --brand: <?php echo e($brand); ?>; --accent: <?php echo e($accent); ?>; --on-brand: <?php echo BrandConfig::textOn($brand); ?>; --on-accent: <?php echo BrandConfig::textOn($accent); ?>; }</style>
</head>
<body>
<?php if (!$bareLayout && isset($_SESSION['user_id'])): ?>
<header class="top">
    <a href="index.php"><?php echo BrandConfig::logoHtml('logo'); ?></a>
    <span class="app"><b>Ergebnisse</b><small><?php echo e($appName); ?></small></span>
    <nav class="tabs" aria-label="Hauptnavigation">
        <?php foreach ($navItems as $key => [$href, $ico, $label]): ?>
            <a href="<?php echo $href; ?>"<?php echo $activeNav === $key ? ' class="active" aria-current="page"' : ''; ?>><?php echo icon($ico) . e($label); ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="user">
        <span class="avatar" title="Prüfer: <?php echo e($username); ?>"><?php echo e(mb_substr($username, 0, 2)); ?></span>
        <a class="icon-btn" href="logout.php" title="Abmelden"><?php echo icon('logout'); ?><span class="txt">Abmelden</span></a>
    </div>
</header>
<?php endif; ?>
<main<?php echo !empty($wideLayout) ? ' class="wide"' : ''; ?>>
