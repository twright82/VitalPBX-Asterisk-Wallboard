<?php
/**
 * Alert Processor - Checks alert conditions and triggers notifications
 *
 * @package VitalPBX-Asterisk-Wallboard
 */

require_once __DIR__ . '/alert-sender.php';

class AlertProcessor {
    private $db;
    private $sender;
    private $config;
    private $lastCheck = 0;
    private $checkInterval = 30; // seconds

    public function __construct($db) {
        $this->db = $db;
        $this->sender = new AlertSender($db);
        $this->loadConfig();
    }

    private function loadConfig() {
        $stmt = $this->db->query("SELECT * FROM company_config LIMIT 1");
        $this->config = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Check if it's time to run alert checks
     */
    public function shouldCheck() {
        return (time() - $this->lastCheck) >= $this->checkInterval;
    }

    /**
     * Main entry point - run all alert checks
     */
    public function checkAlerts() {
        $this->lastCheck = time();

        // Skip if alerts are disabled globally
        if (!($this->config['alerts_enabled'] ?? true)) {
            return;
        }

        // Skip during quiet hours
        if ($this->isQuietHours()) {
            return;
        }

        // Reload config periodically
        $this->loadConfig();

        // Get enabled alert rules
        $rules = $this->getEnabledRules();

        foreach ($rules as $rule) {
            // Skip if still in cooldown
            if ($this->isInCooldown($rule)) {
                continue;
            }

            // Check condition based on alert type
            $triggered = $this->checkCondition($rule);

            if ($triggered) {
                $this->triggerAlert($rule, $triggered);
            }
        }

        // Auto-resolve cleared alerts
        $this->resolveCleared();
    }

    private function isQuietHours() {
        if (!($this->config['quiet_hours_enabled'] ?? false)) {
            return false;
        }

        $now = new DateTime();
        $currentTime = $now->format('H:i:s');
        $startTime = $this->config['quiet_hours_start'] ?? '21:00:00';
        $endTime = $this->config['quiet_hours_end'] ?? '07:00:00';

        // Handle overnight quiet hours (e.g., 21:00 to 07:00)
        if ($startTime > $endTime) {
            return ($currentTime >= $startTime || $currentTime < $endTime);
        }

        return ($currentTime >= $startTime && $currentTime < $endTime);
    }

    private function getEnabledRules() {
        $stmt = $this->db->query("SELECT * FROM alert_rules WHERE is_enabled = 1");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function isInCooldown($rule) {
        if (!$rule['last_triggered_at']) {
            return false;
        }

        $cooldownMinutes = $rule['cooldown_minutes'] ?? 15;
        $lastTriggered = strtotime($rule['last_triggered_at']);
        $cooldownEnds = $lastTriggered + ($cooldownMinutes * 60);

        return time() < $cooldownEnds;
    }

    /**
     * Check alert condition - returns array with details if triggered, false otherwise
     */
    private function checkCondition($rule) {
        $type = $rule['alert_type'];
        $threshold = (float) $rule['threshold'];

        switch ($type) {
            case 'calls_waiting_high':
            case 'queue_overflow':
                return $this->checkCallsWaiting($threshold, $rule);

            case 'longest_wait_high':
            case 'max_wait_time':
            case 'long_hold':
                return $this->checkLongestWait($threshold, $rule);

            case 'sla_below':
            case 'sla_breach':
                return $this->checkSlaBelowThreshold($threshold, $rule);

            case 'abandoned_rate_high':
            case 'abandoned_call':
                return $this->checkAbandonedRate($threshold, $rule);

            case 'no_agents':
                return $this->checkNoAgents($threshold, $rule);
        }

        return false;
    }

    private function checkCallsWaiting($threshold, $rule) {
        $stmt = $this->db->prepare("
            SELECT
                q.queue_number,
                q.display_name,
                COUNT(c.id) as waiting
            FROM queues q
            LEFT JOIN calls c ON q.queue_number = c.queue_number AND c.status = 'waiting'
            WHERE q.is_active = 1
            GROUP BY q.queue_number
            HAVING waiting >= ?
        ");
        $stmt->execute([$threshold]);

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($results)) {
            return false;
        }

        // Return the worst offender
        $worst = $results[0];
        foreach ($results as $r) {
            if ($r['waiting'] > $worst['waiting']) {
                $worst = $r;
            }
        }

        return [
            'queue_number' => $worst['queue_number'],
            'queue_name' => $worst['display_name'],
            'current_value' => $worst['waiting'],
            'message' => sprintf(
                '%s: %d calls waiting (threshold: %d)',
                $worst['display_name'],
                $worst['waiting'],
                $threshold
            )
        ];
    }

    private function checkLongestWait($threshold, $rule) {
        $stmt = $this->db->prepare("
            SELECT
                c.queue_number,
                q.display_name,
                MAX(TIMESTAMPDIFF(SECOND, c.entered_queue_at, NOW())) as longest_wait
            FROM calls c
            JOIN queues q ON c.queue_number = q.queue_number
            WHERE c.status = 'waiting' AND q.is_active = 1
            GROUP BY c.queue_number
            HAVING longest_wait >= ?
        ");
        $stmt->execute([$threshold]);

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($results)) {
            return false;
        }

        $worst = $results[0];
        foreach ($results as $r) {
            if ($r['longest_wait'] > $worst['longest_wait']) {
                $worst = $r;
            }
        }

        return [
            'queue_number' => $worst['queue_number'],
            'queue_name' => $worst['display_name'],
            'current_value' => $worst['longest_wait'],
            'message' => sprintf(
                '%s: Caller waiting %s (threshold: %s)',
                $worst['display_name'],
                $this->formatDuration($worst['longest_wait']),
                $this->formatDuration($threshold)
            )
        ];
    }

    private function checkSlaBelowThreshold($threshold, $rule) {
        // Get overall SLA for today
        $stmt = $this->db->query("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN wait_time <= (SELECT sla_threshold FROM company_config LIMIT 1) THEN 1 ELSE 0 END) as within_sla
            FROM calls
            WHERE status = 'completed' AND DATE(created_at) = CURDATE()
        ");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (($result['total'] ?? 0) < 5) { // Need minimum calls to trigger
            return false;
        }

        $slaPercent = ($result['within_sla'] / $result['total']) * 100;

        if ($slaPercent >= $threshold) {
            return false;
        }

        return [
            'queue_number' => null,
            'current_value' => round($slaPercent, 1),
            'message' => sprintf(
                'SLA dropped to %.1f%% (target: %.0f%%)',
                $slaPercent,
                $threshold
            )
        ];
    }

    private function checkAbandonedRate($threshold, $rule) {
        // Check abandoned calls in the last hour
        $stmt = $this->db->query("
            SELECT COUNT(*) as abandoned
            FROM calls
            WHERE status = 'abandoned'
            AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $abandoned = $result['abandoned'] ?? 0;

        if ($abandoned < $threshold) {
            return false;
        }

        return [
            'queue_number' => null,
            'current_value' => $abandoned,
            'message' => sprintf(
                '%d calls abandoned in the last hour (threshold: %d)',
                $abandoned,
                $threshold
            )
        ];
    }

    private function checkNoAgents($threshold, $rule) {
        // Check each queue for no available agents with waiting calls
        $stmt = $this->db->query("
            SELECT q.queue_number, q.display_name,
                   COALESCE(qs.agents_available, 0) as agents_available
            FROM queues q
            LEFT JOIN queue_stats_realtime qs ON q.queue_number = qs.queue_number
            WHERE q.is_active = 1 AND (qs.agents_available IS NULL OR qs.agents_available = 0)
        ");

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($results)) {
            return false;
        }

        // Check if any queue has waiting calls with no agents
        foreach ($results as $queue) {
            $waitingStmt = $this->db->prepare("
                SELECT COUNT(*) as waiting FROM calls
                WHERE queue_number = ? AND status = 'waiting'
            ");
            $waitingStmt->execute([$queue['queue_number']]);
            $waiting = $waitingStmt->fetch(PDO::FETCH_ASSOC)['waiting'];

            if ($waiting > 0) {
                return [
                    'queue_number' => $queue['queue_number'],
                    'queue_name' => $queue['display_name'],
                    'current_value' => $waiting,
                    'message' => sprintf(
                        '%s: No agents available, %d calls waiting',
                        $queue['display_name'],
                        $waiting
                    )
                ];
            }
        }

        return false;
    }

    private function triggerAlert($rule, $data) {
        // Update last triggered timestamp
        $stmt = $this->db->prepare("
            UPDATE alert_rules SET last_triggered_at = NOW() WHERE id = ?
        ");
        $stmt->execute([$rule['id']]);

        // Insert into active_alerts
        $stmt = $this->db->prepare("
            INSERT INTO active_alerts
            (alert_rule_id, alert_type, queue_number, current_value, threshold, message, severity)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $rule['id'],
            $rule['alert_type'],
            $data['queue_number'] ?? null,
            $data['current_value'],
            $rule['threshold'],
            $data['message'],
            $rule['severity'] ?? 'warning'
        ]);

        // Log to alert_history
        $stmt = $this->db->prepare("
            INSERT INTO alert_history (alert_type, alert_message, alert_data, sent_via)
            VALUES (?, ?, ?, 'pending')
        ");
        $stmt->execute([
            $rule['alert_type'],
            $data['message'],
            json_encode($data)
        ]);

        // Send notifications
        $this->sender->sendAll($rule, $data);

        $this->log("ALERT TRIGGERED: " . $data['message']);
    }

    private function resolveCleared() {
        // Get active alerts and check if conditions are still met
        $stmt = $this->db->query("
            SELECT aa.*, ar.threshold, ar.alert_type as rule_type
            FROM active_alerts aa
            JOIN alert_rules ar ON aa.alert_rule_id = ar.id
            WHERE aa.is_active = 1 AND aa.resolved_at IS NULL
        ");

        while ($alert = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rule = [
                'alert_type' => $alert['rule_type'],
                'threshold' => $alert['threshold']
            ];

            $stillTriggered = $this->checkCondition($rule);

            if (!$stillTriggered) {
                $update = $this->db->prepare("
                    UPDATE active_alerts SET is_active = 0, resolved_at = NOW() WHERE id = ?
                ");
                $update->execute([$alert['id']]);
                $this->log("Alert resolved: " . $alert['message']);
            }
        }
    }

    private function formatDuration($seconds) {
        $seconds = (int) $seconds;
        $mins = floor($seconds / 60);
        $secs = $seconds % 60;
        return sprintf("%d:%02d", $mins, $secs);
    }

    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logFile = '/var/log/wallboard/alerts.log';
        @file_put_contents($logFile, "[$timestamp] [AlertProcessor] $message\n", FILE_APPEND);
    }
}
