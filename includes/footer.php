<?php
if (!class_exists('ConfigManager')) {
    include_once __DIR__ . '/config_init.php';
}
$_footer_cfg = ConfigManager::getInstance()->getArray('footer', []);
$_footer_institute_name   = $_footer_cfg['institute_name']   ?? '';
$_footer_institute_url    = $_footer_cfg['institute_url']    ?? '';
$_footer_license_name     = $_footer_cfg['license_name']     ?? '';
$_footer_license_url      = $_footer_cfg['license_url']      ?? '';
$_footer_copyright_holder = $_footer_cfg['copyright_holder'] ?? '';
$_footer_links            = $_footer_cfg['links']            ?? [];

if (!isset($admin_email) || empty($admin_email)) {
    $admin_email = ConfigManager::getInstance()->getString('admin_email', 'admin@example.com');
}
?>
<footer>
    <div class="footer-content">
        <!-- Left: Institute Info -->
        <div class="footer-section">
            <div class="footer-info">
                <div class="footer-text">
                    <?php if ($_footer_institute_name !== ''): ?>
                        <?php if ($_footer_institute_url !== ''): ?>
                            <strong><a href="<?= htmlspecialchars($_footer_institute_url) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($_footer_institute_name) ?></a></strong>
                        <?php else: ?>
                            <strong><?= htmlspecialchars($_footer_institute_name) ?></strong>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Center: Contact -->
        <div class="footer-section footer-center">
            <div class="footer-text">
                <p>Questions, issues, requests, or missing/incorrect data?</p>
                <p>
                    <a href="mailto:<?= htmlspecialchars($admin_email) ?>">
                        <i class="fas fa-envelope"></i> Contact Administrator
                    </a>
                </p>
            </div>
        </div>

        <!-- Right: Links and License -->
        <div class="footer-section footer-right">
            <div class="footer-text">
                <?php foreach ($_footer_links as $link): ?>
                    <?php if (!empty($link['label']) && !empty($link['url'])): ?>
                    <p>
                        <a href="<?= htmlspecialchars($link['url']) ?>" target="_blank" rel="noopener">
                            <?= htmlspecialchars($link['label']) ?>
                        </a>
                    </p>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if ($_footer_license_name !== '' || $_footer_copyright_holder !== ''): ?>
                <p>
                    <?php if ($_footer_license_name !== ''): ?>
                        <span class="footer-meta">License:
                            <?php if ($_footer_license_url !== ''): ?>
                                <a href="<?= htmlspecialchars($_footer_license_url) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($_footer_license_name) ?></a>
                            <?php else: ?>
                                <?= htmlspecialchars($_footer_license_name) ?>
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($_footer_copyright_holder !== ''): ?>
                        <span class="footer-meta">© <?= date('Y') ?> <?= htmlspecialchars($_footer_copyright_holder) ?></span>
                    <?php endif; ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</footer>
</body>
</html>
