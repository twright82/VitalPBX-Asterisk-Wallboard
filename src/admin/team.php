<?php
/**
 * Team Members Management
 *
 * Manage non-queue staff to display in Team Extensions section
 *
 * @package VitalPBX-Asterisk-Wallboard
 */

$pageTitle = 'Team Members';
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
                INSERT INTO extensions (extension, first_name, last_name, team, is_team_member, is_active)
                VALUES (:ext, :first, :last, :team, 1, :active)
            ", [
                'ext' => trim($_POST['extension']),
                'first' => trim($_POST['first_name']) ?: 'Ext ' . trim($_POST['extension']),
                'last' => trim($_POST['last_name']) ?: null,
                'team' => trim($_POST['team']) ?: null,
                'active' => isset($_POST['is_active']) ? 1 : 0
            ]);

            // Create agent_status entry for real-time tracking
            $db->execute("
                INSERT INTO agent_status (extension, agent_name, status)
                VALUES (:ext, :name, 'offline')
                ON DUPLICATE KEY UPDATE agent_name = :name2
            ", [
                'ext' => trim($_POST['extension']),
                'name' => trim($_POST['first_name']) . ' ' . trim($_POST['last_name']),
                'name2' => trim($_POST['first_name']) . ' ' . trim($_POST['last_name'])
            ]);

            $message = 'Team member added successfully';

        } elseif ($action === 'edit') {
            $db->execute("
                UPDATE extensions SET
                    extension = :ext,
                    first_name = :first,
                    last_name = :last,
                    team = :team,
                    is_active = :active
                WHERE id = :id AND is_team_member = 1
            ", [
                'id' => (int) $_POST['id'],
                'ext' => trim($_POST['extension']),
                'first' => trim($_POST['first_name']) ?: 'Ext ' . trim($_POST['extension']),
                'last' => trim($_POST['last_name']) ?: null,
                'team' => trim($_POST['team']) ?: null,
                'active' => isset($_POST['is_active']) ? 1 : 0
            ]);

            // Sync to agent_status
            $displayName = trim($_POST['first_name']) . ' ' . trim($_POST['last_name']);
            $db->execute("
                INSERT INTO agent_status (extension, agent_name, status)
                VALUES (:ext, :name, 'offline')
                ON DUPLICATE KEY UPDATE agent_name = :name2
            ", [
                'ext' => trim($_POST['extension']),
                'name' => $displayName,
                'name2' => $displayName
            ]);

            $message = 'Team member updated successfully';

        } elseif ($action === 'delete') {
            $db->execute("DELETE FROM extensions WHERE id = :id AND is_team_member = 1", ['id' => (int) $_POST['id']]);
            $message = 'Team member removed successfully';

        } elseif ($action === 'convert_from_agent') {
            // Convert existing agent to team member
            $db->execute("UPDATE extensions SET is_team_member = 1 WHERE id = :id", ['id' => (int) $_POST['id']]);
            $message = 'Agent converted to team member';

        } elseif ($action === 'convert_to_agent') {
            // Convert team member back to agent
            $db->execute("UPDATE extensions SET is_team_member = 0 WHERE id = :id", ['id' => (int) $_POST['id']]);
            $message = 'Team member converted to agent';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get team members
$teamMembers = $db->fetchAll("
    SELECT e.*,
           a.status,
           a.calls_today
    FROM extensions e
    LEFT JOIN agent_status a ON e.extension = a.extension
    WHERE e.is_team_member = 1
    ORDER BY e.team, e.extension
");

// Get available agents (for conversion dropdown)
$availableAgents = $db->fetchAll("
    SELECT id, extension, display_name
    FROM extensions
    WHERE is_team_member = 0 AND is_active = 1
    ORDER BY extension
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
        <h3>Team Members (Non-Queue Staff)</h3>
        <div style="display: flex; gap: 10px;">
            <button class="btn btn-secondary" onclick="openConvertModal()">Convert Agent</button>
            <button class="btn btn-primary" onclick="openModal('add')">+ Add Team Member</button>
        </div>
    </div>
    <div class="card-body">
        <p style="color: #64748b; margin-bottom: 20px;">
            Team members appear in the "Team Extensions" section below agents on the wallboard.
            They are tracked for phone status but are not part of any call queue.
        </p>

        <?php if (empty($teamMembers)): ?>
            <div class="empty-state">
                <div class="icon">👥</div>
                <h3>No Team Members Configured</h3>
                <p>Add managers, NOC staff, or back-office personnel to track their availability on the wallboard.</p>
                <button class="btn btn-primary" onclick="openModal('add')">Add First Team Member</button>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Extension</th>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th>Active</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teamMembers as $member): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($member['extension']) ?></strong></td>
                                <td><?= htmlspecialchars($member['display_name']) ?></td>
                                <td><?= htmlspecialchars($member['team'] ?: '-') ?></td>
                                <td>
                                    <?php
                                    $status = $member['status'] ?? 'unknown';
                                    $statusClass = [
                                        'available' => 'success',
                                        'on_call' => 'info',
                                        'ringing' => 'warning',
                                        'offline' => 'gray',
                                        'unknown' => 'gray'
                                    ][$status] ?? 'gray';
                                    ?>
                                    <span class="badge badge-<?= $statusClass ?>"><?= ucfirst($status) ?></span>
                                </td>
                                <td>
                                    <?php if ($member['is_active']): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-gray">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="table-actions">
                                    <button class="btn btn-sm btn-secondary" onclick='editMember(<?= json_encode($member) ?>)'>Edit</button>
                                    <button class="btn btn-sm btn-secondary" onclick="convertToAgent(<?= $member['id'] ?>, '<?= htmlspecialchars($member['display_name']) ?>')">To Agent</button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteMember(<?= $member['id'] ?>, '<?= htmlspecialchars($member['display_name']) ?>')">Remove</button>
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
<div class="modal-overlay" id="memberModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalTitle">Add Team Member</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST" id="memberForm">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="formId" value="">

            <div class="modal-body">
                <div class="form-group">
                    <label for="extension">Extension Number *</label>
                    <input type="text" id="extension" name="extension" required placeholder="e.g., 1501">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" placeholder="John">
                    </div>

                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" placeholder="Smith">
                    </div>
                </div>

                <div class="form-group">
                    <label for="team">Department/Team</label>
                    <input type="text" id="team" name="team" placeholder="e.g., Management, NOC, Accounting">
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
                <button type="submit" class="btn btn-primary">Save Team Member</button>
            </div>
        </form>
    </div>
</div>

<!-- Convert Agent Modal -->
<div class="modal-overlay" id="convertModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Convert Agent to Team Member</h3>
            <button class="modal-close" onclick="closeConvertModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="convert_from_agent">

            <div class="modal-body">
                <p style="margin-bottom: 20px; color: #64748b;">
                    Select an existing agent to convert to a team member. They will be removed from the Agents panel and appear in Team Extensions instead.
                </p>

                <div class="form-group">
                    <label for="convert_id">Select Agent</label>
                    <select id="convert_id" name="id" required>
                        <option value="">-- Select Agent --</option>
                        <?php foreach ($availableAgents as $agent): ?>
                            <option value="<?= $agent['id'] ?>">
                                <?= htmlspecialchars($agent['extension'] . ' - ' . $agent['display_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeConvertModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Convert to Team Member</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Form -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteId">
</form>

<!-- Convert to Agent Form -->
<form id="convertToAgentForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="convert_to_agent">
    <input type="hidden" name="id" id="convertToAgentId">
</form>

<script>
function openModal(mode) {
    document.getElementById('modalTitle').textContent = mode === 'add' ? 'Add Team Member' : 'Edit Team Member';
    document.getElementById('formAction').value = mode;
    document.getElementById('memberModal').classList.add('active');
}

function closeModal() {
    document.getElementById('memberModal').classList.remove('active');
    document.getElementById('memberForm').reset();
    document.getElementById('formId').value = '';
}

function openConvertModal() {
    document.getElementById('convertModal').classList.add('active');
}

function closeConvertModal() {
    document.getElementById('convertModal').classList.remove('active');
}

function editMember(member) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('formId').value = member.id;
    document.getElementById('extension').value = member.extension;
    document.getElementById('first_name').value = member.first_name;
    document.getElementById('last_name').value = member.last_name || '';
    document.getElementById('team').value = member.team || '';
    document.getElementById('is_active').checked = member.is_active == 1;
    openModal('edit');
}

function deleteMember(id, name) {
    if (confirm('Remove "' + name + '" from team members?')) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }
}

function convertToAgent(id, name) {
    if (confirm('Convert "' + name + '" back to a regular agent?')) {
        document.getElementById('convertToAgentId').value = id;
        document.getElementById('convertToAgentForm').submit();
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
        closeConvertModal();
    }
});
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
