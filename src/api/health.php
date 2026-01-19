<?php
/**
 * Health Check Endpoint
 * 
 * @package VitalPBX-Asterisk-Wallboard
 */

header('Content-Type: application/json');

$status = [
    'status' => 'ok',
    'timestamp' => date('c'),
    'checks' => []
];

// Check database
try {
    require_once __DIR__ . '/../includes/db.php';
    $db = get_db_connection();
    $db->query("SELECT 1");
    $status['checks']['database'] = 'ok';
} catch (Exception $e) {
    $status['checks']['database'] = 'error: ' . $e->getMessage();
    $status['status'] = 'degraded';
}

// Check daemon PID file
$pidFile = '/var/run/wallboard-daemon.pid';
if (file_exists($pidFile)) {
    $pid = (int) file_get_contents($pidFile);
    if ($pid > 0 && file_exists("/proc/$pid")) {
        $status['checks']['daemon'] = 'running';
    } else {
        $status['checks']['daemon'] = 'not running';
        $status['status'] = 'degraded';
    }
} else {
    $status['checks']['daemon'] = 'not started';
}

http_response_code($status['status'] === 'ok' ? 200 : 503);
echo json_encode($status, JSON_PRETTY_PRINT);
