<?php
/**
 * Daily Email Reports Configuration
 *
 * @package VitalPBX-Asterisk-Wallboard
 */

$pageTitle = 'Email Reports';
require_once __DIR__ . '/templates/header.php';
require_role('manager');

$db = Database::getInstance();
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'update_config') {
            $db->execute("
                UPDATE report_config SET
                    is_enabled = :enabled,
                    send_time = :send_time,
                    include_hourly_chart = :hourly,
                    include_queue_breakdown = :queues,
                    include_agent_table = :agents,
                    include_pdf = :pdf
                WHERE report_type = 'daily'
            ", [
                'enabled' => isset($_POST['is_enabled']) ? 1 : 0,
                'send_time' => $_POST['send_time'] . ':00',
                'hourly' => isset($_POST['include_hourly_chart']) ? 1 : 0,
                'queues' => isset($_POST['include_queue_breakdown']) ? 1 : 0,
                'agents' => isset($_POST['include_agent_table']) ? 1 : 0,
                'pdf' => isset($_POST['include_pdf']) ? 1 : 0
            ]);
            $message = 'Report settings updated';

        } elseif ($action === 'add_recipient') {
            $email = trim($_POST['email']);
            $name = trim($_POST['name']);

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email address');
            }

            $db->execute("
                INSERT INTO report_recipients (name, email, is_active)
                VALUES (:name, :email, 1)
            ", [
                'name' => $name,
                'email' => $email
            ]);
            $message = 'Recipient added';

        } elseif ($action === 'delete_recipient') {
            $db->execute("DELETE FROM report_recipients WHERE id = :id", ['id' => (int) $_POST['id']]);
            $message = 'Recipient removed';

        } elseif ($action === 'toggle_recipient') {
            $db->execute("
                UPDATE report_recipients SET is_active = NOT is_active WHERE id = :id
            ", ['id' => (int) $_POST['id']]);

        } elseif ($action === 'send_test') {
            // Run the report script with --force flag
            $output = shell_exec('php ' . __DIR__ . '/../scripts/daily-report.php --force 2>&1');
            $message = 'Test report sent! Check your email.';

        } elseif ($action === 'preview') {
            // Run the report script with --test flag to preview
            $output = shell_exec('php ' . __DIR__ . '/../scripts/daily-report.php --test 2>&1');
            $_SESSION['report_preview'] = $output;
            $message = 'Preview generated below';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get data
$config = $db->fetchOne("SELECT * FROM report_config WHERE report_type = 'daily' LIMIT 1") ?: [];
$recipients = $db->fetchAll("SELECT * FROM report_recipients ORDER BY name");
?>

<?php if ($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Report Configuration -->
<div class="card">
    <div class="card-header">
        <h3>📊 Daily Email Report Settings</h3>
        <div>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="preview">
                <button type="submit" class="btn btn-secondary">Preview Report</button>
            </form>
            <form method="POST" style="display: inline; margin-left: 10px;">
                <input type="hidden" name="action" value="send_test">
                <button type="submit" class="btn btn-primary" onclick="return confirm('Send test report to all active recipients?')">
                    Send Test Now
                </button>
            </form>
        </div>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="update_config">

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                <div class="form-group">
                    <div class="toggle-group">
                        <label class="toggle">
                            <input type="checkbox" name="is_enabled" <?= ($config['is_enabled'] ?? 1) ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                        <span><strong>Daily Reports Enabled</strong></span>
                    </div>
                    <p style="font-size: 12px; color: #64748b; margin-top: 5px;">Send automated end-of-day report</p>
                </div>

                <div class="form-group">
                    <label>Send Time</label>
                    <input type="time" name="send_time" value="<?= substr($config['send_time'] ?? '17:30:00', 0, 5) ?>" style="width: 150px;">
                    <p style="font-size: 12px; color: #64748b; margin-top: 5px;">Time to send daily report</p>
                </div>
            </div>

            <h4 style="margin: 30px 0 15px 0; color: #64748b;">Report Sections</h4>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div class="form-group">
                    <div class="toggle-group">
                        <label class="toggle">
                            <input type="checkbox" name="include_hourly_chart" <?= ($config['include_hourly_chart'] ?? 1) ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                        <span>Hourly Call Volume Chart</span>
                    </div>
                </div>

                <div class="form-group">
                    <div class="toggle-group">
                        <label class="toggle">
                            <input type="checkbox" name="include_queue_breakdown" <?= ($config['include_queue_breakdown'] ?? 1) ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                        <span>Calls by Queue Breakdown</span>
                    </div>
                </div>

                <div class="form-group">
                    <div class="toggle-group">
                        <label class="toggle">
                            <input type="checkbox" name="include_agent_table" <?= ($config['include_agent_table'] ?? 1) ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                        <span>Agent Performance Table</span>
                    </div>
                </div>

                <div class="form-group">
                    <div class="toggle-group">
                        <label class="toggle">
                            <input type="checkbox" name="include_pdf" <?= ($config['include_pdf'] ?? 0) ? 'checked' : '' ?> disabled>
                            <span class="toggle-slider"></span>
                        </label>
                        <span>Attach PDF (coming soon)</span>
                    </div>
                </div>
            </div>

            <div style="margin-top: 20px;">
                <button type="submit" class="btn btn-primary">Save Settings</button>
            </div>
        </form>

        <?php if (!empty($config['last_sent_at'])): ?>
            <p style="margin-top: 20px; font-size: 13px; color: #64748b;">
                Last report sent: <?= date('M j, Y \a\t g:i A', strtotime($config['last_sent_at'])) ?>
            </p>
        <?php endif; ?>
    </div>
</div>

<!-- Report Recipients -->
<div class="card">
    <div class="card-header">
        <h3>📧 Report Recipients</h3>
        <button class="btn btn-primary" onclick="openRecipientModal()">+ Add Recipient</button>
    </div>
    <div class="card-body">
        <?php if (empty($recipients)): ?>
            <div class="empty-state">
                <div class="icon">📧</div>
                <h3>No Recipients Configured</h3>
                <p>Add email addresses to receive the daily report.</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recipients as $r): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
                                <td><?= htmlspecialchars($r['email']) ?></td>
                                <td>
                                    <?php if ($r['is_active']): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-gray">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="table-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle_recipient">
                                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-secondary">
                                            <?= $r['is_active'] ? 'Disable' : 'Enable' ?>
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;"
                                          onsubmit="return confirm('Remove this recipient?')">
                                        <input type="hidden" name="action" value="delete_recipient">
                                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Preview Section -->
<?php if (isset($_SESSION['report_preview'])): ?>
<div class="card">
    <div class="card-header">
        <h3>📋 Report Preview</h3>
    </div>
    <div class="card-body">
        <pre style="background: #1a1a28; padding: 20px; border-radius: 8px; color: #e2e8f0; overflow-x: auto; font-size: 13px; line-height: 1.6;"><?= htmlspecialchars($_SESSION['report_preview']) ?></pre>
    </div>
</div>
<?php unset($_SESSION['report_preview']); endif; ?>

<!-- Sample Report Preview -->
<div class="card">
    <div class="card-header">
        <h3>📄 Sample Report Format</h3>
    </div>
    <div class="card-body">
        <div style="background: #f8fafc; border-radius: 8px; padding: 20px; font-family: monospace; font-size: 13px; color: #1e293b;">
            <pre style="margin: 0; white-space: pre-wrap;">
■ Bertram Communications
Daily Call Center Report — <?= date('F j, Y') ?>

┌────────────┬────────────┬────────────┬────────────┐
│     37     │     33     │      4     │   96.9%    │
│ Total Calls│  Answered  │ Abandoned  │    SLA     │
└────────────┴────────────┴────────────┴────────────┘

■ Hourly Call Volume
[Bar chart: 8am-5pm with call counts]

■ Calls by Queue
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Support     ████████████████████  15 (41%)
Billing     ███████████████       12 (32%)
Sales       ██████████            10 (27%)

■ Agent Performance
┌──────────┬───────┬───────────┬────────────┬────────┐
│ Agent    │ Calls │ Talk Time │ Avg Handle │ Missed │
├──────────┼───────┼───────────┼────────────┼────────┤
│ Rachel L │   7   │   31:07   │    4:27    │   0    │
│ Joel V   │   6   │   23:19   │    3:53    │   1    │
│ Chris B  │   6   │   35:46   │    5:58    │   0    │
│ Brad W   │   5   │  1:11:25  │   14:17    │   0    │
└──────────┴───────┴───────────┴────────────┴────────┘

Generated by Bertram Wallboard
            </pre>
        </div>
    </div>
</div>

<!-- Cron Setup Info -->
<div class="card">
    <div class="card-header">
        <h3>⚙️ Automated Sending Setup</h3>
    </div>
    <div class="card-body">
        <p style="margin-bottom: 15px;">To enable automated daily reports, add this cron job:</p>
        <pre style="background: #1a1a28; padding: 15px; border-radius: 8px; color: #4ade80; font-family: monospace;">* * * * * php /var/www/html/scripts/daily-report.php >> /var/log/wallboard/reports.log 2>&1</pre>
        <p style="font-size: 13px; color: #64748b; margin-top: 10px;">
            This runs every minute and checks if it's time to send the report (based on your configured send time).
        </p>
    </div>
</div>

<!-- Add Recipient Modal -->
<div class="modal-overlay" id="recipientModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Add Report Recipient</h3>
            <button class="modal-close" onclick="closeRecipientModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_recipient">

            <div class="modal-body">
                <div class="form-group">
                    <label for="name">Name *</label>
                    <input type="text" id="name" name="name" required placeholder="John Smith">
                </div>

                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" required placeholder="john@example.com">
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeRecipientModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Recipient</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRecipientModal() {
    document.getElementById('recipientModal').classList.add('active');
}

function closeRecipientModal() {
    document.getElementById('recipientModal').classList.remove('active');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeRecipientModal();
});
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
