<div class="row">
    <div class="col-12">
        <h1>JBrowse2 - Genome Browser</h1>
        <p class="lead">Explore and analyze genome sequences from our collection</p>
    </div>
</div>

<div class="row mt-4">
    <div id="jbrowse-main-col" class="col-md-12">
        <!-- JBrowse2 Content Area -->
        <div id="jbrowse2-container" class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <span id="user-status"></span>
                    <span id="assembly-count" class="float-end badge bg-primary"></span>
                </h5>
            </div>
            <div class="card-body">
                <div id="assembly-list-container">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3">Loading available assemblies...</p>
                    </div>
                </div>
                <div id="assembly-viewer-container" style="display: none;">
                    <div style="margin-bottom: 1rem; display: flex; gap: 0.5rem; justify-content: space-between;">
                        <button id="back-to-list" class="btn btn-sm btn-secondary">← Back to Assembly List</button>
                        <div>
                            <button id="toggle-fullscreen" class="btn btn-sm btn-primary" title="Toggle fullscreen mode">
                                <i class="fas fa-expand"></i> Fullscreen
                            </button>
                            <button id="open-new-window" class="btn btn-sm btn-outline-primary" title="Open in new window">
                                <i class="fas fa-external-link-alt"></i> New Window
                            </button>
                        </div>
                    </div>
                    <div id="jbrowse-iframe-container" style="height: 800px; width: 100%; border: 1px solid #ddd;">
                        <iframe id="jbrowse2-iframe" style="width: 100%; height: 100%; border: none;" title="JBrowse2 Genome Browser"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="jbrowse-sidebar" class="col-md-3 collapsed">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-muted small fw-semibold">Info Panels</span>
            <button id="sidebar-collapse-btn" class="btn btn-sm btn-outline-secondary" title="Collapse sidebar">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
        <!-- Info Panel -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Current Session</h5>
            </div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-6">Status:</dt>
                    <dd class="col-6"><span id="session-status" class="badge bg-secondary">Loading</span></dd>
                    
                    <dt class="col-6">Access Level:</dt>
                    <dd class="col-6"><span id="access-badge" class="badge bg-info"></span></dd>
                    
                    <dt class="col-6">User:</dt>
                    <dd class="col-6"><span id="username-display">Anonymous</span></dd>
                </dl>
                <hr>
                <p class="small text-muted">
                    You're viewing assemblies based on your access level. 
                    <a href="/moop/login.php">Sign in</a> to see more.
                </p>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Help & Info</h5>
            </div>
            <div class="list-group list-group-flush">
                <a href="/moop/help.php#jbrowse2" class="list-group-item list-group-item-action">
                    How to use JBrowse2
                </a>
                <a href="/moop/docs/JBrowse2/JBROWSE2_DYNAMIC_CONFIG.md" class="list-group-item list-group-item-action">
                    Assembly Documentation
                </a>
                <a href="/moop/about.php" class="list-group-item list-group-item-action">
                    About this browser
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Sidebar expand tab (visible when sidebar is collapsed) -->
<button id="sidebar-expand-btn" title="Show info panels">
    <i class="fas fa-chevron-left"></i><span>Info</span>
</button>

<style>
    #jbrowse2-container {
        min-height: 600px;
    }
    
    /* Fullscreen mode styles */
    #jbrowse-iframe-container.fullscreen {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        width: 100vw !important;
        height: 100vh !important;
        z-index: 9999;
        border: none !important;
        background: white;
    }
    
    #jbrowse-iframe-container.fullscreen #jbrowse2-iframe {
        width: 100%;
        height: 100%;
    }
    
    /* Hide MOOP layout when in fullscreen */
    body.jbrowse-fullscreen .navbar,
    body.jbrowse-fullscreen .footer,
    body.jbrowse-fullscreen #back-to-list,
    body.jbrowse-fullscreen .col-md-3 {
        display: none !important;
    }
    
    body.jbrowse-fullscreen #assembly-viewer-container {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        margin: 0;
        padding: 0;
        background: white;
        z-index: 9998;
    }
    
    body.jbrowse-fullscreen .row {
        margin: 0;
    }

    .assembly-card {
        border-left: 4px solid #007bff;
        transition: all 0.2s ease;
    }

    .assembly-card:hover {
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        border-left-color: #0056b3;
    }

    .assembly-card.restricted {
        opacity: 0.7;
        border-left-color: #dc3545;
    }

    .badge-public {
        background-color: #28a745;
    }

    .badge-collaborator {
        background-color: #ffc107;
        color: #000;
    }

    .badge-admin {
        background-color: #dc3545;
    }

    .access-denied {
        padding: 2rem;
        text-align: center;
        color: #6c757d;
    }

    .access-denied svg {
        width: 80px;
        height: 80px;
        margin-bottom: 1rem;
        opacity: 0.5;
    }

    /* Sidebar collapse */
    #jbrowse-sidebar.collapsed {
        display: none !important;
    }

    /* Expand side-tab */
    #sidebar-expand-btn {
        position: fixed;
        top: 50%;
        right: 0;
        transform: translateY(-50%);
        z-index: 1050;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;
        padding: 10px 6px;
        background: #fff;
        border: 1px solid #dee2e6;
        border-right: none;
        border-radius: 6px 0 0 6px;
        box-shadow: -2px 2px 6px rgba(0,0,0,0.12);
        color: #495057;
        cursor: pointer;
        font-size: 0.75rem;
        line-height: 1;
    }
    #sidebar-expand-btn:hover {
        background: #f8f9fa;
        color: #0d6efd;
        border-color: #0d6efd;
    }
    #sidebar-expand-btn span {
        writing-mode: vertical-rl;
        text-orientation: mixed;
        letter-spacing: 0.05em;
        font-weight: 500;
    }
    #sidebar-expand-btn.hidden {
        display: none !important;
    }
</style>

<script>
    // This script runs after jbrowse2-loader.js loads the actual content
    document.addEventListener('DOMContentLoaded', function() {
        // Display user session info
        const userInfo = window.moopUserInfo;
        
        // Update session display
        document.getElementById('username-display').textContent = userInfo.username || 'Anonymous';
        document.getElementById('session-status').textContent = userInfo.logged_in ? 'Logged In' : 'Guest';
        document.getElementById('session-status').className = userInfo.logged_in ? 'badge bg-success' : 'badge bg-warning';
        
        // Update access level badge
        const accessLevelMap = {
            'PUBLIC': { text: 'Public', class: 'badge-public' },
            'COLLABORATOR': { text: 'Collaborator', class: 'badge-collaborator' },
            'IP_IN_RANGE': { text: 'Trusted Network', class: 'badge-info' },
            'ADMIN': { text: 'Administrator', class: 'badge-admin' }
        };
        
        const accessInfo = accessLevelMap[userInfo.access_level] || accessLevelMap['PUBLIC'];
        const accessBadge = document.getElementById('access-badge');
        accessBadge.textContent = accessInfo.text;
        accessBadge.className = 'badge ' + accessInfo.class;
        
        // Handle back button from viewer
        const backBtn = document.getElementById('back-to-list');
        if (backBtn) {
            backBtn.addEventListener('click', function() {
                document.getElementById('assembly-list-container').style.display = 'block';
                document.getElementById('assembly-viewer-container').style.display = 'none';
                document.getElementById('jbrowse2-iframe').src = '';
                
                // Exit fullscreen if active
                if (document.body.classList.contains('jbrowse-fullscreen')) {
                    exitFullscreen();
                }
            });
        }
        
        // Fullscreen toggle functionality
        const toggleFullscreenBtn = document.getElementById('toggle-fullscreen');
        const iframeContainer = document.getElementById('jbrowse-iframe-container');
        
        function enterFullscreen() {
            document.body.classList.add('jbrowse-fullscreen');
            iframeContainer.classList.add('fullscreen');
            toggleFullscreenBtn.innerHTML = '<i class="fas fa-compress"></i> Exit Fullscreen';
            toggleFullscreenBtn.classList.remove('btn-primary');
            toggleFullscreenBtn.classList.add('btn-warning');
            
            // Position toggle button in fullscreen mode
            toggleFullscreenBtn.style.position = 'fixed';
            toggleFullscreenBtn.style.top = '10px';
            toggleFullscreenBtn.style.right = '10px';
            toggleFullscreenBtn.style.zIndex = '10000';
        }
        
        function exitFullscreen() {
            document.body.classList.remove('jbrowse-fullscreen');
            iframeContainer.classList.remove('fullscreen');
            toggleFullscreenBtn.innerHTML = '<i class="fas fa-expand"></i> Fullscreen';
            toggleFullscreenBtn.classList.remove('btn-warning');
            toggleFullscreenBtn.classList.add('btn-primary');
            
            // Reset button positioning
            toggleFullscreenBtn.style.position = '';
            toggleFullscreenBtn.style.top = '';
            toggleFullscreenBtn.style.right = '';
            toggleFullscreenBtn.style.zIndex = '';
        }
        
        if (toggleFullscreenBtn) {
            toggleFullscreenBtn.addEventListener('click', function() {
                if (document.body.classList.contains('jbrowse-fullscreen')) {
                    exitFullscreen();
                } else {
                    enterFullscreen();
                }
            });
        }
        
        // ESC key to exit fullscreen
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && document.body.classList.contains('jbrowse-fullscreen')) {
                exitFullscreen();
            }
        });

        // Sidebar collapse/expand — always starts closed
        const sidebar     = document.getElementById('jbrowse-sidebar');
        const mainCol     = document.getElementById('jbrowse-main-col');
        const collapseBtn = document.getElementById('sidebar-collapse-btn');
        const expandBtn   = document.getElementById('sidebar-expand-btn');

        function collapseSidebar() {
            sidebar.classList.add('collapsed');
            mainCol.classList.remove('col-md-9');
            mainCol.classList.add('col-md-12');
            expandBtn.classList.remove('hidden');
        }

        function expandSidebar() {
            sidebar.classList.remove('collapsed');
            mainCol.classList.remove('col-md-12');
            mainCol.classList.add('col-md-9');
            expandBtn.classList.add('hidden');
        }

        collapseBtn.addEventListener('click', collapseSidebar);
        expandBtn.addEventListener('click', expandSidebar);
        
        // Open in new window functionality
        const openNewWindowBtn = document.getElementById('open-new-window');
        if (openNewWindowBtn) {
            openNewWindowBtn.addEventListener('click', function() {
                const iframe = document.getElementById('jbrowse2-iframe');
                const currentUrl = iframe.src;
                
                if (currentUrl) {
                    // Open in new window with optimal size
                    const width = screen.width * 0.9;
                    const height = screen.height * 0.9;
                    const left = (screen.width - width) / 2;
                    const top = (screen.height - height) / 2;
                    
                    window.open(
                        currentUrl,
                        'JBrowse2_' + Date.now(),
                        `width=${width},height=${height},left=${left},top=${top},menubar=no,toolbar=no,location=no,status=no`
                    );
                }
            });
        }
    });
</script>
