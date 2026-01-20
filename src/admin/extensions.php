<?php
/**
 * Extension Management
 * 
 * @package VitalPBX-Asterisk-Wallboard
 */

$pageTitle = 'Extensions';
require_once __DIR__ . '/templates/header.php';
require_role('admin');

$db = Database::getInstance();
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'add') {
            $db->execute("
                INSERT INTO extensions (extension, first_name, last_name, team, is_active)
                VALUES (:ext, :first, :last, :team, :active)
            ", [
                'ext' => trim($_POST['extension']),
                'first' => trim($_POST['first_name']) ?: 'Ext ' . trim($_POST['extension']),
                'last' => trim($_POST['last_name']) ?: null,
                'team' => trim($_POST['team']) ?: null,
                'active' => isset($_POST['is_active']) ? 1 : 0
            ]);
            
            // Also create agent_status entry
            $db->execute("
                INSERT INTO agent_status (extension, agent_name, status)
                VALUES (:ext, :name, 'unknown')
                ON DUPLICATE KEY UPDATE agent_name = :name2
            ", [
                'ext' => trim($_POST['extension']),
                'name' => trim($_POST['first_name']) . ' ' . trim($_POST['last_name']),
                'name2' => trim($_POST['first_name']) . ' ' . trim($_POST['last_name'])
            ]);
            
            $message = 'Extension added successfully';
            
        } elseif ($action === 'edit') {
            $db->execute("
                UPDATE extensions SET
                    extension = :ext,
                    first_name = :first,
                    last_name = :last,
                    team = :team,
                    is_active = :active
                WHERE id = :id
            ", [
                'id' => (int) $_POST['id'],
                'ext' => trim($_POST['extension']),
                'first' => trim($_POST['first_name']) ?: 'Ext ' . trim($_POST['extension']),
                'last' => trim($_POST['last_name']) ?: null,
                'team' => trim($_POST['team']) ?: null,
                'active' => isset($_POST['is_active']) ? 1 : 0
            ]);
            // Also sync to agent_status
            $displayName = trim($_POST['first_name']) . ' ' . trim($_POST['last_name']);
            if (isset($_POST['is_active']) && $_POST['is_active']) {
                $db->execute("
                    INSERT INTO agent_status (extension, agent_name, status)
                    VALUES (:ext, :name, 'offline')
                    ON DUPLICATE KEY UPDATE agent_name = :name2
                ", [
                    'ext' => trim($_POST['extension']),
                    'name' => $displayName,
                    'name2' => $displayName
                ]);
            }
            $message = 'Extension updated successfully';
            
        } elseif ($action === 'delete') {
            $db->execute("DELETE FROM extensions WHERE id = :id", ['id' => (int) $_POST['id']]);
            $message = 'Extension deleted successfully';
            
        
        } elseif ($action === 'sync_vitalpbx') {
            // Run VitalPBX sync
            $output = shell_exec('php /var/www/html/scripts/sync_vitalpbx.php 2>&1');
            $message = 'VitalPBX sync completed: ' . trim($output);

        } elseif ($action === 'bulk_add') {
            // Bulk add extensions
            $start = (int) $_POST['start_ext'];
            $end = (int) $_POST['end_ext'];
            $prefix = trim($_POST['name_prefix']);
            
            if ($start > 0 && $end >= $start && $end - $start < 100) {
                $count = 0;
                for ($ext = $start; $ext <= $end; $ext++) {
                    try {
                        $db->execute("
                            INSERT INTO extensions (extension, first_name, is_active)
                            VALUES (:ext, :name, 1)
                        ", [
                            'ext' => (string) $ext,
                            'name' => $prefix ? "$prefix $ext" : "Agent $ext"
                        ]);
                        $count++;
                    } catch (Exception $e) {
                        // Skip duplicates
                    }
                }
                $message = "$count extensions added successfully";
            } else {
                $error = 'Invalid range (max 100 extensions at a time)';
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get extensions
$extensions = $db->fetchAll("
    SELECT e.*, 
           a.status, 
           a.calls_today,
           a.missed_today
    FROM extensions e
    LEFT JOIN agent_status a ON e.extension = a.extension
    ORDER BY e.extension
");
?>

<?php if ($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3>Configured Extensions</h3>
        <div style="display: flex; gap: 10px;">
            <form method="POST" style="display:inline;"><input type="hidden" name="action" value="sync_vitalpbx"><button type="submit" class="btn btn-secondary">🔄 Sync from VitalPBX</button></form>
            <button class="btn btn-secondary" onclick="openBulkModal()">+ Bulk Add</button>
            <button class="btn btn-primary" onclick="openModal('add')">+ Add Extension</button>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($extensions)): ?>
            <div class="empty-state">
                <div class="icon">👥</div>
                <h3>No Extensions Configured</h3>
                <p>Add your agent extensions to start tracking them.</p>
                <button class="btn btn-primary" onclick="openModal('add')">Add First Extension</button>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Extension</th>
                            <th>Name</th>
                            <th>Team</th>
                            <th>Status</th>
                            <th>Calls Today</th>
                            <th>Missed</th>
                            <th>Active</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($extensions as $ext): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($ext['extension']) ?></strong></td>
                                <td><?= htmlspecialchars($ext['display_name']) ?></td>
                                <td><?= htmlspecialchars($ext['team'] ?: '-') ?></td>
                                <td>
                                    <?php
                                    $status = $ext['status'] ?? 'unknown';
                                    $statusClass = [
                                        'available' => 'success',
                                        'on_call' => 'info',
                                        'ringing' => 'warning',
                                        'wrapup' => 'info',
                                        'paused' => 'gray',
                                        'offline' => 'gray',
                                        'unknown' => 'gray'
                                    ][$status] ?? 'gray';
                                    ?>
                                    <span class="badge badge-<?= $statusClass ?>"><?= ucfirst($status) ?></span>
                                </td>
                                <td><?= $ext['calls_today'] ?? 0 ?></td>
                                <td>
                                    <?php if (($ext['missed_today'] ?? 0) > 0): ?>
                                        <span class="badge badge-danger"><?= $ext['missed_today'] ?></span>
                                    <?php else: ?>
                                        0
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($ext['is_active']): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-gray">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="table-actions">
                                    <button class="btn btn-sm btn-secondary" onclick='editExt(<?= json_encode($ext) ?>)'>Edit</button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteExt(<?= $ext['id'] ?>, '<?= htmlspecialchars($ext['display_name']) ?>')">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal-overlay" id="extModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalTitle">Add Extension</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST" id="extForm">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="formId" value="">
            
            <div class="modal-body">
                <div class="form-group">
                    <label for="extension">Extension Number *</label>
                    <input type="text" id="extension" name="extension" required placeholder="e.g., 1201">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name <span style="color:#666">(auto-syncs from VitalPBX)</span></label>
                        <input type="text" id="first_name" name="first_name" placeholder="John">
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" placeholder="Smith">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="team">Team</label>
                    <input type="text" id="team" name="team" placeholder="e.g., Support">
                </div>
                
                <div class="form-group">
                    <div class="toggle-group">
                        <label class="toggle">
                            <input type="checkbox" name="is_active" id="is_active" checked>
                            <span class="toggle-slider"></span>
                        </label>
                        <span>Active</span>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Extension</button>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Add Modal -->
<div class="modal-overlay" id="bulkModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Bulk Add Extensions</h3>
            <button class="modal-close" onclick="closeBulkModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="bulk_add">
            
            <div class="modal-body">
                <p style="margin-bottom: 20px; color: #64748b;">
                    Add a range of extensions at once. You can edit names afterwards.
                </p>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="start_ext">Start Extension *</label>
                        <input type="number" id="start_ext" name="start_ext" required placeholder="1200">
                    </div>
                    
                    <div class="form-group">
                        <label for="end_ext">End Extension *</label>
                        <input type="number" id="end_ext" name="end_ext" required placeholder="1220">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="name_prefix">Name Prefix</label>
                    <input type="text" id="name_prefix" name="name_prefix" placeholder="Agent">
                    <div class="help-text">Names will be "Prefix ExtNumber" (e.g., "Agent 1201")</div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeBulkModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Extensions</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Form (hidden) -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteId">
</form>

<script>
function openModal(mode) {
    document.getElementById('modalTitle').textContent = mode === 'add' ? 'Add Extension' : 'Edit Extension';
    document.getElementById('formAction').value = mode;
    document.getElementById('extModal').classList.add('active');
}

function closeModal() {
    document.getElementById('extModal').classList.remove('active');
    document.getElementById('extForm').reset();
    document.getElementById('formId').value = '';
}

function openBulkModal() {
    document.getElementById('bulkModal').classList.add('active');
}

function closeBulkModal() {
    document.getElementById('bulkModal').classList.remove('active');
}

function editExt(ext) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('formId').value = ext.id;
    document.getElementById('extension').value = ext.extension;
    document.getElementById('first_name').value = ext.first_name;
    document.getElementById('last_name').value = ext.last_name || '';
    document.getElementById('team').value = ext.team || '';
    document.getElementById('is_active').checked = ext.is_active == 1;
    openModal('edit');
}

function deleteExt(id, name) {
    if (confirm('Are you sure you want to delete extension "' + name + '"?')) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }
}

// Close modals on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
        closeBulkModal();
    }
});
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
