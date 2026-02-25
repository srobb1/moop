# MOOP User Authentication Flow Diagram

**Version:** 1.0 (Created 2026-02-25)  
**Shows:** Complete user authentication, session management, and access control

---

## Complete Authentication Flow

```
┌─────────────────────────────────────────────────────────────────────────┐
│                      USER VISITS MOOP WEBSITE                           │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                    ┌───────────────┴───────────────┐
                    │                               │
                    ▼                               ▼
        
    🌐 ANY PAGE REQUEST              🔐 EXPLICIT LOGIN
    (index.php, tools, etc)          (login.php form submission)
                    │                               │
                    │                               │
                    ▼                               ▼

┌─────────────────────────────────────────────────────────────────────────┐
│                   LAYER 1: SESSION INITIALIZATION                       │
│                   (includes/access_control.php)                         │
│                                                                         │
│  📍 Step 1: Start or resume PHP session                                │
│             session_start()                                             │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼

┌─────────────────────────────────────────────────────────────────────────┐
│                   LAYER 2: IP-BASED AUTO-AUTHENTICATION                 │
│                   (access_control.php lines 17-43)                      │
│                                                                         │
│  📍 Step 2: Check if visitor IP is in whitelist ranges                 │
│             - Load auto_login_ip_ranges from config                     │
│             - Check: 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16         │
│             - Check: 127.0.0.0/8 (localhost)                           │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                    ┌───────────────┴───────────────┐
                    │                               │
                    ▼                               ▼
        
    ✅ IP IN WHITELIST              ❌ IP NOT IN WHITELIST
    (Internal Network)               (External/Public)
                    │                               │
                    │                               │
                    ▼                               │
                                                    │
    🔓 AUTO-LOGIN GRANTED                           │
    $_SESSION["logged_in"] = true                   │
    $_SESSION["username"] = "IP_USER_10.0.5.42"     │
    $_SESSION["access_level"] = "IP_IN_RANGE"       │
    $_SESSION["access"] = []  (full access)         │
                    │                               │
                    │                               │
    Skip to Layer 6 (Access Granted)                │
                                                    │
                                                    ▼

┌─────────────────────────────────────────────────────────────────────────┐
│                   LAYER 3: SESSION STATUS CHECK                         │
│                   (All pages check session state)                       │
│                                                                         │
│  📍 Step 3: Is user already logged in?                                 │
│             Check: $_SESSION["logged_in"] === true                      │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                    ┌───────────────┴───────────────┐
                    │                               │
                    ▼                               ▼
        
    ✅ ALREADY LOGGED IN            ❌ NOT LOGGED IN
    (Has valid session)              (Anonymous visitor)
                    │                               │
                    │                               │
    Skip to Layer 6 │                               │
                    │                               ▼

┌─────────────────────────────────────────────────────────────────────────┐
│                   LAYER 4: LOGIN FORM SUBMISSION                        │
│                   (login.php - POST request processing)                 │
│                                                                         │
│  📍 Step 4a: Receive username and password                             │
│              $username = $_POST["username"]                             │
│              $password = $_POST["password"]                             │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼

┌─────────────────────────────────────────────────────────────────────────┐
│                   LAYER 5: CREDENTIAL VALIDATION                        │
│                   (login.php lines 27-45)                               │
│                                                                         │
│  📍 Step 4b: Load users.json file                                      │
│              /var/www/html/users.json                                   │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼

┌─────────────────────────────────────────────────────────────────────────┐
│  📍 Step 4c: Username exists?                                           │
│              isset($users[$username])                                   │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                    ┌───────────────┴───────────────┐
                    │                               │
                    ▼                               ▼
        
    ❌ USERNAME NOT FOUND           ✅ USERNAME EXISTS
                    │                               │
                    │                               │
    Return error:   │                               ▼
    "Invalid username                   
     or password"                   ┌─────────────────────────────────┐
                    │               │ 📍 Step 4d: Verify password     │
                    │               │    password_verify(             │
                    │               │      $password,                 │
                    │               │      $users[$username]["password"])│
    Show login form │               └─────────────────────────────────┘
    with error      │                               │
                    │               ┌───────────────┴───────────────┐
                    │               │                               │
                    │               ▼                               ▼
                    │   
                    │   ❌ PASSWORD INCORRECT      ✅ PASSWORD CORRECT
                    │               │                               │
                    └───────────────┘                               │
                                                                    ▼

┌─────────────────────────────────────────────────────────────────────────┐
│  📍 Step 4e: Password Verification Details (SECURITY)                  │
│                                                                         │
│  HOW IT WORKS:                                                          │
│  1. Password stored in users.json is HASHED (bcrypt)                   │
│     Example: $2y$10$abcdefg...60_characters                             │
│                                                                         │
│  2. password_verify() performs:                                         │
│     a. Extract salt from stored hash                                    │
│     b. Hash submitted password with same salt                           │
│     c. Compare hashes in CONSTANT TIME (timing attack resistant)        │
│                                                                         │
│  3. Returns true if match, false otherwise                              │
│                                                                         │
│  WHY BCRYPT?                                                            │
│  - Computationally expensive (slow by design)                           │
│  - Makes brute force attacks impractical                                │
│  - Automatic salting (unique hash per password)                         │
│  - Industry standard for password storage                               │
└─────────────────────────────────────────────────────────────────────────┘
                                                                    │
                                                                    ▼

┌─────────────────────────────────────────────────────────────────────────┐
│  📍 Step 4f: Create Session & Set Variables                            │
│              (login.php lines 28-38)                                    │
│                                                                         │
│  $_SESSION["logged_in"] = true                                          │
│  $_SESSION["username"] = "researcher123"                                │
│  $_SESSION["access"] = [                                                │
│      "Organism_A" => ["Assembly_1", "Assembly_2"],                      │
│      "Organism_B" => ["Assembly_1"]                                     │
│  ]                                                                      │
│  $_SESSION["role"] = "admin" or "user"                                  │
│                                                                         │
│  // Determine access level from role                                   │
│  IF role === "admin":                                                   │
│      $_SESSION["access_level"] = "ADMIN"                                │
│  ELSE:                                                                  │
│      $_SESSION["access_level"] = "COLLABORATOR"                         │
└─────────────────────────────────────────────────────────────────────────┘
                                                                    │
                                                                    ▼

┌─────────────────────────────────────────────────────────────────────────┐
│  📍 Step 4g: Redirect to Home                                           │
│              header("Location: index.php")                              │
│              exit;                                                      │
└─────────────────────────────────────────────────────────────────────────┘
                                                                    │
                                                                    ▼

┌─────────────────────────────────────────────────────────────────────────┐
│                   LAYER 6: ACCESS LEVEL DETERMINATION                   │
│                   (access_control.php - Helper functions)               │
│                                                                         │
│  📍 Step 5: Determine user's access level                              │
│             get_access_level() returns:                                 │
│                                                                         │
│  ADMIN (Level 4)                                                        │
│    - Full system access                                                 │
│    - Can manage users                                                   │
│    - Sees all organisms/assemblies                                      │
│    - Source: role === "admin"                                           │
│                                                                         │
│  IP_IN_RANGE (Level 3)                                                  │
│    - Auto-authenticated by IP                                           │
│    - Sees all organisms/assemblies                                      │
│    - Cannot manage users                                                │
│    - Source: IP in whitelist                                            │
│                                                                         │
│  COLLABORATOR (Level 2)                                                 │
│    - Authenticated with username/password                               │
│    - Sees PUBLIC + explicitly granted assemblies                        │
│    - Grant list in $_SESSION["access"]                                  │
│    - Source: role === "user"                                            │
│                                                                         │
│  PUBLIC (Level 1)                                                       │
│    - Anonymous visitor                                                  │
│    - Sees only PUBLIC assemblies/tracks                                 │
│    - No login required                                                  │
│    - Source: No session, external IP                                    │
└─────────────────────────────────────────────────────────────────────────┘
                                                                    │
                                                                    ▼

┌─────────────────────────────────────────────────────────────────────────┐
│                   LAYER 7: RESOURCE ACCESS CHECK                        │
│                   (Per-page authorization)                              │
│                                                                         │
│  📍 Step 6: Check if user can access requested resource                │
│                                                                         │
│  Example: Viewing Organism_X / Assembly_1                              │
└─────────────────────────────────────────────────────────────────────────┘
                                                                    │
                                                                    ▼

┌─────────────────────────────────────────────────────────────────────────┐
│  Check 1: Is assembly marked as PUBLIC?                                │
│           is_public_assembly("Organism_X", "Assembly_1")                │
└─────────────────────────────────────────────────────────────────────────┘
                                                                    │
                    ┌───────────────────────────────────────────────┘
                    │
        ┌───────────┴───────────┐
        │                       │
        ▼                       ▼
    
    ✅ PUBLIC                 ❌ NOT PUBLIC (Protected)
                                          │
    Grant access                          │
    (All users can view)                  ▼
                                          
                            ┌─────────────────────────────────┐
                            │ Check 2: User access level      │
                            └─────────────────────────────────┘
                                          │
                            ┌─────────────┴─────────────┐
                            │                           │
                            ▼                           ▼
                    
                    ADMIN or IP_IN_RANGE        COLLABORATOR
                            │                           │
                            │                           │
                    Grant access                        ▼
                    (Full access)           
                                            ┌─────────────────────────────────┐
                                            │ Check 3: Explicit grant?        │
                                            │ $_SESSION["access"]["Organism_X"]│
                                            │ contains "Assembly_1"?          │
                                            └─────────────────────────────────┘
                                                        │
                                            ┌───────────┴───────────┐
                                            │                       │
                                            ▼                       ▼
                                    
                                    ✅ GRANTED              ❌ NOT GRANTED
                                                                    │
                                    Allow access            Redirect to:
                                                            access_denied.php

┌─────────────────────────────────────────────────────────────────────────┐
│                   ✅ ACCESS GRANTED - RENDER PAGE                       │
│                                                                         │
│  • Page content displayed                                               │
│  • User can interact with tools                                         │
│  • Access logged (optional)                                             │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Session Lifecycle

### Session Creation
```
User logs in
    ↓
PHP creates session file: /tmp/sess_[random_id]
    ↓
Session cookie sent to browser: PHPSESSID=[random_id]
    ↓
Session data stored server-side
```

### Session Persistence
```
User visits another page
    ↓
Browser sends PHPSESSID cookie
    ↓
PHP loads session file: /tmp/sess_[random_id]
    ↓
$_SESSION variables available
```

### Session Termination
```
User clicks logout OR Session expires
    ↓
logout.php calls:
  - session_destroy()
  - unset($_SESSION)
  - Delete session cookie
    ↓
User redirected to index.php as anonymous
```

---

## Password Storage & Verification

### When User Account is Created
```
Admin enters password: "MySecurePass123!"
    ↓
password_hash($password, PASSWORD_BCRYPT)
    ↓
Generated hash: $2y$10$abcdefghijklmnopqrstuvwxyz0123456789...
    |           |   |  |
    |           |   |  └── Hash output (31 chars)
    |           |   └────── Salt (22 chars)
    |           └────────── Cost factor (10 = 2^10 iterations)
    └────────────────────── Algorithm identifier (2y = bcrypt)
    ↓
Stored in users.json:
{
  "researcher123": {
    "password": "$2y$10$abcdefg...60_chars_total",
    "role": "user",
    "access": {...}
  }
}
```

### When User Logs In
```
User submits: username="researcher123", password="MySecurePass123!"
    ↓
Load stored hash from users.json: $2y$10$abcdefg...
    ↓
password_verify("MySecurePass123!", "$2y$10$abcdefg...")
    ↓
Internally:
  1. Extract salt from stored hash
  2. Hash submitted password with same salt and cost
  3. Compare: newly_hashed === stored_hash
  4. Comparison done in CONSTANT TIME (no early exit)
    ↓
Return: true (match) or false (no match)
    ↓
If true: Create session, redirect to index
If false: Show error "Invalid username or password"
```

---

## Access Level Hierarchy

```
┌─────────────────────────────────────────────────────────────┐
│                        ADMIN                                │
│  - Full system access                                       │
│  - User management                                          │
│  - All organisms/assemblies                                 │
│  - Admin dashboard                                          │
└─────────────────────────────────────────────────────────────┘
                            ↓ includes all below

┌─────────────────────────────────────────────────────────────┐
│                      IP_IN_RANGE                            │
│  - Auto-authenticated (no login)                            │
│  - All organisms/assemblies                                 │
│  - No user management                                       │
│  - Relaxed JWT expiry                                       │
└─────────────────────────────────────────────────────────────┘
                            ↓ includes all below

┌─────────────────────────────────────────────────────────────┐
│                     COLLABORATOR                            │
│  - Manual login required                                    │
│  - PUBLIC + granted assemblies                              │
│  - Explicit grant list                                      │
│  - Standard JWT expiry                                      │
└─────────────────────────────────────────────────────────────┘
                            ↓ includes all below

┌─────────────────────────────────────────────────────────────┐
│                        PUBLIC                               │
│  - No login required                                        │
│  - PUBLIC assemblies only                                   │
│  - Read-only access                                         │
│  - Anonymous                                                │
└─────────────────────────────────────────────────────────────┘
```

---

## Security Features

### 1. Password Hashing (Bcrypt)
**Why Bcrypt?**
- **Slow by design:** Takes ~100ms to verify (prevents brute force)
- **Automatic salting:** Each password gets unique hash
- **Adaptive cost:** Can increase iterations as computers get faster
- **Industry standard:** Used by major platforms

**What gets stored:**
```
NEVER:     "MySecurePass123!"  ← Plain text password
ALWAYS:    "$2y$10$abcdefg..."  ← Bcrypt hash
```

### 2. Timing Attack Resistance
**Problem:**
```php
// ❌ VULNERABLE - Early exit reveals information
if ($password === $stored_password) {
    return true;  // Returns immediately when characters match
}
```

**Solution:**
```php
// ✅ SECURE - password_verify() uses constant-time comparison
password_verify($password, $stored_hash);
// Always takes same time regardless of match position
```

### 3. Session Security
**Recommended Configuration:**
```php
// session configuration (should be set)
ini_set('session.cookie_httponly', 1);     // No JavaScript access
ini_set('session.cookie_secure', 1);       // HTTPS only
ini_set('session.cookie_samesite', 'Lax'); // CSRF protection
ini_set('session.gc_maxlifetime', 3600);   // 1 hour timeout
```

### 4. IP Whitelist Auto-Login
**Security Considerations:**
- ✅ Convenient for internal users
- ✅ No password needed on trusted network
- ⚠️ IP spoofing possible if not using HTTPS
- ⚠️ Shared computers on internal network
- ✅ Audit trail maintained (username = "IP_USER_x.x.x.x")

---

## Common Authentication Scenarios

### Scenario 1: Internal Lab User
```
IP: 10.0.5.42 (internal network)
    ↓
access_control.php detects IP in range
    ↓
Auto-login:
  - access_level = IP_IN_RANGE
  - Full access to all data
  - No password required
```

### Scenario 2: External Collaborator
```
IP: 203.0.113.45 (external)
    ↓
Not in whitelist → No auto-login
    ↓
User visits login.php
    ↓
Enters: username="jane_doe", password="..."
    ↓
Credentials validated → Session created
    ↓
access_level = COLLABORATOR
    ↓
Can access:
  - PUBLIC assemblies
  - Organism_X/Assembly_A (in grant list)
```

### Scenario 3: Public Anonymous Visitor
```
IP: 198.51.100.10 (external)
    ↓
Not in whitelist → No auto-login
    ↓
User browses site without logging in
    ↓
access_level = PUBLIC (default)
    ↓
Can access:
  - Only PUBLIC assemblies
  - Public tools
  - No protected data
```

### Scenario 4: Administrator
```
User logs in with admin account
    ↓
role = "admin" (in users.json)
    ↓
access_level = ADMIN
    ↓
Can access:
  - Everything
  - User management
  - Admin dashboard
  - System configuration
```

---

## File Locations

### Authentication Files
- **`login.php`** - Login form and credential validation
- **`logout.php`** - Session termination
- **`access_denied.php`** - Access denied page
- **`includes/access_control.php`** - Auto-login and helper functions

### Data Files
- **`/var/www/html/users.json`** - User accounts (passwords hashed)
- **`config/site_config.php`** - IP whitelist ranges
- **`/tmp/sess_*`** - PHP session files (server-side)

### Session Variables
- **`$_SESSION["logged_in"]`** - Boolean: Is user authenticated?
- **`$_SESSION["username"]`** - String: Username or "IP_USER_x.x.x.x"
- **`$_SESSION["access_level"]`** - String: PUBLIC/COLLABORATOR/IP_IN_RANGE/ADMIN
- **`$_SESSION["access"]`** - Array: Granted organisms/assemblies
- **`$_SESSION["role"]`** - String: "admin" or "user"

---

## Security Best Practices

### ✅ DO
- Use bcrypt for password hashing
- Set secure session cookie flags (HttpOnly, Secure, SameSite)
- Implement session timeout (1-4 hours)
- Log authentication attempts
- Use HTTPS for all traffic
- Regenerate session ID on login
- Generic error messages ("Invalid username or password")

### ❌ DON'T
- Store passwords in plain text
- Use MD5 or SHA1 for passwords
- Reveal which field is wrong ("Username not found" vs "Wrong password")
- Allow unlimited login attempts (implement rate limiting)
- Store sensitive data in cookies
- Use predictable session IDs

---

## Troubleshooting

### Issue: "Invalid username or password" even with correct credentials
**Possible causes:**
1. Password not hashed correctly in users.json
2. Wrong username (case-sensitive)
3. users.json file corrupted

**Check:**
```bash
# View user entry
cat /var/www/html/users.json | grep -A5 "username"

# Password should start with $2y$ (bcrypt)
# If not, rehash password:
php -r 'echo password_hash("YourPassword", PASSWORD_BCRYPT);'
```

### Issue: Session not persisting
**Possible causes:**
1. Cookies disabled in browser
2. session_start() not called
3. Session files not writable

**Check:**
```bash
# Check session directory
ls -la /tmp/sess_*

# Check permissions
ls -ld /tmp
```

### Issue: IP whitelist not working
**Possible causes:**
1. Behind proxy (wrong IP detected)
2. IP range misconfigured
3. Session already set to different type

**Check:**
```php
// Add to test page:
echo "Your IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
echo "IP ranges configured: " . print_r($ip_ranges, true);
```

---

**Created:** 2026-02-25  
**Version:** 1.0  
**Related:** SECURITY_FLOW_DIAGRAM.md (JWT token validation)
