<?php foreach (($pageScripts ?? []) as $script): ?>
    <script src="<?= htmlspecialchars((string) $script, ENT_QUOTES, 'UTF-8') ?>" defer></script>
<?php endforeach; ?>
<?php require __DIR__ . "/language_switcher.php"; ?><script src="assets/js/responsive-language.js?v=20261021-session-language" defer></script>
</body>
</html>
