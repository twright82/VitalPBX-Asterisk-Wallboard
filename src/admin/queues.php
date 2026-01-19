<?php
/**
 * Queue Management
 * 
 * @package VitalPBX-Asterisk-Wallboard
 */

$pageTitle = 'Queues';
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
                INSERT INTO queues (queue_number, queue_name, display_name, group_name, sort_order, is_active, show_on_wallboard)
                VALUES (:number, :name, :display, :group_name, :sort, :active, :show)
            ", [
                'number' => trim($_POST['queue_number']),
                'name' => trim($_POST['queue_name']),
                'display' => trim($_POST['display_name']),
                'group_name' => trim($_POST['group_name']) ?: null,
                'sort' => (int) $_POST['sort_order'],
                'active' => isset($_POST['is_active']) ? 1 : 0,
                'show' => isset($_POST['show_on_wallboard']) ? 1 : 0
            ]);
            $message = 'Queue added successfully';
            
        } elseif ($action === 'edit') {
            $db->execute("
                UPDATE queues SET
                    queue_number = :number,
                    queue_name = :name,
                    display_name = :display,
                    group_name = :group_name,
                    sort_order = :sort,
                    is_active = :active,
                    show_on_wallboard = :show
                WHERE id = :id
            ", [
                'id' => (int) $_POST['id'],
                'number' => trim($_POST['queue_number']),
                'name' => trim($_POST['queue_name']),
                'display' => trim($_POST['display_name']),
                'group_name' => trim($_POST['group_name']) ?: null,
                'sort' => (int) $_POST['sort_order'],
                'active' => isset($_POST['is_active']) ? 1 : 0,
                'show' => isset($_POST['show_on_wallboard']) ? 1 : 0
            ]);
            $message = 'Queue updated successfully';
            
        } elseif ($action === 'delete') {
            $db->execute("DELETE FROM queues WHERE id = :id", ['id' => (int) $_POST['id']]);
            $message = 'Queue deleted successfully';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get queues
$queues = $db->fetchAll("SELECT * FROM queues ORDER BY sort_order, queue_name");
?>

<?php if ($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3>Configured Queues</h3>
        <button class="btn btn-primary" onclick="openModal('add')">+ Add Queue</button>
    </div>
    <div class="card-body">
        <?php if (empty($queues)): ?>
            <div class="empty-state">
                <div class="icon">📞</div>
                <h3>No Queues Configured</h3>
                <p>Add your VitalPBX queues to start monitoring them.</p>
                <button class="btn btn-primary" onclick="openModal('add')">Add First Queue</button>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Number</th>
                            <th>Name</th>
                            <th>Display Name</th>
                            <th>Group</th>
                            <th>Order</th>
                            <th>Status</th>
                            <th>Wallboard</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($queues as $queue): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($queue['queue_number']) ?></strong></td>
                                <td><?= htmlspecialchars($queue['queue_name']) ?></td>
                                <td><?= htmlspecialchars($queue['display_name']) ?></td>
                                <td><?= htmlspecialchars($queue['group_name'] ?: '-') ?></td>
                                <td><?= $queue['sort_order'] ?></td>
                                <td>
                                    <?php if ($queue['is_active']): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-gray">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($queue['show_on_wallboard']): ?>
                                        <span class="badge badge-info">Visible</span>
                                    <?php else: ?>
                                        <span class="badge badge-gray">Hidden</span>
                                    <?php endif; ?>
                                </td>
                                <td class="table-actions">
                                    <button class="btn btn-sm btn-secondary" onclick='editQueue(<?= json_encode($queue) ?>)'>Edit</button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteQueue(<?= $queue['id'] ?>, '<?= htmlspecialchars($queue['queue_name']) ?>')">Delete</button>
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
<div class="modal-overlay" id="queueModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalTitle">Add Queue</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST" id="queueForm">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="formId" value="">
            
            <div class="modal-body">
                <div class="form-group">
                    <label for="queue_number">Queue Number *</label>
                    <input type="text" id="queue_number" name="queue_number" required placeholder="e.g., 1293">
                    <div class="help-text">The queue extension number from VitalPBX</div>
                </div>
                
                <div class="form-group">
                    <label for="queue_name">Queue Name *</label>
                    <input type="text" id="queue_name" name="queue_name" required placeholder="e.g., ISP Support Queue">
                    <div class="help-text">The name as it appears in VitalPBX</div>
                </div>
                
                <div class="form-group">
                    <label for="display_name">Display Name *</label>
                    <input type="text" id="display_name" name="display_name" required placeholder="e.g., Support">
                    <div class="help-text">Short name shown on the wallboard</div>
                </div>
                
                <div class="form-group">
                    <label for="group_name">Group Name</label>
                    <input type="text" id="group_name" name="group_name" placeholder="e.g., ISP">
                    <div class="help-text">Optional - for grouping related queues</div>
                </div>
                
                <div class="form-group">
                    <label for="sort_order">Sort Order</label>
                    <input type="number" id="sort_order" name="sort_order" value="0" min="0">
                    <div class="help-text">Lower numbers appear first</div>
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
                
                <div class="form-group">
                    <div class="toggle-group">
                        <label class="toggle">
                            <input type="checkbox" name="show_on_wallboard" id="show_on_wallboard" checked>
                            <span class="toggle-slider"></span>
                        </label>
                        <span>Show on Wallboard</span>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Queue</button>
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
    document.getElementById('modalTitle').textContent = mode === 'add' ? 'Add Queue' : 'Edit Queue';
    document.getElementById('formAction').value = mode;
    document.getElementById('queueModal').classList.add('active');
}

function closeModal() {
    document.getElementById('queueModal').classList.remove('active');
    document.getElementById('queueForm').reset();
    document.getElementById('formId').value = '';
}

function editQueue(queue) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('formId').value = queue.id;
    document.getElementById('queue_number').value = queue.queue_number;
    document.getElementById('queue_name').value = queue.queue_name;
    document.getElementById('display_name').value = queue.display_name;
    document.getElementById('group_name').value = queue.group_name || '';
    document.getElementById('sort_order').value = queue.sort_order;
    document.getElementById('is_active').checked = queue.is_active == 1;
    document.getElementById('show_on_wallboard').checked = queue.show_on_wallboard == 1;
    openModal('edit');
}

function deleteQueue(id, name) {
    if (confirm('Are you sure you want to delete queue "' + name + '"?')) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }
}

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});

// Close modal on overlay click
document.getElementById('queueModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
