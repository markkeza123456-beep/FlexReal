<?php foreach (($pageScripts ?? []) as $script): ?>
    <script src="<?= htmlspecialchars((string) $script, ENT_QUOTES, 'UTF-8') ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
