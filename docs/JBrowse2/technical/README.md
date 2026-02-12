# JBrowse2 Technical Documentation

Technical implementation details, security, and infrastructure documentation.

## Core Technical Docs

- **[NO_COPY_FILE_HANDLING.md](NO_COPY_FILE_HANDLING.md)** - ⭐ Zero-copy file policy (no duplication)
- **[AUTO_CONFIG_GENERATION.md](AUTO_CONFIG_GENERATION.md)** - Automated config generation system
- **[ACCESS_CONTROL_UPDATE.md](ACCESS_CONTROL_UPDATE.md)** - Access control implementation
- **[SECURITY.md](SECURITY.md)** - Comprehensive security documentation
- **[dynamic-config-and-jwt-security.md](dynamic-config-and-jwt-security.md)** - JWT authentication and dynamic configs

## Infrastructure

- **[TRACKS_SERVER_IT_SETUP.md](TRACKS_SERVER_IT_SETUP.md)** - Remote tracks server setup and configuration
- **[TEXT_SEARCH_INDEXING.md](TEXT_SEARCH_INDEXING.md)** - Text search indexing for annotations
- **[FULLSCREEN_IMPLEMENTATION.md](FULLSCREEN_IMPLEMENTATION.md)** - Fullscreen mode implementation

## Key Concepts

### No-Copy Policy
All track files (BAM, BigWig, VCF, etc.) are used in-place. Only reference genomes and annotations are copied during assembly setup. This saves 50% storage and prevents file duplication.

### Access Levels
- **PUBLIC** (1) - Anonymous users
- **COLLABORATOR** (2) - Logged-in users with assembly access
- **IP_IN_RANGE** (3) - IP whitelist
- **ADMIN** (4) - Full access

### Config Caching
Configs are generated per access level and cached for performance:
- `PUBLIC.json` - Public tracks only
- `COLLABORATOR.json` - Public + Collaborator tracks
- `ADMIN.json` - All tracks

## Quick Links

- [Back to Main Documentation](../README.md)
- [Developer Guide](../DEVELOPER_GUIDE.md)
- [Security Details](SECURITY.md)
