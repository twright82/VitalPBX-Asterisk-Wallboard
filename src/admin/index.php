<?php
/**
 * Admin Dashboard
 * 
 * @package VitalPBX-Asterisk-Wallboard
 */

$pageTitle = 'Dashboard';
require_once __DIR__ . '/templates/header.php';

// Get stats
try {
    $db = Database::getInstance();
    
    // Today's stats
    $todayStats = $db->fetchOne("
        SELECT 
            COUNT(*) as total_calls,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as answered,
            SUM(CASE WHEN status = 'abandoned' THEN 1 ELSE 0 END) as abandoned,
            AVG(CASE WHEN wait_time IS NOT NULL THEN wait_time END) as avg_wait,
            AVG(CASE WHEN talk_time IS NOT NULL THEN talk_time END) as avg_talk
        FROM calls
        WHERE DATE(created_at) = CURDATE()
    ");
    
    // Calculate SLA
    $slaData = $db->fetchOne("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN wait_time <= 30 THEN 1 ELSE 0 END) as within_sla
        FROM calls 
        WHERE DATE(created_at) = CURDATE() AND status = 'completed'
    ");
    $slaPercent = $slaData['total'] > 0 ? round(($slaData['within_sla'] / $slaData['total']) * 100, 1) : 100;
    
    // Queue count
    $queueCount = $db->fetchValue("SELECT COUNT(*) FROM queues WHERE is_active = 1");
    
    // Extension count
    $extCount = $db->fetchValue("SELECT COUNT(*) FROM extensions WHERE is_active = 1");
    
    // Daemon status
    $pidFile = '/var/run/wallboard-daemon.pid';
    $daemonRunning = false;
    if (file_exists($pidFile)) {
        $pid = (int) file_get_contents($pidFile);
        $daemonRunning = $pid > 0 && file_exists("/proc/$pid");
    }
    
    // Recent alerts
    $recentAlerts = $db->fetchAll("
        SELECT * FROM alert_history 
        ORDER BY sent_at DESC 
        LIMIT 5
    ");
    
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<?php if (isset($error)): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="label">Calls Today</div>
        <div class="value"><?= number_format($todayStats['total_calls'] ?? 0) ?></div>
    </div>
    
    <div class="stat-card">
        <div class="label">Answered</div>
        <div class="value good"><?= number_format($todayStats['answered'] ?? 0) ?></div>
    </div>
    
    <div class="stat-card">
        <div class="label">Abandoned</div>
        <div class="value <?= ($todayStats['abandoned'] ?? 0) > 5 ? 'bad' : '' ?>"><?= number_format($todayStats['abandoned'] ?? 0) ?></div>
    </div>
    
    <div class="stat-card">
        <div class="label">SLA (30s)</div>
        <div class="value <?= $slaPercent >= 90 ? 'good' : ($slaPercent >= 80 ? 'warning' : 'bad') ?>"><?= $slaPercent ?>%</div>
    </div>
    
    <div class="stat-card">
        <div class="label">Avg Wait</div>
        <div class="value"><?= format_duration(round($todayStats['avg_wait'] ?? 0)) ?></div>
    </div>
    
    <div class="stat-card">
        <div class="label">Avg Talk</div>
        <div class="value"><?= format_duration(round($todayStats['avg_talk'] ?? 0)) ?></div>
    </div>
</div>

<!-- System Status & Quick Actions -->
<div class="form-grid">
    <div class="card">
        <div class="card-header">
            <h3>System Status</h3>
        </div>
        <div class="card-body">
            <table>
                <tr>
                    <td>Daemon</td>
                    <td>
                        <?php if ($daemonRunning): ?>
                            <span class="badge badge-success">Running</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Stopped</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td>Queues Configured</td>
                    <td><strong><?= $queueCount ?></strong></td>
                </tr>
                <tr>
                    <td>Extensions Configured</td>
                    <td><strong><?= $extCount ?></strong></td>
                </tr>
                <tr>
                    <td>Database</td>
                    <td><span class="badge badge-success">Connected</span></td>
                </tr>
            </table>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h3>Quick Actions</h3>
        </div>
        <div class="card-body">
            <p style="margin-bottom: 15px;">
                <a href="/admin/queues.php" class="btn btn-primary" style="width: 100%; justify-content: center;">
                    📞 Manage Queues
                </a>
            </p>
            <p style="margin-bottom: 15px;">
                <a href="/admin/extensions.php" class="btn btn-primary" style="width: 100%; justify-content: center;">
                    👥 Manage Extensions
                </a>
            </p>
            <p style="margin-bottom: 15px;">
                <a href="/admin/settings.php" class="btn btn-secondary" style="width: 100%; justify-content: center;">
                    ⚙️ AMI Settings
                </a>
            </p>
        </div>
    </div>
</div>

<!-- Recent Alerts -->
<div class="card">
    <div class="card-header">
        <h3>Recent Alerts</h3>
        <a href="/admin/alerts.php" class="btn btn-sm btn-secondary">View All</a>
    </div>
    <div class="card-body">
        <?php if (empty($recentAlerts)): ?>
            <div class="empty-state">
                <p>No alerts yet</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Type</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentAlerts as $alert): ?>
                        <tr>
                            <td><?= date('M j, g:i a', strtotime($alert['sent_at'])) ?></td>
                            <td>
                                <span class="badge badge-<?= $alert['alert_type'] === 'sla_breach' ? 'danger' : 'warning' ?>">
                                    <?= htmlspecialchars($alert['alert_type']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($alert['alert_message']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
