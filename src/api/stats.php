<?php
/**
 * Stats API Endpoint
 * 
 * Returns historical statistics and aggregated data
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
    
    $type = $_GET['type'] ?? 'daily';
    $date = $_GET['date'] ?? date('Y-m-d');
    $queue = $_GET['queue'] ?? null;
    $days = min((int)($_GET['days'] ?? 7), 90);
    
    $response = ['success' => true, 'type' => $type, 'data' => null];
    
    switch ($type) {
        case 'daily':
            // Today's summary stats
            $params = [];
            $queueFilter = '';
            if ($queue) {
                $queueFilter = ' AND queue_number = :queue';
                $params['queue'] = $queue;
            }
            
            $response['data'] = $db->fetchOne("
                SELECT 
                    COUNT(*) as total_calls,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as answered,
                    SUM(CASE WHEN status = 'abandoned' THEN 1 ELSE 0 END) as abandoned,
                    SUM(CASE WHEN status = 'voicemail' THEN 1 ELSE 0 END) as voicemail,
                    AVG(CASE WHEN wait_time IS NOT NULL THEN wait_time END) as avg_wait,
                    MAX(wait_time) as max_wait,
                    AVG(CASE WHEN talk_time IS NOT NULL THEN talk_time END) as avg_talk,
                    SUM(COALESCE(talk_time, 0)) as total_talk
                FROM calls
                WHERE DATE(created_at) = :date $queueFilter
            ", array_merge(['date' => $date], $params));
            break;
            
        case 'missed_today':
            // Missed calls grouped by agent
            $response['data'] = $db->fetchAll("
                SELECT 
                    m.extension,
                    m.agent_name,
                    COUNT(*) as missed_count,
                    MAX(m.missed_at) as last_missed
                FROM missed_calls m
                WHERE DATE(m.missed_at) = CURDATE()
                GROUP BY m.extension, m.agent_name
                ORDER BY missed_count DESC
            ");
            break;
            
        case 'repeat_callers':
            // Repeat callers (configurable threshold)
            $config = $db->fetchOne("SELECT repeat_caller_days, repeat_caller_threshold FROM company_config LIMIT 1");
            $repeatDays = $config['repeat_caller_days'] ?? 30;
            $threshold = $config['repeat_caller_threshold'] ?? 2;
            
            $response['data'] = $db->fetchAll("
                SELECT 
                    caller_number,
                    caller_name,
                    call_count,
                    first_call_at,
                    last_call_at,
                    last_queue,
                    is_flagged
                FROM repeat_callers
                WHERE call_count >= :threshold
                    AND last_call_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                ORDER BY call_count DESC, last_call_at DESC
                LIMIT 20
            ", ['threshold' => $threshold, 'days' => $repeatDays]);
            break;
            
        case 'agent_stats':
            // Agent performance stats
            $response['data'] = $db->fetchAll("
                SELECT 
                    a.extension,
                    e.display_name as name,
                    a.calls_today,
                    a.talk_time_today,
                    a.avg_handle_time,
                    a.missed_today,
                    a.paused_time_today,
                    COALESCE(d.inbound_calls, 0) as inbound_calls,
                    COALESCE(d.outbound_calls, 0) as outbound_calls
                FROM agent_status a
                LEFT JOIN extensions e ON a.extension = e.extension
                LEFT JOIN agent_daily_stats d ON a.extension = d.extension AND d.stat_date = CURDATE()
                WHERE e.is_active = 1
                ORDER BY a.calls_today DESC
            ");
            break;
            
        case 'queue_stats':
            // Queue performance for date range
            $response['data'] = $db->fetchAll("
                SELECT 
                    stat_date,
                    queue_number,
                    total_calls,
                    answered_calls,
                    abandoned_calls,
                    sla_percent,
                    avg_wait_time,
                    avg_handle_time
                FROM daily_stats
                WHERE stat_date >= DATE_SUB(:date, INTERVAL :days DAY)
                ORDER BY stat_date DESC, queue_number
            ", ['date' => $date, 'days' => $days]);
            break;
            
        case 'hourly':
            // Hourly breakdown for today
            $response['data'] = $db->fetchAll("
                SELECT 
                    HOUR(created_at) as hour,
                    COUNT(*) as total_calls,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as answered,
                    SUM(CASE WHEN status = 'abandoned' THEN 1 ELSE 0 END) as abandoned,
                    AVG(wait_time) as avg_wait
                FROM calls
                WHERE DATE(created_at) = :date
                GROUP BY HOUR(created_at)
                ORDER BY hour
            ", ['date' => $date]);
            break;
            
        case 'call_history':
            // Recent call history
            $limit = min((int)($_GET['limit'] ?? 50), 500);
            $offset = (int)($_GET['offset'] ?? 0);
            
            $response['data'] = $db->fetchAll("
                SELECT 
                    c.id,
                    c.unique_id,
                    c.call_type,
                    c.caller_number,
                    c.caller_name,
                    c.queue_number,
                    q.display_name as queue_name,
                    c.agent_extension,
                    c.agent_name,
                    c.status,
                    c.entered_queue_at,
                    c.answered_at,
                    c.ended_at,
                    c.wait_time,
                    c.talk_time,
                    c.created_at
                FROM calls c
                LEFT JOIN queues q ON c.queue_number = q.queue_number
                ORDER BY c.created_at DESC
                LIMIT :limit OFFSET :offset
            ", ['limit' => $limit, 'offset' => $offset]);
            break;
            
        case 'sla':
            // SLA breakdown by queue
            $response['data'] = $db->fetchAll("
                SELECT 
                    c.queue_number,
                    q.display_name as queue_name,
                    COUNT(*) as total_answered,
                    SUM(CASE WHEN c.wait_time <= 30 THEN 1 ELSE 0 END) as within_sla,
                    ROUND(SUM(CASE WHEN c.wait_time <= 30 THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 1) as sla_percent
                FROM calls c
                LEFT JOIN queues q ON c.queue_number = q.queue_number
                WHERE DATE(c.created_at) = :date
                    AND c.status = 'completed'
                    AND c.queue_number IS NOT NULL
                GROUP BY c.queue_number, q.display_name
                ORDER BY sla_percent ASC
            ", ['date' => $date]);
            break;
            
        default:
            $response['success'] = false;
            $response['error'] = 'Unknown stats type';
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
