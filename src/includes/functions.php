<?php
/**
 * Common Helper Functions
 * 
 * @package VitalPBX-Asterisk-Wallboard
 * @version 1.0.0
 */

/**
 * Format seconds as MM:SS or HH:MM:SS
 */
function format_duration($seconds) {
    if ($seconds < 0) $seconds = 0;
    
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;
    
    if ($hours > 0) {
        return sprintf("%d:%02d:%02d", $hours, $minutes, $secs);
    }
    return sprintf("%d:%02d", $minutes, $secs);
}

/**
 * Format phone number for display
 */
function format_phone($number) {
    // Remove non-digits
    $digits = preg_replace('/\D/', '', $number);
    
    // Handle US numbers
    if (strlen($digits) === 10) {
        return sprintf("(%s) %s-%s", 
            substr($digits, 0, 3),
            substr($digits, 3, 3),
            substr($digits, 6)
        );
    }
    
    if (strlen($digits) === 11 && $digits[0] === '1') {
        return sprintf("+1 (%s) %s-%s", 
            substr($digits, 1, 3),
            substr($digits, 4, 3),
            substr($digits, 7)
        );
    }
    
    // Return as-is if not US format
    return $number;
}

/**
 * Get time ago string
 */
function time_ago($timestamp) {
    if (is_string($timestamp)) {
        $timestamp = strtotime($timestamp);
    }
    
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return "Just now";
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return "$mins min" . ($mins > 1 ? 's' : '') . " ago";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return "$hours hour" . ($hours > 1 ? 's' : '') . " ago";
    } else {
        $days = floor($diff / 86400);
        return "$days day" . ($days > 1 ? 's' : '') . " ago";
    }
}

/**
 * Get wait time CSS class
 */
function wait_time_class($seconds, $warningThreshold = 30, $criticalThreshold = 120) {
    if ($seconds >= $criticalThreshold) {
        return 'critical';
    } elseif ($seconds >= $warningThreshold) {
        return 'warning';
    }
    return 'ok';
}

/**
 * Get SLA CSS class
 */
function sla_class($percent) {
    if ($percent >= 90) {
        return 'good';
    } elseif ($percent >= 80) {
        return 'warning';
    }
    return 'bad';
}

/**
 * Sanitize output for HTML
 */
function h($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Generate CSRF token
 */
function csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verify_csrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Check if request is AJAX
 */
function is_ajax() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Send JSON response
 */
function json_response($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Redirect to URL
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Get config value from database
 */
function get_config($key, $default = null) {
    static $config = null;
    
    if ($config === null) {
        try {
            $db = Database::getInstance();
            
            // Load company config
            $row = $db->fetchOne("SELECT * FROM company_config LIMIT 1");
            if ($row) {
                $config = $row;
            } else {
                $config = [];
            }
        } catch (Exception $e) {
            $config = [];
        }
    }
    
    return $config[$key] ?? $default;
}

/**
 * Get alert rules
 */
function get_alert_rules() {
    try {
        $db = Database::getInstance();
        return $db->fetchAll("SELECT * FROM alert_rules WHERE is_enabled = 1");
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Calculate SLA percentage
 */
function calculate_sla($queueNumber = null, $date = null) {
    try {
        $db = Database::getInstance();
        $params = [];
        
        $sql = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN wait_time <= 30 THEN 1 ELSE 0 END) as within_sla
            FROM calls 
            WHERE status = 'completed'
        ";
        
        if ($queueNumber) {
            $sql .= " AND queue_number = :queue";
            $params['queue'] = $queueNumber;
        }
        
        if ($date) {
            $sql .= " AND DATE(created_at) = :date";
            $params['date'] = $date;
        } else {
            $sql .= " AND DATE(created_at) = CURDATE()";
        }
        
        $result = $db->fetchOne($sql, $params);
        
        if ($result['total'] > 0) {
            return round(($result['within_sla'] / $result['total']) * 100, 1);
        }
        
        return 100; // No calls = 100% SLA
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Get leaderboard
 */
function get_leaderboard($limit = 3, $date = null) {
    try {
        $db = Database::getInstance();
        
        $dateFilter = $date ? "DATE(c.created_at) = :date" : "DATE(c.created_at) = CURDATE()";
        $params = $date ? ['date' => $date] : [];
        
        $sql = "
            SELECT 
                c.agent_extension as extension,
                CONCAT(e.first_name, ' ', COALESCE(e.last_name, '')) as name,
                COUNT(*) as calls,
                AVG(c.talk_time) as avg_time
            FROM calls c
            INNER JOIN extensions e ON c.agent_extension = e.extension AND e.is_active = 1
            WHERE c.status = 'completed' 
                AND c.agent_extension IS NOT NULL
                AND $dateFilter
            GROUP BY c.agent_extension
            ORDER BY calls DESC, avg_time ASC
            LIMIT $limit
        ";
        
        return $db->fetchAll($sql, $params);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get leaderboard title (King, Queen, Princess)
 */
function get_leaderboard_title($position) {
    $titles = [
        1 => ['title' => 'King', 'emoji' => '👑'],
        2 => ['title' => 'Queen', 'emoji' => '👸'],
        3 => ['title' => 'Princess', 'emoji' => '🎀']
    ];
    
    return $titles[$position] ?? ['title' => '', 'emoji' => ''];
}

/**
 * Get medal emoji for position
 */
function get_medal($position) {
    $medals = [1 => '🥇', 2 => '🥈', 3 => '🥉'];
    return $medals[$position] ?? '';
}

/**
 * Log application message
 */
function app_log($message, $level = 'INFO', $file = null) {
    $logFile = $file ?? '/var/log/wallboard/app.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message\n";
    
    @file_put_contents($logFile, $logMessage, FILE_APPEND);
}

/**
 * Clean old data based on retention policy
 */
function clean_old_data($retentionDays = null) {
    if ($retentionDays === null) {
        $retentionDays = get_config('data_retention_days', 90);
    }
    
    try {
        $db = Database::getInstance();
        $cutoff = date('Y-m-d', strtotime("-$retentionDays days"));
        
        // Clean old calls
        $db->execute("DELETE FROM calls WHERE created_at < :cutoff", ['cutoff' => $cutoff]);
        
        // Clean old daily stats
        $db->execute("DELETE FROM daily_stats WHERE stat_date < :cutoff", ['cutoff' => $cutoff]);
        
        // Clean old agent daily stats
        $db->execute("DELETE FROM agent_daily_stats WHERE stat_date < :cutoff", ['cutoff' => $cutoff]);
        
        // Clean old alerts
        $db->execute("DELETE FROM alert_history WHERE sent_at < :cutoff", ['cutoff' => $cutoff]);
        
        // Clean old AMI event log
        $db->execute("DELETE FROM ami_event_log WHERE created_at < :cutoff", ['cutoff' => $cutoff]);
        
        // Clean old missed calls
        $db->execute("DELETE FROM missed_calls WHERE missed_at < :cutoff", ['cutoff' => $cutoff]);
        
        app_log("Cleaned data older than $cutoff");
        
    } catch (Exception $e) {
        app_log("Error cleaning old data: " . $e->getMessage(), 'ERROR');
    }
}

/**
 * Reset daily counters (run at midnight)
 */
function reset_daily_counters() {
    try {
        $db = Database::getInstance();
        
        // Reset agent daily stats
        $db->execute("
            UPDATE agent_status 
            SET calls_today = 0, 
                talk_time_today = 0, 
                missed_today = 0,
                paused_time_today = 0
        ");
        
        // Reset queue daily stats
        $db->execute("
            UPDATE queue_stats_realtime 
            SET calls_today = 0, 
                answered_today = 0, 
                abandoned_today = 0,
                sla_percent_today = 100,
                avg_wait_today = 0,
                avg_talk_today = 0
        ");
        
        app_log("Daily counters reset");
        
    } catch (Exception $e) {
        app_log("Error resetting daily counters: " . $e->getMessage(), 'ERROR');
    }
}
