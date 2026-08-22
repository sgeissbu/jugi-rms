<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? \App\Config\BrandConfig::getAppName(); ?></title>

    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="assets/css/table-layout.css">
    <?php if (!empty($extraStylesheets) && is_array($extraStylesheets)): ?>
        <?php foreach ($extraStylesheets as $cssHref): ?>
            <link rel="stylesheet" href="<?php echo htmlspecialchars($cssHref, ENT_QUOTES, 'UTF-8'); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.3/themes/base/jquery-ui.css">
    <style>:root { --color-primary: <?php echo \App\Config\BrandConfig::getPrimaryColor(); ?>; --color-secondary: <?php echo \App\Config\BrandConfig::getSecondaryColor(); ?>; }</style>
</head>
<?php
// Erlaubt es jeder Seite, ihre eigene Breite für den weißen Rahmen festzulegen
// Default ist 'max-w-md' (für Login und Erfassung)
$containerWidthClass = $containerWidthClass ?? 'max-w-md';
?>
<body class="bg-[var(--color-primary)] min-h-screen flex flex-col items-center py-8 px-4">
    <div class="w-full <?php echo $containerWidthClass; ?> bg-white p-6 md:p-8 rounded-xl shadow-2xl">
        <div class="flex justify-center mb-6">
            <div class="w-24 sm:w-32 lg:w-40 max-w-[150px]">
                <img src="<?php echo htmlspecialchars(\App\Config\BrandConfig::getLogoWebPath(), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars(\App\Config\BrandConfig::getAppName(), ENT_QUOTES, 'UTF-8'); ?>" class="w-full h-auto">
            </div>
        </div>
        <div class="flex flex-col md:justify-between md:items-start mb-6 border-b pb-4">
            <div class="w-full md:w-auto mb-4">
                <h1 class="text-2xl font-bold text-gray-800"><?php echo $pageHeading ?? 'Ergebnisse erfassen'; ?></h1>
                <?php if (isset($_SESSION['username'])): ?>
                    <p class="text-sm text-gray-500 mt-1">Prüfer: <span class="font-semibold text-[var(--color-primary)]"><?php echo htmlspecialchars($_SESSION['username']); ?></span></p>
                <?php endif; ?>
            </div>
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="flex flex-row space-x-2 w-full">
                     <a href="index.php" class="flex-1 text-sm font-medium text-center text-gray-600 hover:text-[var(--color-primary)] transition duration-150 py-2 px-1 border border-gray-300 rounded-lg whitespace-nowrap">Erfassen</a>
                     <a href="resultsoverview.php" class="flex-1 text-sm font-medium text-center text-gray-600 hover:text-[var(--color-primary)] transition duration-150 py-2 px-1 border border-gray-300 rounded-lg whitespace-nowrap">Übersicht</a>
                     <a href="rangliste.php" class="flex-1 text-sm font-medium text-center text-gray-600 hover:text-[var(--color-primary)] transition duration-150 py-2 px-1 border border-gray-300 rounded-lg whitespace-nowrap">Ranglisten</a>
                     <a href="logout.php" class="flex-1 text-sm font-medium text-center text-red-600 hover:text-red-800 transition duration-150 py-2 px-1 border border-gray-300 rounded-lg whitespace-nowrap">Logout</a>
                </div>
            <?php endif; ?>
        </div>
