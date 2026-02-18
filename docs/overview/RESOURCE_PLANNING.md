# MOOP System Resource Planning Guide

**Last Updated:** January 25, 2026  
**Current Data:** 5 organisms, 11 GB total

---

## Current System Analysis (5 Organisms)

### Per-Organism Disk Usage Breakdown

| Organism | Total | Database | FASTA | BLAST | Assemblies | Notes |
|----------|-------|----------|-------|-------|-----------|-------|
| **Anoura_caudifer** | 3.0 GB | 131 MB | 57 MB | 11 MB | 1 | Large genome (2.2 GB) |
| **Chamaeleo_calyptratus** | 2.9 GB | 113 MB | 97 MB | 15 MB | 1 | Large genome (2.0 GB) |
| **Lasiurus_cinereus** | 411 MB | 252 MB | 59 MB | 11 MB | 1 | Large database |
| **Montipora_capitata** | 315 MB | 155 MB | 25 MB | 21 MB | 1 | Smaller organism |
| **Pteropus_vampyrus** | 3.6 GB | 152 MB | 152 MB | 26 MB | 1 | Large genome (2.1 GB) |
| **AVERAGE** | **2.0 GB** | **161 MB** | **78 MB** | **17 MB** | 1 | Typical organism |

### Storage Breakdown by Component Type

**Database (organism.sqlite):**
- Range: 113 MB - 252 MB per organism
- Average: **161 MB per organism**
- Stores: features, annotations, relationships
- Growth: 1-2 MB per 10,000 additional annotations

**FASTA Files (genome.fa, transcript.nt.fa, cds.nt.fa, protein.aa.fa):**
- Range: 25 MB - 152 MB per organism
- Average: **78 MB per organism**
- Directly correlates with genome size
- 3-4 FASTA files per assembly
- Minimal growth after initial load

**BLAST Indices (.nhr, .nin, .nsq, .phr, .pin, .psq, etc.):**
- Range: 11 MB - 26 MB per organism
- Average: **17 MB per organism**
- Size ≈ 15-25% of source FASTA size
- Regenerated from FASTA files if lost
- Indexing time: 10-30 minutes per genome

**Other Files:**
- organism.json: ~2-5 KB per organism
- Metadata (config, taxonomy tree): ~68 KB total (grows slowly)

---

## Scaling Projections

### Disk Space Requirements

**Calculation Formula:**
```
Total Disk = (Average per organism × Number of organisms) × 1.2 (overhead)
           = (2.0 GB × N) × 1.2
```

| # Organisms | Average Disk | Recommended Disk | Notes |
|-------------|--------------|------------------|-------|
| **5** | 10 GB | 15 GB | Current |
| **10** | 20 GB | 30 GB | Small research group |
| **20** | 40 GB | 50 GB | Medium lab |
| **50** | 100 GB | 125 GB | Large lab / institute |
| **100** | 200 GB | 250 GB | Multi-lab / institutional |
| **200** | 400 GB | 500 GB | Large institution |

**Recommended Storage Strategy:**
- **Single fast disk:** Up to 50 organisms (< 125 GB)
- **RAID-1 (mirrored):** 50-100 organisms (for redundancy)
- **RAID-5 or RAID-6:** 100+ organisms (for performance + redundancy)

**Storage Breakdown for 100 organisms:**
```
Database files:        16 GB (100 × 161 MB)
FASTA files:           7.8 GB (100 × 78 MB)
BLAST indices:         1.7 GB (100 × 17 MB)
Other files/overhead:  2-3 GB
────────────────────────────────
TOTAL:                ~27-28 GB usable
                      ~35-40 GB with safety margin
                      ~50 GB recommended (with growth room)
```

---

## Memory (RAM) Requirements

### Analysis

**Per-Request Memory Usage:**
- Small search (feature lookup): 10-50 MB
- Large multi-organism search: 100-300 MB
- BLAST search (indexing): 200-500 MB
- Database query with 100k results: 150-400 MB

**Peak Memory Scenarios:**
1. **Concurrent BLAST search** + **Multi-organism search**: High memory spike
2. **Large export** (CSV/Excel of 10k+ rows): Sustained high memory
3. **Phylogenetic tree generation**: Sustained memory (grows with organism count)

### Recommendations by Scale

| Organisms | Concurrent Users | Min RAM | Recommended RAM | Peak Usage |
|-----------|------------------|---------|-----------------|------------|
| **5** | 2-3 | 2 GB | 4 GB | ~3 GB |
| **10** | 4-5 | 4 GB | 8 GB | ~5 GB |
| **20** | 8-10 | 6 GB | 12 GB | ~8 GB |
| **50** | 15-20 | 12 GB | 24 GB | ~15 GB |
| **100** | 30-50 | 24 GB | 48 GB | ~25-30 GB |

**Calculation:**
```
Recommended RAM = (Organisms × 0.1 GB) + 4 GB base
Peak RAM = Recommended × 1.25 (for load spikes)
```

**Memory Usage Breakdown (at scale - 100 organisms):**
- OS/Base system: 1-2 GB
- Apache/PHP processes (10 processes × ~200 MB): 2-4 GB
- Database query overhead: 2-3 GB (concurrent large searches)
- Free cache for OS: 2-4 GB
- Safety margin: 2-3 GB
- **Total needed: 12-18 GB minimum, 24+ GB recommended**

### RAM Optimization Tips
- Set PHP memory limit: 256-512 MB per process
- Use MySQL query cache if available (SQLite has limited caching)
- Limit concurrent BLAST searches to 1-2 (resource-intensive)
- Enable gzip compression for exports (reduces memory for large results)

---

## CPU/Processor Requirements

### Analysis

**CPU-Intensive Operations:**
1. **BLAST Searches**: 70-90% CPU utilization, 5-30 minutes per search
2. **Phylogenetic Tree Generation**: 40-60% CPU utilization, 1-5 minutes for 100 organisms
3. **Database Indexing**: 50-70% CPU utilization, 10-30 minutes per organism
4. **Taxonomy Tree Auto-generation**: Moderate CPU, ~1 second per organism API call

**CPU Allocation:**
- Regular database searches: 10-20% CPU (I/O bound, not CPU bound)
- Multi-organism searches: 20-40% CPU
- BLAST searches: 80-100% CPU (heavily parallelized by BLAST)
- Normal load (no BLAST): < 5% CPU

### Recommendations by Scale

| Organisms | Concurrent BLAST | Min Cores | Recommended Cores | Peak Load |
|-----------|------------------|-----------|-------------------|-----------|
| **5** | 0-1 | 2 | 4 | 1-2 cores active |
| **10** | 1 | 4 | 8 | 2-4 cores active |
| **20** | 1-2 | 4 | 8 | 4-8 cores active |
| **50** | 2-3 | 8 | 16 | 8-12 cores active |
| **100** | 3-5 | 16 | 24-32 | 12-20 cores active |

**Calculation:**
```
Recommended Cores = (Organisms ÷ 10) + 4
Optimal with BLAST = Cores × 2 (hyperthreading helps)
```

### CPU Optimization Tips
- BLAST uses all available cores automatically
- For 100 organisms, 16 cores (8 physical) is good baseline
- More cores = faster BLAST searches
- More cores also improves concurrency for multiple users

---

## Network I/O Considerations

### Bandwidth Requirements

**Typical User Operations:**
- Gene search: 50-200 KB (depends on results)
- Feature page load: 100-500 KB (with images, annotations)
- FASTA download (1 MB genome): 1-2 MB (compression helps)
- BLAST results export: 100 KB - 5 MB

**Recommendation:**
- 100 Mbps sufficient for < 50 organisms
- 1 Gbps recommended for 50+ organisms
- Consider internal network (10 Gbps) if on same building network

---

## Complete System Specifications by Scale

### Configuration 1: Small Lab (5 Organisms)

**Hardware:**
```
CPU:         4 cores / 2 GHz (e.g., Intel i5 or equivalent)
RAM:         8 GB
Storage:     30 GB SSD (15 GB organisms + space for backups)
Network:     1 Gbps
```

**Performance:**
- Response time (search): < 1 second
- BLAST search time: 5-15 minutes
- Multi-organism search: 1-5 seconds
- Concurrent users: 2-3

---

### Configuration 2: Small Research Group (10 Organisms)

**Hardware:**
```
CPU:         8 cores / 2.5 GHz (e.g., Intel Xeon, AMD Ryzen)
RAM:         16 GB
Storage:     50 GB SSD + 100 GB HDD for backups
Network:     1 Gbps
```

**Performance:**
- Response time (search): < 1 second
- BLAST search time: 5-20 minutes
- Multi-organism search (all 10): 2-5 seconds
- Concurrent users: 4-5

---

### Configuration 3: Medium Lab (20 Organisms)

**Hardware:**
```
CPU:         16 cores / 2.5 GHz (e.g., Intel Xeon E5 v4)
RAM:         32 GB
Storage:     RAID-1: 100 GB SSD (organisms) + 200 GB HDD (backups)
Network:     1 Gbps
```

**Performance:**
- Response time (search): < 1 second
- BLAST search time: 10-30 minutes
- Multi-organism search (all 20): 3-8 seconds
- Concurrent users: 8-10
- Concurrent BLAST: 1-2 allowed

---

### Configuration 4: Large Lab / Institute (50 Organisms)

**Hardware:**
```
CPU:         24-32 cores / 2.5+ GHz (e.g., Intel Xeon Gold, AMD EPYC)
RAM:         64 GB
Storage:     RAID-5: 150 GB SSD (organisms) + 500 GB HDD (backups)
Network:     1 Gbps (internal) / 10 Gbps (if heavy external use)
```

**Performance:**
- Response time (search): < 1 second
- BLAST search time: 15-40 minutes
- Multi-organism search (all 50): 5-15 seconds
- Concurrent users: 15-20
- Concurrent BLAST: 2-3 allowed

---

### Configuration 5: Large Institution (100 Organisms)

**Hardware:**
```
CPU:         48-64 cores / 2.5+ GHz (e.g., Intel Xeon Platinum, AMD EPYC)
RAM:         128 GB
Storage:     RAID-6: 250 GB SSD (organisms) + 1 TB HDD (backups)
Network:     10 Gbps recommended
Backup:      External NAS or cloud storage (500 GB+ capacity)
```

**Performance:**
- Response time (search): < 1 second
- BLAST search time: 20-60 minutes
- Multi-organism search (all 100): 8-20 seconds
- Concurrent users: 30-50
- Concurrent BLAST: 3-5 allowed

---

### Configuration 6: Very Large Institution (200+ Organisms)

**Hardware:**
```
CPU:         64+ cores / 2.5+ GHz (e.g., dual-socket Xeon/EPYC)
RAM:         256 GB+
Storage:     RAID-6 or RAID-10: 500+ GB SSD + redundant 2+ TB HDD
Network:     10 Gbps minimum
Load Balancer: Distribute across 2-3 servers
Backup:      Automated daily backups to external storage
```

**Performance:**
- Load balancing across multiple servers
- Consider database replication for read scaling
- Dedicated BLAST server optional
- Up to 100+ concurrent users

---

## Operational Checklist

### Minimum (5-20 organisms)
- [ ] Daily backups (local or cloud)
- [ ] Monitor disk usage (alert at 80%)
- [ ] Monitor RAM usage during peak hours
- [ ] Log rotation for MOOP error logs

### Medium (20-50 organisms)
- [ ] Daily automated backups (local + cloud)
- [ ] Disk health monitoring (SMART)
- [ ] RAM and CPU monitoring dashboards
- [ ] RAID-1 or RAID-5 for redundancy
- [ ] Network monitoring

### Large (50-100+ organisms)
- [ ] Hourly incremental + daily full backups
- [ ] Redundant storage (RAID-6 minimum)
- [ ] Real-time monitoring (Prometheus, Grafana, Nagios)
- [ ] Automated failover if available
- [ ] Dedicated backup server
- [ ] Network redundancy
- [ ] Capacity planning quarterly

---

## Growth Planning

### Year 1 Growth Scenario
```
Starting:     5 organisms, 11 GB
After 6 mo:  15 organisms, 35 GB
After 12 mo: 25 organisms, 55 GB

Recommendation: Start with 50 GB storage to avoid early upgrade
```

### Scaling Timeline
```
5 → 10 organisms:   Upgrade RAM to 16 GB (no CPU upgrade needed)
10 → 20 organisms:  Add 4 more cores, upgrade RAM to 32 GB
20 → 50 organisms:  Upgrade to RAID storage, 16+ cores, 64 GB RAM
50 → 100 organisms: RAID-6 storage, 32+ cores, 128 GB RAM
```

---

## Cost Estimation (Approximate 2026 Pricing)

### Hardware Costs by Configuration

| Config | CPU | RAM | Storage | Network | Total |
|--------|-----|-----|---------|---------|-------|
| 5 org | $300 | $100 | $100 | $100 | $600 |
| 10 org | $600 | $200 | $200 | $100 | $1,100 |
| 20 org | $1,200 | $400 | $400 | $100 | $2,100 |
| 50 org | $2,000 | $1,000 | $800 | $200 | $4,000 |
| 100 org | $4,000 | $2,000 | $1,500 | $300 | $7,800 |

**Ongoing Costs:**
- Power: ~$50-200/month (depending on server)
- Cooling: ~$20-100/month (if data center, often included)
- Backups (cloud): ~$50-500/month (depends on storage)
- Network: ~$100-500/month (ISP + bandwidth)

---

## Performance Benchmarks

### Database Queries (SQLite)
- Feature lookup by ID: 10-50 ms
- Feature search by name (substring): 50-200 ms
- Get all annotations for feature: 100-500 ms
- Multi-organism search (10 organisms): 1-5 seconds

### BLAST Searches
- Sequence validation: 100-500 ms
- BLAST run (small sequence, mammal): 5-15 minutes
- BLAST run (large sequence, 100+ organisms): 30-60 minutes
- Result parsing: 1-5 seconds

### Sequence Extraction
- Retrieve 10 sequences: 100-500 ms
- Generate FASTA (10 KB): 200-800 ms
- Export large results (1 MB CSV): 1-5 seconds

### Phylogenetic Tree Generation
- Auto-generate from 10 organisms: 1-2 minutes
- Auto-generate from 50 organisms: 5-10 minutes
- Auto-generate from 100 organisms: 10-20 minutes
- (Limited by NCBI API rate limits: 3 req/sec)

---

## Monitoring & Alerts

### Key Metrics to Monitor

**Storage:**
- Disk usage % (alert > 80%)
- Free space (alert < 10 GB available)
- Growth rate (track month-to-month)

**Memory:**
- RAM usage % (alert > 85%)
- Peak usage (track daily spikes)
- Cache hit rate (if applicable)

**CPU:**
- Utilization % (alert > 90% sustained)
- Load average (should be < cores available)
- BLAST process CPU (often 100% is OK)

**Network:**
- Bandwidth usage (peak traffic analysis)
- Connection errors (log and investigate)
- Response times (track slowdowns)

**Application:**
- Error log size (truncate when > 10 MB)
- Failed searches (track in error log)
- BLAST timeout errors (increase limit if frequent)
- Database consistency (daily checks)

---

## Migration Path Example

**Current State (5 organisms):**
```
Anoura_caudifer        3.0 GB
Chamaeleo_calyptratus  2.9 GB
Lasiurus_cinereus      0.4 GB
Montipora_capitata     0.3 GB
Pteropus_vampyrus      3.6 GB
─────────────────────────────
TOTAL                  11 GB
```

**6 Months (10 organisms):**
```
+ Homo_sapiens         5.0 GB
+ Mus_musculus         2.8 GB
+ Danio_rerio          2.0 GB
+ Arabidopsis_thaliana 0.8 GB
+ Caenorhabditis_elegans 0.1 GB
─────────────────────────────
TOTAL                  ~24 GB
→ Action: Increase RAM to 16 GB, add 2 cores
```

**12 Months (20 organisms):**
```
+ 10 more organisms    ~20 GB
─────────────────────────────
TOTAL                  ~44 GB
→ Action: Move to RAID-1 storage, increase RAM to 32 GB
```

---

## Summary Table: Quick Reference

| Scale | Disk | RAM | CPU | Users | BLAST | Notes |
|-------|------|-----|-----|-------|-------|-------|
| 5 org | 15 GB | 8 GB | 4c | 2-3 | 1 serial | Small lab |
| 10 org | 30 GB | 16 GB | 8c | 4-5 | 1 serial | Workgroup |
| 20 org | 50 GB | 32 GB | 16c | 8-10 | 1-2 | Medium lab |
| 50 org | 125 GB | 64 GB | 24c | 15-20 | 2-3 | Large lab |
| 100 org | 250 GB | 128 GB | 48c | 30-50 | 3-5 | Institution |
| 200+ org | 500+ GB | 256+ GB | 64+ c | 100+ | 5+ | Multi-lab |

---

## Assumptions & Notes

1. **Organism Size Variation:** Current organisms range 315 MB - 3.6 GB. New organisms may vary significantly.

2. **Number of Assemblies:** Current setup: 1 assembly per organism. Multiple assemblies increase disk/memory 1:1.

3. **Annotation Growth:** Most organisms have mature annotations. New organisms may have fewer initially but can grow.

4. **BLAST Database Indexing:** Takes 10-30 minutes per organism depending on genome size.

5. **Network Latency:** Assumes local network or low-latency connection to organism data.

6. **Concurrency:** "Concurrent users" = users actively searching simultaneously. Larger systems can handle brief spikes with slower response.

7. **BLAST Concurrency:** BLAST searches lock single threads. Use process-level concurrency limiting to prevent overload.

8. **Backup Storage:** Not included in primary disk calculations. Add 100-200% for backups.

9. **Operating System:** Linux/Unix with ~5-10 GB OS partition (separate from organism data).

10. **Growth Uncertainty:** Scale projections assume linear growth. Exponential growth may require earlier hardware upgrades.

---

**For questions about resource planning, contact:** [Admin contact information]

