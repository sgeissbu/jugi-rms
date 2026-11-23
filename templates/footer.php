<?php
// templates/footer.php – Navigation unten (Handy) und Skripte der Seite ($extraScripts).
?>
</main>
<?php if (!($bareLayout ?? false) && isset($_SESSION['user_id'])): ?>
<nav class="tabbar" aria-label="Hauptnavigation">
    <?php foreach ($navItems as $key => [$href, $ico, $label]): ?>
        <a href="<?php echo $href; ?>"<?php echo ($activeNav ?? '') === $key ? ' class="active" aria-current="page"' : ''; ?>><?php echo icon($ico) . e($label); ?></a>
    <?php endforeach; ?>
</nav>
<?php endif; ?>
<?php if (isset($extraScripts)) echo $extraScripts; ?>
</body>
</html>
