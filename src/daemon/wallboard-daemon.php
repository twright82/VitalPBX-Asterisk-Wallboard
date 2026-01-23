#!/usr/bin/env php
<?php
/**
 * Wallboard Daemon
 * 
 * Main service that connects to AMI and processes events
 * 
 * Usage:
 *   php wallboard-daemon.php start
 *   php wallboard-daemon.php stop
 *   php wallboard-daemon.php restart
 *   php wallboard-daemon.php status
 * 
 * @package VitalPBX-Asterisk-Wallboard
 * @version 1.0.0
 */

// Change to script directory
chdir(__DIR__);

require_once __DIR__ . '/ami-connector.php';
require_once __DIR__ . '/event-handler.php';
require_once __DIR__ . '/alert-processor.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Configuration
define('PID_FILE', '/var/run/wallboard-daemon.pid');
define('LOG_FILE', '/var/log/wallboard/daemon.log');
define('EVENT_LOG_FILE', '/var/log/wallboard/events.log');

// Ensure log directory exists
if (!is_dir('/var/log/wallboard')) {
    @mkdir('/var/log/wallboard', 0755, true);
}

/**
 * Log message to file
 */
function daemon_log($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [Daemon] [$level] $message\n";
    file_put_contents(LOG_FILE, $logMessage, FILE_APPEND);
    
    if (php_sapi_name() === 'cli') {
        echo $logMessage;
    }
}

/**
 * Check if daemon is running
 */
function is_running() {
    if (!file_exists(PID_FILE)) {
        return false;
    }
    
    $pid = (int) file_get_contents(PID_FILE);
    if ($pid <= 0) {
        return false;
    }
    
    // Check if process exists
    if (function_exists('posix_kill')) {
        return posix_kill($pid, 0);
    } else {
        return file_exists("/proc/$pid");
    }
}

/**
 * Get running PID
 */
function get_pid() {
    if (!file_exists(PID_FILE)) {
        return null;
    }
    return (int) file_get_contents(PID_FILE);
}

/**
 * Write PID file
 */
function write_pid() {
    file_put_contents(PID_FILE, getmypid());
}

/**
 * Remove PID file
 */
function remove_pid() {
    if (file_exists(PID_FILE)) {
        unlink(PID_FILE);
    }
}

/**
 * Start the daemon
 */
function start_daemon() {
    if (is_running()) {
        daemon_log("Daemon already running (PID: " . get_pid() . ")", 'WARN');
        return false;
    }
    
    daemon_log("Starting wallboard daemon...");
    
    // Fork to background (if not in debug mode)
    global $argv;
    $debug = in_array('--debug', $argv) || in_array('-d', $argv);
    
    if (!$debug && function_exists('pcntl_fork')) {
        $pid = pcntl_fork();
        
        if ($pid === -1) {
            daemon_log("Failed to fork process", 'ERROR');
            return false;
        }
        
        if ($pid > 0) {
            // Parent process
            daemon_log("Daemon started (PID: $pid)");
            exit(0);
        }
        
        // Child process
        posix_setsid();
    }
    
    write_pid();
    
    // Set up signal handlers
    if (function_exists('pcntl_signal')) {
        pcntl_signal(SIGTERM, 'signal_handler');
        pcntl_signal(SIGINT, 'signal_handler');
        pcntl_signal(SIGHUP, 'signal_handler');
    }
    
    // Run main loop
    run_daemon($debug);
    
    return true;
}

/**
 * Stop the daemon
 */
function stop_daemon() {
    if (!is_running()) {
        daemon_log("Daemon not running", 'WARN');
        return false;
    }
    
    $pid = get_pid();
    daemon_log("Stopping daemon (PID: $pid)...");
    
    if (function_exists('posix_kill')) {
        posix_kill($pid, SIGTERM);
    } else {
        exec("kill $pid");
    }
    
    // Wait for process to end
    $tries = 0;
    while (is_running() && $tries < 10) {
        sleep(1);
        $tries++;
    }
    
    if (is_running()) {
        daemon_log("Force killing daemon...", 'WARN');
        if (function_exists('posix_kill')) {
            posix_kill($pid, SIGKILL);
        } else {
            exec("kill -9 $pid");
        }
    }
    
    remove_pid();
    daemon_log("Daemon stopped");
    
    return true;
}

/**
 * Signal handler
 */
function signal_handler($signal) {
    global $running;
    
    switch ($signal) {
        case SIGTERM:
        case SIGINT:
            daemon_log("Received shutdown signal");
            $running = false;
            break;
        case SIGHUP:
            daemon_log("Received reload signal");
            // Reload config
            break;
    }
}

/**
 * Main daemon loop
 */
function run_daemon($debug = false) {
    global $running;
    $running = true;
    
    daemon_log("Daemon running in " . ($debug ? "debug" : "background") . " mode");
    
    // Get database connection
    try {
        $db = get_db_connection();
    } catch (Exception $e) {
        daemon_log("Database connection failed: " . $e->getMessage(), 'ERROR');
        remove_pid();
        exit(1);
    }
    
    // Get AMI config
    $stmt = $db->query("SELECT * FROM ami_config WHERE is_active = 1 LIMIT 1");
    $amiConfig = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$amiConfig) {
        daemon_log("No AMI configuration found. Please configure AMI settings in admin.", 'ERROR');
        remove_pid();
        exit(1);
    }
    
    // Create AMI connector
    $ami = new AMIConnector(
        $amiConfig['ami_host'],
        $amiConfig['ami_port'],
        $amiConfig['ami_username'],
        $amiConfig['ami_password']
    );
    
    if ($debug) {
        $ami->enableDebug(EVENT_LOG_FILE);
    }
    
    // Create event handler
    $eventHandler = new EventHandler($db);
    if ($debug) {
        // $eventHandler->enableDebug(EVENT_LOG_FILE);
    }

    // Create alert processor
    $alertProcessor = new AlertProcessor($db);
    daemon_log("Alert processor initialized");
    
    // Register event callback
    $ami->onAnyEvent(function($event) use ($eventHandler) {
        $eventHandler->handleEvent($event);
    });
    
    // Main loop with reconnection logic
    while ($running) {
        daemon_log("Connecting to AMI at {$amiConfig['ami_host']}:{$amiConfig['ami_port']}...");
        
        if (!$ami->connectAndLogin()) {
            daemon_log("Failed to connect: " . $ami->getLastError(), 'ERROR');
            daemon_log("Retrying in 10 seconds...");
            sleep(10);
            continue;
        }
        
        daemon_log("Connected to AMI successfully");
        
        // Request initial queue status
        daemon_log("Requesting initial queue status...");
        $ami->getQueueStatus();
        $ami->getQueueSummary();
        
        // Event loop
        $lastPing = time();
        $lastStatusPoll = time();
        
        while ($running && $ami->isConnected()) {
            // Process signals
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
            
            // Read events (with 1 second timeout)
            $event = $ami->readEvent(1);
            
            if ($event) {
                $eventHandler->handleEvent($event);
            }
            
            // Periodic tasks
            $now = time();
            
            // Ping every 30 seconds to keep connection alive
            if ($now - $lastPing >= 30) {
                $ami->ping();
                $lastPing = $now;
            }
            
            // Poll queue status every 60 seconds (backup to events)
            if ($now - $lastStatusPoll >= 60) {
                $ami->getQueueSummary();
                $lastStatusPoll = $now;
            }

            // Check alerts every 30 seconds
            if ($alertProcessor->shouldCheck()) {
                try {
                    $alertProcessor->checkAlerts();
                } catch (Exception $e) {
                    daemon_log("Alert check error: " . $e->getMessage(), 'ERROR');
                }
            }

            // Update SLA every 5 minutes instead (less disruptive)
            // Disabled for now - causes connection issues
            // static $lastSlaUpdate = 0;
            // if ($now - $lastSlaUpdate >= 300) {
            //     updateDailyStats($db);
            //     $lastSlaUpdate = $now;
            // }
        }
        
        if ($ami->isConnected()) {
            $ami->disconnect();
        }
        
        if ($running) {
            daemon_log("Connection lost. Reconnecting in 5 seconds...", 'WARN');
            sleep(5);
        }
    }
    
    daemon_log("Daemon shutting down");
    remove_pid();
}

/**
 * Update daily statistics
 */
function updateDailyStats($db) {
    try {
        // Get today's stats per queue
        $stmt = $db->query("
            SELECT 
                queue_number,
                COUNT(*) as total_calls,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as answered_calls,
                SUM(CASE WHEN status = 'abandoned' THEN 1 ELSE 0 END) as abandoned_calls,
                AVG(CASE WHEN wait_time IS NOT NULL THEN wait_time ELSE NULL END) as avg_wait,
                MAX(wait_time) as max_wait,
                AVG(CASE WHEN talk_time IS NOT NULL THEN talk_time ELSE NULL END) as avg_talk,
                SUM(COALESCE(talk_time, 0)) as total_talk
            FROM calls 
            WHERE DATE(created_at) = CURDATE() AND queue_number IS NOT NULL
            GROUP BY queue_number
        ");
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Calculate SLA (answered within 30 seconds)
            $slaStmt = $db->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN wait_time <= 30 THEN 1 ELSE 0 END) as within_sla
                FROM calls 
                WHERE DATE(created_at) = CURDATE() 
                    AND queue_number = :queue 
                    AND status = 'completed'
            ");
            $slaStmt->execute(['queue' => $row['queue_number']]);
            $sla = $slaStmt->fetch(PDO::FETCH_ASSOC);
            
            $slaPercent = 0;
            if ($sla['total'] > 0) {
                $slaPercent = ($sla['within_sla'] / $sla['total']) * 100;
            }
            
            // Update daily stats
            $updateStmt = $db->prepare("
                INSERT INTO daily_stats (stat_date, queue_number, total_calls, answered_calls, abandoned_calls, sla_percent, avg_wait_time, max_wait_time, avg_talk_time, total_talk_time)
                VALUES (CURDATE(), :queue, :total, :answered, :abandoned, :sla, :avg_wait, :max_wait, :avg_talk, :total_talk)
                ON DUPLICATE KEY UPDATE
                    total_calls = :total2,
                    answered_calls = :answered2,
                    abandoned_calls = :abandoned2,
                    sla_percent = :sla2,
                    avg_wait_time = :avg_wait2,
                    max_wait_time = :max_wait2,
                    avg_talk_time = :avg_talk2,
                    total_talk_time = :total_talk2
            ");
            $updateStmt->execute([
                'queue' => $row['queue_number'],
                'total' => $row['total_calls'],
                'answered' => $row['answered_calls'],
                'abandoned' => $row['abandoned_calls'],
                'sla' => $slaPercent,
                'avg_wait' => $row['avg_wait'] ?? 0,
                'max_wait' => $row['max_wait'] ?? 0,
                'avg_talk' => $row['avg_talk'] ?? 0,
                'total_talk' => $row['total_talk'] ?? 0,
                'total2' => $row['total_calls'],
                'answered2' => $row['answered_calls'],
                'abandoned2' => $row['abandoned_calls'],
                'sla2' => $slaPercent,
                'avg_wait2' => $row['avg_wait'] ?? 0,
                'max_wait2' => $row['max_wait'] ?? 0,
                'avg_talk2' => $row['avg_talk'] ?? 0,
                'total_talk2' => $row['total_talk'] ?? 0
            ]);
            
            // Update realtime queue stats
            $realtimeStmt = $db->prepare("
                UPDATE queue_stats_realtime 
                SET calls_today = :total,
                    answered_today = :answered,
                    abandoned_today = :abandoned,
                    sla_percent_today = :sla,
                    avg_wait_today = :avg_wait,
                    avg_talk_today = :avg_talk
                WHERE queue_number = :queue
            ");
            $realtimeStmt->execute([
                'queue' => $row['queue_number'],
                'total' => $row['total_calls'],
                'answered' => $row['answered_calls'],
                'abandoned' => $row['abandoned_calls'],
                'sla' => $slaPercent,
                'avg_wait' => $row['avg_wait'] ?? 0,
                'avg_talk' => $row['avg_talk'] ?? 0
            ]);
        }
    } catch (Exception $e) {
        daemon_log("Error updating daily stats: " . $e->getMessage(), 'ERROR');
    }
}

// =========================================
// COMMAND LINE INTERFACE
// =========================================

if (php_sapi_name() !== 'cli') {
    die("This script must be run from command line\n");
}

$command = $argv[1] ?? 'help';

switch ($command) {
    case 'start':
        start_daemon();
        break;
        
    case 'stop':
        stop_daemon();
        break;
        
    case 'restart':
        stop_daemon();
        sleep(2);
        start_daemon();
        break;
        
    case 'status':
        if (is_running()) {
            echo "Daemon is running (PID: " . get_pid() . ")\n";
            exit(0);
        } else {
            echo "Daemon is not running\n";
            exit(1);
        }
        break;
        
    case 'help':
    default:
        echo "Wallboard Daemon\n";
        echo "================\n\n";
        echo "Usage: php wallboard-daemon.php <command> [options]\n\n";
        echo "Commands:\n";
        echo "  start     Start the daemon\n";
        echo "  stop      Stop the daemon\n";
        echo "  restart   Restart the daemon\n";
        echo "  status    Check if daemon is running\n";
        echo "  help      Show this help\n\n";
        echo "Options:\n";
        echo "  --debug, -d   Run in foreground with debug output\n\n";
        break;
}
