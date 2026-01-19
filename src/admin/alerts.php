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
                        cooldown_minutes = :cooldown
                    WHERE id = :id
                ", [
                    'id' => $id,
                    'threshold' => (int) $rule['threshold'],
                    'enabled' => isset($rule['enabled']) ? 1 : 0,
                    'cooldown' => (int) $rule['cooldown']
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
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get data
$alertRules = $db->fetchAll("SELECT * FROM alert_rules ORDER BY alert_name");
$recipients = $db->fetchAll("SELECT * FROM alert_recipients ORDER BY name");
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
