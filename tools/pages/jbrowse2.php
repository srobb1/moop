<div class="row">
    <div class="col-12">
        <h1>JBrowse2 - Genome Browser</h1>
        <p class="lead">Explore and analyze genome sequences from our collection</p>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-9">
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
                    <div style="margin-bottom: 1rem;">
                        <button id="back-to-list" class="btn btn-sm btn-secondary">← Back to Assembly List</button>
                    </div>
                    <div style="height: 800px; width: 100%; border: 1px solid #ddd;">
                        <iframe id="jbrowse2-iframe" style="width: 100%; height: 100%; border: none;" title="JBrowse2 Genome Browser"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
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

<style>
    #jbrowse2-container {
        min-height: 600px;
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
            'Public': { text: 'Public', class: 'badge-public' },
            'Collaborator': { text: 'Collaborator', class: 'badge-collaborator' },
            'ALL': { text: 'Administrator', class: 'badge-admin' }
        };
        
        const accessInfo = accessLevelMap[userInfo.access_level] || accessLevelMap['Public'];
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
            });
        }
    });
</script>
