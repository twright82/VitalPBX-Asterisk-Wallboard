<?php
/**
 * Alert Management
 * 
 * @package VitalPBX-Asterisk-Wallboard
 */

$pageTitle = 'Alerts';
require_once __DIR__ . '/templates/header.php';
require_role('manager');

$db = Database::getInstance();
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'update_rules') {
            // Update all alert rules
            foreach ($_POST['rules'] as $id => $rule) {
                $db->execute("
                    UPDATE alert_rules SET
                        threshold = :threshold,
                        is_enabled = :enabled,
                        cooldown_minutes = :cooldown,
                        severity = :severity
                    WHERE id = :id
                ", [
                    'id' => $id,
                    'threshold' => (int) $rule['threshold'],
                    'enabled' => isset($rule['enabled']) ? 1 : 0,
                    'cooldown' => (int) $rule['cooldown'],
                    'severity' => $rule['severity'] ?? 'warning'
                ]);
            }
            $message = 'Alert rules updated';

        } elseif ($action === 'add_recipient') {
            $db->execute("
                INSERT INTO alert_recipients (name, email, phone, receives_email, receives_sms, is_active)
                VALUES (:name, :email, :phone, :email_on, :sms_on, 1)
            ", [
                'name' => trim($_POST['name']),
                'email' => trim($_POST['email']) ?: null,
                'phone' => trim($_POST['phone']) ?: null,
                'email_on' => isset($_POST['receives_email']) ? 1 : 0,
                'sms_on' => isset($_POST['receives_sms']) ? 1 : 0
            ]);
            $message = 'Recipient added';

        } elseif ($action === 'delete_recipient') {
            $db->execute("DELETE FROM alert_recipients WHERE id = :id", ['id' => (int) $_POST['id']]);
            $message = 'Recipient removed';

        } elseif ($action === 'toggle_recipient') {
            $db->execute("
                UPDATE alert_recipients SET is_active = NOT is_active WHERE id = :id
            ", ['id' => (int) $_POST['id']]);

        } elseif ($action === 'update_quiet_hours') {
            $db->execute("
                UPDATE company_config SET
                    alerts_enabled = :alerts_enabled,
                    quiet_hours_enabled = :quiet_enabled,
                    quiet_hours_start = :quiet_start,
                    quiet_hours_end = :quiet_end
            ", [
                'alerts_enabled' => isset($_POST['alerts_enabled']) ? 1 : 0,
                'quiet_enabled' => isset($_POST['quiet_hours_enabled']) ? 1 : 0,
                'quiet_start' => $_POST['quiet_hours_start'] ?: '21:00:00',
                'quiet_end' => $_POST['quiet_hours_end'] ?: '07:00:00'
            ]);
            $message = 'Alert settings updated';

        } elseif ($action === 'add_webhook') {
            $db->execute("
                INSERT INTO webhook_config (webhook_type, webhook_name, webhook_url, channel_name, is_active)
                VALUES (:type, :name, :url, :channel, 1)
            ", [
                'type' => $_POST['webhook_type'],
                'name' => trim($_POST['webhook_name']),
                'url' => trim($_POST['webhook_url']),
                'channel' => trim($_POST['channel_name']) ?: null
            ]);
            $message = 'Webhook added';

        } elseif ($action === 'delete_webhook') {
            $db->execute("DELETE FROM webhook_config WHERE id = :id", ['id' => (int) $_POST['id']]);
            $message = 'Webhook removed';

        } elseif ($action === 'toggle_webhook') {
            $db->execute("
                UPDATE webhook_config SET is_active = NOT is_active WHERE id = :id
            ", ['id' => (int) $_POST['id']]);
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get data
$alertRules = $db->fetchAll("SELECT * FROM alert_rules ORDER BY alert_name");
$recipients = $db->fetchAll("SELECT * FROM alert_recipients ORDER BY name");
$webhooks = $db->fetchAll("SELECT * FROM webhook_config ORDER BY webhook_type, webhook_name");
$config = $db->fetchOne("SELECT * FROM company_config LIMIT 1") ?: [];
$recentAlerts = $db->fetchAll("
    SELECT * FROM alert_history
    ORDER BY sent_at DESC
    LIMIT 25
");
?>

<?php if ($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Alert Rules -->
<div class="card">
    <div class="card-header">
        <h3>🚨 Alert Rules</h3>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="update_rules">
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Alert Type</th>
                            <th>Threshold</th>
                            <th>Severity</th>
                            <th>Cooldown (min)</th>
                            <th>Enabled</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alertRules as $rule): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($rule['alert_name']) ?></strong>
                                    <div style="font-size: 12px; color: #64748b;">
                                        <?= htmlspecialchars($rule['alert_type']) ?>
                                    </div>
                                </td>
                                <td>
                                    <input type="number"
                                           name="rules[<?= $rule['id'] ?>][threshold]"
                                           value="<?= $rule['threshold'] ?>"
                                           style="width: 80px;">
                                    <span style="color: #64748b; font-size: 12px;">
                                        <?= htmlspecialchars($rule['threshold_unit']) ?>
                                    </span>
                                </td>
                                <td>
                                    <select name="rules[<?= $rule['id'] ?>][severity]" style="width: 100px;">
                                        <option value="info" <?= ($rule['severity'] ?? 'warning') === 'info' ? 'selected' : '' ?>>Info</option>
                                        <option value="warning" <?= ($rule['severity'] ?? 'warning') === 'warning' ? 'selected' : '' ?>>Warning</option>
                                        <option value="critical" <?= ($rule['severity'] ?? 'warning') === 'critical' ? 'selected' : '' ?>>Critical</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="number"
                                           name="rules[<?= $rule['id'] ?>][cooldown]"
                                           value="<?= $rule['cooldown_minutes'] ?>"
                                           style="width: 60px;">
                                </td>
                                <td>
                                    <label class="toggle">
                                        <input type="checkbox"
                                               name="rules[<?= $rule['id'] ?>][enabled]"
                                               <?= $rule['is_enabled'] ? 'checked' : '' ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div style="margin-top: 20px;">
                <button type="submit" class="btn btn-primary">Save Rules</button>
            </div>
        </form>
    </div>
</div>

<!-- Quiet Hours & Global Settings -->
<div class="card">
    <div class="card-header">
        <h3>⏰ Alert Settings & Quiet Hours</h3>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="update_quiet_hours">

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                <div class="form-group">
                    <div class="toggle-group">
                        <label class="toggle">
                            <input type="checkbox" name="alerts_enabled" <?= ($config['alerts_enabled'] ?? 1) ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                        <span><strong>Alerts Enabled</strong></span>
                    </div>
                    <p style="font-size: 12px; color: #64748b; margin-top: 5px;">Master switch for all alerts</p>
                </div>

                <div class="form-group">
                    <div class="toggle-group">
                        <label class="toggle">
                            <input type="checkbox" name="quiet_hours_enabled" <?= ($config['quiet_hours_enabled'] ?? 0) ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                        <span><strong>Quiet Hours Enabled</strong></span>
                    </div>
                    <p style="font-size: 12px; color: #64748b; margin-top: 5px;">Suppress alerts during specified times</p>
                </div>

                <div class="form-group">
                    <label>Quiet Hours Start</label>
                    <input type="time" name="quiet_hours_start" value="<?= substr($config['quiet_hours_start'] ?? '21:00:00', 0, 5) ?>">
                </div>

                <div class="form-group">
                    <label>Quiet Hours End</label>
                    <input type="time" name="quiet_hours_end" value="<?= substr($config['quiet_hours_end'] ?? '07:00:00', 0, 5) ?>">
                </div>
            </div>

            <div style="margin-top: 20px;">
                <button type="submit" class="btn btn-primary">Save Settings</button>
            </div>
        </form>
    </div>
</div>

<!-- Webhook Configuration -->
<div class="card">
    <div class="card-header">
        <h3>🔗 Webhook Integrations (Slack / Teams)</h3>
        <button class="btn btn-primary" onclick="openWebhookModal()">+ Add Webhook</button>
    </div>
    <div class="card-body">
        <?php if (empty($webhooks)): ?>
            <div class="empty-state">
                <div class="icon">🔗</div>
                <h3>No Webhooks Configured</h3>
                <p>Add Slack or Microsoft Teams webhooks to receive alerts.</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Name</th>
                            <th>Channel</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($webhooks as $wh): ?>
                            <tr>
                                <td>
                                    <?php if ($wh['webhook_type'] === 'slack'): ?>
                                        <span class="badge" style="background: #4a154b; color: #fff;">Slack</span>
                                    <?php else: ?>
                                        <span class="badge" style="background: #5059c9; color: #fff;">Teams</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= htmlspecialchars($wh['webhook_name']) ?></strong></td>
                                <td><?= htmlspecialchars($wh['channel_name'] ?: '-') ?></td>
                                <td>
                                    <?php if ($wh['is_active']): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-gray">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="table-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle_webhook">
                                        <input type="hidden" name="id" value="<?= $wh['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-secondary">
                                            <?= $wh['is_active'] ? 'Disable' : 'Enable' ?>
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;"
                                          onsubmit="return confirm('Remove this webhook?')">
                                        <input type="hidden" name="action" value="delete_webhook">
                                        <input type="hidden" name="id" value="<?= $wh['id'] ?>">
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

<!-- Alert Recipients -->
<div class="card">
    <div class="card-header">
        <h3>📧 Alert Recipients</h3>
        <button class="btn btn-primary" onclick="openRecipientModal()">+ Add Recipient</button>
    </div>
    <div class="card-body">
        <?php if (empty($recipients)): ?>
            <div class="empty-state">
                <div class="icon">📧</div>
                <h3>No Recipients Configured</h3>
                <p>Add recipients to receive alert notifications.</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Receives</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recipients as $r): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
                                <td><?= htmlspecialchars($r['email'] ?: '-') ?></td>
                                <td><?= htmlspecialchars($r['phone'] ?: '-') ?></td>
                                <td>
                                    <?php if ($r['receives_email']): ?>
                                        <span class="badge badge-info">Email</span>
                                    <?php endif; ?>
                                    <?php if ($r['receives_sms']): ?>
                                        <span class="badge badge-success">SMS</span>
                                    <?php endif; ?>
                                </td>
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

<!-- Alert History -->
<div class="card">
    <div class="card-header">
        <h3>📜 Recent Alerts</h3>
    </div>
    <div class="card-body">
        <?php if (empty($recentAlerts)): ?>
            <div class="empty-state">
                <p>No alerts have been triggered yet.</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Type</th>
                            <th>Message</th>
                            <th>Sent To</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentAlerts as $alert): ?>
                            <tr>
                                <td><?= date('M j, g:i a', strtotime($alert['sent_at'])) ?></td>
                                <td>
                                    <span class="badge badge-<?= in_array($alert['alert_type'], ['sla_breach', 'no_agents']) ? 'danger' : 'warning' ?>">
                                        <?= htmlspecialchars($alert['alert_type']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($alert['alert_message']) ?></td>
                                <td><?= htmlspecialchars($alert['sent_to'] ?: 'Dashboard only') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Recipient Modal -->
<div class="modal-overlay" id="recipientModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Add Alert Recipient</h3>
            <button class="modal-close" onclick="closeRecipientModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_recipient">
            
            <div class="modal-body">
                <div class="form-group">
                    <label for="name">Name *</label>
                    <input type="text" id="name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email">
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone (for SMS)</label>
                    <input type="tel" id="phone" name="phone" placeholder="+1234567890">
                </div>
                
                <div class="form-group">
                    <div class="toggle-group">
                        <label class="toggle">
                            <input type="checkbox" name="receives_email" checked>
                            <span class="toggle-slider"></span>
                        </label>
                        <span>Receive Email Alerts</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="toggle-group">
                        <label class="toggle">
                            <input type="checkbox" name="receives_sms">
                            <span class="toggle-slider"></span>
                        </label>
                        <span>Receive SMS Alerts</span>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeRecipientModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Recipient</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Webhook Modal -->
<div class="modal-overlay" id="webhookModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Add Webhook Integration</h3>
            <button class="modal-close" onclick="closeWebhookModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_webhook">

            <div class="modal-body">
                <div class="form-group">
                    <label for="webhook_type">Type *</label>
                    <select id="webhook_type" name="webhook_type" required>
                        <option value="slack">Slack</option>
                        <option value="teams">Microsoft Teams</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="webhook_name">Name *</label>
                    <input type="text" id="webhook_name" name="webhook_name" placeholder="e.g., #alerts-channel" required>
                </div>

                <div class="form-group">
                    <label for="webhook_url">Webhook URL *</label>
                    <input type="url" id="webhook_url" name="webhook_url" placeholder="https://hooks.slack.com/..." required>
                    <p style="font-size: 11px; color: #64748b; margin-top: 5px;">
                        Slack: Create via Apps > Incoming Webhooks<br>
                        Teams: Create via Connectors > Incoming Webhook
                    </p>
                </div>

                <div class="form-group">
                    <label for="channel_name">Channel Name (optional)</label>
                    <input type="text" id="channel_name" name="channel_name" placeholder="#general">
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeWebhookModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Webhook</button>
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

function openWebhookModal() {
    document.getElementById('webhookModal').classList.add('active');
}

function closeWebhookModal() {
    document.getElementById('webhookModal').classList.remove('active');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeRecipientModal();
        closeWebhookModal();
    }
});
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
