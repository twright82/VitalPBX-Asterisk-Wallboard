<?php
/**
 * User Management
 * 
 * @package VitalPBX-Asterisk-Wallboard
 */

$pageTitle = 'Users';
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
            $password = trim($_POST['password']);
            if (strlen($password) < 6) {
                throw new Exception('Password must be at least 6 characters');
            }
            
            $result = create_user(
                trim($_POST['username']),
                $password,
                $_POST['role'],
                trim($_POST['email']) ?: null
            );
            
            if ($result['success']) {
                $message = 'User created successfully';
            } else {
                $error = $result['error'];
            }
            
        } elseif ($action === 'edit') {
            $id = (int) $_POST['id'];
            
            // Update basic info
            $db->execute("
                UPDATE users SET
                    username = :username,
                    email = :email,
                    role = :role,
                    is_active = :active
                WHERE id = :id
            ", [
                'id' => $id,
                'username' => trim($_POST['username']),
                'email' => trim($_POST['email']) ?: null,
                'role' => $_POST['role'],
                'active' => isset($_POST['is_active']) ? 1 : 0
            ]);
            
            // Update password if provided
            if (!empty($_POST['password'])) {
                $password = trim($_POST['password']);
                if (strlen($password) < 6) {
                    throw new Exception('Password must be at least 6 characters');
                }
                update_password($id, $password);
            }
            
            $message = 'User updated successfully';
            
        } elseif ($action === 'delete') {
            $id = (int) $_POST['id'];
            
            // Don't delete yourself
            if ($id === $_SESSION['user_id']) {
                throw new Exception('Cannot delete your own account');
            }
            
            // Don't delete the last admin
            $adminCount = $db->fetchValue("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1");
            $deletingAdmin = $db->fetchValue("SELECT role FROM users WHERE id = :id", ['id' => $id]) === 'admin';
            
            if ($deletingAdmin && $adminCount <= 1) {
                throw new Exception('Cannot delete the last admin user');
            }
            
            $db->execute("DELETE FROM users WHERE id = :id", ['id' => $id]);
            $message = 'User deleted';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get users
$users = $db->fetchAll("SELECT * FROM users ORDER BY role, username");
?>

<?php if ($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3>👤 Users</h3>
        <button class="btn btn-primary" onclick="openModal('add')">+ Add User</button>
    </div>
    <div class="card-body">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($user['username']) ?></strong>
                                <?php if ($user['id'] === $_SESSION['user_id']): ?>
                                    <span class="badge badge-info">You</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($user['email'] ?: '-') ?></td>
                            <td>
                                <?php
                                $roleClass = [
                                    'admin' => 'danger',
                                    'manager' => 'warning',
                                    'viewer' => 'info'
                                ][$user['role']] ?? 'gray';
                                ?>
                                <span class="badge badge-<?= $roleClass ?>">
                                    <?= ucfirst($user['role']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($user['is_active']): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-gray">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= $user['last_login'] ? date('M j, g:i a', strtotime($user['last_login'])) : 'Never' ?>
                            </td>
                            <td class="table-actions">
                                <button class="btn btn-sm btn-secondary" 
                                        onclick='editUser(<?= json_encode($user) ?>)'>Edit</button>
                                <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                    <button class="btn btn-sm btn-danger" 
                                            onclick="deleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')">Delete</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Role Descriptions -->
<div class="card">
    <div class="card-header">
        <h3>📋 Role Permissions</h3>
    </div>
    <div class="card-body">
        <table>
            <thead>
                <tr>
                    <th>Role</th>
                    <th>Permissions</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><span class="badge badge-danger">Admin</span></td>
                    <td>Full access: Settings, Users, Queues, Extensions, Alerts, Reports, All Dashboards</td>
                </tr>
                <tr>
                    <td><span class="badge badge-warning">Manager</span></td>
                    <td>View Dashboards, Manage Alerts, View Reports, View Queues/Extensions (no edit)</td>
                </tr>
                <tr>
                    <td><span class="badge badge-info">Viewer</span></td>
                    <td>View Dashboards only (Wallboard and Manager View)</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal-overlay" id="userModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalTitle">Add User</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST" id="userForm">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="formId" value="">
            
            <div class="modal-body">
                <div class="form-group">
                    <label for="username">Username *</label>
                    <input type="text" id="username" name="username" required 
                           pattern="[a-zA-Z0-9_]+" minlength="3">
                    <div class="help-text">Letters, numbers, underscore only</div>
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email">
                </div>
                
                <div class="form-group">
                    <label for="password">Password <span id="passwordNote">*</span></label>
                    <input type="password" id="password" name="password" minlength="6">
                    <div class="help-text">Minimum 6 characters</div>
                </div>
                
                <div class="form-group">
                    <label for="role">Role *</label>
                    <select id="role" name="role" required>
                        <option value="viewer">Viewer</option>
                        <option value="manager">Manager</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                
                <div class="form-group" id="activeGroup" style="display: none;">
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
                <button type="submit" class="btn btn-primary">Save User</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Form -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteId">
</form>

<script>
function openModal(mode) {
    document.getElementById('modalTitle').textContent = mode === 'add' ? 'Add User' : 'Edit User';
    document.getElementById('formAction').value = mode;
    document.getElementById('userModal').classList.add('active');
    
    if (mode === 'add') {
        document.getElementById('password').required = true;
        document.getElementById('passwordNote').textContent = '*';
        document.getElementById('activeGroup').style.display = 'none';
    } else {
        document.getElementById('password').required = false;
        document.getElementById('passwordNote').textContent = '(leave blank to keep)';
        document.getElementById('activeGroup').style.display = 'block';
    }
}

function closeModal() {
    document.getElementById('userModal').classList.remove('active');
    document.getElementById('userForm').reset();
    document.getElementById('formId').value = '';
}

function editUser(user) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('formId').value = user.id;
    document.getElementById('username').value = user.username;
    document.getElementById('email').value = user.email || '';
    document.getElementById('password').value = '';
    document.getElementById('role').value = user.role;
    document.getElementById('is_active').checked = user.is_active == 1;
    openModal('edit');
}

function deleteUser(id, name) {
    if (confirm('Are you sure you want to delete user "' + name + '"?')) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
