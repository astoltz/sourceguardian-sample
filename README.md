# 📦 SourceGuardian Licensing Prototype
*A demonstration project for evaluating advanced SourceGuardian features.*

This project provides a fully working prototype that demonstrates how a PHP application can integrate with **SourceGuardian licensing**, including:

- Machine ID / IP / MAC restrictions  
- External license files  
- Build-specific constants  
- Remote verification URLs  
- Custom error handling (`sg_error_page.php`)  
- JSON diagnostic output for automation  
- A Bootstrap-styled frontend showing environment + license metadata  

The prototype is designed to be **developer-friendly**, providing convenient features such as copy-to-clipboard controls and machine-readable response formats.

---

## 🚀 Features

### ✔ Inline Copy-to-Clipboard Controls
`index.php` exposes all licensable fields (IP, domain, machine ID, MAC addresses, license file name, build ID) with **inline copy buttons** for quick license generation.

### ✔ JSON Diagnostic Mode
Both endpoints support a machine-readable JSON form:

| Endpoint | Description |
|---------|-------------|
| `index.php?json` | Full environment + loader + license + file info |
| `sg_error_page.php?json` | Standardized error payload + license-relevant environment |

This allows external systems to pull licensing candidate data programmatically.

### ✔ Remote Verification URL
`index.php` automatically generates the correct URL required for the SG feature:

```bash
--remote-verification-url=http(s)://host/index.php?sg_verification_id=1
```

The script returns `sg_get_verification_id()` when the loader calls it.

### ✔ External License File Resolution
The filename passed via:

```bash
--const license_file=FILENAME
```

is automatically located by walking upward from the script directory until found.

### ✔ Accurate Time-Left Display
Expiration timestamps display using the same style SourceGuardian uses in its CLI:

```text
01d02h03m04s
05m10s
12s
expired
```

### ✔ Custom SG Error Page
`sg_error_page.php`:

- Handles all major SG error codes with helpful descriptions
- Exposes the same environment values used in restrictions
- Supports `?json` mode for automated diagnostics
- Includes SourceGuardian Loader version in the footer
- Uses Bootstrap for clean and readable presentation

---

## 🛠 Build Process

The project is built using `sourceguardian` via the included `build.sh`:

```bash
#!/bin/bash -xe

LICENSE_FILE=sg_api_test.lic

sourceguardian \
    -r src/ \
    -o dst \
    -p @sg_header.php \
    --keep-file-date \
    -n \
    --phpversion 8.2 \
    --phpversion 8.3 \
    --phpversion 8.4 \
    --projid <proj id> \
    --projkey <proj key> \
    --external "$LICENSE_FILE" \
    --catch ERR_ALL=sg_catchall \
    --const build_id=developer1 \
    --const license_file="$LICENSE_FILE"

sourceguardian \
    sg_error_page.php \
    -o dst/src \
    --keep-file-date \
    -n \
    --phpversion 8.2 \
    --phpversion 8.3 \
    --phpversion 8.4 \
    --entangle 5 \
    --projid <proj id> \
    --projkey <proj key> \
    --const build_id=developer1 \
    --const license_file="$LICENSE_FILE"
```

### Key build concepts

| Feature | Purpose |
|--------|---------|
| `--const build_id=…` | Identifies build variant inside PHP |
| `--const license_file=…` | Makes the license filename available to PHP |
| `--external file.lic` | Enables SG external license validation |
| `--catch ERR_ALL=sg_catchall` | Routes all SG errors to the custom handler |
| Multiple `--phpversion` | Ensures compatibility with PHP 8.2–8.4 |

---

## 🌐 Deployment

Deployment is handled by an example rsync command:

```bash
rsync -rvlt dst/src/ phpuser@server:/path/to/public_html/
```

You may deploy to any web server with the SourceGuardian loader installed.

**Important:**  
Ensure the external license file (`sg_api_test.lic`) is deployed in a directory where the loader can find it.

---

## 🔍 Testing

### 1. Visit `index.php`
You’ll see:

- IP, Host, MAC, Machine ID
- License file presence
- Loader version
- Build ID
- Copy buttons
- Remote verification URL
- Time-left calculation (if license expiry defined)
- Ability to fetch diagnostic JSON

### 2. Test JSON Mode

```text
/index.php?json
```

### 3. Test the Remote Verification ID

```text
/index.php?sg_verification_id=1
```

### 4. Trigger SG Error Page

Break the license on purpose:

- Remove the license file
- Change machine ID
- Change host
- Change IP
- Alter MAC

Then load the protected script.  
You’ll see:

- Custom Bootstrap error UI  
- Detailed reason  
- Environment values  
- Loader version  
- JSON optional mode:

```text
/sg_error_page.php?json
```

---

## 📑 Licensable Fields (Available in Both HTML & JSON)

| Field | Purpose |
|-------|---------|
| Machine ID | Unique hardware identifier |
| HTTP Host | Domain locking |
| Server IP | IP locking |
| MAC addresses | NIC locking |
| License filename | For external license validation |
| Build ID | Runtime build metadata |

These are exactly the fields a licensing workflow needs to generate license files or restrict execution.

---

## 🧩 Project Structure

```text
src/
  index.php              # Main demo page
  sg_error_page.php      # Unified SG error output
  sg_header.php          # Optional file header injected by SG

build.sh                 # Encoder script
README.md                # Project documentation
```

---

## 📘 Purpose of This Prototype

This project exists to test and validate:

- Real-world SourceGuardian licensing flows  
- Developer-friendly UX around licensing restrictions  
- Automated tooling to extract machine information  
- Custom remote verification systems  
- Compatibility across PHP versions  
- Deployment approaches with external license files  
