<?php
/**
 * Reports
 * 
 * @package VitalPBX-Asterisk-Wallboard
 */

$pageTitle = 'Reports';
require_once __DIR__ . '/templates/header.php';
require_role('manager');

$db = Database::getInstance();

// Get date range
$startDate = $_GET['start'] ?? date('Y-m-d', strtotime('-7 days'));
$endDate = $_GET['end'] ?? date('Y-m-d');
$selectedQueue = $_GET['queue'] ?? '';

// Get queues for filter
$queues = $db->fetchAll("SELECT queue_number, display_name FROM queues WHERE is_active = 1 ORDER BY display_name");

// Build query filters
$params = ['start' => $startDate, 'end' => $endDate];
$queueFilter = '';
if ($selectedQueue) {
    $queueFilter = ' AND c.queue_number = :queue';
    $params['queue'] = $selectedQueue;
}

// Summary stats for date range
$summary = $db->fetchOne("
    SELECT 
        COUNT(*) as total_calls,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as answered,
        SUM(CASE WHEN status = 'abandoned' THEN 1 ELSE 0 END) as abandoned,
        AVG(CASE WHEN wait_time IS NOT NULL THEN wait_time END) as avg_wait,
        AVG(CASE WHEN talk_time IS NOT NULL THEN talk_time END) as avg_talk,
        SUM(COALESCE(talk_time, 0)) as total_talk_time
    FROM calls c
    WHERE DATE(c.created_at) BETWEEN :start AND :end $queueFilter
", $params);

// SLA calculation
$slaParams = $params;
$slaData = $db->fetchOne("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN wait_time <= 30 THEN 1 ELSE 0 END) as within_sla
    FROM calls c
    WHERE DATE(c.created_at) BETWEEN :start AND :end 
        AND status = 'completed' $queueFilter
", $slaParams);
$slaPercent = $slaData['total'] > 0 ? round(($slaData['within_sla'] / $slaData['total']) * 100, 1) : 100;

// Daily breakdown
$dailyStats = $db->fetchAll("
    SELECT 
        DATE(c.created_at) as date,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as answered,
        SUM(CASE WHEN status = 'abandoned' THEN 1 ELSE 0 END) as abandoned,
        AVG(wait_time) as avg_wait,
        AVG(talk_time) as avg_talk
    FROM calls c
    WHERE DATE(c.created_at) BETWEEN :start AND :end $queueFilter
    GROUP BY DATE(c.created_at)
    ORDER BY date DESC
", $params);

// Agent leaderboard
$agentStats = $db->fetchAll("
    SELECT 
        c.agent_extension as extension,
        c.agent_name as name,
        COUNT(*) as total_calls,
        AVG(c.talk_time) as avg_talk,
        SUM(c.talk_time) as total_talk
    FROM calls c
    WHERE DATE(c.created_at) BETWEEN :start AND :end
        AND c.status = 'completed'
        AND c.agent_extension IS NOT NULL $queueFilter
    GROUP BY c.agent_extension, c.agent_name
    ORDER BY total_calls DESC
    LIMIT 15
", $params);

// Queue breakdown
$queueStats = $db->fetchAll("
    SELECT 
        c.queue_number,
        q.display_name as queue_name,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as answered,
        SUM(CASE WHEN status = 'abandoned' THEN 1 ELSE 0 END) as abandoned,
        AVG(wait_time) as avg_wait
    FROM calls c
    LEFT JOIN queues q ON c.queue_number = q.queue_number
    WHERE DATE(c.created_at) BETWEEN :start AND :end
        AND c.queue_number IS NOT NULL
    GROUP BY c.queue_number, q.display_name
    ORDER BY total DESC
", ['start' => $startDate, 'end' => $endDate]);
?>

<!-- Filters -->
<div class="card">
    <div class="card-body">
        <form method="GET" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
            <div class="form-group" style="margin: 0;">
                <label for="start">Start Date</label>
                <input type="date" id="start" name="start" value="<?= htmlspecialchars($startDate) ?>">
            </div>
            
            <div class="form-group" style="margin: 0;">
                <label for="end">End Date</label>
                <input type="date" id="end" name="end" value="<?= htmlspecialchars($endDate) ?>">
            </div>
            
            <div class="form-group" style="margin: 0;">
                <label for="queue">Queue</label>
                <select id="queue" name="queue">
                    <option value="">All Queues</option>
                    <?php foreach ($queues as $q): ?>
                        <option value="<?= htmlspecialchars($q['queue_number']) ?>" 
                                <?= $selectedQueue === $q['queue_number'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($q['display_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary">Apply Filter</button>
            
            <a href="/api/export.php?type=calls&start=<?= urlencode($startDate) ?>&end=<?= urlencode($endDate) ?>&queue=<?= urlencode($selectedQueue) ?>" 
               class="btn btn-secondary">📥 Export CSV</a>
        </form>
    </div>
</div>

<!-- Summary Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="label">Total Calls</div>
        <div class="value"><?= number_format($summary['total_calls'] ?? 0) ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Answered</div>
        <div class="value good"><?= number_format($summary['answered'] ?? 0) ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Abandoned</div>
        <div class="value <?= ($summary['abandoned'] ?? 0) > 10 ? 'bad' : '' ?>">
            <?= number_format($summary['abandoned'] ?? 0) ?>
        </div>
    </div>
    <div class="stat-card">
        <div class="label">SLA (30s)</div>
        <div class="value <?= $slaPercent >= 90 ? 'good' : ($slaPercent >= 80 ? 'warning' : 'bad') ?>">
            <?= $slaPercent ?>%
        </div>
    </div>
    <div class="stat-card">
        <div class="label">Avg Wait</div>
        <div class="value"><?= format_duration(round($summary['avg_wait'] ?? 0)) ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Avg Talk</div>
        <div class="value"><?= format_duration(round($summary['avg_talk'] ?? 0)) ?></div>
    </div>
</div>

<div class="form-grid">
    <!-- Daily Breakdown -->
    <div class="card">
        <div class="card-header">
            <h3>📅 Daily Breakdown</h3>
        </div>
        <div class="card-body">
            <?php if (empty($dailyStats)): ?>
                <div class="empty-state">
                    <p>No data for selected date range</p>
                </div>
            <?php else: ?>
                <div class="table-container" style="max-height: 400px; overflow-y: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Total</th>
                                <th>Answered</th>
                                <th>Abandoned</th>
                                <th>Avg Wait</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dailyStats as $day): ?>
                                <tr>
                                    <td><?= date('M j, Y', strtotime($day['date'])) ?></td>
                                    <td><strong><?= $day['total'] ?></strong></td>
                                    <td class="good"><?= $day['answered'] ?></td>
                                    <td><?= $day['abandoned'] ?></td>
                                    <td><?= format_duration(round($day['avg_wait'] ?? 0)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Queue Breakdown -->
    <div class="card">
        <div class="card-header">
            <h3>📞 By Queue</h3>
        </div>
        <div class="card-body">
            <?php if (empty($queueStats)): ?>
                <div class="empty-state">
                    <p>No queue data</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Queue</th>
                                <th>Total</th>
                                <th>Answered</th>
                                <th>Abandoned</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($queueStats as $qs): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($qs['queue_name'] ?: $qs['queue_number']) ?></strong></td>
                                    <td><?= $qs['total'] ?></td>
                                    <td class="good"><?= $qs['answered'] ?></td>
                                    <td><?= $qs['abandoned'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Agent Leaderboard -->
<div class="card">
    <div class="card-header">
        <h3>🏆 Agent Performance</h3>
    </div>
    <div class="card-body">
        <?php if (empty($agentStats)): ?>
            <div class="empty-state">
                <p>No agent data</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Agent</th>
                            <th>Extension</th>
                            <th>Calls</th>
                            <th>Avg Talk</th>
                            <th>Total Talk</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($agentStats as $i => $agent): ?>
                            <tr>
                                <td>
                                    <?php
                                    $medals = ['🥇', '🥈', '🥉'];
                                    echo $medals[$i] ?? ($i + 1);
                                    ?>
                                </td>
                                <td><strong><?= htmlspecialchars($agent['name'] ?: 'Unknown') ?></strong></td>
                                <td><?= htmlspecialchars($agent['extension']) ?></td>
                                <td><strong><?= $agent['total_calls'] ?></strong></td>
                                <td><?= format_duration(round($agent['avg_talk'] ?? 0)) ?></td>
                                <td><?= format_duration(round($agent['total_talk'] ?? 0)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.good { color: #4ade80; }
.warning { color: #fbbf24; }
.bad { color: #ef4444; }
</style>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
