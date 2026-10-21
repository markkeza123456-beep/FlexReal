<?php $currentLang = $langCode ?? ($_SESSION['site_lang'] ?? 'th'); ?>
<div class="flh-language" aria-label="Language switcher"><span aria-hidden="true">🌐</span><a href="?lang=th" lang="th" class="<?= $currentLang === 'th' ? 'active' : '' ?>">TH</a><span>|</span><a href="?lang=en" lang="en" class="<?= $currentLang === 'en' ? 'active' : '' ?>">EN</a></div>
