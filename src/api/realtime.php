<?php
/**
 * Realtime API Endpoint
 * 
 * Returns current state of queues and agents for dashboard
 * 
 * @package VitalPBX-Asterisk-Wallboard
 * @version 1.0.0
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $db = Database::getInstance();
    
    // Get queue stats
    $queues = $db->fetchAll("
        SELECT 
            q.queue_number,
            q.queue_name,
            q.display_name,
            q.group_name,
            COALESCE(qs.calls_waiting, 0) as calls_waiting,
            COALESCE(qs.longest_wait_seconds, 0) as longest_wait,
            COALESCE(qs.agents_available, 0) as agents_available,
            COALESCE(qs.agents_on_call, 0) as agents_on_call,
            COALESCE(qs.agents_paused, 0) as agents_paused,
            COALESCE(qs.agents_wrapup, 0) as agents_wrapup,
            COALESCE(qs.calls_today, 0) as calls_today,
            COALESCE(qs.answered_today, 0) as answered_today,
            COALESCE(qs.abandoned_today, 0) as abandoned_today,
            COALESCE(qs.sla_percent_today, 100) as sla_percent,
            COALESCE(qs.avg_wait_today, 0) as avg_wait,
            COALESCE(qs.avg_talk_today, 0) as avg_talk
        FROM queues q
        LEFT JOIN queue_stats_realtime qs ON q.queue_number = qs.queue_number
        WHERE q.is_active = 1 AND q.show_on_wallboard = 1
        ORDER BY q.sort_order, q.queue_name
    ");
    
    // Calculate totals
    $totalWaiting = 0;
    $longestWait = 0;
    $totalCalls = 0;
    $totalAnswered = 0;
    $totalAbandoned = 0;
    
    foreach ($queues as &$queue) {
        $totalWaiting += $queue['calls_waiting'];
        $longestWait = max($longestWait, $queue['longest_wait']);
        $totalCalls += $queue['calls_today'];
        $totalAnswered += $queue['answered_today'];
        $totalAbandoned += $queue['abandoned_today'];
        
        // Add wait time class
        $queue['wait_class'] = wait_time_class($queue['longest_wait']);
    }
    
    // Calculate overall SLA
    $overallSla = $totalAnswered > 0 ? calculate_sla() : 100;
    
    // Get agent statuses
    $agents = $db->fetchAll("
        SELECT 
            a.extension,
            a.agent_name as name,
            a.status,
            a.pause_reason,
            a.status_since,
            a.current_call_id,
            a.current_call_type,
            a.talking_to,
            a.talking_to_name,
            a.call_started_at,
            a.brand_tag,
            a.calls_today,
            a.talk_time_today,
            a.avg_handle_time,
            a.missed_today
        FROM agent_status a
        INNER JOIN extensions e ON a.extension = e.extension
        WHERE e.is_active = 1
        ORDER BY 
            FIELD(a.status, 'available', 'ringing', 'on_call', 'wrapup', 'paused', 'offline', 'unknown'),
            a.agent_name
    ");
    
    // Calculate call duration for active calls
    foreach ($agents as &$agent) {
        if ($agent['call_started_at'] && in_array($agent['status'], ['on_call', 'ringing'])) {
            $agent['call_duration'] = time() - strtotime($agent['call_started_at']);
        } else {
            $agent['call_duration'] = 0;
        }
        
        // Calculate status duration
        if ($agent['status_since']) {
            $agent['status_duration'] = time() - strtotime($agent['status_since']);
        } else {
            $agent['status_duration'] = 0;
        }
        
        // Format average handle time
        $agent['avg_handle_time_formatted'] = format_duration($agent['avg_handle_time']);
    }
    unset($agent);
    
    // Get queue membership for each agent
    $memberships = $db->fetchAll("
        SELECT extension, queue_number, is_signed_in, is_paused
        FROM agent_queue_membership
    ");
    
    $agentQueues = [];
    foreach ($memberships as $m) {
        if (!isset($agentQueues[$m['extension']])) {
            $agentQueues[$m['extension']] = [];
        }
        $agentQueues[$m['extension']][$m['queue_number']] = [
            'signed_in' => (bool) $m['is_signed_in'],
            'paused' => (bool) $m['is_paused']
        ];
    }
    
    // Add queue membership to agents
    foreach ($agents as &$agent) {
        $agent['queues'] = $agentQueues[$agent['extension']] ?? [];
    }
    unset($agent);
    
    // Get calls waiting details
    $callsWaiting = $db->fetchAll("
        SELECT 
            c.unique_id,
            c.caller_number,
            c.caller_name,
            c.queue_number,
            c.queue_name,
            c.entered_queue_at,
            TIMESTAMPDIFF(SECOND, c.entered_queue_at, NOW()) as wait_time
        FROM calls c
        WHERE c.status = 'waiting'
        ORDER BY c.entered_queue_at ASC
    ");
    
    foreach ($callsWaiting as &$call) {
        $call['wait_class'] = wait_time_class($call['wait_time']);
        $call['wait_formatted'] = format_duration($call['wait_time']);
        $call['caller_formatted'] = format_phone($call['caller_number']);
    }
    
    // Get callbacks
    $callbacks = $db->fetchAll("
        SELECT 
            caller_number,
            caller_name,
            queue_number,
            queue_name,
            requested_at,
            TIMESTAMPDIFF(MINUTE, requested_at, NOW()) as minutes_waiting
        FROM callbacks
        WHERE status = 'waiting'
        ORDER BY requested_at ASC
    ");
    
    foreach ($callbacks as &$cb) {
        $cb['caller_formatted'] = format_phone($cb['caller_number']);
    }
    
    // Get leaderboard
    $leaderboard = get_leaderboard(3);
    foreach ($leaderboard as $i => &$leader) {
        $position = $i + 1;
        $leader['position'] = $position;
        $leader['medal'] = get_medal($position);
        $leader['title'] = get_leaderboard_title($position);
        $leader['avg_formatted'] = format_duration(round($leader['avg_time']));
    }
    
    // Count agents by status
    $statusCounts = [
        'available' => 0,
        'on_call' => 0,
        'ringing' => 0,
        'wrapup' => 0,
        'paused' => 0,
        'offline' => 0
    ];
    
    foreach ($agents as $agent) {
        $status = $agent['status'];
        if (isset($statusCounts[$status])) {
            $statusCounts[$status]++;
        }
    }
    
    // Get recent alerts
    $alerts = $db->fetchAll("
        SELECT alert_type, alert_message, sent_at
        FROM alert_history
        WHERE DATE(sent_at) = CURDATE()
        ORDER BY sent_at DESC
        LIMIT 10
    ");
    
    // Get company config
    $config = $db->fetchOne("SELECT company_name, timezone, wrapup_time FROM company_config LIMIT 1");
    
    // Build response
    $response = [
        'success' => true,
        'timestamp' => date('c'),
        'company' => $config['company_name'] ?? 'Call Center',
        'summary' => [
            'total_waiting' => $totalWaiting,
            'longest_wait' => $longestWait,
            'longest_wait_formatted' => format_duration($longestWait),
            'longest_wait_class' => wait_time_class($longestWait),
            'total_calls_today' => $totalCalls,
            'answered_today' => $totalAnswered,
            'abandoned_today' => $totalAbandoned,
            'abandon_rate' => $totalCalls > 0 ? round(($totalAbandoned / $totalCalls) * 100, 1) : 0,
            'sla_percent' => round($overallSla, 1),
            'sla_class' => sla_class($overallSla),
            'callbacks_waiting' => count($callbacks)
        ],
        'agent_counts' => $statusCounts,
        'queues' => $queues,
        'agents' => $agents,
        'calls_waiting' => $callsWaiting,
        'callbacks' => $callbacks,
        'leaderboard' => $leaderboard,
        'alerts' => $alerts,
        'config' => [
            'wrapup_time' => $config['wrapup_time'] ?? 60,
            'timezone' => $config['timezone'] ?? 'America/Chicago'
        ]
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
