#!/usr/bin/env php
<?php
/**
 * Daily Report Generator
 *
 * Generates and sends end-of-day call center report with charts and stats
 *
 * Usage:
 *   php daily-report.php          # Send if it's time
 *   php daily-report.php --force  # Send immediately (for testing)
 *   php daily-report.php --test   # Generate but don't send (preview)
 *
 * @package VitalPBX-Asterisk-Wallboard
 */

// Change to script directory
chdir(__DIR__);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Parse arguments
$force = in_array('--force', $argv);
$testMode = in_array('--test', $argv);

/**
 * Log message
 */
function report_log($message) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] $message\n";
    @file_put_contents('/var/log/wallboard/reports.log', "[$timestamp] $message\n", FILE_APPEND);
}

/**
 * Main function
 */
function run() {
    global $force, $testMode;

    $db = Database::getInstance();

    // Get report config
    $config = $db->fetchOne("SELECT * FROM report_config WHERE report_type = 'daily' LIMIT 1");

    if (!$config) {
        report_log("No report configuration found");
        return;
    }

    if (!$config['is_enabled'] && !$force) {
        report_log("Daily reports are disabled");
        return;
    }

    // Check if it's time to send (within 5 minute window of send_time)
    if (!$force) {
        $sendTime = $config['send_time'];
        $now = new DateTime();
        $scheduledTime = DateTime::createFromFormat('H:i:s', $sendTime);
        $scheduledTime->setDate($now->format('Y'), $now->format('m'), $now->format('d'));

        $diffMinutes = abs(($now->getTimestamp() - $scheduledTime->getTimestamp()) / 60);

        if ($diffMinutes > 5) {
            // Not time yet
            return;
        }

        // Check if already sent today
        if ($config['last_sent_at']) {
            $lastSent = new DateTime($config['last_sent_at']);
            if ($lastSent->format('Y-m-d') === $now->format('Y-m-d')) {
                report_log("Report already sent today");
                return;
            }
        }
    }

    report_log("Generating daily report...");

    // Get recipients
    $recipients = $db->fetchAll("SELECT * FROM report_recipients WHERE is_active = 1");

    if (empty($recipients) && !$testMode) {
        report_log("No active recipients configured");
        return;
    }

    // Generate report data
    $reportData = generateReportData($db);

    // Generate HTML
    $html = generateReportHtml($reportData, $config);

    if ($testMode) {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "REPORT PREVIEW (not sent)\n";
        echo str_repeat("=", 60) . "\n\n";
        echo strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $html));
        return;
    }

    // Send to each recipient
    $sentCount = 0;
    foreach ($recipients as $recipient) {
        if (sendReportEmail($recipient['email'], $recipient['name'], $html, $reportData)) {
            $sentCount++;
            report_log("Sent to: {$recipient['email']}");
        } else {
            report_log("Failed to send to: {$recipient['email']}");
        }
    }

    // Update last sent timestamp
    $db->execute("UPDATE report_config SET last_sent_at = NOW() WHERE report_type = 'daily'");

    report_log("Daily report complete. Sent to $sentCount recipients.");
}

/**
 * Generate report data from database
 */
function generateReportData($db) {
    $data = [];

    // Get company name
    $config = $db->fetchOne("SELECT company_name FROM company_config LIMIT 1");
    $data['company_name'] = $config['company_name'] ?? 'Call Center';
    $data['report_date'] = date('F j, Y');

    // Summary stats
    $summary = $db->fetchOne("
        SELECT
            COUNT(*) as total_calls,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as answered,
            SUM(CASE WHEN status = 'abandoned' THEN 1 ELSE 0 END) as abandoned,
            AVG(CASE WHEN status = 'completed' THEN wait_time ELSE NULL END) as avg_wait,
            AVG(CASE WHEN status = 'completed' THEN talk_time ELSE NULL END) as avg_talk
        FROM calls
        WHERE DATE(created_at) = CURDATE()
    ");

    $data['total_calls'] = (int) ($summary['total_calls'] ?? 0);
    $data['answered'] = (int) ($summary['answered'] ?? 0);
    $data['abandoned'] = (int) ($summary['abandoned'] ?? 0);
    $data['avg_wait'] = round($summary['avg_wait'] ?? 0);
    $data['avg_talk'] = round($summary['avg_talk'] ?? 0);

    // Calculate SLA (answered within 30 seconds)
    $sla = $db->fetchOne("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN wait_time <= 30 THEN 1 ELSE 0 END) as within_sla
        FROM calls
        WHERE DATE(created_at) = CURDATE() AND status = 'completed'
    ");

    if (($sla['total'] ?? 0) > 0) {
        $data['sla_percent'] = round(($sla['within_sla'] / $sla['total']) * 100, 1);
    } else {
        $data['sla_percent'] = 100;
    }

    // Hourly breakdown
    $hourly = $db->fetchAll("
        SELECT
            HOUR(created_at) as hour,
            COUNT(*) as calls
        FROM calls
        WHERE DATE(created_at) = CURDATE()
        GROUP BY HOUR(created_at)
        ORDER BY hour
    ");

    $data['hourly'] = [];
    for ($h = 8; $h <= 17; $h++) {
        $data['hourly'][$h] = 0;
    }
    foreach ($hourly as $row) {
        $h = (int) $row['hour'];
        if ($h >= 8 && $h <= 17) {
            $data['hourly'][$h] = (int) $row['calls'];
        }
    }
    $data['hourly_max'] = max(1, max($data['hourly']));

    // Queue breakdown
    $queues = $db->fetchAll("
        SELECT
            q.display_name as queue_name,
            COUNT(c.id) as calls
        FROM calls c
        JOIN queues q ON c.queue_number = q.queue_number
        WHERE DATE(c.created_at) = CURDATE()
        GROUP BY c.queue_number
        ORDER BY calls DESC
    ");

    $data['queues'] = $queues;

    // Agent performance
    $agents = $db->fetchAll("
        SELECT
            a.agent_name as name,
            a.calls_today as calls,
            a.talk_time_today as talk_time,
            a.avg_handle_time,
            a.missed_today as missed
        FROM agent_status a
        INNER JOIN extensions e ON a.extension = e.extension
        WHERE e.is_active = 1 AND (e.is_team_member = 0 OR e.is_team_member IS NULL)
        AND a.calls_today > 0
        ORDER BY a.calls_today DESC
    ");

    $data['agents'] = $agents;

    return $data;
}

/**
 * Generate HTML report
 */
function generateReportHtml($data, $config) {
    $companyName = htmlspecialchars($data['company_name']);
    $reportDate = $data['report_date'];

    // Calculate abandon rate
    $abandonRate = $data['total_calls'] > 0
        ? round(($data['abandoned'] / $data['total_calls']) * 100, 1)
        : 0;

    // Get SLA color
    $slaColor = getSlaColor($data['sla_percent']);

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5;">
    <div style="max-width: 700px; margin: 0 auto; background: #ffffff;">

        <!-- Header -->
        <div style="background: linear-gradient(135deg, #1a2744 0%, #0d1522 100%); padding: 30px; text-align: center;">
            <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 600;">$companyName</h1>
            <p style="color: #64748b; margin: 10px 0 0 0; font-size: 14px;">Daily Call Center Report — $reportDate</p>
        </div>

        <!-- Summary Stats -->
        <div style="padding: 30px; background: #ffffff;">
            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
                <tr>
                    <td width="25%" style="text-align: center; padding: 20px; background: #f8fafc; border-radius: 8px 0 0 8px;">
                        <div style="font-size: 36px; font-weight: 800; color: #1e293b;">{$data['total_calls']}</div>
                        <div style="font-size: 12px; color: #64748b; text-transform: uppercase; margin-top: 5px;">Total Calls</div>
                    </td>
                    <td width="25%" style="text-align: center; padding: 20px; background: #f8fafc;">
                        <div style="font-size: 36px; font-weight: 800; color: #4ade80;">{$data['answered']}</div>
                        <div style="font-size: 12px; color: #64748b; text-transform: uppercase; margin-top: 5px;">Answered</div>
                    </td>
                    <td width="25%" style="text-align: center; padding: 20px; background: #f8fafc;">
                        <div style="font-size: 36px; font-weight: 800; color: #ef4444;">{$data['abandoned']}</div>
                        <div style="font-size: 12px; color: #64748b; text-transform: uppercase; margin-top: 5px;">Abandoned</div>
                    </td>
                    <td width="25%" style="text-align: center; padding: 20px; background: #f8fafc; border-radius: 0 8px 8px 0;">
                        <div style="font-size: 36px; font-weight: 800; color: {$slaColor};">{$data['sla_percent']}%</div>
                        <div style="font-size: 12px; color: #64748b; text-transform: uppercase; margin-top: 5px;">SLA</div>
                    </td>
                </tr>
            </table>
        </div>
HTML;

    // Hourly Chart
    if ($config['include_hourly_chart']) {
        $html .= generateHourlyChart($data);
    }

    // Queue Breakdown
    if ($config['include_queue_breakdown'] && !empty($data['queues'])) {
        $html .= generateQueueBreakdown($data);
    }

    // Agent Performance Table
    if ($config['include_agent_table'] && !empty($data['agents'])) {
        $html .= generateAgentTable($data);
    }

    // Footer
    $html .= <<<HTML

        <!-- Footer -->
        <div style="padding: 20px 30px; background: #f8fafc; text-align: center; border-top: 1px solid #e2e8f0;">
            <p style="margin: 0; font-size: 12px; color: #94a3b8;">
                Generated by Bertram Wallboard<br>
                <a href="https://bertram-wallboard.as36001.net" style="color: #3b82f6;">View Live Dashboard</a>
            </p>
        </div>

    </div>
</body>
</html>
HTML;

    return $html;
}

/**
 * Get SLA color
 */
function getSlaColor($percent) {
    if ($percent >= 90) return '#4ade80';
    if ($percent >= 80) return '#fbbf24';
    return '#ef4444';
}

/**
 * Generate hourly call volume chart
 */
function generateHourlyChart($data) {
    $maxCalls = $data['hourly_max'];

    $html = <<<HTML
        <!-- Hourly Chart -->
        <div style="padding: 0 30px 30px 30px;">
            <h2 style="font-size: 16px; color: #1e293b; margin: 0 0 20px 0; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                Hourly Call Volume
            </h2>
            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
                <tr>
HTML;

    foreach ($data['hourly'] as $hour => $calls) {
        $height = $maxCalls > 0 ? round(($calls / $maxCalls) * 100) : 0;
        $barColor = $calls > 0 ? '#3b82f6' : '#e2e8f0';
        $hourLabel = ($hour % 12 == 0 ? 12 : $hour % 12) . ($hour < 12 ? 'a' : 'p');

        $html .= <<<HTML
                    <td width="10%" style="vertical-align: bottom; text-align: center; padding: 0 2px;">
                        <div style="font-size: 10px; color: #64748b; margin-bottom: 5px;">{$calls}</div>
                        <div style="background: {$barColor}; height: {$height}px; min-height: 4px; border-radius: 3px 3px 0 0;"></div>
                        <div style="font-size: 10px; color: #94a3b8; margin-top: 5px;">{$hourLabel}</div>
                    </td>
HTML;
    }

    $html .= <<<HTML
                </tr>
            </table>
        </div>
HTML;

    return $html;
}

/**
 * Generate queue breakdown
 */
function generateQueueBreakdown($data) {
    $totalCalls = array_sum(array_column($data['queues'], 'calls'));

    $html = <<<HTML
        <!-- Queue Breakdown -->
        <div style="padding: 0 30px 30px 30px;">
            <h2 style="font-size: 16px; color: #1e293b; margin: 0 0 20px 0; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                Calls by Queue
            </h2>
HTML;

    $colors = ['#3b82f6', '#8b5cf6', '#ec4899', '#f97316', '#10b981'];
    $i = 0;

    foreach ($data['queues'] as $queue) {
        $percent = $totalCalls > 0 ? round(($queue['calls'] / $totalCalls) * 100) : 0;
        $color = $colors[$i % count($colors)];
        $queueName = htmlspecialchars($queue['queue_name']);

        $html .= <<<HTML
            <div style="margin-bottom: 12px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <span style="font-size: 14px; color: #1e293b;">{$queueName}</span>
                    <span style="font-size: 14px; color: #64748b;">{$queue['calls']} calls ({$percent}%)</span>
                </div>
                <div style="background: #e2e8f0; border-radius: 4px; height: 8px; overflow: hidden;">
                    <div style="background: {$color}; width: {$percent}%; height: 100%;"></div>
                </div>
            </div>
HTML;
        $i++;
    }

    $html .= "</div>";
    return $html;
}

/**
 * Generate agent performance table
 */
function generateAgentTable($data) {
    $html = <<<HTML
        <!-- Agent Performance -->
        <div style="padding: 0 30px 30px 30px;">
            <h2 style="font-size: 16px; color: #1e293b; margin: 0 0 20px 0; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                Agent Performance
            </h2>
            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; font-size: 14px;">
                <thead>
                    <tr style="background: #f8fafc;">
                        <th style="padding: 12px; text-align: left; color: #64748b; font-weight: 600; border-bottom: 2px solid #e2e8f0;">Agent</th>
                        <th style="padding: 12px; text-align: center; color: #64748b; font-weight: 600; border-bottom: 2px solid #e2e8f0;">Calls</th>
                        <th style="padding: 12px; text-align: center; color: #64748b; font-weight: 600; border-bottom: 2px solid #e2e8f0;">Talk Time</th>
                        <th style="padding: 12px; text-align: center; color: #64748b; font-weight: 600; border-bottom: 2px solid #e2e8f0;">Avg Handle</th>
                        <th style="padding: 12px; text-align: center; color: #64748b; font-weight: 600; border-bottom: 2px solid #e2e8f0;">Missed</th>
                    </tr>
                </thead>
                <tbody>
HTML;

    foreach ($data['agents'] as $agent) {
        $name = htmlspecialchars($agent['name']);
        $talkTime = formatDuration($agent['talk_time'] ?? 0);
        $avgHandle = formatDuration($agent['avg_handle_time'] ?? 0);
        $missedColor = ($agent['missed'] ?? 0) > 0 ? '#ef4444' : '#4ade80';

        $html .= <<<HTML
                    <tr>
                        <td style="padding: 12px; border-bottom: 1px solid #e2e8f0; color: #1e293b; font-weight: 500;">{$name}</td>
                        <td style="padding: 12px; border-bottom: 1px solid #e2e8f0; text-align: center; color: #1e293b;">{$agent['calls']}</td>
                        <td style="padding: 12px; border-bottom: 1px solid #e2e8f0; text-align: center; color: #64748b; font-family: monospace;">{$talkTime}</td>
                        <td style="padding: 12px; border-bottom: 1px solid #e2e8f0; text-align: center; color: #64748b; font-family: monospace;">{$avgHandle}</td>
                        <td style="padding: 12px; border-bottom: 1px solid #e2e8f0; text-align: center; color: {$missedColor}; font-weight: 600;">{$agent['missed']}</td>
                    </tr>
HTML;
    }

    $html .= <<<HTML
                </tbody>
            </table>
        </div>
HTML;

    return $html;
}

/**
 * Format seconds to MM:SS or H:MM:SS
 */
function formatDuration($seconds) {
    $seconds = (int) $seconds;
    if ($seconds < 3600) {
        return sprintf("%d:%02d", floor($seconds / 60), $seconds % 60);
    }
    return sprintf("%d:%02d:%02d", floor($seconds / 3600), floor(($seconds % 3600) / 60), $seconds % 60);
}

/**
 * Send report email
 */
function sendReportEmail($to, $name, $html, $data) {
    $subject = "{$data['company_name']} Daily Report — {$data['report_date']}";

    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=utf-8',
        'From: Bertram Wallboard <noreply@bertram-communications.com>',
        'X-Mailer: PHP/' . phpversion()
    ];

    return @mail($to, $subject, $html, implode("\r\n", $headers));
}

// Run the report
try {
    run();
} catch (Exception $e) {
    report_log("ERROR: " . $e->getMessage());
    exit(1);
}
