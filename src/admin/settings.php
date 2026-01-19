<?php
/**
 * Settings Page
 * 
 * @package VitalPBX-Asterisk-Wallboard
 */

$pageTitle = 'Settings';
require_once __DIR__ . '/templates/header.php';
require_role('admin');

$db = Database::getInstance();
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section = $_POST['section'] ?? '';
    
    try {
        if ($section === 'ami') {
            // Update AMI config
            $existing = $db->fetchOne("SELECT id FROM ami_config LIMIT 1");
            
            if ($existing) {
                $db->execute("
                    UPDATE ami_config SET
                        ami_host = :host,
                        ami_port = :port,
                        ami_username = :user,
                        ami_password = :pass,
                        is_active = 1
                    WHERE id = :id
                ", [
                    'id' => $existing['id'],
                    'host' => trim($_POST['ami_host']),
                    'port' => (int) $_POST['ami_port'],
                    'user' => trim($_POST['ami_username']),
                    'pass' => trim($_POST['ami_password'])
                ]);
            } else {
                $db->execute("
                    INSERT INTO ami_config (ami_host, ami_port, ami_username, ami_password, is_active)
                    VALUES (:host, :port, :user, :pass, 1)
                ", [
                    'host' => trim($_POST['ami_host']),
                    'port' => (int) $_POST['ami_port'],
                    'user' => trim($_POST['ami_username']),
                    'pass' => trim($_POST['ami_password'])
                ]);
            }
            $message = 'AMI settings saved. Restart daemon for changes to take effect.';
            
        } elseif ($section === 'company') {
            // Update company config
            $db->execute("
                UPDATE company_config SET
                    company_name = :name,
                    timezone = :tz,
                    refresh_rate = :refresh,
                    business_hours_start = :hours_start,
                    business_hours_end = :hours_end,
                    wrapup_time = :wrapup,
                    ring_timeout = :ring_timeout,
                    repeat_caller_days = :repeat_days,
                    repeat_caller_threshold = :repeat_threshold,
                    data_retention_days = :retention
            ", [
                'name' => trim($_POST['company_name']),
                'tz' => trim($_POST['timezone']),
                'refresh' => (int) $_POST['refresh_rate'],
                'hours_start' => $_POST['business_hours_start'],
                'hours_end' => $_POST['business_hours_end'],
                'wrapup' => (int) $_POST['wrapup_time'],
                'ring_timeout' => (int) $_POST['ring_timeout'],
                'repeat_days' => (int) $_POST['repeat_caller_days'],
                'repeat_threshold' => (int) $_POST['repeat_caller_threshold'],
                'retention' => (int) $_POST['data_retention_days']
            ]);
            $message = 'Settings saved successfully';
            
        } elseif ($section === 'smtp') {
            // Update SMTP config
            $db->execute("
                UPDATE smtp_config SET
                    smtp_host = :host,
                    smtp_port = :port,
                    smtp_username = :user,
                    smtp_password = :pass,
                    smtp_encryption = :encryption,
                    from_address = :from_addr,
                    from_name = :from_name,
                    is_configured = 1
            ", [
                'host' => trim($_POST['smtp_host']),
                'port' => (int) $_POST['smtp_port'],
                'user' => trim($_POST['smtp_username']),
                'pass' => trim($_POST['smtp_password']),
                'encryption' => $_POST['smtp_encryption'],
                'from_addr' => trim($_POST['from_address']),
                'from_name' => trim($_POST['from_name'])
            ]);
            $message = 'SMTP settings saved';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get current settings
$amiConfig = $db->fetchOne("SELECT * FROM ami_config LIMIT 1") ?: [];
$companyConfig = $db->fetchOne("SELECT * FROM company_config LIMIT 1") ?: [];
$smtpConfig = $db->fetchOne("SELECT * FROM smtp_config LIMIT 1") ?: [];

// Timezone list (simplified)
$timezones = [
    'America/New_York' => 'Eastern Time',
    'America/Chicago' => 'Central Time',
    'America/Denver' => 'Mountain Time',
    'America/Los_Angeles' => 'Pacific Time',
    'America/Phoenix' => 'Arizona',
    'America/Anchorage' => 'Alaska',
    'Pacific/Honolulu' => 'Hawaii',
    'UTC' => 'UTC'
];
?>

<?php if ($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- AMI Configuration -->
<div class="card">
    <div class="card-header">
        <h3>📡 AMI Connection</h3>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="section" value="ami">
            
            <div class="form-grid">
                <div class="form-group">
                    <label for="ami_host">AMI Host *</label>
                    <input type="text" id="ami_host" name="ami_host" required
                           value="<?= htmlspecialchars($amiConfig['ami_host'] ?? '') ?>"
                           placeholder="pbx.example.com">
                    <div class="help-text">VitalPBX hostname or IP address</div>
                </div>
                
                <div class="form-group">
                    <label for="ami_port">AMI Port</label>
                    <input type="number" id="ami_port" name="ami_port"
                           value="<?= htmlspecialchars($amiConfig['ami_port'] ?? '5038') ?>">
                </div>
                
                <div class="form-group">
                    <label for="ami_username">AMI Username *</label>
                    <input type="text" id="ami_username" name="ami_username" required
                           value="<?= htmlspecialchars($amiConfig['ami_username'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="ami_password">AMI Password *</label>
                    <input type="password" id="ami_password" name="ami_password" required
                           value="<?= htmlspecialchars($amiConfig['ami_password'] ?? '') ?>">
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">Save AMI Settings</button>
        </form>
    </div>
</div>

<!-- Company Configuration -->
<div class="card">
    <div class="card-header">
        <h3>🏢 Company Settings</h3>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="section" value="company">
            
            <div class="form-grid">
                <div class="form-group">
                    <label for="company_name">Company Name</label>
                    <input type="text" id="company_name" name="company_name"
                           value="<?= htmlspecialchars($companyConfig['company_name'] ?? 'Call Center') ?>">
                </div>
                
                <div class="form-group">
                    <label for="timezone">Timezone</label>
                    <select id="timezone" name="timezone">
                        <?php foreach ($timezones as $tz => $label): ?>
                            <option value="<?= $tz ?>" <?= ($companyConfig['timezone'] ?? '') === $tz ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="refresh_rate">Dashboard Refresh Rate (seconds)</label>
                    <input type="number" id="refresh_rate" name="refresh_rate" min="1" max="60"
                           value="<?= htmlspecialchars($companyConfig['refresh_rate'] ?? '5') ?>">
                </div>
                
                <div class="form-group">
                    <label for="wrapup_time">Wrap-up Time (seconds)</label>
                    <input type="number" id="wrapup_time" name="wrapup_time" min="0" max="300"
                           value="<?= htmlspecialchars($companyConfig['wrapup_time'] ?? '60') ?>">
                    <div class="help-text">Time after call before agent is available</div>
                </div>
                
                <div class="form-group">
                    <label for="business_hours_start">Business Hours Start</label>
                    <input type="time" id="business_hours_start" name="business_hours_start"
                           value="<?= htmlspecialchars($companyConfig['business_hours_start'] ?? '08:00') ?>">
                </div>
                
                <div class="form-group">
                    <label for="business_hours_end">Business Hours End</label>
                    <input type="time" id="business_hours_end" name="business_hours_end"
                           value="<?= htmlspecialchars($companyConfig['business_hours_end'] ?? '18:00') ?>">
                </div>
                
                <div class="form-group">
                    <label for="ring_timeout">Ring Timeout (seconds)</label>
                    <input type="number" id="ring_timeout" name="ring_timeout" min="5" max="120"
                           value="<?= htmlspecialchars($companyConfig['ring_timeout'] ?? '20') ?>">
                    <div class="help-text">Time before considering a call "missed"</div>
                </div>
                
                <div class="form-group">
                    <label for="repeat_caller_days">Repeat Caller Window (days)</label>
                    <input type="number" id="repeat_caller_days" name="repeat_caller_days" min="1" max="90"
                           value="<?= htmlspecialchars($companyConfig['repeat_caller_days'] ?? '30') ?>">
                </div>
                
                <div class="form-group">
                    <label for="repeat_caller_threshold">Repeat Caller Threshold</label>
                    <input type="number" id="repeat_caller_threshold" name="repeat_caller_threshold" min="2" max="10"
                           value="<?= htmlspecialchars($companyConfig['repeat_caller_threshold'] ?? '2') ?>">
                    <div class="help-text">Number of calls to flag as repeat caller</div>
                </div>
                
                <div class="form-group">
                    <label for="data_retention_days">Data Retention (days)</label>
                    <input type="number" id="data_retention_days" name="data_retention_days" min="30" max="365"
                           value="<?= htmlspecialchars($companyConfig['data_retention_days'] ?? '90') ?>">
                    <div class="help-text">How long to keep call history</div>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">Save Company Settings</button>
        </form>
    </div>
</div>

<!-- SMTP Configuration -->
<div class="card">
    <div class="card-header">
        <h3>📧 Email Settings (for Alerts)</h3>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="section" value="smtp">
            
            <div class="form-grid">
                <div class="form-group">
                    <label for="smtp_host">SMTP Host</label>
                    <input type="text" id="smtp_host" name="smtp_host"
                           value="<?= htmlspecialchars($smtpConfig['smtp_host'] ?? '') ?>"
                           placeholder="smtp.gmail.com">
                </div>
                
                <div class="form-group">
                    <label for="smtp_port">SMTP Port</label>
                    <input type="number" id="smtp_port" name="smtp_port"
                           value="<?= htmlspecialchars($smtpConfig['smtp_port'] ?? '587') ?>">
                </div>
                
                <div class="form-group">
                    <label for="smtp_username">SMTP Username</label>
                    <input type="text" id="smtp_username" name="smtp_username"
                           value="<?= htmlspecialchars($smtpConfig['smtp_username'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="smtp_password">SMTP Password</label>
                    <input type="password" id="smtp_password" name="smtp_password"
                           value="<?= htmlspecialchars($smtpConfig['smtp_password'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="smtp_encryption">Encryption</label>
                    <select id="smtp_encryption" name="smtp_encryption">
                        <option value="tls" <?= ($smtpConfig['smtp_encryption'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS</option>
                        <option value="ssl" <?= ($smtpConfig['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                        <option value="none" <?= ($smtpConfig['smtp_encryption'] ?? '') === 'none' ? 'selected' : '' ?>>None</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="from_address">From Email</label>
                    <input type="email" id="from_address" name="from_address"
                           value="<?= htmlspecialchars($smtpConfig['from_address'] ?? '') ?>"
                           placeholder="alerts@example.com">
                </div>
                
                <div class="form-group">
                    <label for="from_name">From Name</label>
                    <input type="text" id="from_name" name="from_name"
                           value="<?= htmlspecialchars($smtpConfig['from_name'] ?? '') ?>"
                           placeholder="Wallboard Alerts">
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">Save Email Settings</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
