<?php
/**
 * Acknowledge Alert API Endpoint
 *
 * POST /api/acknowledge-alert.php
 * Body: alert_id=123
 *
 * @package VitalPBX-Asterisk-Wallboard
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

try {
    // Check if user is logged in (optional - depends on your security needs)
    $user = null;
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['user_id'])) {
        $user = current_user();
    }

    $db = Database::getInstance();
    $alertId = (int) ($_POST['alert_id'] ?? $_GET['alert_id'] ?? 0);

    if (!$alertId) {
        throw new Exception('Alert ID required');
    }

    // Acknowledge the alert
    $username = $user ? $user['username'] : 'anonymous';

    $db->execute("
        UPDATE active_alerts
        SET acknowledged_at = NOW(), acknowledged_by = ?
        WHERE id = ? AND is_active = 1
    ", [$username, $alertId]);

    echo json_encode([
        'success' => true,
        'message' => 'Alert acknowledged'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
