<footer>
    <div class="footer-content">
        <!-- Left: Logo and Institute Info -->
        <div class="footer-section">
            <div class="footer-info">
                <div class="footer-logo">
                    Logo
                </div>
                <div class="footer-text">
                    <strong>Hosting Institute</strong><br>
                    <span class="footer-subtitle">Institute Name Here</span>
                </div>
            </div>
        </div>

        <!-- Center: Support and Links -->
        <div class="footer-section footer-center">
            <div class="footer-text">
                <p>Questions, issues, requests, or missing/incorrect data?</p>
                <p>
                    <?php
                    // Get admin email from ConfigManager if not already set
                    if (!isset($admin_email) || empty($admin_email)) {
                        if (!class_exists('ConfigManager')) {
                            include_once __DIR__ . '/config_init.php';
                        }
                        $config = ConfigManager::getInstance();
                        $admin_email = $config->getString('admin_email', 'admin@example.com');
                    }
                    ?>
                    <a href="mailto:<?= htmlspecialchars($admin_email) ?>">
                        <i class="fas fa-envelope"></i> Contact Administrator
                    </a>
                </p>
            </div>
        </div>

        <!-- Right: Links and License -->
        <div class="footer-section footer-right">
            <div class="footer-text">
                <p>
                    <a href="https://github.com/gboncoraglio/moop" target="_blank" rel="noopener">
                        <i class="fab fa-github"></i> MOOP on GitHub
                    </a>
                </p>
                <p>
                    <span class="footer-meta">License: <a href="#">Placeholder License</a></span>
                    <span class="footer-meta">© <?= date('Y') ?> All rights reserved</span>
                </p>
            </div>
        </div>
    </div>
</footer>
