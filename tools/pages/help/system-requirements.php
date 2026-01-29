<?php
/**
 * SYSTEM REQUIREMENTS & RESOURCE PLANNING - Technical Help Documentation
 * 
 * Technical guide for system requirements, hardware sizing, and resource planning.
 * Based on analysis of current deployment with 5 organisms totaling 11 GB.
 * 
 * Available variables:
 * - $config (ConfigManager instance)
 * - $siteTitle (Site title)
 */
?>

<div class="container mt-5">
  <h2><i class="fa fa-server"></i> System Requirements & Resource Planning</h2>
  <p class="lead text-muted">Hardware specifications and capacity planning guide for MOOP deployments.</p>

  <!-- Back to Help Link -->
  <div class="mb-4">
    <a href="help.php" class="btn btn-outline-secondary btn-sm">
      <i class="fa fa-arrow-left"></i> Back to Help
    </a>
  </div>

  <!-- Quick Navigation -->
  <div class="alert alert-light border">
    <strong>On this page:</strong>
    <ul class="mb-0">
      <li><a href="#current-analysis">Current System Analysis</a></li>
      <li><a href="#scaling-projections">Scaling Projections</a></li>
      <li><a href="#configurations">Complete System Configurations</a></li>
      <li><a href="#image-caching">Wikipedia Image Caching</a></li>
      <li><a href="#resource-breakdown">Resource Breakdown by Component</a></li>
      <li><a href="#performance-benchmarks">Performance Benchmarks</a></li>
      <li><a href="#monitoring">Monitoring & Alerts</a></li>
      <li><a href="#cost-estimation">Cost Estimation</a></li>
    </ul>
  </div>

  <!-- Alert Box -->
  <div class="alert alert-info">
    <strong><i class="fa fa-info-circle"></i> Current Baseline (Complete Datasets):</strong> 
    MOOP has <strong>3 organisms with complete data (genome.fa)</strong>, totaling <strong>9.5 GB</strong>, and <strong>~3.2 GB average per complete organism</strong>. 
    All scaling calculations below are based on this empirical complete dataset. <em>Note: 2 smaller organisms lack full genome files, reducing their total size to 0.7 GB average.</em>
  </div>

  <!-- Section 1: Current Analysis -->
  <section id="current-analysis" class="mt-5">
    <h3><i class="fa fa-chart-pie"></i> Current System Analysis (5 Organisms)</h3>

    <h4 class="mt-4">Per-Organism Disk Usage Breakdown</h4>
    <div class="card">
      <div class="card-body">
        <table class="table table-sm">
          <thead class="table-light">
            <tr>
              <th>Organism</th>
              <th>Total</th>
              <th>Database</th>
              <th>FASTA Files</th>
              <th>BLAST</th>
              <th>Notes</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><strong>Anoura_caudifer</strong></td>
              <td>3.0 GB</td>
              <td>131 MB</td>
              <td>2.8 GB</td>
              <td>11 MB</td>
              <td>Genome 2.2 GB + transcripts/CDS/protein</td>
            </tr>
            <tr>
              <td><strong>Chamaeleo_calyptratus</strong></td>
              <td>2.9 GB</td>
              <td>113 MB</td>
              <td>2.7 GB</td>
              <td>15 MB</td>
              <td>Genome 2.0 GB + transcripts/CDS/protein</td>
            </tr>
            <tr>
              <td><strong>Lasiurus_cinereus</strong></td>
              <td>411 MB</td>
              <td>252 MB</td>
              <td>59 MB</td>
              <td>11 MB</td>
              <td>Large database, <strong>no genome.fa</strong></td>
            </tr>
            <tr>
              <td><strong>Montipora_capitata</strong></td>
              <td>315 MB</td>
              <td>155 MB</td>
              <td>25 MB</td>
              <td>21 MB</td>
              <td>Smaller organism, <strong>no genome.fa</strong></td>
            </tr>
            <tr>
              <td><strong>Pteropus_vampyrus</strong></td>
              <td>3.6 GB</td>
              <td>152 MB</td>
              <td>3.4 GB</td>
              <td>26 MB</td>
              <td>Genome 2.1 GB + transcripts/CDS/protein</td>
            </tr>
            <tr class="table-active">
              <td><strong>AVERAGE (Complete)</strong></td>
              <td><strong>3.2 GB</strong></td>
              <td><strong>132 MB</strong></td>
              <td><strong>3.0 GB</strong></td>
              <td><strong>17 MB</strong></td>
              <td>Complete datasets only (genome.fa included)</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="alert alert-warning mt-3">
      <strong><i class="fa fa-exclamation-triangle"></i> Important: Missing genome.fa Files</strong>
      <p class="mb-0"><strong>Lasiurus_cinereus</strong> and <strong>Montipora_capitata</strong> do not have full genome FASTA files. These organisms have:</p>
      <ul class="mb-0 mt-2">
        <li>Transcript sequences (transcript.nt.fa)</li>
        <li>Coding sequences (cds.nt.fa)</li>
        <li>Protein sequences (protein.aa.fa)</li>
        <li><strong>❌ NO full genome reference (genome.fa)</strong></li>
      </ul>
      <p class="mb-0 mt-2">This affects capacity planning—not all organisms have complete genomic data. When adding new organisms, verify which sequence files are available.</p>
    </div>

    <h4 class="mt-4">Storage Breakdown by Component Type</h4>
    <div class="row">
      <div class="col-lg-6">
        <div class="card mb-3">
          <div class="card-header bg-primary bg-opacity-10">
            <strong>Database (organism.sqlite)</strong>
          </div>
          <div class="card-body">
            <ul class="mb-0">
              <li><strong>Range:</strong> 113 MB - 252 MB per organism</li>
              <li><strong>Average (complete):</strong> 132 MB per organism</li>
              <li><strong>Stores:</strong> Features, annotations, relationships</li>
              <li><strong>Growth:</strong> 1-2 MB per 10,000 additional annotations</li>
              <li><strong>% of Total:</strong> ~4% per complete organism</li>
            </ul>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card mb-3">
          <div class="card-header bg-info bg-opacity-10">
            <strong>FASTA Files (All Sequence Data)</strong>
          </div>
          <div class="card-body">
            <ul class="mb-0">
              <li><strong>Range:</strong> 2.7 GB - 3.4 GB per organism</li>
              <li><strong>Average (complete):</strong> 3.0 GB per organism</li>
              <li><strong>Includes:</strong> genome.fa, transcript.nt.fa, cds.nt.fa, protein.aa.fa</li>
              <li><strong>Growth:</strong> Fixed size for each organism</li>
              <li><strong>% of Total:</strong> ~94% per complete organism</li>
              <li><strong>Largest component:</strong> genome.fa (2.0-2.2 GB)</li>
            </ul>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card mb-3">
          <div class="card-header bg-success bg-opacity-10">
            <strong>BLAST Indices</strong>
          </div>
          <div class="card-body">
            <ul class="mb-0">
              <li><strong>Range:</strong> 11 MB - 26 MB per organism</li>
              <li><strong>Average:</strong> 17 MB per organism</li>
              <li><strong>Size ratio:</strong> 15-25% of source FASTA</li>
              <li><strong>Regeneration:</strong> 10-30 minutes per organism</li>
              <li><strong>% of Total:</strong> ~1% per organism</li>
              <li><strong>Optional:</strong> Can be recreated if lost</li>
            </ul>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card mb-3">
          <div class="card-header bg-warning bg-opacity-10">
            <strong>Metadata & Configuration</strong>
          </div>
          <div class="card-body">
            <ul class="mb-0">
              <li><strong>organism.json:</strong> 2-5 KB per organism</li>
              <li><strong>Taxonomy tree:</strong> ~50 KB (shared)</li>
              <li><strong>Group metadata:</strong> ~18 KB (shared)</li>
              <li><strong>Annotation config:</strong> ~10 KB (shared)</li>
              <li><strong>Growth:</strong> Very minimal</li>
              <li><strong>% of Total:</strong> < 0.1% per organism</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Section 2: Scaling Projections -->
  <section id="scaling-projections" class="mt-5">
    <h3><i class="fa fa-chart-line"></i> Scaling Projections</h3>

    <h4 class="mt-4">Disk Space Requirements Formula</h4>
    <div class="alert alert-light border">
      <pre><code>Total Disk = (Average per complete organism × Number of organisms) × 1.2 (overhead)
           = (3.2 GB × N) × 1.2</code></pre>
    </div>

    <h4 class="mt-4">Disk Space by Scale</h4>
    <div class="card mb-4">
      <div class="card-body">
        <table class="table table-sm">
          <thead class="table-light">
            <tr>
              <th># Organisms</th>
              <th>Average Disk</th>
              <th>Recommended Disk</th>
              <th>Use Case</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><strong>3</strong></td>
              <td>9.6 GB</td>
              <td>15 GB</td>
              <td>Current deployment (complete only)</td>
            </tr>
            <tr>
              <td><strong>5</strong></td>
              <td>16 GB</td>
              <td>25 GB</td>
              <td>Small lab</td>
            </tr>
            <tr>
              <td><strong>10</strong></td>
              <td>32 GB</td>
              <td>50 GB</td>
              <td>Small research group</td>
            </tr>
            <tr>
              <td><strong>20</strong></td>
              <td>64 GB</td>
              <td>80 GB</td>
              <td>Medium lab</td>
            </tr>
            <tr>
              <td><strong>50</strong></td>
              <td>160 GB</td>
              <td>200 GB</td>
              <td>Large lab</td>
            </tr>
            <tr>
              <td><strong>100</strong></td>
              <td>320 GB</td>
              <td>400 GB</td>
              <td>Multi-lab / institutional</td>
            </tr>
            <tr>
              <td><strong>200</strong></td>
              <td>640 GB</td>
              <td>800 GB</td>
              <td>Large institution</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <h4 class="mt-4">Storage Strategy Recommendations</h4>
    <div class="row">
      <div class="col-lg-4">
        <div class="card">
          <div class="card-header bg-info bg-opacity-10">
            <strong>Single Fast Disk</strong>
          </div>
          <div class="card-body">
            <p><strong>Best for:</strong> Up to 50 organisms</p>
            <ul class="mb-0">
              <li>< 125 GB total</li>
              <li>Lower cost</li>
              <li>Simple setup</li>
              <li><strong>Requires:</strong> Good backup strategy</li>
            </ul>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="card">
          <div class="card-header bg-success bg-opacity-10">
            <strong>RAID-1 (Mirrored)</strong>
          </div>
          <div class="card-body">
            <p><strong>Best for:</strong> 50-100 organisms</p>
            <ul class="mb-0">
              <li>125-250 GB</li>
              <li>Redundancy</li>
              <li>Automatic failover</li>
              <li><strong>Cost:</strong> 2× disk cost</li>
            </ul>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="card">
          <div class="card-header bg-warning bg-opacity-10">
            <strong>RAID-5 / RAID-6</strong>
          </div>
          <div class="card-body">
            <p><strong>Best for:</strong> 100+ organisms</p>
            <ul class="mb-0">
              <li>250+ GB</li>
              <li>Performance + redundancy</li>
              <li>Parity protection</li>
              <li><strong>Setup:</strong> More complex</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <h4 class="mt-4 mt-3">Storage Breakdown Example (100 Organisms with Complete Data)</h4>
    <div class="alert alert-light border">
      <pre><code>Per organism average: 3.2 GB

For 100 organisms:
─────────────────────────────────
Database files (SQLite):   13.2 GB (100 × 132 MB)
FASTA Files (all):        300 GB  (100 × 3.0 GB)
  ├─ genome.fa            270 GB  (~90% of FASTA)
  └─ transcripts/CDS/protein 30 GB (~10% of FASTA)
BLAST indices:             1.7 GB (100 × 17 MB)
Other files/metadata:      2-3 GB
────────────────────────────────
SUBTOTAL:                  ~317 GB
With 20% overhead:         ~380 GB
Recommended allocation:    400 GB (with growth room)</code></pre>
    </div>
  </section>

  <!-- Section 3: Complete Configurations -->
  <section id="configurations" class="mt-5">
    <h3><i class="fa fa-cogs"></i> Complete System Configurations by Scale</h3>

    <h4 class="mt-4">Quick Reference Table</h4>
    <div class="card mb-4">
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-sm">
            <thead class="table-light">
              <tr>
                <th>Scale</th>
                <th>Disk</th>
                <th>RAM</th>
                <th>CPU</th>
                <th>Concurrent Users</th>
                <th>BLAST</th>
                <th>Use Case</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><strong>3 org</strong></td>
                <td>15 GB</td>
                <td>8 GB</td>
                <td>4c</td>
                <td>2-3</td>
                <td>1 serial</td>
                <td>Current (complete)</td>
              </tr>
              <tr>
                <td><strong>5 org</strong></td>
                <td>25 GB</td>
                <td>8-16 GB</td>
                <td>4c</td>
                <td>2-3</td>
                <td>1 serial</td>
                <td>Small lab</td>
              </tr>
              <tr>
                <td><strong>10 org</strong></td>
                <td>50 GB</td>
                <td>16 GB</td>
                <td>8c</td>
                <td>4-5</td>
                <td>1 serial</td>
                <td>Workgroup</td>
              </tr>
              <tr>
                <td><strong>20 org</strong></td>
                <td>80 GB</td>
                <td>32 GB</td>
                <td>16c</td>
                <td>8-10</td>
                <td>1-2</td>
                <td>Medium lab</td>
              </tr>
              <tr>
                <td><strong>50 org</strong></td>
                <td>200 GB</td>
                <td>64 GB</td>
                <td>24c</td>
                <td>15-20</td>
                <td>2-3</td>
                <td>Large lab</td>
              </tr>
              <tr>
                <td><strong>100 org</strong></td>
                <td>400 GB</td>
                <td>128 GB</td>
                <td>48c</td>
                <td>30-50</td>
                <td>3-5</td>
                <td>Institution</td>
              </tr>
              <tr>
                <td><strong>200+ org</strong></td>
                <td>800+ GB</td>
                <td>256+ GB</td>
                <td>64+ c</td>
                <td>100+</td>
                <td>5+</td>
                <td>Multi-lab</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <h4 class="mt-4">Configuration 1: Small Lab (3-5 Organisms with Complete Data)</h4>
    <div class="card mb-4">
      <div class="card-body">
        <div class="row">
          <div class="col-lg-6">
            <h6>Hardware</h6>
            <ul>
              <li><strong>CPU:</strong> 4 cores / 2 GHz (Intel i5 or equivalent)</li>
              <li><strong>RAM:</strong> 8 GB</li>
              <li><strong>Storage:</strong> 25 GB SSD (15-25 GB organisms + backups)</li>
              <li><strong>Network:</strong> 1 Gbps</li>
            </ul>
          </div>
          <div class="col-lg-6">
            <h6>Performance</h6>
            <ul>
              <li><strong>Search response:</strong> < 1 second</li>
              <li><strong>BLAST search:</strong> 5-20 minutes</li>
              <li><strong>Multi-organism search:</strong> 1-5 seconds</li>
              <li><strong>Concurrent users:</strong> 2-3</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <h4 class="mt-4">Configuration 2: Small Research Group (10 Organisms)</h4>
    <div class="card mb-4">
      <div class="card-body">
        <div class="row">
          <div class="col-lg-6">
            <h6>Hardware</h6>
            <ul>
              <li><strong>CPU:</strong> 8 cores / 2.5 GHz (Intel Xeon / AMD Ryzen)</li>
              <li><strong>RAM:</strong> 16 GB</li>
              <li><strong>Storage:</strong> 50 GB SSD + 100 GB HDD for backups</li>
              <li><strong>Network:</strong> 1 Gbps</li>
            </ul>
          </div>
          <div class="col-lg-6">
            <h6>Performance</h6>
            <ul>
              <li><strong>Search response:</strong> < 1 second</li>
              <li><strong>BLAST search:</strong> 5-20 minutes</li>
              <li><strong>Multi-organism search (all 10):</strong> 2-5 seconds</li>
              <li><strong>Concurrent users:</strong> 4-5</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <h4 class="mt-4">Configuration 3: Medium Lab (20 Organisms)</h4>
    <div class="card mb-4">
      <div class="card-body">
        <div class="row">
          <div class="col-lg-6">
            <h6>Hardware</h6>
            <ul>
              <li><strong>CPU:</strong> 16 cores / 2.5 GHz (Intel Xeon E5 v4)</li>
              <li><strong>RAM:</strong> 32 GB</li>
              <li><strong>Storage:</strong> RAID-1: 100 GB SSD + 200 GB HDD</li>
              <li><strong>Network:</strong> 1 Gbps</li>
            </ul>
          </div>
          <div class="col-lg-6">
            <h6>Performance</h6>
            <ul>
              <li><strong>Search response:</strong> < 1 second</li>
              <li><strong>BLAST search:</strong> 10-30 minutes</li>
              <li><strong>Multi-organism search (all 20):</strong> 3-8 seconds</li>
              <li><strong>Concurrent users:</strong> 8-10</li>
              <li><strong>Concurrent BLAST:</strong> 1-2</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <h4 class="mt-4">Configuration 4: Large Lab (50 Organisms)</h4>
    <div class="card mb-4">
      <div class="card-body">
        <div class="row">
          <div class="col-lg-6">
            <h6>Hardware</h6>
            <ul>
              <li><strong>CPU:</strong> 24-32 cores / 2.5+ GHz (Xeon Gold / EPYC)</li>
              <li><strong>RAM:</strong> 64 GB</li>
              <li><strong>Storage:</strong> RAID-5: 150 GB SSD + 500 GB HDD</li>
              <li><strong>Network:</strong> 1 Gbps (or 10 Gbps for heavy use)</li>
            </ul>
          </div>
          <div class="col-lg-6">
            <h6>Performance</h6>
            <ul>
              <li><strong>Search response:</strong> < 1 second</li>
              <li><strong>BLAST search:</strong> 15-40 minutes</li>
              <li><strong>Multi-organism search (all 50):</strong> 5-15 seconds</li>
              <li><strong>Concurrent users:</strong> 15-20</li>
              <li><strong>Concurrent BLAST:</strong> 2-3</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <h4 class="mt-4">Configuration 5: Large Institution (100 Organisms)</h4>
    <div class="card mb-4">
      <div class="card-body">
        <div class="row">
          <div class="col-lg-6">
            <h6>Hardware</h6>
            <ul>
              <li><strong>CPU:</strong> 48-64 cores / 2.5+ GHz (Xeon Platinum / EPYC)</li>
              <li><strong>RAM:</strong> 128 GB</li>
              <li><strong>Storage:</strong> RAID-6: 250 GB SSD + 1 TB HDD</li>
              <li><strong>Network:</strong> 10 Gbps recommended</li>
              <li><strong>Backup:</strong> External NAS (500 GB+)</li>
            </ul>
          </div>
          <div class="col-lg-6">
            <h6>Performance</h6>
            <ul>
              <li><strong>Search response:</strong> < 1 second</li>
              <li><strong>BLAST search:</strong> 20-60 minutes</li>
              <li><strong>Multi-organism search (all 100):</strong> 8-20 seconds</li>
              <li><strong>Concurrent users:</strong> 30-50</li>
              <li><strong>Concurrent BLAST:</strong> 3-5</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <h4 class="mt-4">Configuration 6: Very Large Institution (200+ Organisms)</h4>
    <div class="card mb-4">
      <div class="card-body">
        <div class="row">
          <div class="col-lg-6">
            <h6>Hardware</h6>
            <ul>
              <li><strong>CPU:</strong> 64+ cores / 2.5+ GHz (dual-socket Xeon/EPYC)</li>
              <li><strong>RAM:</strong> 256 GB+</li>
              <li><strong>Storage:</strong> RAID-6 or RAID-10: 500+ GB SSD + 2+ TB HDD</li>
              <li><strong>Network:</strong> 10 Gbps minimum</li>
              <li><strong>Load Balancer:</strong> Distribute across 2-3 servers</li>
              <li><strong>Backup:</strong> Automated daily to external storage</li>
            </ul>
          </div>
          <div class="col-lg-6">
            <h6>Performance</h6>
            <ul>
              <li><strong>Load balancing:</strong> Multiple servers</li>
              <li><strong>Database replication:</strong> For read scaling</li>
              <li><strong>Dedicated BLAST server:</strong> Optional</li>
              <li><strong>Concurrent users:</strong> 100+</li>
              <li><strong>HA/Failover:</strong> Recommended</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- NEW: Image Caching Section -->
  <section id="image-caching" class="mt-5">
    <h3><i class="fa fa-image"></i> Wikipedia Image Caching for Taxonomy Groups</h3>

    <h4 class="mt-4">Overview</h4>
    <div class="alert alert-info">
      <strong><i class="fa fa-info-circle"></i> Automatic Image Caching:</strong>
      MOOP automatically downloads and caches Wikipedia/Wikimedia images for taxonomy groups on first access. 
      This improves page load times and provides a better user experience.
    </div>

    <h4 class="mt-4">Storage Impact Estimation</h4>
    <div class="card mb-4">
      <div class="card-header bg-light">
        <strong>Current Deployment (28 Taxonomy Groups)</strong>
      </div>
      <div class="card-body">
        <table class="table table-sm">
          <thead class="table-light">
            <tr>
              <th>Metric</th>
              <th>Value</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><strong>Total taxonomy groups</strong></td>
              <td>28</td>
            </tr>
            <tr>
              <td><strong>Average image size</strong></td>
              <td>~116 KB</td>
            </tr>
            <tr>
              <td><strong>Estimated total for all groups</strong></td>
              <td><strong>~3.2 MB</strong></td>
            </tr>
            <tr>
              <td><strong>Current cached images</strong></td>
              <td>3 images (Mammalia, Pteropodidae, Pteropus)</td>
            </tr>
            <tr>
              <td><strong>Current cache size</strong></td>
              <td>~352 KB</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <h4 class="mt-4">How It Works</h4>
    <div class="card mb-4">
      <div class="card-body">
        <h6>Fallback Chain (in order):</h6>
        <ol>
          <li><strong>Check custom image:</strong> <code>/images/groups/{GroupName}.jpg</code> (manually uploaded)</li>
          <li><strong>Check cached image:</strong> <code>/images/wikimedia/{GroupName}.jpg</code> (previously downloaded)</li>
          <li><strong>Download from Wikipedia:</strong> Automatic on-demand download and cache</li>
          <li><strong>No image:</strong> Display group without image (graceful failure)</li>
        </ol>

        <h6 class="mt-3">Benefits:</h6>
        <ul>
          <li><strong>Fast loading:</strong> Cached images are served locally after first load</li>
          <li><strong>On-demand:</strong> Only downloads images users actually view</li>
          <li><strong>Graceful:</strong> If download fails, page still works without image</li>
          <li><strong>Automatic:</strong> No manual intervention needed</li>
          <li><strong>Efficient:</strong> Minimal disk space usage</li>
        </ul>
      </div>
    </div>

    <h4 class="mt-4">Scaling to Larger Deployments</h4>
    <div class="card mb-4">
      <div class="card-body">
        <table class="table table-sm">
          <thead class="table-light">
            <tr>
              <th>Scenario</th>
              <th>Groups</th>
              <th>Avg Image</th>
              <th>Total Cache</th>
              <th>Assessment</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><strong>Current</strong></td>
              <td>28</td>
              <td>116 KB</td>
              <td><strong>~3.2 MB</strong></td>
              <td>Negligible</td>
            </tr>
            <tr>
              <td><strong>50 groups</strong></td>
              <td>50</td>
              <td>116 KB</td>
              <td><strong>~5.8 MB</strong></td>
              <td>Negligible</td>
            </tr>
            <tr>
              <td><strong>100 groups</strong></td>
              <td>100</td>
              <td>116 KB</td>
              <td><strong>~11.6 MB</strong></td>
              <td>Negligible</td>
            </tr>
            <tr>
              <td><strong>500 groups</strong></td>
              <td>500</td>
              <td>116 KB</td>
              <td><strong>~58 MB</strong></td>
              <td>Negligible</td>
            </tr>
            <tr>
              <td><strong>2000 groups</strong></td>
              <td>2000</td>
              <td>116 KB</td>
              <td><strong>~232 MB</strong></td>
              <td>Very reasonable</td>
            </tr>
          </tbody>
        </table>

        <div class="alert alert-light border mt-3 mb-0">
          <strong>Conclusion:</strong> Image caching has <strong>negligible impact</strong> on disk usage. 
          Even with 2,000 taxonomy groups, cached images would use only ~230 MB, which is less than 0.1% 
          of typical deployment storage.
        </div>
      </div>
    </div>

    <h4 class="mt-4">Cache Directory</h4>
    <div class="card mb-4">
      <div class="card-body">
        <p><strong>Location:</strong> <code>/images/wikimedia/</code></p>
        <ul>
          <li><strong>Permissions:</strong> 775 (rwxrwsr-x) for group access</li>
          <li><strong>Owner:</strong> ubuntu:www-data (allows both manual and web server management)</li>
          <li><strong>Auto-created:</strong> Directory is automatically created on first image download</li>
          <li><strong>File format:</strong> Original format from Wikipedia (PNG, JPG, etc.)</li>
        </ul>

        <h6 class="mt-3">Example Contents:</h6>
        <pre class="bg-light p-3 rounded"><code>$ ls -lah /images/wikimedia/
Mammalia.png          (279 KB)
Pteropodidae.jpg      (61 KB)
Pteropus.jpg          (7 KB)
...</code></pre>
      </div>
    </div>

    <h4 class="mt-4">Troubleshooting</h4>
    <div class="card mb-4">
      <div class="card-body">
        <div class="row">
          <div class="col-lg-6">
            <h6><i class="fa fa-check-circle text-success"></i> Image displays correctly</h6>
            <p>Image was either cached or downloaded successfully on first access. Subsequent page loads will be faster.</p>
          </div>
          <div class="col-lg-6">
            <h6><i class="fa fa-exclamation-circle text-warning"></i> No image displayed</h6>
            <p>Could mean:</p>
            <ul>
              <li>Wikipedia API is temporarily unavailable</li>
              <li>No Wikipedia entry for that taxonomy group</li>
              <li>Image URL format changed on Wikipedia</li>
            </ul>
            <p class="text-muted small">Page still functions normally without the image.</p>
          </div>
        </div>
      </div>
    </div>

    <h4 class="mt-4">Manual Cache Management (Optional)</h4>
    <div class="card mb-4">
      <div class="card-body">
        <h6>Clear specific cached image:</h6>
        <pre class="bg-light p-3 rounded"><code>rm /images/wikimedia/GroupName.jpg</code></pre>
        <p class="text-muted small">Will be re-downloaded on next page access</p>

        <h6 class="mt-3">Clear entire image cache:</h6>
        <pre class="bg-light p-3 rounded"><code>rm -rf /images/wikimedia/*</code></pre>
        <p class="text-muted small">All images will be re-downloaded on first access (may take longer initially)</p>

        <h6 class="mt-3">Check cache size:</h6>
        <pre class="bg-light p-3 rounded"><code>du -sh /images/wikimedia/</code></pre>
        <p class="text-muted small">Shows total disk space used by cached images</p>
      </div>
    </div>

  </section>
  <section id="resource-breakdown" class="mt-5">
    <h3><i class="fa fa-tachometer-alt"></i> Resource Breakdown by Component</h3>

    <h4 class="mt-4">Memory (RAM) Requirements</h4>
    <div class="card mb-4">
      <div class="card-body">
        <h6 class="mt-3">Per-Request Memory Usage</h6>
        <ul>
          <li><strong>Small search (feature lookup):</strong> 10-50 MB</li>
          <li><strong>Large multi-organism search:</strong> 100-300 MB</li>
          <li><strong>BLAST search (indexing):</strong> 200-500 MB</li>
          <li><strong>Database query (100k results):</strong> 150-400 MB</li>
        </ul>

        <h6 class="mt-3">Peak Memory Scenarios</h6>
        <ul>
          <li><strong>Concurrent BLAST + Multi-organism search:</strong> High memory spike</li>
          <li><strong>Large export (CSV/Excel 10k+ rows):</strong> Sustained high memory</li>
        </ul>

        <h6 class="mt-3">Formula</h6>
        <div class="alert alert-light border">
          <pre><code>Recommended RAM = (Organisms × 0.1 GB) + 4 GB base
Peak RAM = Recommended × 1.25 (for load spikes)</code></pre>
        </div>

        <h6 class="mt-3">Memory Breakdown at 100 Organisms</h6>
        <ul>
          <li><strong>OS/Base system:</strong> 1-2 GB</li>
          <li><strong>Apache/PHP processes (10 × 200 MB):</strong> 2-4 GB</li>
          <li><strong>Database query overhead:</strong> 2-3 GB</li>
          <li><strong>Free cache for OS:</strong> 2-4 GB</li>
          <li><strong>Safety margin:</strong> 2-3 GB</li>
        </ul>
        <div class="alert alert-light border mt-2 mb-0">
          <strong>Total needed:</strong> 12-18 GB minimum, 24+ GB recommended
        </div>
      </div>
    </div>

    <h4 class="mt-4">CPU/Processor Requirements</h4>
    <div class="card mb-4">
      <div class="card-body">
        <h6 class="mt-3">CPU-Intensive Operations</h6>
        <table class="table table-sm">
          <thead class="table-light">
            <tr>
              <th>Operation</th>
              <th>CPU Usage</th>
              <th>Time</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><strong>BLAST Searches</strong></td>
              <td>70-90%</td>
              <td>5-30 minutes</td>
            </tr>
            <tr>
              <td><strong>Database Indexing</strong></td>
              <td>50-70%</td>
              <td>10-30 minutes/organism</td>
            </tr>
            <tr>
              <td><strong>Taxonomy Tree Generation</strong></td>
              <td>< 5%</td>
              <td>100 ms - 5 seconds (all organisms)</td>
            </tr>
          </tbody>
        </table>

        <h6 class="mt-3">Formula</h6>
        <div class="alert alert-light border">
          <pre><code>Recommended Cores = (Organisms ÷ 10) + 4
Optimal with BLAST = Cores × 2 (hyperthreading helps)</code></pre>
        </div>

        <h6 class="mt-3">CPU Optimization Tips</h6>
        <ul>
          <li>BLAST uses all available cores automatically</li>
          <li>For 100 organisms, 16 cores (8 physical) is good baseline</li>
          <li>More cores = faster BLAST searches</li>
          <li>More cores improves concurrency for multiple users</li>
        </ul>
      </div>
    </div>

    <h4 class="mt-4">Network I/O Considerations</h4>
    <div class="card mb-4">
      <div class="card-body">
        <h6 class="mt-3">Typical User Operations</h6>
        <ul>
          <li><strong>Gene search:</strong> 50-200 KB (depends on results)</li>
          <li><strong>Feature page load:</strong> 100-500 KB (with images/annotations)</li>
          <li><strong>FASTA download (1 MB genome):</strong> 1-2 MB (compression helps)</li>
          <li><strong>BLAST results export:</strong> 100 KB - 5 MB</li>
        </ul>

        <h6 class="mt-3">Bandwidth Recommendations</h6>
        <ul>
          <li><strong>100 Mbps:</strong> Sufficient for < 50 organisms</li>
          <li><strong>1 Gbps:</strong> Recommended for 50+ organisms</li>
          <li><strong>10 Gbps:</strong> Consider if internal network in same building</li>
        </ul>
      </div>
    </div>
  </section>

  <!-- Section 5: Performance Benchmarks -->
  <section id="performance-benchmarks" class="mt-5">
    <h3><i class="fa fa-stopwatch"></i> Performance Benchmarks</h3>

    <h4 class="mt-4">Database Queries (SQLite)</h4>
    <div class="card mb-4">
      <div class="card-body">
        <ul>
          <li><strong>Feature lookup by ID:</strong> 10-50 ms</li>
          <li><strong>Feature search by name (substring):</strong> 50-200 ms</li>
          <li><strong>Get all annotations for feature:</strong> 100-500 ms</li>
          <li><strong>Multi-organism search (10 organisms):</strong> 1-5 seconds</li>
        </ul>
      </div>
    </div>

    <h4 class="mt-4">BLAST Searches</h4>
    <div class="card mb-4">
      <div class="card-body">
        <ul>
          <li><strong>Sequence validation:</strong> 100-500 ms</li>
          <li><strong>BLAST run (small sequence, mammal):</strong> 5-15 minutes</li>
          <li><strong>BLAST run (large sequence, 100+ organisms):</strong> 30-60 minutes</li>
          <li><strong>Result parsing:</strong> 1-5 seconds</li>
        </ul>
      </div>
    </div>

    <h4 class="mt-4">Sequence Extraction</h4>
    <div class="card mb-4">
      <div class="card-body">
        <ul>
          <li><strong>Retrieve 10 sequences:</strong> 100-500 ms</li>
          <li><strong>Generate FASTA (10 KB):</strong> 200-800 ms</li>
          <li><strong>Export large results (1 MB CSV):</strong> 1-5 seconds</li>
        </ul>
      </div>
    </div>

    <h4 class="mt-4">Taxonomy Tree Generation</h4>
    <div class="card mb-4">
      <div class="card-body">
        <ul>
          <li><strong>Build tree from 5 organisms:</strong> 100-500 ms</li>
          <li><strong>Build tree from 20 organisms:</strong> 500 ms - 2 seconds</li>
          <li><strong>Build tree from 100+ organisms:</strong> 2-5 seconds</li>
          <li><strong>CPU Usage:</strong> Low (< 5%)</li>
          <li><strong>Memory Usage:</strong> Low (< 50 MB)</li>
          <li><strong>Operation:</strong> Reads organism metadata and builds hierarchical JSON structure</li>
        </ul>
      </div>
    </div>

  <!-- Section 6: Monitoring -->
  <section id="monitoring" class="mt-5">
    <h3><i class="fa fa-bar-chart"></i> Monitoring & Alerts</h3>

    <h4 class="mt-4">Key Metrics to Monitor</h4>
    <div class="row">
      <div class="col-lg-6">
        <div class="card mb-3">
          <div class="card-header bg-primary bg-opacity-10">
            <strong>Storage</strong>
          </div>
          <div class="card-body">
            <ul class="mb-0">
              <li>Disk usage % (alert > 80%)</li>
              <li>Free space (alert < 10 GB available)</li>
              <li>Growth rate (track month-to-month)</li>
            </ul>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card mb-3">
          <div class="card-header bg-info bg-opacity-10">
            <strong>Memory</strong>
          </div>
          <div class="card-body">
            <ul class="mb-0">
              <li>RAM usage % (alert > 85%)</li>
              <li>Peak usage (track daily spikes)</li>
              <li>Cache hit rate (if applicable)</li>
            </ul>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card mb-3">
          <div class="card-header bg-success bg-opacity-10">
            <strong>CPU</strong>
          </div>
          <div class="card-body">
            <ul class="mb-0">
              <li>Utilization % (alert > 90% sustained)</li>
              <li>Load average (should be < cores available)</li>
              <li>BLAST process CPU (100% is OK)</li>
            </ul>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card mb-3">
          <div class="card-header bg-warning bg-opacity-10">
            <strong>Network</strong>
          </div>
          <div class="card-body">
            <ul class="mb-0">
              <li>Bandwidth usage (peak traffic analysis)</li>
              <li>Connection errors (log and investigate)</li>
              <li>Response times (track slowdowns)</li>
            </ul>
          </div>
        </div>
      </div>

      <div class="col-lg-12">
        <div class="card mb-3">
          <div class="card-header bg-danger bg-opacity-10">
            <strong>Application</strong>
          </div>
          <div class="card-body">
            <ul class="mb-0">
              <li>Error log size (truncate when > 10 MB)</li>
              <li>Failed searches (track in error log)</li>
              <li>BLAST timeout errors (increase limit if frequent)</li>
              <li>Database consistency (daily checks)</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <h4 class="mt-4">Operational Checklist by Scale</h4>
    <div class="row">
      <div class="col-lg-4">
        <div class="card">
          <div class="card-header">
            <strong>Minimum (5-20 organisms)</strong>
          </div>
          <div class="card-body">
            <ul class="mb-0" style="font-size: 0.9em;">
              <li>Daily backups (local or cloud)</li>
              <li>Monitor disk usage (alert at 80%)</li>
              <li>Monitor RAM during peak hours</li>
              <li>Log rotation for error logs</li>
            </ul>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="card">
          <div class="card-header">
            <strong>Medium (20-50 organisms)</strong>
          </div>
          <div class="card-body">
            <ul class="mb-0" style="font-size: 0.9em;">
              <li>Daily automated backups (local + cloud)</li>
              <li>Disk health monitoring (SMART)</li>
              <li>RAM and CPU monitoring dashboards</li>
              <li>RAID-1 or RAID-5</li>
              <li>Network monitoring</li>
            </ul>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="card">
          <div class="card-header">
            <strong>Large (50-100+ organisms)</strong>
          </div>
          <div class="card-body">
            <ul class="mb-0" style="font-size: 0.9em;">
              <li>Hourly + daily backups</li>
              <li>RAID-6 minimum</li>
              <li>Real-time monitoring (Prometheus, Grafana)</li>
              <li>Automated failover</li>
              <li>Dedicated backup server</li>
              <li>Network redundancy</li>
              <li>Quarterly capacity planning</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Section 7: Cost Estimation -->
  <section id="cost-estimation" class="mt-5">
    <h3><i class="fa fa-dollar-sign"></i> Cost Estimation (2026 Pricing)</h3>

    <h4 class="mt-4">Hardware Costs by Configuration</h4>
    <div class="card mb-4">
      <div class="card-body">
        <table class="table table-sm">
          <thead class="table-light">
            <tr>
              <th>Configuration</th>
              <th>CPU</th>
              <th>RAM</th>
              <th>Storage</th>
              <th>Network</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><strong>5 org</strong></td>
              <td>$300</td>
              <td>$100</td>
              <td>$100</td>
              <td>$100</td>
              <td><strong>$600</strong></td>
            </tr>
            <tr>
              <td><strong>10 org</strong></td>
              <td>$600</td>
              <td>$200</td>
              <td>$200</td>
              <td>$100</td>
              <td><strong>$1,100</strong></td>
            </tr>
            <tr>
              <td><strong>20 org</strong></td>
              <td>$1,200</td>
              <td>$400</td>
              <td>$400</td>
              <td>$100</td>
              <td><strong>$2,100</strong></td>
            </tr>
            <tr>
              <td><strong>50 org</strong></td>
              <td>$2,000</td>
              <td>$1,000</td>
              <td>$800</td>
              <td>$200</td>
              <td><strong>$4,000</strong></td>
            </tr>
            <tr>
              <td><strong>100 org</strong></td>
              <td>$4,000</td>
              <td>$2,000</td>
              <td>$1,500</td>
              <td>$300</td>
              <td><strong>$7,800</strong></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <h4 class="mt-4">Ongoing Operational Costs</h4>
    <div class="card mb-4">
      <div class="card-body">
        <ul>
          <li><strong>Power:</strong> ~$50-200/month (depending on server)</li>
          <li><strong>Cooling:</strong> ~$20-100/month (if data center, often included)</li>
          <li><strong>Backups (cloud):</strong> ~$50-500/month (depends on storage)</li>
          <li><strong>Network:</strong> ~$100-500/month (ISP + bandwidth)</li>
          <li><strong>Maintenance:</strong> ~$100-500/month (support, patching)</li>
        </ul>
      </div>
    </div>
  </section>

  <!-- Section 8: Growth Planning -->
  <section id="growth" class="mt-5">
    <h3><i class="fa fa-rocket"></i> Growth Planning & Scaling Timeline</h3>

    <h4 class="mt-4">Year 1 Growth Scenario</h4>
    <div class="card mb-4">
      <div class="card-body">
        <div class="alert alert-light border">
          <pre><code>Starting:     5 organisms, 11 GB
After 6 mo:  15 organisms, 35 GB
After 12 mo: 25 organisms, 55 GB

Recommendation: Start with 50 GB storage to avoid early upgrade</code></pre>
        </div>
      </div>
    </div>

    <h4 class="mt-4">Scaling Timeline & Action Items</h4>
    <div class="card mb-4">
      <div class="card-body">
        <table class="table table-sm">
          <thead class="table-light">
            <tr>
              <th>Milestone</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><strong>5 → 10 organisms</strong></td>
              <td>Upgrade RAM to 16 GB (no CPU upgrade needed)</td>
            </tr>
            <tr>
              <td><strong>10 → 20 organisms</strong></td>
              <td>Add 4 more cores, upgrade RAM to 32 GB</td>
            </tr>
            <tr>
              <td><strong>20 → 50 organisms</strong></td>
              <td>Upgrade to RAID storage, 16+ cores, 64 GB RAM</td>
            </tr>
            <tr>
              <td><strong>50 → 100 organisms</strong></td>
              <td>RAID-6 storage, 32+ cores, 128 GB RAM</td>
            </tr>
            <tr>
              <td><strong>100+ organisms</strong></td>
              <td>Consider multi-server setup, load balancing</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <h4 class="mt-4">Migration Path Example</h4>
    <div class="card mb-4">
      <div class="card-body">
        <h6>Current State (5 organisms):</h6>
        <pre class="bg-light p-3 rounded mb-3"><code>Anoura_caudifer        3.0 GB
Chamaeleo_calyptratus  2.9 GB
Lasiurus_cinereus      0.4 GB
Montipora_capitata     0.3 GB
Pteropus_vampyrus      3.6 GB
─────────────────────────────
TOTAL                  11 GB</code></pre>

        <h6>6 Months (10 organisms):</h6>
        <pre class="bg-light p-3 rounded mb-3"><code>+ Homo_sapiens         5.0 GB
+ Mus_musculus         2.8 GB
+ Danio_rerio          2.0 GB
+ Arabidopsis_thaliana 0.8 GB
+ Caenorhabditis_elegans 0.1 GB
─────────────────────────────
TOTAL                  ~24 GB
→ Action: Increase RAM to 16 GB, add 2 cores</code></pre>

        <h6>12 Months (20 organisms):</h6>
        <pre class="bg-light p-3 rounded"><code>+ 10 more organisms    ~20 GB
─────────────────────────────
TOTAL                  ~44 GB
→ Action: Move to RAID-1 storage, increase RAM to 32 GB</code></pre>
      </div>
    </div>
  </section>

  <!-- Summary -->
  <section id="summary" class="mt-5 mb-5">
    <div class="alert alert-success">
      <h5><i class="fa fa-lightbulb"></i> Key Takeaways</h5>
      <ul class="mb-0">
        <li><strong>Complete dataset baseline:</strong> 3.2 GB average per organism (with genome.fa), 132 MB database, 102 MB FASTA, 17 MB BLAST</li>
        <li><strong>Disk growth formula:</strong> (3.2 GB × organisms) × 1.2 for overhead</li>
        <li><strong>RAM formula:</strong> (organisms × 0.1 GB) + 4 GB base; peak = recommended × 1.25</li>
        <li><strong>CPU formula:</strong> (organisms ÷ 10) + 4 cores; double for optimal BLAST performance</li>
        <li><strong>Important:</strong> Not all organisms have complete data. Verify genome.fa availability when adding new organisms.</li>
        <li><strong>BLAST is resource-intensive:</strong> Limit concurrent BLAST searches; uses all available cores</li>
        <li><strong>Scale incrementally:</strong> Plan for 6-month growth cycles, upgrade components as needed</li>
      </ul>
    </div>

    <!-- Back to Help Link -->
    <div class="mt-4">
      <a href="help.php" class="btn btn-outline-secondary btn-sm">
        <i class="fa fa-arrow-left"></i> Back to Help
      </a>
    </div>
  </section>

</div>

<style>
.card {
  margin-bottom: 1rem;
}

code {
  background-color: #f5f5f5;
  padding: 2px 6px;
  border-radius: 3px;
  font-family: 'Courier New', monospace;
}

pre {
  overflow-x: auto;
}

table.table-sm {
  font-size: 0.9rem;
}
</style>
