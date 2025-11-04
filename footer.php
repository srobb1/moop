<footer style="background-color: #f8f9fa; border-top: 1px solid #dee2e6; width: 100vw; margin-left: calc(-50vw + 50%); padding: 2rem 3rem;">
    <div style="display: flex; justify-content: space-around; align-items: center; gap: 2rem;">
        <!-- Left: Logo and Institute Info -->
        <div style="flex: 1;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 50px; height: 50px; background-color: #f0f0f0; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 12px; color: #999; flex-shrink: 0;">
                    Logo
                </div>
                <div style="font-size: 0.875rem; color: #666;">
                    <strong>Hosting Institute</strong><br>
                    <span style="font-size: 0.85rem;">Institute Name Here</span>
                </div>
            </div>
        </div>

        <!-- Center: Support and Links -->
        <div style="flex: 1; text-align: center;">
            <div style="font-size: 0.875rem; color: #666;">
                <p style="margin-bottom: 0.5rem;">Questions, issues, requests, or missing/incorrect data?</p>
                <p style="margin-bottom: 0;">
                    <a href="mailto:<?= htmlspecialchars($admin_email ?? 'admin@example.com') ?>" style="color: #0066cc; text-decoration: none;">
                        <i class="fas fa-envelope"></i> Contact Administrator
                    </a>
                </p>
            </div>
        </div>

        <!-- Right: Links and License -->
        <div style="flex: 1; text-align: right;">
            <div style="font-size: 0.875rem; color: #666;">
                <p style="margin-bottom: 0.5rem;">
                    <a href="https://github.com/gboncoraglio/moop" target="_blank" rel="noopener" style="color: #0066cc; text-decoration: none;">
                        <i class="fab fa-github"></i> MOOP on GitHub
                    </a>
                </p>
                <p style="margin-bottom: 0;">
                    <span style="display: block;">License: <a href="#" style="color: #0066cc; text-decoration: none;">Placeholder License</a></span>
                    <span style="display: block; font-size: 0.8rem; margin-top: 0.25rem;">© <?= date('Y') ?> All rights reserved</span>
                </p>
            </div>
        </div>
    </div>
</footer>

<style>
/* Footer Styling */
footer {
    background-color: #f8f9fa;
    border-top: 1px solid #dee2e6;
}

footer a {
    color: #0066cc;
    text-decoration: none;
    transition: color 0.2s ease;
}

footer a:hover {
    color: #0052a3;
}

footer strong {
    color: #333;
}

footer p {
    font-size: 0.875rem;
    color: #666;
    line-height: 1.6;
}

footer small {
    font-size: 0.8rem;
    color: #999;
}
</style>
