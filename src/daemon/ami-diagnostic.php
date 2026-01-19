#!/usr/bin/env php
<?php
/**
 * AMI Diagnostic Tool
 * 
 * Connects to AMI and logs ALL raw events to help discover
 * what VitalPBX actually sends. Run this first before the main daemon.
 * 
 * Usage:
 *   php ami-diagnostic.php <host> <port> <username> <password> [duration_seconds]
 * 
 * Example:
 *   php ami-diagnostic.php pbx1.as36001.net 5038 wallboard TbctHkwecb6Mhw 300
 * 
 * @package VitalPBX-Asterisk-Wallboard
 */

if (php_sapi_name() !== 'cli') {
    die("Run from command line only\n");
}

// Parse arguments
$host = $argv[1] ?? null;
$port = $argv[2] ?? 5038;
$username = $argv[3] ?? null;
$password = $argv[4] ?? null;
$duration = $argv[5] ?? 300; // 5 minutes default

if (!$host || !$username || !$password) {
    echo "AMI Diagnostic Tool\n";
    echo "===================\n\n";
    echo "Usage: php ami-diagnostic.php <host> <port> <username> <password> [duration_seconds]\n\n";
    echo "Example:\n";
    echo "  php ami-diagnostic.php pbx1.as36001.net 5038 wallboard secret 300\n\n";
    echo "This tool will:\n";
    echo "  1. Connect to AMI and authenticate\n";
    echo "  2. Log ALL raw events to ami-diagnostic.log\n";
    echo "  3. Summarize event types seen\n";
    echo "  4. Show sample events for queue-related activity\n\n";
    exit(1);
}

$logFile = __DIR__ . '/ami-diagnostic.log';
$eventCounts = [];
$sampleEvents = [];
$queueEvents = [];

echo "AMI Diagnostic Tool\n";
echo "===================\n";
echo "Host: $host:$port\n";
echo "User: $username\n";
echo "Duration: {$duration}s\n";
echo "Log file: $logFile\n";
echo "\n";

// Clear log file
file_put_contents($logFile, "AMI Diagnostic Log - Started " . date('Y-m-d H:i:s') . "\n");
file_put_contents($logFile, "Host: $host:$port, User: $username\n", FILE_APPEND);
file_put_contents($logFile, str_repeat("=", 80) . "\n\n", FILE_APPEND);

function logEvent($event, $raw) {
    global $logFile, $eventCounts, $sampleEvents, $queueEvents;
    
    $eventType = $event['Event'] ?? 'Unknown';
    
    // Count events
    if (!isset($eventCounts[$eventType])) {
        $eventCounts[$eventType] = 0;
    }
    $eventCounts[$eventType]++;
    
    // Store first 3 samples of each type
    if (!isset($sampleEvents[$eventType])) {
        $sampleEvents[$eventType] = [];
    }
    if (count($sampleEvents[$eventType]) < 3) {
        $sampleEvents[$eventType][] = $event;
    }
    
    // Queue-related events get full logging
    $queueKeywords = ['Queue', 'Agent', 'Member', 'Caller'];
    $isQueueEvent = false;
    foreach ($queueKeywords as $kw) {
        if (stripos($eventType, $kw) !== false) {
            $isQueueEvent = true;
            break;
        }
    }
    
    if ($isQueueEvent) {
        $queueEvents[] = $event;
        file_put_contents($logFile, "[" . date('H:i:s') . "] QUEUE EVENT: $eventType\n", FILE_APPEND);
        file_put_contents($logFile, $raw . "\n", FILE_APPEND);
        file_put_contents($logFile, str_repeat("-", 40) . "\n", FILE_APPEND);
        
        // Print to console
        echo "\033[33m[QUEUE] $eventType\033[0m\n";
        foreach ($event as $k => $v) {
            if ($k !== 'Event') {
                echo "  $k: $v\n";
            }
        }
        echo "\n";
    }
    
    // Log everything to file
    file_put_contents($logFile, "[" . date('H:i:s') . "] $eventType\n", FILE_APPEND);
}

// Connect
echo "Connecting to $host:$port...\n";
$socket = @fsockopen($host, $port, $errno, $errstr, 10);

if (!$socket) {
    echo "\033[31mERROR: Could not connect - $errstr ($errno)\033[0m\n";
    echo "\nTroubleshooting:\n";
    echo "  1. Check if AMI is enabled on VitalPBX\n";
    echo "  2. Check firewall allows port $port from this IP\n";
    echo "  3. Verify the hostname/IP is correct\n";
    exit(1);
}

echo "\033[32mConnected!\033[0m\n";

// Set timeout
stream_set_timeout($socket, 1);

// Read banner
$banner = fgets($socket);
echo "Banner: $banner";
file_put_contents($logFile, "Banner: $banner\n", FILE_APPEND);

// Login
echo "Authenticating...\n";
$loginCmd = "Action: Login\r\n";
$loginCmd .= "Username: $username\r\n";
$loginCmd .= "Secret: $password\r\n";
$loginCmd .= "\r\n";

fwrite($socket, $loginCmd);

// Read login response
$response = '';
while (!feof($socket)) {
    $line = fgets($socket);
    $response .= $line;
    if (trim($line) === '') break;
}

if (stripos($response, 'Success') !== false) {
    echo "\033[32mAuthentication successful!\033[0m\n\n";
} else {
    echo "\033[31mAuthentication FAILED!\033[0m\n";
    echo "Response: $response\n";
    echo "\nTroubleshooting:\n";
    echo "  1. Check AMI username and secret in VitalPBX\n";
    echo "  2. Check permit list includes this server's IP\n";
    fclose($socket);
    exit(1);
}

// Request initial queue status
echo "Requesting queue status...\n";
fwrite($socket, "Action: QueueStatus\r\n\r\n");
fwrite($socket, "Action: QueueSummary\r\n\r\n");

// Main event loop
echo "Listening for events (Ctrl+C to stop)...\n";
echo str_repeat("=", 50) . "\n\n";

$startTime = time();
$buffer = '';

while (time() - $startTime < $duration) {
    $line = @fgets($socket, 4096);
    
    if ($line === false) {
        // Timeout, check if still connected
        $info = stream_get_meta_data($socket);
        if ($info['eof']) {
            echo "\033[31mConnection lost!\033[0m\n";
            break;
        }
        continue;
    }
    
    $buffer .= $line;
    
    // Check for complete event (double newline)
    if (trim($line) === '' && trim($buffer) !== '') {
        // Parse event
        $event = [];
        $lines = explode("\n", trim($buffer));
        $raw = $buffer;
        
        foreach ($lines as $l) {
            $l = trim($l);
            if (strpos($l, ':') !== false) {
                list($key, $value) = explode(':', $l, 2);
                $event[trim($key)] = trim($value);
            }
        }
        
        if (!empty($event)) {
            logEvent($event, $raw);
        }
        
        $buffer = '';
    }
}

// Cleanup
fwrite($socket, "Action: Logoff\r\n\r\n");
fclose($socket);

// Print summary
echo "\n\n";
echo str_repeat("=", 50) . "\n";
echo "SUMMARY\n";
echo str_repeat("=", 50) . "\n\n";

echo "Events received by type:\n";
arsort($eventCounts);
foreach ($eventCounts as $type => $count) {
    $highlight = '';
    if (stripos($type, 'Queue') !== false || stripos($type, 'Agent') !== false) {
        $highlight = "\033[33m";
    }
    echo "  $highlight$type: $count\033[0m\n";
}

echo "\n\nQueue-related events: " . count($queueEvents) . "\n";

if (count($queueEvents) > 0) {
    echo "\nSample Queue Event Fields:\n";
    $allFields = [];
    foreach ($queueEvents as $e) {
        foreach (array_keys($e) as $k) {
            $allFields[$k] = true;
        }
    }
    echo "  " . implode(", ", array_keys($allFields)) . "\n";
}

echo "\n\nFull log written to: $logFile\n";
echo "\n\033[32mDiagnostic complete!\033[0m\n";
echo "\nNext steps:\n";
echo "  1. Review $logFile for event formats\n";
echo "  2. Make test calls to generate queue events\n";
echo "  3. Check field names match what the daemon expects\n";
