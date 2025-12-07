<?php

/**
 * Collect environment info useful for licensing checks:
 * machine ID, HTTP host, server IP, MAC.
 */
function sg_collect_env_info(): array
{
    $extra = [];

    // Machine ID
    if (function_exists('sg_get_machine_id')) {
        $extra['Machine ID'] = sg_get_machine_id();
    }

    // HTTP Host / Server name
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
    if ($host === '') {
        $host = '(not available)';
    }
    $extra['HTTP Host'] = $host;

    // Server IP (IP locking)
    $serverIp = $_SERVER['SERVER_ADDR'] ?? ($_SERVER['LOCAL_ADDR'] ?? '');
    if ($serverIp === '') {
        $serverIp = '(not available)';
    }
    $extra['Server IP'] = $serverIp;

    // MAC addresses
    if (function_exists('sg_get_mac_addresses')) {
        $macs = sg_get_mac_addresses();
        if (!empty($macs) && is_array($macs)) {
            $extra['MAC Addresses'] = array_values(array_filter($macs, static function ($v) {
                return (string)$v !== '';
            }));
        }
    } else {
        // Best-effort Linux fallback
        $mac = trim(@shell_exec("ip link 2>/dev/null | awk '/ether/ {print \$2; exit}'"));
        if ($mac !== '') {
            $extra['MAC Address'] = $mac;
        }
    }

    return $extra;
}

// -----------------------------------------------------------------------------
// CLI mode: plain-text output
// -----------------------------------------------------------------------------
if (PHP_SAPI === 'cli') {
    $envInfoCodes = [1, 2, 3, 4, 5, 9, 13];
    $extra = in_array((int)$code, $envInfoCodes, true) ? sg_collect_env_info() : [];

    switch ($code) {
        case 1:
            echo "Invalid IP address", PHP_EOL;
            break;
        case 2:
            echo "Invalid domain / HTTP host", PHP_EOL;
            break;
        case 3:
            echo "Invalid MAC address", PHP_EOL;
            break;
        case 4:
            echo "Invalid machine ID", PHP_EOL;
            break;
        case 5:
            echo "Remote verification URL locking: this script is not licensed to run from this URL or server context.", PHP_EOL;
            break;
        case 6:
            echo "Invalid external license file (CRC check failed or license is corrupted).", PHP_EOL;
            break;
        case 9:
            echo "Script has expired.", PHP_EOL;
            break;
        case 13:
            echo "This script requires a valid external license file in order to run (missing or not found).", PHP_EOL;
            break;
        case 20:
            echo "This script requires an active Internet connection in order to run.", PHP_EOL;
            break;
        default:
            echo "Unknown error #{$code}", PHP_EOL, $message, PHP_EOL;
            break;
    }

    if (!empty($extra)) {
        echo PHP_EOL, "Environment information:", PHP_EOL;
        foreach ($extra as $label => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            echo "  {$label}: {$value}", PHP_EOL;
        }
    }

    exit;
}

// -----------------------------------------------------------------------------
// Web output: JSON or Bootstrap HTML
// -----------------------------------------------------------------------------
http_response_code(500);
header_remove('Content-Type'); // we'll set this explicitly later

// Defaults
$alertClass = 'danger';
$headline   = 'SourceGuardian protection error';
$summary    = '';
$details    = '';
$extra      = [];

// For HTML, we only show env info on some codes
$envInfoCodes = [1, 2, 3, 4, 5, 9, 13];
if (in_array((int)$code, $envInfoCodes, true)) {
    $extra = sg_collect_env_info();
}

// Map code → messages
switch ($code) {
    case 1:
        $summary = 'Invalid IP address';
        $details = 'This script is not licensed to run from the current server IP address.';
        break;

    case 2:
        $summary = 'Invalid domain / HTTP host';
        $details = 'The requested host name does not match the domain(s) allowed by this license.';
        break;

    case 3:
        $summary = 'Invalid MAC address';
        $details = 'The license is bound to a different network adapter MAC address than the one currently in use.';
        break;

    case 4:
        $summary = 'Invalid machine ID';
        $details = 'The encoded script expects a different machine ID than the current one.';
        break;

    case 5:
        $summary    = 'Remote verification URL locking';
        $details    = 'This script is not licensed to run from this URL or server context (remote verification URL lock).';
        $alertClass = 'danger';
        break;

    case 6:
        $summary    = 'Invalid external license file';
        $details    = 'A license file which is required to run this protected script is invalid or corrupted.';
        $alertClass = 'danger';
        break;

    case 9:
        $summary    = 'Script has expired';
        $details    = 'The protected script has expired. Please update or renew the script or license.';
        $alertClass = 'warning';
        break;

    case 13:
        $summary    = 'Missing external license file';
        $details    = 'This script requires a valid external license file in order to run, but none was found.';
        $alertClass = 'warning';
        break;

    case 20:
        $summary    = 'Internet connection required';
        $details    = 'This script requires a working Internet connection in order to run.';
        $alertClass = 'info';
        break;

    default:
        $summary    = 'Unknown error #' . (int)$code;
        $details    = $message;
        $alertClass = 'secondary';
        break;
}

// -----------------------------------------------------------------------------
// JSON mode: /?json
// -----------------------------------------------------------------------------
if (isset($_GET['json'])) {
    $env = sg_collect_env_info();

    $machineId = $env['Machine ID'] ?? null;
    $httpHost  = $env['HTTP Host'] ?? null;
    $serverIp  = $env['Server IP'] ?? null;

    $macArray = null;
    if (isset($env['MAC Addresses']) && is_array($env['MAC Addresses'])) {
        $macArray = $env['MAC Addresses'];
    } elseif (isset($env['MAC Address'])) {
        $macArray = [$env['MAC Address']];
    }

    $payload = [
        'error_code'     => (int)$code,
        'error_summary'  => $summary,
        'error_details'  => $details,
        'engine_message' => $message,
        'license_environment' => [
            'machine_id'    => $machineId,
            'http_host'     => $httpHost,
            'server_ip'     => $serverIp,
            'mac_addresses' => $macArray,
        ],
    ];

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_PRETTY_PRINT);
    exit;
}

// -----------------------------------------------------------------------------
// HTML mode
// -----------------------------------------------------------------------------
header('Content-Type: text/html; charset=utf-8');

$loaderVersion = function_exists('sg_loader_version')
    ? sg_loader_version()
    : 'Unknown';

?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SourceGuardian Error</title>

    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css"
      rel="stylesheet"
    >
  </head>
  <body>
    <div class="container mt-4 mb-4">
      <div class="row justify-content-center">
        <div class="col-lg-8">
          <div class="card shadow-sm border-<?php echo htmlspecialchars($alertClass); ?>">
            <div class="card-header bg-<?php echo htmlspecialchars($alertClass); ?> text-white">
              SourceGuardian Error
            </div>
            <div class="card-body">
              <h5 class="card-title mb-3">
                <?php echo htmlspecialchars($headline); ?>
              </h5>

              <div class="alert alert-<?php echo htmlspecialchars($alertClass); ?>" role="alert">
                <strong><?php echo htmlspecialchars($summary); ?></strong>
                <?php if (!empty($details)): ?>
                  <div class="mt-2 mb-0">
                    <?php echo nl2br(htmlspecialchars($details)); ?>
                  </div>
                <?php endif; ?>
              </div>

              <?php if (!empty($message)): ?>
                <p class="small text-muted mt-3 mb-0">
                  Engine message:<br>
                  <?php echo nl2br(htmlspecialchars($message)); ?>
                </p>
              <?php endif; ?>

              <p class="small text-muted mt-2 mb-0">
                Error code: <?php echo (int)$code; ?>
              </p>

              <?php if (!empty($extra)): ?>
                <div class="card mt-3 border-info">
                  <div class="card-header bg-info text-white">
                    Environment Information
                  </div>
                  <div class="card-body">
                    <?php foreach ($extra as $label => $value): ?>
                      <div class="row mb-2">
                        <div class="col-sm-4 fw-bold">
                          <?php echo htmlspecialchars($label); ?>:
                        </div>
                        <div class="col-sm-8">
                          <?php
                            if (is_array($value)) {
                                echo htmlspecialchars(implode(', ', $value));
                            } else {
                                echo htmlspecialchars($value);
                            }
                          ?>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <p class="text-center text-muted small mt-3 mb-0">
            If this problem persists, please contact the site administrator.
          </p>

          <p class="text-center text-muted small mt-2">
            SourceGuardian Loader Version:
            <?php echo htmlspecialchars($loaderVersion); ?>
          </p>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
