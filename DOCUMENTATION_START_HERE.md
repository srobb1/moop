# 📚 MOOP Documentation - Start Here

Welcome! This guide helps you find the right documentation for your needs.

---

## 🚀 Quick Navigation

**Choose based on what you need:**

### 1️⃣ **"I need to understand MOOP comprehensively"**
👉 **Read:** [`MOOP_COMPREHENSIVE_OVERVIEW.md`](MOOP_COMPREHENSIVE_OVERVIEW.md)
- 40+ pages of complete system documentation
- Architecture, concepts, permissions, search, tools, deployment
- Best for: Developers, admins, anyone learning the system

### 2️⃣ **"I need to give a presentation or write a proposal"**
👉 **Read:** [`MOOP_PRESENTATION_OUTLINE.md`](MOOP_PRESENTATION_OUTLINE.md)
- Pre-written outlines for 3 different audiences
- 15-min, 30-min, and 20-min presentations
- Fact sheets for different stakeholders
- Best for: Presentations, grant proposals, publications

### 3️⃣ **"I'm lost - where do I find what?"**
👉 **Read:** [`DOCUMENTATION_GUIDE.md`](DOCUMENTATION_GUIDE.md)
- Index of all documentation files
- How to use each document
- Scenario-based navigation
- Best for: Finding what you need quickly

---

## 📖 All Documentation Files

| File | Purpose | Audience | Length |
|------|---------|----------|--------|
| **MOOP_COMPREHENSIVE_OVERVIEW.md** | Complete technical overview | Developers, Admins | 40 pages |
| **MOOP_PRESENTATION_OUTLINE.md** | Presentations & proposals | Presenters, Writers | 25 pages |
| **DOCUMENTATION_GUIDE.md** | How to navigate docs | Everyone | 15 pages |
| **SYSTEM_GOALS.md** | Design philosophy & standards | Developers | 15 pages |
| **PERMISSIONS_WORKFLOW.md** | Access control system | Admins, Developers | 25 pages |
| **SECURITY_IMPLEMENTATION.md** | Security architecture | Security-minded admins | 20 pages |
| **CONFIG_ADMIN_GUIDE.md** | Configuration & setup | Admins | 20 pages |
| **PHYLO_TREE_MANAGER.md** | Phylogenetic tree | Admins | 10 pages |
| **FUNCTION_REGISTRY.md** | Function reference | Developers | ~30 pages |
| **DEVELOPER_GUIDE.md** | Development setup | New developers | 15 pages |

---

## 🎯 Common Scenarios

### Scenario: "I'm giving a talk in 2 hours"
```
1. Open: MOOP_PRESENTATION_OUTLINE.md
2. Pick the outline that matches your time (15/20/30 min)
3. Customize with your data/examples
4. Done!
```

### Scenario: "I need to deploy MOOP"
```
1. Read: MOOP_COMPREHENSIVE_OVERVIEW.md (Architecture section)
2. Read: CONFIG_ADMIN_GUIDE.md
3. Follow: Step-by-step in deployment section
4. Reference: PERMISSIONS_WORKFLOW.md for access control
```

### Scenario: "I'm new to this project and need to understand everything"
```
1. Read: MOOP_COMPREHENSIVE_OVERVIEW.md (all sections)
2. Read: SYSTEM_GOALS.md (understand design)
3. Reference: FUNCTION_REGISTRY.md (as you code)
4. Read: DEVELOPER_GUIDE.md (when ready to contribute)
```

### Scenario: "I need to write a scientific methods paper"
```
1. Adapt: MOOP_PRESENTATION_OUTLINE.md → "For Publications" section
2. Reference: MOOP_COMPREHENSIVE_OVERVIEW.md (for deep facts)
3. Include: Architecture diagrams and data stats
4. Done!
```

### Scenario: "A user can't access their data"
```
1. Check: PERMISSIONS_WORKFLOW.md (permission hierarchy)
2. Verify: users.json and organism_assembly_groups.json
3. Debug: /admin/error_log.php
4. Reference: PERMISSIONS_WORKFLOW.md (troubleshooting)
```

---

## 💡 Pro Tips

✅ **For Presentations:** Copy text from `MOOP_PRESENTATION_OUTLINE.md` directly into your slides  
✅ **For Understanding:** Read `MOOP_COMPREHENSIVE_OVERVIEW.md` end-to-end  
✅ **For Finding Things:** Use `DOCUMENTATION_GUIDE.md` as an index  
✅ **For Coding:** Keep `FUNCTION_REGISTRY.md` and `SYSTEM_GOALS.md` nearby  
✅ **For Permissions Questions:** `PERMISSIONS_WORKFLOW.md` is your source of truth  
✅ **For Security Concerns:** `SECURITY_IMPLEMENTATION.md` covers everything  

---

## 🔍 Key Concepts at a Glance

**Organism** = A biological species (e.g., *Homo sapiens*)  
**Assembly** = A genome build for an organism (e.g., GRCh38)  
**Feature** = A genomic element (e.g., a specific gene)  
**Group** = UI organization (e.g., "Bats", "Public", "Project_2024")  
**Annotation** = Functional hit from analysis (BLAST, domain, etc.)  

---

## 🆘 Still Lost?

**Check:** `DOCUMENTATION_GUIDE.md` → "Scenario-based navigation" section

Each scenario is mapped to exactly which documents to read.

---

## 📝 Creating New Docs

When adding documentation:
1. Update existing files if adding to known topics
2. Link new docs from `DOCUMENTATION_GUIDE.md`
3. Add entry to table above
4. Keep comprehensive overview current

---

## 📅 Last Updated
January 2025

---

**Ready to dive in?** Pick one of the three main docs above and get started! 👆
