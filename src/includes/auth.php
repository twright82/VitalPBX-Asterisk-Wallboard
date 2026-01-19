<?php
/**
 * Authentication Helper
 * 
 * @package VitalPBX-Asterisk-Wallboard
 * @version 1.0.0
 */

session_start();

require_once __DIR__ . '/db.php';

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

/**
 * Check if user has role
 */
function has_role($role) {
    if (!is_logged_in()) return false;
    
    $userRole = $_SESSION['user_role'] ?? '';
    
    // Admin has access to everything
    if ($userRole === 'admin') return true;
    
    // Manager has access to manager and viewer
    if ($userRole === 'manager' && in_array($role, ['manager', 'viewer'])) return true;
    
    // Viewer only has viewer access
    if ($userRole === 'viewer' && $role === 'viewer') return true;
    
    return $userRole === $role;
}

/**
 * Require login - redirect if not logged in
 */
function require_login() {
    if (!is_logged_in()) {
        header('Location: /admin/login.php');
        exit;
    }
}

/**
 * Require specific role
 */
function require_role($role) {
    require_login();
    
    if (!has_role($role)) {
        http_response_code(403);
        die('Access denied');
    }
}

/**
 * Attempt login
 */
function attempt_login($username, $password) {
    try {
        $db = Database::getInstance();
        
        $user = $db->fetchOne(
            "SELECT id, username, password_hash, role, is_active FROM users WHERE username = :username",
            ['username' => $username]
        );
        
        if (!$user) {
            return ['success' => false, 'error' => 'Invalid username or password'];
        }
        
        if (!$user['is_active']) {
            return ['success' => false, 'error' => 'Account is disabled'];
        }
        
        if (!password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Invalid username or password'];
        }
        
        // Success - set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_role'] = $user['role'];
        
        // Update last login
        $db->execute(
            "UPDATE users SET last_login = NOW() WHERE id = :id",
            ['id' => $user['id']]
        );
        
        return ['success' => true];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Database error'];
    }
}

/**
 * Logout
 */
function logout() {
    $_SESSION = [];
    session_destroy();
}

/**
 * Get current user info
 */
function current_user() {
    if (!is_logged_in()) return null;
    
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'role' => $_SESSION['user_role']
    ];
}

/**
 * Hash password
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Create user
 */
function create_user($username, $password, $role = 'viewer', $email = null) {
    try {
        $db = Database::getInstance();
        
        // Check if username exists
        $existing = $db->fetchValue(
            "SELECT COUNT(*) FROM users WHERE username = :username",
            ['username' => $username]
        );
        
        if ($existing > 0) {
            return ['success' => false, 'error' => 'Username already exists'];
        }
        
        $db->execute(
            "INSERT INTO users (username, password_hash, email, role, is_active) VALUES (:username, :password, :email, :role, 1)",
            [
                'username' => $username,
                'password' => hash_password($password),
                'email' => $email,
                'role' => $role
            ]
        );
        
        return ['success' => true, 'id' => $db->lastInsertId()];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Update user password
 */
function update_password($userId, $newPassword) {
    try {
        $db = Database::getInstance();
        $db->execute(
            "UPDATE users SET password_hash = :password WHERE id = :id",
            ['password' => hash_password($newPassword), 'id' => $userId]
        );
        return true;
    } catch (Exception $e) {
        return false;
    }
}
