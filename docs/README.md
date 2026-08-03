# MOOP Documentation

**Last Updated:** February 18, 2026  
**Organization:** Categorized by purpose (current, planning, overview)

---

## 🗺️ Quick Navigation

| I want to... | Go to... |
|--------------|----------|
| **Configure the system** | [current/admin/CONFIG_GUIDE.md](current/admin/CONFIG_GUIDE.md) |
| **Manage permissions** | [current/admin/PERMISSIONS_GUIDE.md](current/admin/PERMISSIONS_GUIDE.md) |
| **Understand security** | [current/admin/SECURITY_GUIDE.md](current/admin/SECURITY_GUIDE.md) |
| **Help end users** | [current/user/USER_GUIDE.md](current/user/USER_GUIDE.md) |
| **Learn about BLAST** | [current/features/BLAST_FLOW_CHART.md](current/features/BLAST_FLOW_CHART.md) |
| **Use JBrowse2** | [JBrowse2/README.md](JBrowse2/README.md) |
| **Integrate Galaxy tools** | [Galaxy/GALAXY_INTEGRATION.md](Galaxy/GALAXY_INTEGRATION.md) |
| **Plan future features** | [planning/](planning/) |
| **Get system overview** | [overview/SYSTEM_OVERVIEW.md](overview/SYSTEM_OVERVIEW.md) |

---

## 📚 Documentation Structure

```
docs/
├── README.md                          ← You are here
│
├── current/                           ← Production system docs
│   ├── admin/
│   │   ├── CONFIG_GUIDE.md           - System configuration
│   │   ├── PERMISSIONS_GUIDE.md      - Access control
│   │   └── SECURITY_GUIDE.md         - Security implementation
│   ├── user/
│   │   └── USER_GUIDE.md             - End user walkthrough
│   ├── features/
│   │   ├── BLAST_FLOW_CHART.md       - BLAST search system
│   │   └── PHYLO_TREE_MANAGER.md     - Phylogenetic tree features
│                                      (attribution lives in notes/THIRD_PARTY_LICENSES.md)
│
├── planning/                          ← Future features & ideas
│   └── SEQUENCE_ALIGNER.md           - Galaxy UI integration (pending)
│
├── overview/                          ← High-level system docs
│   ├── SYSTEM_OVERVIEW.md            - Complete system documentation
│   ├── PRESENTATION.md               - Presentation outlines
│   ├── GOALS.md                      - System goals & vision
│   └── RESOURCE_PLANNING.md          - Infrastructure planning
│
├── Galaxy/                            ← Galaxy integration (COMPLETE)
│   ├── GALAXY_INTEGRATION.md         - Setup & usage guide
│   ├── GALAXY_INTEGRATION_STATUS.md  - Implementation status
│   ├── GALAXY_INTEGRATION_PLAN.md    - Architecture details
│   └── GALAXY_MAFFT_TEST.md          - Testing documentation
│
├── JBrowse2/                          ← Genome browser (COMPLETE)
│   ├── README.md                     - Start here for JBrowse2
│   ├── USER_GUIDE.md                 - For end users
│   ├── ADMIN_GUIDE.md                - For administrators
│   ├── DEVELOPER_GUIDE.md            - For developers
│   ├── SETUP_NEW_ORGANISM.md         - Adding organisms
│   ├── SYNTENY_AND_COMPARATIVE.md    - Comparative genomics
│   ├── reference/                    - Track formats & API
│   ├── technical/                    - Security & deployment
│   └── workflows/                    - Google Sheets integration
│
└── SETUP/                             ← System installation
    └── ...
```

---

## 📖 Documentation by Audience

### For System Administrators

**Essential:**
- [CONFIG_GUIDE.md](current/admin/CONFIG_GUIDE.md) - All configuration files
- [PERMISSIONS_GUIDE.md](current/admin/PERMISSIONS_GUIDE.md) - Managing user access
- [SECURITY_GUIDE.md](current/admin/SECURITY_GUIDE.md) - Security implementation

**JBrowse2:**
- [JBrowse2/ADMIN_GUIDE.md](JBrowse2/ADMIN_GUIDE.md) - Managing genome browser
- [JBrowse2/SETUP_NEW_ORGANISM.md](JBrowse2/SETUP_NEW_ORGANISM.md) - Adding organisms

**Galaxy:**
- [Galaxy/GALAXY_INTEGRATION.md](Galaxy/GALAXY_INTEGRATION.md) - Galaxy setup

### For End Users

- [current/user/USER_GUIDE.md](current/user/USER_GUIDE.md) - Complete user walkthrough
- [JBrowse2/USER_GUIDE.md](JBrowse2/USER_GUIDE.md) - Using genome browser

### For Developers

**Core System:**
- [overview/SYSTEM_OVERVIEW.md](overview/SYSTEM_OVERVIEW.md) - Complete architecture
- [current/admin/SECURITY_GUIDE.md](current/admin/SECURITY_GUIDE.md) - Security patterns

**JBrowse2:**
- [JBrowse2/DEVELOPER_GUIDE.md](JBrowse2/DEVELOPER_GUIDE.md) - Architecture & API
- [JBrowse2/technical/SECURITY.md](JBrowse2/technical/SECURITY.md) - JWT & auth

**Features:**
- [current/features/BLAST_FLOW_CHART.md](current/features/BLAST_FLOW_CHART.md) - BLAST system
- [current/features/PHYLO_TREE_MANAGER.md](current/features/PHYLO_TREE_MANAGER.md) - Taxonomy tree

**Planning:**
- [planning/SEQUENCE_ALIGNER.md](planning/SEQUENCE_ALIGNER.md) - Pending UI integration

### For Presentations

- [overview/PRESENTATION.md](overview/PRESENTATION.md) - Presentation outlines
- [overview/SYSTEM_OVERVIEW.md](overview/SYSTEM_OVERVIEW.md) - Comprehensive overview
- [overview/GOALS.md](overview/GOALS.md) - Vision & goals

---

## ✅ Implementation Status

### Production Features (Documented)

| Feature | Documentation | Status |
|---------|---------------|--------|
| **Core System** | current/admin/ | ✅ Production |
| **JBrowse2** | JBrowse2/ | ✅ Production |
| **Galaxy Backend** | Galaxy/ | ✅ Production |
| **BLAST Search** | current/features/ | ✅ Production |
| **Phylo Tree** | current/features/ | ✅ Production |
| **Permissions** | current/admin/ | ✅ Production |
| **Security** | current/admin/ | ✅ Production |

### Planned Features

| Feature | Documentation | Status |
|---------|---------------|--------|
| **Sequence Aligner UI** | planning/SEQUENCE_ALIGNER.md | ⏳ Backend done, UI pending |

---

## 📝 Documentation Standards

### Categories

- **current/** - Production system documentation (keep up-to-date)
- **planning/** - Future features and ideas (move to current/ when implemented)
- **overview/** - High-level system documentation (update quarterly)

### Status Indicators

- ✅ Complete and tested
- ⚠️ In progress / needs testing
- ⏳ Not started / needs implementation
- 📋 Planning/design phase

### Maintenance

**When adding a feature:**
1. Plan in `planning/` directory
2. Implement feature
3. Move doc to appropriate `current/` subdirectory
4. Delete old planning doc
5. Update this README

**When removing a feature:**
1. Delete from `current/` directory
2. Update this README
3. No need to archive (use git history)

---

## 🔍 Finding Documentation

**Search by keyword:**
```bash
cd /data/moop/docs
grep -r "keyword" .
```

**List all docs:**
```bash
find /data/moop/docs -name "*.md" | sort
```

**Check recent changes:**
```bash
cd /data/moop/docs
git log --oneline --all -- .
```

---

## 📦 External Resources

- **JBrowse2 Official:** https://jbrowse.org/jb2/
- **Galaxy Project:** https://galaxyproject.org/
- **UseGalaxy.org:** https://usegalaxy.org/

---

**Questions?** Start with the Quick Navigation table above or browse by category.
