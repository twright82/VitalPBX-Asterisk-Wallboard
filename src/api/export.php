<?php
/**
 * CSV Export API
 * 
 * @package VitalPBX-Asterisk-Wallboard
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$type = $_GET['type'] ?? 'calls';
$startDate = $_GET['start'] ?? date('Y-m-d', strtotime('-7 days'));
$endDate = $_GET['end'] ?? date('Y-m-d');
$queue = $_GET['queue'] ?? '';

try {
    $db = Database::getInstance();
    
    switch ($type) {
        case 'calls':
            $filename = "call_history_{$startDate}_to_{$endDate}.csv";
            
            $params = ['start' => $startDate, 'end' => $endDate];
            $queueFilter = '';
            if ($queue) {
                $queueFilter = ' AND c.queue_number = :queue';
                $params['queue'] = $queue;
            }
            
            $data = $db->fetchAll("
                SELECT 
                    c.created_at as 'Date/Time',
                    c.call_type as 'Type',
                    c.caller_number as 'Caller',
                    c.caller_name as 'Caller Name',
                    c.queue_number as 'Queue',
                    q.display_name as 'Queue Name',
                    c.agent_extension as 'Agent Ext',
                    c.agent_name as 'Agent Name',
                    c.status as 'Status',
                    c.wait_time as 'Wait (sec)',
                    c.talk_time as 'Talk (sec)',
                    c.hold_time as 'Hold (sec)'
                FROM calls c
                LEFT JOIN queues q ON c.queue_number = q.queue_number
                WHERE DATE(c.created_at) BETWEEN :start AND :end $queueFilter
                ORDER BY c.created_at DESC
            ", $params);
            break;
            
        case 'agents':
            $filename = "agent_stats_{$startDate}_to_{$endDate}.csv";
            
            $data = $db->fetchAll("
                SELECT 
                    e.extension as 'Extension',
                    e.display_name as 'Agent Name',
                    e.team as 'Team',
                    COUNT(c.id) as 'Total Calls',
                    SUM(CASE WHEN c.call_type = 'inbound' THEN 1 ELSE 0 END) as 'Inbound',
                    SUM(CASE WHEN c.call_type = 'outbound' THEN 1 ELSE 0 END) as 'Outbound',
                    ROUND(AVG(c.talk_time)) as 'Avg Talk (sec)',
                    SUM(c.talk_time) as 'Total Talk (sec)',
                    (SELECT COUNT(*) FROM missed_calls m 
                     WHERE m.extension = e.extension 
                     AND DATE(m.missed_at) BETWEEN :start AND :end) as 'Missed Calls'
                FROM extensions e
                LEFT JOIN calls c ON e.extension = c.agent_extension 
                    AND DATE(c.created_at) BETWEEN :start AND :end
                    AND c.status = 'completed'
                WHERE e.is_active = 1
                GROUP BY e.extension, e.display_name, e.team
                ORDER BY COUNT(c.id) DESC
            ", ['start' => $startDate, 'end' => $endDate]);
            break;
            
        case 'queues':
            $filename = "queue_stats_{$startDate}_to_{$endDate}.csv";
            
            $data = $db->fetchAll("
                SELECT 
                    q.queue_number as 'Queue Number',
                    q.display_name as 'Queue Name',
                    COUNT(c.id) as 'Total Calls',
                    SUM(CASE WHEN c.status = 'completed' THEN 1 ELSE 0 END) as 'Answered',
                    SUM(CASE WHEN c.status = 'abandoned' THEN 1 ELSE 0 END) as 'Abandoned',
                    ROUND(AVG(c.wait_time)) as 'Avg Wait (sec)',
                    MAX(c.wait_time) as 'Max Wait (sec)',
                    ROUND(AVG(c.talk_time)) as 'Avg Talk (sec)',
                    ROUND(SUM(CASE WHEN c.wait_time <= 30 THEN 1 ELSE 0 END) * 100.0 / 
                          NULLIF(SUM(CASE WHEN c.status = 'completed' THEN 1 ELSE 0 END), 0), 1) as 'SLA %'
                FROM queues q
                LEFT JOIN calls c ON q.queue_number = c.queue_number 
                    AND DATE(c.created_at) BETWEEN :start AND :end
                WHERE q.is_active = 1
                GROUP BY q.queue_number, q.display_name
                ORDER BY COUNT(c.id) DESC
            ", ['start' => $startDate, 'end' => $endDate]);
            break;
            
        default:
            throw new Exception('Invalid export type');
    }
    
    // Output CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache');
    
    $output = fopen('php://output', 'w');
    
    // Headers
    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]));
        
        // Data rows
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
