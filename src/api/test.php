<?php
/**
 * API Test Endpoint
 * 
 * Tests database, AMI connection, and returns diagnostic info
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache');

require_once __DIR__ . '/../includes/db.php';

$response = [
    'success' => true,
    'timestamp' => date('Y-m-d H:i:s'),
    'tests' => []
];

// Test 1: Database connection
try {
    $db = Database::getInstance();
    $version = $db->fetchValue("SELECT VERSION()");
    $response['tests']['database'] = [
        'status' => 'ok',
        'version' => $version
    ];
} catch (Exception $e) {
    $response['tests']['database'] = [
        'status' => 'error',
        'error' => $e->getMessage()
    ];
    $response['success'] = false;
}

// Test 2: Tables exist
if (isset($db)) {
    try {
        $tables = $db->fetchAll("SHOW TABLES");
        $tableCount = count($tables);
        $response['tests']['tables'] = [
            'status' => $tableCount >= 15 ? 'ok' : 'warning',
            'count' => $tableCount,
            'expected' => 19
        ];
    } catch (Exception $e) {
        $response['tests']['tables'] = [
            'status' => 'error',
            'error' => $e->getMessage()
        ];
    }
}

// Test 3: AMI config exists
if (isset($db)) {
    try {
        $ami = $db->fetchOne("SELECT ami_host, ami_port, ami_username FROM ami_config WHERE is_active = 1 LIMIT 1");
        if ($ami) {
            $response['tests']['ami_config'] = [
                'status' => 'ok',
                'host' => $ami['ami_host'],
                'port' => $ami['ami_port'],
                'user' => $ami['ami_username']
            ];
        } else {
            $response['tests']['ami_config'] = [
                'status' => 'warning',
                'message' => 'No AMI configuration found'
            ];
        }
    } catch (Exception $e) {
        $response['tests']['ami_config'] = [
            'status' => 'error',
            'error' => $e->getMessage()
        ];
    }
}

// Test 4: Queues configured
if (isset($db)) {
    try {
        $queueCount = $db->fetchValue("SELECT COUNT(*) FROM queues WHERE is_active = 1");
        $response['tests']['queues'] = [
            'status' => $queueCount > 0 ? 'ok' : 'warning',
            'count' => (int)$queueCount,
            'message' => $queueCount > 0 ? null : 'No queues configured - add queues in Admin panel'
        ];
    } catch (Exception $e) {
        $response['tests']['queues'] = [
            'status' => 'error',
            'error' => $e->getMessage()
        ];
    }
}

// Test 5: Extensions configured
if (isset($db)) {
    try {
        $extCount = $db->fetchValue("SELECT COUNT(*) FROM extensions WHERE is_active = 1");
        $response['tests']['extensions'] = [
            'status' => $extCount > 0 ? 'ok' : 'warning',
            'count' => (int)$extCount,
            'message' => $extCount > 0 ? null : 'No extensions configured - add extensions in Admin panel'
        ];
    } catch (Exception $e) {
        $response['tests']['extensions'] = [
            'status' => 'error',
            'error' => $e->getMessage()
        ];
    }
}

// Test 6: Daemon status
$pidFile = '/var/run/wallboard-daemon.pid';
$daemonRunning = false;
$daemonPid = null;

if (file_exists($pidFile)) {
    $pid = (int) file_get_contents($pidFile);
    if ($pid > 0 && file_exists("/proc/$pid")) {
        $daemonRunning = true;
        $daemonPid = $pid;
    }
}

$response['tests']['daemon'] = [
    'status' => $daemonRunning ? 'ok' : 'error',
    'running' => $daemonRunning,
    'pid' => $daemonPid,
    'message' => $daemonRunning ? null : 'Daemon not running - check docker logs'
];

if (!$daemonRunning) {
    $response['success'] = false;
}

// Test 7: AMI connectivity (if config exists and requested)
if (isset($_GET['test_ami']) && isset($ami) && $ami) {
    $errno = 0;
    $errstr = '';
    $socket = @fsockopen($ami['ami_host'], $ami['ami_port'], $errno, $errstr, 5);
    
    if ($socket) {
        $banner = fgets($socket);
        fclose($socket);
        $response['tests']['ami_connection'] = [
            'status' => 'ok',
            'banner' => trim($banner)
        ];
    } else {
        $response['tests']['ami_connection'] = [
            'status' => 'error',
            'error' => "$errstr (errno: $errno)",
            'help' => [
                'Check firewall allows port ' . $ami['ami_port'],
                'Verify VitalPBX hostname/IP is correct',
                'Ensure AMI is enabled in VitalPBX'
            ]
        ];
        $response['success'] = false;
    }
}

// Test 8: Log files writable
$logDir = '/var/log/wallboard';
$logWritable = is_dir($logDir) && is_writable($logDir);
$response['tests']['logging'] = [
    'status' => $logWritable ? 'ok' : 'warning',
    'directory' => $logDir,
    'writable' => $logWritable
];

// Summary
$allOk = true;
foreach ($response['tests'] as $test) {
    if ($test['status'] === 'error') {
        $allOk = false;
        break;
    }
}
$response['overall'] = $allOk ? 'ok' : 'issues_detected';

echo json_encode($response, JSON_PRETTY_PRINT);
