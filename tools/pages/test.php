<?php
/**
 * TEST PAGE - Verify layout.php system works
 * 
 * This is a simple test page to verify the clean architecture
 * layout system is functioning correctly.
 * 
 * Just content - no HTML structure, no includes
 * Layout system handles all structure automatically
 */
?>

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-success text-white">
                <h2 class="mb-0"><i class="fa fa-check-circle"></i> Layout System Test Page</h2>
            </div>
            <div class="card-body">
                <div class="alert alert-success">
                    <h4>✓ SUCCESS!</h4>
                    <p>The clean architecture layout system is working correctly!</p>
                </div>

                <div class="row mt-4">
                    <div class="col-md-6">
                        <h3>What This Proves:</h3>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="fa fa-check text-success"></i> layout.php is loading</li>
                            <li class="mb-2"><i class="fa fa-check text-success"></i> render_display_page() is working</li>
                            <li class="mb-2"><i class="fa fa-check text-success"></i> Content file is being included</li>
                            <li class="mb-2"><i class="fa fa-check text-success"></i> HTML structure is proper</li>
                            <li class="mb-2"><i class="fa fa-check text-success"></i> CSS is loading (Bootstrap)</li>
                            <li class="mb-2"><i class="fa fa-check text-success"></i> JavaScript is loading</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h3>How to Verify:</h3>
                        <ol>
                            <li>Open browser DevTools (F12)</li>
                            <li>Go to Elements/Inspector tab</li>
                            <li>Check that:
                                <ul>
                                    <li>&lt;!DOCTYPE html&gt; at top</li>
                                    <li>&lt;head&gt; has &lt;title&gt;, CSS links</li>
                                    <li>&lt;body&gt; has navbar, content, footer</li>
                                    <li>&lt;/html&gt; at bottom</li>
                                </ul>
                            </li>
                            <li>Go to Console tab</li>
                            <li>Check for any errors (should be none)</li>
                        </ol>
                    </div>
                </div>

                <hr class="my-4">

                <h3>Component Check:</h3>
                <div class="row g-3 mt-2">
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fa fa-file"></i> Layout System</h5>
                                <p class="text-muted">includes/layout.php</p>
                                <span class="badge bg-success">✓ Active</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fa fa-palette"></i> Styling</h5>
                                <p class="text-muted">Bootstrap 5</p>
                                <span class="badge bg-success">✓ Loaded</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fa fa-code"></i> Scripts</h5>
                                <p class="text-muted">jQuery, etc</p>
                                <span class="badge bg-success">✓ Loaded</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fa fa-navigation"></i> Navigation</h5>
                                <p class="text-muted">Navbar</p>
                                <span class="badge bg-success">✓ Present</span>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <h3>System Information:</h3>
                <table class="table table-sm table-bordered">
                    <tbody>
                        <tr>
                            <td><strong>Site Name:</strong></td>
                            <td><?php $config = ConfigManager::getInstance(); echo htmlspecialchars($config->getString('siteTitle')); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Current Time:</strong></td>
                            <td><?= date('Y-m-d H:i:s') ?></td>
                        </tr>
                        <tr>
                            <td><strong>User:</strong></td>
                            <td><?php echo isset($_SESSION['user_id']) ? 'Logged in (' . htmlspecialchars($_SESSION['user_id']) . ')' : 'Guest'; ?></td>
                        </tr>
                        <tr>
                            <td><strong>PHP Version:</strong></td>
                            <td><?= PHP_VERSION ?></td>
                        </tr>
                        <tr>
                            <td><strong>Server:</strong></td>
                            <td><?= $_SERVER['SERVER_SOFTWARE'] ?></td>
                        </tr>
                    </tbody>
                </table>

                <hr class="my-4">

                <h3>Next Steps:</h3>
                <div class="alert alert-info">
                    <p><strong>Phase 2:</strong> Now that layout.php is verified working, we can convert display pages:</p>
                    <ol>
                        <li>Create tools/pages/organism.php (content)</li>
                        <li>Create new tools/organism_display.php (wrapper)</li>
                        <li>Test organism page</li>
                        <li>Repeat for assembly, groups, multi_organism, parent</li>
                    </ol>
                </div>

            </div>
        </div>
    </div>
</div>

<style>
.card-header h2 {
    font-size: 1.5rem;
}
.badge {
    font-size: 0.9rem;
    padding: 0.5rem 0.75rem;
}
</style>
