# JBrowse2 API Reference

**Version:** 1.0  
**Base URL:** `/moop/api/jbrowse2/`  
**Authentication:** Session-based (cookies)

---

## Endpoints

### GET /api/jbrowse2/get-config.php

Get JBrowse2 configuration with assemblies accessible to the current user.

#### Request

```http
GET /moop/api/jbrowse2/get-config.php HTTP/1.1
Cookie: PHPSESSID=<session_id>
```

**Query Parameters:** None

**Headers:**
- `Cookie: PHPSESSID=<session_id>` - Session cookie (automatic from browser)

#### Response

**Success (200 OK):**

```json
{
  "assemblies": [
    {
      "name": "Anoura_caudifer_GCA_004027475.1",
      "displayName": "Anoura caudifer (GCA_004027475.1)",
      "aliases": ["ACA1", "GCA_004027475.1"],
      "accessLevel": "Public",
      "sequence": {
        "type": "ReferenceSequenceTrack",
        "trackId": "Anoura_caudifer_GCA_004027475.1-ReferenceSequenceTrack",
        "adapter": {
          "type": "IndexedFastaAdapter",
          "fastaLocation": {
            "uri": "/moop/data/genomes/Anoura_caudifer/GCA_004027475.1/reference.fasta",
            "locationType": "UriLocation"
          },
          "faiLocation": {
            "uri": "/moop/data/genomes/Anoura_caudifer/GCA_004027475.1/reference.fasta.fai",
            "locationType": "UriLocation"
          }
        }
      }
    }
  ],
  "configuration": {},
  "connections": [],
  "defaultSession": {
    "name": "New Session"
  },
  "tracks": [],
  "userAccessLevel": "Public"
}
```

**Error (500):**

```json
{
  "error": "Assembly metadata directory not found: /path/to/metadata"
}
```

#### Access Control

- **Anonymous users:** See assemblies with `defaultAccessLevel: "Public"`
- **Logged-in users:** See Public + `"Collaborator"` assemblies
- **Admins:** See all assemblies

#### Examples

**Anonymous user:**
```bash
curl -s "http://localhost:8888/moop/api/jbrowse2/get-config.php" | jq '.userAccessLevel'
# "Public"
```

**Logged-in user:**
```bash
curl -s -b "PHPSESSID=xyz" "http://localhost:8888/moop/api/jbrowse2/get-config.php" | jq '.userAccessLevel'
# "Collaborator"
```

**Filter specific assembly:**
```bash
curl -s "http://localhost:8888/moop/api/jbrowse2/get-config.php?assembly=Anoura_caudifer_GCA_004027475.1" | jq .
```

---

### GET /api/jbrowse2/assembly.php

Get complete JBrowse2 config for a specific organism-assembly, including tracks.

#### Request

```http
GET /moop/api/jbrowse2/assembly.php?organism=Anoura_caudifer&assembly=GCA_004027475.1 HTTP/1.1
Cookie: PHPSESSID=<session_id>
```

**Query Parameters:**
- `organism` (required) - Organism name (e.g., `Anoura_caudifer`)
- `assembly` (required) - Assembly ID (e.g., `GCA_004027475.1`)

#### Response

**Success (200 OK):**

```json
{
  "organism": "Anoura_caudifer",
  "assembly": "GCA_004027475.1",
  "assemblies": [
    {
      "name": "Anoura_caudifer_GCA_004027475.1",
      "displayName": "Anoura caudifer (GCA_004027475.1)",
      "aliases": ["ACA1", "GCA_004027475.1"],
      "sequence": { ... }
    }
  ],
  "tracks": [
    {
      "name": "RNA-seq Coverage",
      "trackId": "Anoura_caudifer_GCA_004027475.1_rna_seq_coverage",
      "assemblyNames": ["GCA_004027475.1"],
      "type": "QuantitativeTrack",
      "adapter": {
        "type": "BigWigAdapter",
        "bigWigLocation": {
          "uri": "http://127.0.0.1:8888/tracks/bigwig/Anoura_caudifer_GCA_004027475.1_rna_coverage.bw?token=eyJ0eXAiOiJKV1QiLCJhbGc..."
        }
      }
    }
  ],
  "defaultSession": {
    "name": "default",
    "view": {
      "type": "LinearGenomeView",
      "tracks": []
    }
  }
}
```

**Error (400):**

```json
{
  "error": "Missing organism or assembly parameter"
}
```

**Error (403):**

```json
{
  "error": "Access denied to this assembly"
}
```

#### Track URLs with JWT Tokens

Track URLs include JWT tokens for authentication:

```
http://127.0.0.1:8888/tracks/bigwig/file.bw?token=eyJ0eXAiOiJKV1Qi...
```

**Token contains:**
- `user_id` - Username or "anonymous"
- `organism` - Organism name
- `assembly` - Assembly ID
- `access_level` - User's access level
- `iat` - Issued at (timestamp)
- `exp` - Expires at (timestamp, +1 hour)

#### Examples

```bash
# Get assembly with tracks
curl -s "http://localhost:8888/moop/api/jbrowse2/assembly.php?organism=Anoura_caudifer&assembly=GCA_004027475.1" | jq .

# Check track count
curl -s "http://localhost:8888/moop/api/jbrowse2/assembly.php?organism=Anoura_caudifer&assembly=GCA_004027475.1" | jq '.tracks | length'

# Extract track tokens
curl -s "http://localhost:8888/moop/api/jbrowse2/assembly.php?organism=Anoura_caudifer&assembly=GCA_004027475.1" | jq -r '.tracks[].adapter.bigWigLocation.uri'
```

---

### GET /api/jbrowse2/fake-tracks-server.php

Test tracks server that validates JWT tokens and serves track files.

**Note:** This is a development/test endpoint. Production should use a separate tracks server.

#### Request

```http
GET /moop/api/jbrowse2/fake-tracks-server.php?file=bigwig/Organism_Assembly_track.bw&token=JWT HTTP/1.1
Range: bytes=0-1000
```

**Query Parameters:**
- `file` (required) - Track file path (e.g., `bigwig/file.bw`, `bam/file.bam`)
- `token` (optional) - JWT token (not required for localhost/internal IPs)

**Headers:**
- `Range: bytes=<start>-<end>` - Optional range request for partial file access

#### Response

**Success (200 OK or 206 Partial Content):**

```http
HTTP/1.1 206 Partial Content
Content-Type: application/octet-stream
Content-Range: bytes 0-1000/524288
Content-Length: 1001
Accept-Ranges: bytes

<binary data>
```

**Error (400):**
```
Invalid file parameter
```

**Error (403):**
```
Access denied: Invalid or missing token
```

**Error (404):**
```
File not found: filename.bw
```

**Error (416):**
```http
HTTP/1.1 416 Range Not Satisfiable
Content-Range: bytes */524288
```

#### JWT Token Validation

Token is validated if:
1. User is NOT from whitelisted IP (127.0.0.1, 192.168.x.x, 10.x.x.x)
2. Token signature is valid
3. Token has not expired
4. Token organism/assembly matches requested file

#### Examples

**With JWT token:**
```bash
TOKEN="eyJ0eXAiOiJKV1QiLCJhbGc..."
curl -H "Range: bytes=0-1000" \
  "http://localhost:8888/moop/api/jbrowse2/fake-tracks-server.php?file=bigwig/test.bw&token=$TOKEN"
```

**From localhost (no token needed):**
```bash
curl "http://127.0.0.1:8888/moop/api/jbrowse2/fake-tracks-server.php?file=bigwig/test.bw"
```

**Full file:**
```bash
curl -o test.bw "http://localhost:8888/moop/api/jbrowse2/fake-tracks-server.php?file=bigwig/test.bw"
```

---

## Data Models

### Assembly Definition

```typescript
interface AssemblyDefinition {
  name: string;                    // Unique identifier (organism_assembly)
  displayName: string;             // Human-readable name
  organism: string;                // Organism name
  assemblyId: string;              // Assembly ID (e.g., GCA_004027475.1)
  aliases: string[];               // Alternative names
  defaultAccessLevel: "Public" | "Collaborator" | "ALL";
  sequence: {
    type: "ReferenceSequenceTrack";
    trackId: string;
    adapter: {
      type: "IndexedFastaAdapter";
      fastaLocation: {
        uri: string;               // Web-accessible path
        locationType: "UriLocation";
      };
      faiLocation: {
        uri: string;               // FASTA index path
        locationType: "UriLocation";
      };
    };
  };
  metadata?: {
    createdAt?: string;            // ISO 8601 timestamp
    source?: string;
    description?: string;
  };
}
```

### Track Definition

```typescript
interface TrackDefinition {
  name: string;                    // Display name
  description?: string;
  type: string;                    // "quantitative", "alignment", etc.
  track_id: string;                // Unique identifier
  access_levels: Array<"Public" | "Collaborator" | "ALL">;
  groups?: string[];               // Track categories
  file_template: string;           // Filename pattern with {organism}, {assembly}
  format: "bigwig" | "bam" | "vcf" | "gff";
  color?: string;                  // Hex color
  display?: {
    type: string;                  // JBrowse2 display type
    [key: string]: any;            // Additional display config
  };
}
```

### JWT Token Claims

```typescript
interface JWTClaims {
  user_id: string;                 // Username or "anonymous"
  organism: string;                // Organism name
  assembly: string;                // Assembly ID
  access_level: "Public" | "Collaborator" | "ALL";
  iat: number;                     // Issued at (Unix timestamp)
  exp: number;                     // Expires at (Unix timestamp)
}
```

---

## Error Codes

| Code | Meaning | Common Causes |
|------|---------|---------------|
| 400 | Bad Request | Missing required parameters |
| 403 | Forbidden | User lacks permission to access assembly/track |
| 404 | Not Found | Assembly or track file doesn't exist |
| 416 | Range Not Satisfiable | Invalid byte range in Range header |
| 500 | Internal Server Error | Metadata directory missing, invalid JSON |

---

## Rate Limiting

Currently not implemented. For production, consider:

```php
// Rate limiting example (pseudocode)
$ip = $_SERVER['REMOTE_ADDR'];
$requests_per_minute = redis_get("api_rate_$ip");

if ($requests_per_minute > 60) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many requests']);
    exit;
}

redis_incr("api_rate_$ip");
redis_expire("api_rate_$ip", 60);
```

---

## CORS Headers

For cross-origin requests, add to API endpoints:

```php
header('Access-Control-Allow-Origin: https://jbrowse.example.com');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Range, Authorization');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 3600');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
```

---

## Versioning

Currently unversioned. For future versions, consider:

```
/moop/api/v1/jbrowse2/get-config.php
/moop/api/v2/jbrowse2/get-config.php
```

Or use Accept header:

```http
GET /moop/api/jbrowse2/get-config.php HTTP/1.1
Accept: application/vnd.moop.jbrowse.v1+json
```

---

## SDK / Client Libraries

### JavaScript/TypeScript

```typescript
// moop-jbrowse-client.ts

class MoopJBrowseClient {
  private baseUrl: string;
  
  constructor(baseUrl: string = '/moop/api/jbrowse2') {
    this.baseUrl = baseUrl;
  }
  
  async getConfig(): Promise<JBrowseConfig> {
    const response = await fetch(`${this.baseUrl}/get-config.php`, {
      credentials: 'include'  // Include session cookie
    });
    
    if (!response.ok) {
      throw new Error(`API error: ${response.status}`);
    }
    
    return response.json();
  }
  
  async getAssembly(organism: string, assembly: string): Promise<AssemblyConfig> {
    const url = `${this.baseUrl}/assembly.php?organism=${encodeURIComponent(organism)}&assembly=${encodeURIComponent(assembly)}`;
    const response = await fetch(url, { credentials: 'include' });
    
    if (!response.ok) {
      throw new Error(`API error: ${response.status}`);
    }
    
    return response.json();
  }
}

// Usage
const client = new MoopJBrowseClient();
const config = await client.getConfig();
console.log(`Found ${config.assemblies.length} accessible assemblies`);
```

### Python

```python
# moop_jbrowse_client.py

import requests
from typing import Dict, List, Optional

class MoopJBrowseClient:
    def __init__(self, base_url: str = 'http://localhost:8888/moop/api/jbrowse2'):
        self.base_url = base_url
        self.session = requests.Session()
    
    def get_config(self) -> Dict:
        """Get JBrowse2 configuration with accessible assemblies."""
        response = self.session.get(f'{self.base_url}/get-config.php')
        response.raise_for_status()
        return response.json()
    
    def get_assembly(self, organism: str, assembly: str) -> Dict:
        """Get specific assembly configuration with tracks."""
        params = {'organism': organism, 'assembly': assembly}
        response = self.session.get(f'{self.base_url}/assembly.php', params=params)
        response.raise_for_status()
        return response.json()
    
    def list_assemblies(self) -> List[Dict]:
        """Get list of accessible assemblies."""
        config = self.get_config()
        return config['assemblies']

# Usage
client = MoopJBrowseClient()
config = client.get_config()
print(f"Found {len(config['assemblies'])} accessible assemblies")
print(f"User access level: {config['userAccessLevel']}")
```

---

## Testing

### cURL Examples

```bash
# Get all accessible assemblies
curl -s "http://localhost:8888/moop/api/jbrowse2/get-config.php" | jq .

# Get specific assembly
curl -s "http://localhost:8888/moop/api/jbrowse2/assembly.php?organism=Anoura_caudifer&assembly=GCA_004027475.1" | jq .

# Download track file (with token)
TOKEN=$(curl -s "http://localhost:8888/moop/api/jbrowse2/assembly.php?organism=Anoura_caudifer&assembly=GCA_004027475.1" | jq -r '.tracks[0].adapter.bigWigLocation.uri' | grep -oP 'token=\K[^&]+')
curl -H "Range: bytes=0-1000" "http://localhost:8888/moop/api/jbrowse2/fake-tracks-server.php?file=bigwig/test.bw&token=$TOKEN"
```

### Postman Collection

```json
{
  "info": {
    "name": "MOOP JBrowse2 API",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "item": [
    {
      "name": "Get Config",
      "request": {
        "method": "GET",
        "url": "{{base_url}}/api/jbrowse2/get-config.php"
      }
    },
    {
      "name": "Get Assembly",
      "request": {
        "method": "GET",
        "url": {
          "raw": "{{base_url}}/api/jbrowse2/assembly.php?organism={{organism}}&assembly={{assembly}}",
          "query": [
            {"key": "organism", "value": "{{organism}}"},
            {"key": "assembly", "value": "{{assembly}}"}
          ]
        }
      }
    }
  ],
  "variable": [
    {
      "key": "base_url",
      "value": "http://localhost:8888/moop"
    },
    {
      "key": "organism",
      "value": "Anoura_caudifer"
    },
    {
      "key": "assembly",
      "value": "GCA_004027475.1"
    }
  ]
}
```

---

## Changelog

### Version 1.0 (2026-02-06)
- Initial API release
- Dynamic configuration based on user permissions
- JWT token-based track authentication
- Support for Public, Collaborator, and Admin access levels

---

## Support

For API issues or feature requests:
1. Check [DEVELOPER_GUIDE.md](DEVELOPER_GUIDE.md) for implementation details
2. Check [SECURITY.md](SECURITY.md) for authentication details
3. Contact the development team

---

**API Status:** Production Ready  
**Documentation Version:** 1.0
