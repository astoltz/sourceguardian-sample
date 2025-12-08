<?php
// -----------------------------------------------------------------------------
// sg_verification_id support for --remote-verification-url
// -----------------------------------------------------------------------------
if (isset($_GET['sg_verification_id'])) {
    header('Content-Type: text/plain; charset=utf-8');
    echo function_exists('sg_get_verification_id') ? sg_get_verification_id() : '';
    exit;
}

// -----------------------------------------------------------------------------
// Helper functions
// -----------------------------------------------------------------------------
function sg_demo_loader_version(): string
{
    return function_exists('sg_loader_version')
        ? sg_loader_version()
        : 'Unknown';
}

function sg_demo_machine_id(): string
{
    return function_exists('sg_get_machine_id')
        ? sg_get_machine_id()
        : 'Unavailable';
}

function sg_demo_server_ip(): string
{
    return $_SERVER['SERVER_ADDR'] ?? ($_SERVER['LOCAL_ADDR'] ?? 'Unavailable');
}

function sg_demo_http_host(): string
{
    return $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'Unavailable');
}

/**
 * Safe wrapper around sg_get_const().
 */
function sg_demo_get_const(string $name): ?string
{
    if (!function_exists('sg_get_const')) return null;

    $value = sg_get_const($name);
    return ($value === false || $value === '' || $value === null) ? null : (string)$value;
}

/**
 * License filename passed via --const license_file=...
 */
function sg_demo_license_filename(): ?string
{
    return sg_demo_get_const('license_file');
}

/**
 * Walk upward from this directory until license file is found.
 */
function sg_demo_license_file_path(): ?string
{
    $filename = sg_demo_license_filename();
    if ($filename === null) return null;

    $dir = __DIR__;
    while (true) {
        $candidate = $dir . DIRECTORY_SEPARATOR . $filename;
        if (is_file($candidate)) return realpath($candidate) ?: $candidate;

        $parent = dirname($dir);
        if ($parent === $dir) break;
        $dir = $parent;
    }
    return null;
}

/**
 * Get MAC addresses.
 */
function sg_demo_mac_addresses(): array
{
    if (function_exists('sg_get_mac_addresses')) {
        $macs = sg_get_mac_addresses();
        if (is_array($macs)) {
            return array_values(array_filter($macs, fn($v) => (string)$v !== ''));
        }
    }

    // Linux fallback
    $macs = [];
    $out = @shell_exec("ip link 2>/dev/null | awk '/ether/ {print \$2}'");
    if ($out) {
        foreach (preg_split('/\R+/', trim($out)) as $m) {
            if ($m !== '') $macs[] = $m;
        }
    }
    return $macs;
}

/**
 * Format seconds into SG CLI-style time-left:
 * 01d02h03m04s, 05m10s, 12s, etc.
 */
function sg_demo_format_time_left(int $seconds): string
{
    if ($seconds <= 0) return 'expired';

    $d = intdiv($seconds, 86400);
    $seconds %= 86400;
    $h = intdiv($seconds, 3600);
    $seconds %= 3600;
    $m = intdiv($seconds, 60);
    $s = $seconds % 60;

    $parts = [];
    if ($d > 0) $parts[] = sprintf('%02dd', $d);
    if ($h > 0 || $d > 0) $parts[] = sprintf('%02dh', $h);
    if ($m > 0 || $h > 0 || $d > 0) $parts[] = sprintf('%02dm', $m);
    $parts[] = sprintf('%02ds', $s);

    return implode('', $parts);
}

// -----------------------------------------------------------------------------
// Gather runtime values
// -----------------------------------------------------------------------------
$loaderLoaded    = extension_loaded('ixed') || function_exists('sg_loader_version');
$machineId       = sg_demo_machine_id();
$loaderVersion   = sg_demo_loader_version();
$serverIp        = sg_demo_server_ip();
$httpHost        = sg_demo_http_host();
$licenseFileName = sg_demo_license_filename();
$licensePath     = sg_demo_license_file_path();
$licenseFound    = ($licensePath !== null);
$buildId         = sg_demo_get_const('build_id');
$edition         = sg_demo_get_const('edition');
$macAddresses    = sg_demo_mac_addresses();

// File info
$encoderName     = sg_demo_get_const('encoder');
$encoderVersion  = sg_demo_get_const('version');
$encodeDate      = sg_demo_get_const('encode_date');
$licenseDate     = sg_demo_get_const('license_date');
$expireDate      = sg_demo_get_const('expire_date');

$expireRemaining = ($expireDate !== null && is_numeric($expireDate))
    ? ((int)$expireDate - time())
    : null;

// Remote verification URL
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$uri = strtok($_SERVER['REQUEST_URI'] ?? '/index.php', '?');
$verificationUrl = "{$scheme}://{$httpHost}{$uri}?sg_verification_id=1";

// -----------------------------------------------------------------------------
// JSON MODE: /?json
// -----------------------------------------------------------------------------
if (isset($_GET['json'])) {
    $payload = [
        'environment' => [
            'http_host'     => $httpHost,
            'server_ip'     => $serverIp,
            'machine_id'    => $machineId,
            'mac_addresses' => $macAddresses ?: null,
        ],
        'loader' => [
            'loaded' => (bool)$loaderLoaded,
            'version'=> $loaderVersion,
        ],
        'license' => [
            'const_name' => $licenseFileName,
            'found'      => $licenseFound,
            'path'       => $licensePath,
        ],
        'build' => [
            'build_id' => $buildId,
            'edition'  => $edition,
        ],
        'file_info' => [
            'encoder'      => $encoderName,
            'encoder_ver'  => $encoderVersion,
            'encode_date'  => $encodeDate,
            'license_date' => $licenseDate,
            'expire_date'  => $expireDate,
            'time_left'    => ($expireRemaining !== null
                                ? sg_demo_format_time_left($expireRemaining)
                                : null),
        ],
        'remote_verification_url' => $verificationUrl,
    ];

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_PRETTY_PRINT);
    exit;
}

?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>SourceGuardian Demo</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
  </head>
  <body class="bg-light">

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
      <div class="container">
        <a class="navbar-brand" href="#">SourceGuardian Demo</a>
        <div class="collapse navbar-collapse">
          <ul class="navbar-nav ms-auto">
            <?php if ($buildId !== null): ?>
            <li class="nav-item">
              <span class="nav-link disabled">Build ID: <?= htmlspecialchars($buildId) ?></span>
            </li>
            <?php endif; ?>
            <li class="nav-item">
              <span class="nav-link disabled">Loader: <?= htmlspecialchars($loaderVersion) ?></span>
            </li>
          </ul>
        </div>
      </div>
    </nav>

    <main class="container my-4">
      <div class="row gy-4">
        <div class="col-lg-10 mx-auto">
          <div class="card shadow-sm">
            <div class="card-header">SourceGuardian Protection Overview</div>
            <div class="card-body">

              <p>
                This page displays environment details and configuration constants used
                for SourceGuardian licensing, external license validation, and remote verification.
              </p>

              <div class="row gy-3">
                <!-- Environment -->
                <div class="col-md-6">
                  <h6 class="text-muted text-uppercase small mb-2">Environment</h6>
                  <dl class="row mb-0">

                    <!-- HTTP Host -->
                    <dt class="col-5">HTTP Host</dt>
                    <dd class="col-7">
                      <div class="input-group input-group-sm">
                        <input type="text" class="form-control" readonly value="<?= htmlspecialchars($httpHost) ?>">
                        <button class="btn btn-outline-secondary btn-copy" data-copy-text="<?= htmlspecialchars($httpHost, ENT_QUOTES) ?>">Copy</button>
                      </div>
                    </dd>

                    <!-- Server IP -->
                    <dt class="col-5">Server IP</dt>
                    <dd class="col-7">
                      <div class="input-group input-group-sm">
                        <input type="text" class="form-control" readonly value="<?= htmlspecialchars($serverIp) ?>">
                        <button class="btn btn-outline-secondary btn-copy" data-copy-text="<?= htmlspecialchars($serverIp, ENT_QUOTES) ?>">Copy</button>
                      </div>
                    </dd>

                    <!-- Machine ID -->
                    <dt class="col-5">Machine ID</dt>
                    <dd class="col-7">
                      <div class="input-group input-group-sm">
                        <input type="text" class="form-control" readonly value="<?= htmlspecialchars($machineId) ?>">
                        <button class="btn btn-outline-secondary btn-copy" data-copy-text="<?= htmlspecialchars($machineId, ENT_QUOTES) ?>">Copy</button>
                      </div>
                    </dd>

                    <!-- MAC Addresses -->
                    <dt class="col-5">MAC Addresses</dt>
                    <dd class="col-7">
                      <?php if (!empty($macAddresses)): ?>
                        <?php foreach ($macAddresses as $mac): ?>
                          <div class="input-group input-group-sm mb-1">
                            <input type="text" class="form-control" readonly value="<?= htmlspecialchars($mac) ?>">
                            <button class="btn btn-outline-secondary btn-copy" data-copy-text="<?= htmlspecialchars($mac, ENT_QUOTES) ?>">Copy</button>
                          </div>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <span class="text-muted small">No MAC addresses detected.</span>
                      <?php endif; ?>
                    </dd>

                  </dl>
                </div>

                <!-- Loader & License -->
                <div class="col-md-6">
                  <h6 class="text-muted text-uppercase small mb-2">Loader & License</h6>
                  <dl class="row mb-0">

                    <dt class="col-5">Loader</dt>
                    <dd class="col-7">
                      <?= $loaderLoaded ? '<span class="badge bg-success">Loaded</span>' : '<span class="badge bg-danger">Not loaded</span>' ?>
                    </dd>

                    <dt class="col-5">Loader Version</dt>
                    <dd class="col-7"><?= htmlspecialchars($loaderVersion) ?></dd>

                    <!-- license_file constant -->
                    <dt class="col-5">License Const</dt>
                    <dd class="col-7">
                      <div class="input-group input-group-sm">
                        <input type="text" class="form-control" readonly value="<?= htmlspecialchars($licenseFileName ?? '') ?>">
                        <button class="btn btn-outline-secondary btn-copy" data-copy-text="<?= htmlspecialchars($licenseFileName ?? '', ENT_QUOTES) ?>">Copy</button>
                      </div>
                    </dd>

                    <!-- actual license file detection -->
                    <dt class="col-5">License File</dt>
                    <dd class="col-7">
                      <?php if ($licenseFileName === null): ?>
                        <span class="badge bg-secondary">Not configured</span>
                      <?php elseif ($licenseFound): ?>
                        <span class="badge bg-success">Found</span>
                        <div class="small text-muted mt-1"><?= htmlspecialchars($licensePath) ?></div>
                      <?php else: ?>
                        <span class="badge bg-warning text-dark">Not found (<?= htmlspecialchars($licenseFileName) ?>)</span>
                      <?php endif; ?>
                    </dd>

                    <!-- Build ID -->
                    <?php if ($buildId !== null): ?>
                    <dt class="col-5">Build ID</dt>
                    <dd class="col-7">
                      <div class="input-group input-group-sm">
                        <input type="text" class="form-control" readonly value="<?= htmlspecialchars($buildId) ?>">
                        <button class="btn btn-outline-secondary btn-copy" data-copy-text="<?= htmlspecialchars($buildId, ENT_QUOTES) ?>">Copy</button>
                      </div>
                    </dd>
                    <?php endif; ?>

                    <!-- Edition -->
                    <?php if ($edition !== null): ?>
                    <dt class="col-5">Edition</dt>
                    <dd class="col-7">
                      <div class="input-group input-group-sm">
                        <input type="text" class="form-control" readonly value="<?= htmlspecialchars($edition) ?>">
                        <button class="btn btn-outline-secondary btn-copy" data-copy-text="<?= htmlspecialchars($edition, ENT_QUOTES) ?>">Copy</button>
                      </div>
                    </dd>
                    <?php endif; ?>
                  </dl>
                </div>
              </div>

              <hr class="my-4">

              <!-- Remote Verification URL -->
              <h6 class="text-muted text-uppercase small mb-2">Remote Verification URL</h6>
              <p>
                Configure this as your <code>--remote-verification-url</code>.
                When the loader validates this script, it will request this URL.
              </p>

              <div class="input-group input-group-sm mb-0">
                <input
                  type="text"
                  class="form-control"
                  readonly
                  value="<?= htmlspecialchars($verificationUrl) ?>"
                >
                <button
                  type="button"
                  class="btn btn-outline-secondary btn-copy"
                  data-copy-text="<?= htmlspecialchars($verificationUrl, ENT_QUOTES) ?>"
                >
                  Copy
                </button>
              </div>

              <!-- File Info -->
              <?php if ($encoderName !== null): ?>
              <hr class="my-4">

              <h6 class="text-muted text-uppercase small mb-2">File Info</h6>
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <tr>
                    <th style="width:25%;">Encoder</th>
                    <td><?= htmlspecialchars($encoderName) ?> <?= $encoderVersion ? htmlspecialchars(" $encoderVersion") : '' ?></td>
                  </tr>

                  <tr>
                    <th>File encoded</th>
                    <td><?= ($encodeDate && is_numeric($encodeDate)) ? date(DATE_ATOM, (int)$encodeDate) : '<span class="text-muted">Unknown</span>' ?></td>
                  </tr>

                  <tr>
                    <th>License date</th>
                    <td><?= ($licenseDate && is_numeric($licenseDate)) ? date(DATE_ATOM, (int)$licenseDate) : '<span class="text-muted">Not defined</span>' ?></td>
                  </tr>

                  <tr>
                    <th>License expiry</th>
                    <td>
                      <?php if ($expireDate && is_numeric($expireDate)): ?>
                        <?= date(DATE_ATOM, (int)$expireDate) ?>
                        (<?= sg_demo_format_time_left($expireRemaining ?? 0) ?>)
                      <?php else: ?>
                        <span class="text-muted">No expiry</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                </table>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Copy-to-clipboard logic -->
    <script>
    (function () {
      const buttons = document.querySelectorAll('.btn-copy');

      buttons.forEach(btn => {
        btn.addEventListener('click', () => {
          const text = btn.getAttribute('data-copy-text');
          if (!text) return;

          const original = btn.textContent;
          btn.textContent = 'Copied!';

          if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(() => {
              setTimeout(() => btn.textContent = original, 1200);
            });
          } else {
            const tmp = document.createElement('input');
            tmp.style.position = 'absolute';
            tmp.style.left = '-9999px';
            tmp.value = text;
            document.body.appendChild(tmp);
            tmp.select();
            document.execCommand('copy');
            document.body.removeChild(tmp);
            setTimeout(() => btn.textContent = original, 1200);
          }
        });
      });
    })();
    </script>

  </body>
</html>
