# MOOP Documentation Guide

This guide helps you navigate all MOOP documentation and choose which documents to use for different purposes.

---

## Documentation Files Overview

### 1. **MOOP_COMPREHENSIVE_OVERVIEW.md** (THIS IS YOUR MAIN REFERENCE)
**Purpose:** Complete technical and conceptual overview of MOOP  
**Audience:** Developers, administrators, technically-minded researchers  
**Length:** ~40 pages  
**Sections:**
- Executive summary
- System architecture
- Core concepts (organisms, assemblies, features, groups)
- User access & permissions system
- Data organization (database schema, file structure)
- Search functionality (all types)
- Annotation system
- Tools & features
- Administrative management
- Dependencies & requirements
- Deployment architecture

**Use When:**
- Writing technical documentation
- Understanding how something works
- Troubleshooting issues
- Designing new features
- Training new developers

---

### 2. **MOOP_PRESENTATION_OUTLINE.md** (TALKS & FORMAL PRESENTATIONS)
**Purpose:** Pre-written outlines for presentations to different audiences  
**Audience:** Anyone preparing a talk or presentation  
**Length:** ~25 pages  
**Included:**
- Executive/Non-Technical Presentation (15 min)
- Technical/Developer Presentation (30 min)
- Research Team/Collaborators Presentation (20 min)
- System Administrators Guide outline
- Publication/Grant Writing outline
- Fact sheets (IT teams, end users, grant reviewers)
- Presentation tips for each audience
- Customization guide

**Use When:**
- Giving a talk about MOOP
- Writing a grant proposal
- Publishing a methods paper
- Training new users
- Presenting to non-technical stakeholders

---

### 3. **SYSTEM_GOALS.md** (DESIGN PHILOSOPHY)
**Purpose:** Understand design principles and coding standards  
**Audience:** Developers, maintainers  
**Length:** ~15 pages  
**Key Topics:**
- 5 core system goals (clear code, maintainable, secure, clean CSS, no duplication)
- Code quality checklist
- Naming conventions and standards
- Architecture patterns

**Use When:**
- Writing new code
- Reviewing pull requests
- Understanding why things are designed a certain way
- Training new developers on project standards

---

### 4. **PERMISSIONS_WORKFLOW.md** (ACCESS CONTROL DEEP DIVE)
**Purpose:** Detailed documentation of permission system  
**Audience:** Admins, developers implementing access control  
**Length:** ~25 pages  
**Covers:**
- 4 user types (ALL, Admin, Collaborator, Visitor)
- Permission hierarchy and logic
- Session variables and authentication flow
- users.json structure and examples
- organism_assembly_groups.json structure
- Common workflows and scenarios
- Troubleshooting access issues

**Use When:**
- Creating user accounts
- Debugging permission issues
- Implementing new access control features
- Understanding "Public" vs. restricted assemblies
- Learning how IP-based access works

---

### 5. **SECURITY_IMPLEMENTATION.md** (SECURITY ARCHITECTURE)
**Purpose:** How MOOP implements security at every layer  
**Audience:** Security-conscious admins, developers  
**Length:** ~20 pages  
**Topics:**
- Authentication mechanisms
- Authorization hierarchy
- Input validation & XSS prevention
- SQL injection prevention (prepared statements)
- Session management
- IP whitelisting
- Error handling without info leaks
- Audit logging

**Use When:**
- Evaluating security of deployment
- Setting up security best practices
- Responding to security concerns
- Implementing new features securely
- Preparing for security audits

---

### 6. **PHYLO_TREE_MANAGER.md** (PHYLOGENETIC TREE)
**Purpose:** How phylogenetic tree generation works  
**Audience:** Admins managing organism hierarchy  
**Length:** ~10 pages  
**Covers:**
- Auto-generation from NCBI Taxonomy API
- Manual editing of tree structure
- Rate limiting and API details
- Taxonomic ranks used
- Usage on homepage

**Use When:**
- Adding new organisms to system
- Regenerating phylogenetic tree
- Troubleshooting tree generation
- Customizing tree organization

---

### 7. **CONFIG_ADMIN_GUIDE.md** (CONFIGURATION)
**Purpose:** How to configure MOOP for your deployment  
**Audience:** System administrators  
**Length:** ~20 pages  
**Includes:**
- Configuration file locations
- How to edit site_config.php
- Database configuration
- IP whitelisting setup
- Email configuration (if applicable)
- Performance tuning parameters
- Feature flags and options

**Use When:**
- Setting up a new MOOP installation
- Changing site settings
- Configuring IP ranges for auto-login
- Enabling/disabling features
- Performance optimization

---

### 8. **FUNCTION_REGISTRY.md** (FUNCTION REFERENCE)
**Purpose:** Catalog of all major functions and their signatures  
**Audience:** Developers writing code  
**Sections:**
- Access control functions
- Database query helpers
- Display/rendering helpers
- Sequence extraction helpers
- File/path helpers
- Validation helpers

**Use When:**
- Looking for existing helper function
- Understanding how to implement something
- Writing new features that interact with existing code
- Learning the codebase structure

---

### 9. **DEVELOPER_GUIDE.md** (GETTING STARTED FOR DEVS)
**Purpose:** How to set up development environment and contribute  
**Audience:** New developers, contributors  
**Covers:**
- Development environment setup
- Local installation
- Testing procedures
- Git workflow
- Code submission process
- Common development tasks

**Use When:**
- Contributing to MOOP codebase
- Setting up local development environment
- Running tests
- Deploying changes

---

### 10. **README.md** (PROJECT ROOT)
**Purpose:** Quick overview and main entry point  
**Audience:** Anyone first seeing MOOP  
**Length:** ~5 pages  
**Contains:**
- Project name and description
- Quick start information
- Basic features list
- Getting help information

**Use When:**
- First discovering the project
- Quick reference for project name/description
- Pointing others to MOOP

---

## How to Use This Documentation

### Scenario 1: "I need to give a presentation about MOOP"
1. Start: **MOOP_PRESENTATION_OUTLINE.md**
2. Customize using: **MOOP_COMPREHENSIVE_OVERVIEW.md** (for deep facts)
3. Check: **SYSTEM_GOALS.md** (for design philosophy if technical audience)

### Scenario 2: "I'm a new developer starting on this project"
1. Read: **README.md** (quick overview)
2. Read: **MOOP_COMPREHENSIVE_OVERVIEW.md** (full picture)
3. Read: **SYSTEM_GOALS.md** (coding standards)
4. Read: **DEVELOPER_GUIDE.md** (setup and workflow)
5. Reference: **FUNCTION_REGISTRY.md** (as you code)

### Scenario 3: "I'm deploying MOOP and need to set up access control"
1. Read: **PERMISSIONS_WORKFLOW.md** (understand the system)
2. Read: **CONFIG_ADMIN_GUIDE.md** (how to configure)
3. Edit: `/var/www/html/users.json` (create user accounts)
4. Edit: `metadata/organism_assembly_groups.json` (set up groups)
5. Test: All access levels and scenarios
6. Reference: **SECURITY_IMPLEMENTATION.md** (verify best practices)

### Scenario 4: "I'm debugging why a user can't access something"
1. Check: **PERMISSIONS_WORKFLOW.md** - permission hierarchy section
2. Check: Users.json for their permissions
3. Check: organism_assembly_groups.json for assembly configuration
4. Check: Error logs at `/admin/error_log.php`
5. Reference: **PERMISSIONS_WORKFLOW.md** - troubleshooting section

### Scenario 5: "I'm adding a new organism to MOOP"
1. Read: **MOOP_COMPREHENSIVE_OVERVIEW.md** - adding new organism section
2. Follow: **CONFIG_ADMIN_GUIDE.md** for configuration steps
3. Use: **PHYLO_TREE_MANAGER.md** to regenerate tree
4. Test access using: **PERMISSIONS_WORKFLOW.md** guidelines

### Scenario 6: "I'm writing a methods paper about this platform"
1. Adapt: **MOOP_PRESENTATION_OUTLINE.md** section "For Publications/Grants"
2. Reference: **MOOP_COMPREHENSIVE_OVERVIEW.md** for technical depth
3. Include: Architecture diagrams from **MOOP_COMPREHENSIVE_OVERVIEW.md**
4. Cite: Any papers from your **THIRD_PARTY_LICENSES.md**

### Scenario 7: "I need to implement a new feature securely"
1. Understand: **MOOP_COMPREHENSIVE_OVERVIEW.md** - relevant section
2. Follow: **SYSTEM_GOALS.md** - code quality checklist
3. Implement securely: **SECURITY_IMPLEMENTATION.md** - relevant pattern
4. Test: **DEVELOPER_GUIDE.md** - testing procedures

### Scenario 8: "I'm concerned about security of my deployment"
1. Read: **SECURITY_IMPLEMENTATION.md** (all security measures)
2. Check: **PERMISSIONS_WORKFLOW.md** - permission hierarchy
3. Review: Configuration in **CONFIG_ADMIN_GUIDE.md**
4. Audit: Error logs at `/admin/error_log.php`
5. Test: All access levels thoroughly

---

## Document Organization by Topic

### Permissions & Access Control
- Primary: **PERMISSIONS_WORKFLOW.md**
- Reference: **MOOP_COMPREHENSIVE_OVERVIEW.md** - User Access & Permissions section
- Security: **SECURITY_IMPLEMENTATION.md**

### Architecture & Design
- Primary: **MOOP_COMPREHENSIVE_OVERVIEW.md** - System Architecture section
- Philosophy: **SYSTEM_GOALS.md**
- Security: **SECURITY_IMPLEMENTATION.md** - Architecture section

### Administration & Configuration
- Primary: **CONFIG_ADMIN_GUIDE.md**
- Reference: **MOOP_COMPREHENSIVE_OVERVIEW.md** - Admin section
- Phylogenetic tree: **PHYLO_TREE_MANAGER.md**

### Development & Contributing
- Primary: **DEVELOPER_GUIDE.md**
- Standards: **SYSTEM_GOALS.md**
- Reference: **FUNCTION_REGISTRY.md**
- Architecture: **MOOP_COMPREHENSIVE_OVERVIEW.md**

### Presenting & Documentation
- Primary: **MOOP_PRESENTATION_OUTLINE.md**
- Facts: **MOOP_COMPREHENSIVE_OVERVIEW.md**

### Database & Data Management
- Primary: **MOOP_COMPREHENSIVE_OVERVIEW.md** - Data Organization section
- Details: **FUNCTION_REGISTRY.md** - database functions

### Organisms, Groups, Features
- Primary: **MOOP_COMPREHENSIVE_OVERVIEW.md** - Core Concepts section
- For presentations: **MOOP_PRESENTATION_OUTLINE.md**

### Search Functionality
- Primary: **MOOP_COMPREHENSIVE_OVERVIEW.md** - Search Functionality section
- For users: **MOOP_PRESENTATION_OUTLINE.md** - Research Team outline

---

## Creating New Documentation

When creating new documentation, follow this hierarchy:

1. **Update existing files first** - Add to relevant sections of existing docs
2. **Create specific guides** if needed - Use naming like `FEATURE_NAME_GUIDE.md`
3. **Link from** **DOCUMENTATION_GUIDE.md** (this file) in the appropriate scenario
4. **Keep comprehensive overview updated** - Changes to core system should update MOOP_COMPREHENSIVE_OVERVIEW.md
5. **Update presentation outlines** - If changes affect how MOOP is presented

---

## Documentation Maintenance

Review documentation when:
- [ ] Adding new features
- [ ] Changing permission system
- [ ] Adding new organisms
- [ ] Modifying configuration
- [ ] Making security changes
- [ ] Updating dependencies

Update checklist:
- [ ] MOOP_COMPREHENSIVE_OVERVIEW.md
- [ ] SYSTEM_GOALS.md (if design decisions change)
- [ ] PERMISSIONS_WORKFLOW.md (if access control changes)
- [ ] SECURITY_IMPLEMENTATION.md (if security changes)
- [ ] MOOP_PRESENTATION_OUTLINE.md (if capabilities change)

---

**Last Updated:** January 2025

For questions or corrections, contact the MOOP development team.
