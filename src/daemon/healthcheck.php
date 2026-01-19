#!/usr/bin/env php
<?php
/**
 * Daemon Health Check
 * 
 * Returns exit code 0 if daemon is healthy, 1 if not
 * Used by Docker healthcheck
 */

$pidFile = '/var/run/wallboard-daemon.pid';

// Check PID file exists
if (!file_exists($pidFile)) {
    echo "UNHEALTHY: PID file not found\n";
    exit(1);
}

// Check PID is valid
$pid = (int) file_get_contents($pidFile);
if ($pid <= 0) {
    echo "UNHEALTHY: Invalid PID\n";
    exit(1);
}

// Check process is running
if (!file_exists("/proc/$pid")) {
    echo "UNHEALTHY: Process not running\n";
    exit(1);
}

// Check log file is being written (activity in last 2 minutes)
$logFile = '/var/log/wallboard/daemon.log';
if (file_exists($logFile)) {
    $mtime = filemtime($logFile);
    if (time() - $mtime > 120) {
        echo "WARNING: No log activity for " . (time() - $mtime) . "s\n";
        // Don't fail on this, just warn
    }
}

echo "HEALTHY: Daemon running (PID: $pid)\n";
exit(0);
