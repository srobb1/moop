# JBrowse2 Documentation Index

**Last Cleanup:** February 18, 2026  
**Files:** 12 (down from 87 - 86% reduction)

---

## 📚 Main Guides (Start Here!)

| File | Audience | Purpose |
|------|----------|---------|
| **[README.md](README.md)** | Everyone | Overview, quick start, features |
| **[USER_GUIDE.md](USER_GUIDE.md)** | End users | How to browse genomes |
| **[ADMIN_GUIDE.md](ADMIN_GUIDE.md)** | Admins | Setup assemblies, manage access |
| **[DEVELOPER_GUIDE.md](DEVELOPER_GUIDE.md)** | Developers | Architecture, API, customization |
| **[SETUP_NEW_ORGANISM.md](SETUP_NEW_ORGANISM.md)** | Admins | Complete workflow to add organisms |
| **[SYNTENY_AND_COMPARATIVE.md](SYNTENY_AND_COMPARATIVE.md)** | Admins/Devs | Dual-assembly comparisons (⚠️ in progress) |

---

## 📖 Reference Documentation

### reference/

| File | Purpose | Status |
|------|---------|--------|
| **[TRACK_FORMATS_REFERENCE.md](reference/TRACK_FORMATS_REFERENCE.md)** | All supported track types & formats | ✅ Complete |
| **[API_REFERENCE.md](reference/API_REFERENCE.md)** | API endpoints documentation | ✅ Complete |
| **[SYNTENY_TRACKS_GUIDE.md](reference/SYNTENY_TRACKS_GUIDE.md)** | Google Sheets for synteny tracks | ✅ Complete |

**What's covered:**
- BigWig, BAM, CRAM, VCF, GFF, GTF, BED, PAF formats
- Multi-BigWig combo tracks with color groups
- PIF/PAF synteny tracks (working ✅)
- MAF tracks from Cactus (in progress ⚠️)
- MCScan anchor tracks (not fully implemented ⚠️)
- Google Sheets integration
- CLI commands and file preparation

---

## 🔒 Technical Documentation

### technical/

| File | Purpose | Status |
|------|---------|--------|
| **[SECURITY.md](technical/SECURITY.md)** | Complete security architecture | ✅ Complete |
| **[TRACKS_SERVER_IT_SETUP.md](technical/TRACKS_SERVER_IT_SETUP.md)** | Deploy remote tracks servers | ✅ Complete |

**What's covered in SECURITY.md:**
- RS256 JWT authentication
- Dynamic configuration generation
- Permission filtering (PUBLIC, COLLABORATOR, ADMIN)
- IP-based whitelisting
- External URL handling (no token leakage)
- Metadata-driven architecture
- Session management

**What's covered in TRACKS_SERVER_IT_SETUP.md:**
- Remote tracks server deployment
- Apache/Nginx configuration
- JWT key distribution
- CORS setup
- HTTP range request support
- Monitoring and troubleshooting

---

## 🔄 Workflows

### workflows/

| File | Purpose | Status |
|------|---------|--------|
| **[GOOGLE_SHEETS_WORKFLOW.md](workflows/GOOGLE_SHEETS_WORKFLOW.md)** | Bulk track management via Google Sheets | ✅ Complete |

**What's covered:**
- Setting up Google Sheets for track management
- Column formats for different track types
- Synteny track configuration
- Multi-BigWig combo tracks
- Automation scripts
- Validation and testing

---

## 🗺️ Quick Navigation

### I want to...

**...add a new organism/assembly**
→ [SETUP_NEW_ORGANISM.md](SETUP_NEW_ORGANISM.md)

**...add tracks via Google Sheets**
→ [workflows/GOOGLE_SHEETS_WORKFLOW.md](workflows/GOOGLE_SHEETS_WORKFLOW.md)

**...understand security/access control**
→ [technical/SECURITY.md](technical/SECURITY.md)

**...deploy a remote tracks server**
→ [technical/TRACKS_SERVER_IT_SETUP.md](technical/TRACKS_SERVER_IT_SETUP.md)

**...set up dual-assembly comparisons**
→ [SYNTENY_AND_COMPARATIVE.md](SYNTENY_AND_COMPARATIVE.md)

**...know what file formats are supported**
→ [reference/TRACK_FORMATS_REFERENCE.md](reference/TRACK_FORMATS_REFERENCE.md)

**...integrate JBrowse2 API into my app**
→ [reference/API_REFERENCE.md](reference/API_REFERENCE.md)

**...help end users navigate JBrowse2**
→ [USER_GUIDE.md](USER_GUIDE.md)

---

## ✅ Implementation Status

### Working Features
- ✅ Dynamic configuration generation
- ✅ JWT authentication (RS256)
- ✅ Access control (PUBLIC, COLLABORATOR, ADMIN)
- ✅ BigWig, BAM, CRAM, VCF, GFF, GTF, BED, PAF tracks
- ✅ Multi-BigWig combo tracks
- ✅ PIF/PAF whole genome synteny
- ✅ Google Sheets bulk import
- ✅ IP-based whitelisting
- ✅ External URL handling

### In Progress
- ⚠️ MAF tracks (Cactus alignments) - backend ready, needs testing
- ⚠️ MCScan anchor tracks - partially implemented
- ⏳ Dual-assembly frontend UI - API works, needs UI

---

## 📦 What Was Removed

**Deleted 75 files** (mostly from archive/ and redundant docs):

- `archive/` - 52 historical files (implementation notes, session notes, old examples)
- `implementation/` - 3 files (consolidated into SYNTENY_AND_COMPARATIVE.md)
- `reference/` - 8 files (consolidated into TRACK_FORMATS_REFERENCE.md)
- `technical/` - 12 files (consolidated into SECURITY.md)
- `workflows/` - 3 files (examples/strategies merged into main workflow)
- Top-level: `SINGLE_VS_DUAL_ASSEMBLY_TRACKS.md` (consolidated)

**Result:** 87 → 12 files (86% reduction)

---

## 📝 Documentation Standards

### File Organization
- **Main guides** - Top-level directory
- **reference/** - Detailed references, lookup tables
- **technical/** - Deep technical details, deployment
- **workflows/** - Step-by-step procedures

### Status Indicators
- ✅ Complete and tested
- ⚠️ In progress / needs testing
- ⏳ Not started / needs implementation
- ❌ Deprecated / not supported

### Maintenance
- Keep docs up-to-date with code changes
- Remove outdated information immediately
- No historical archives (use git history)
- One comprehensive doc per topic

---

**Questions?** Start with [README.md](README.md) or the appropriate guide above.
