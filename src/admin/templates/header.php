<?php
/**
 * Admin Header Template
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$user = current_user();

// Get company name
try {
    $db = Database::getInstance();
    $config = $db->fetchOne("SELECT company_name FROM company_config LIMIT 1");
    $companyName = $config['company_name'] ?? 'Wallboard';
} catch (Exception $e) {
    $companyName = 'Wallboard';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Admin' ?> - <?= htmlspecialchars($companyName) ?></title>
    <link rel="stylesheet" href="/admin/css/admin.css">
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h1>📊 <?= htmlspecialchars($companyName) ?></h1>
                <span class="badge">Admin</span>
            </div>
            
            <nav class="sidebar-nav">
                <a href="/admin/" class="nav-item <?= $currentPage === 'index' ? 'active' : '' ?>">
                    <span class="icon">🏠</span> Dashboard
                </a>
                <a href="/admin/queues.php" class="nav-item <?= $currentPage === 'queues' ? 'active' : '' ?>">
                    <span class="icon">📞</span> Queues
                </a>
                <a href="/admin/extensions.php" class="nav-item <?= $currentPage === 'extensions' ? 'active' : '' ?>">
                    <span class="icon">👥</span> Extensions
                </a>
                <a href="/admin/team.php" class="nav-item <?= $currentPage === 'team' ? 'active' : '' ?>">
                    <span class="icon">🏢</span> Team Members
                </a>
                <a href="/admin/alerts.php" class="nav-item <?= $currentPage === 'alerts' ? 'active' : '' ?>">
                    <span class="icon">🚨</span> Alerts
                </a>
                <a href="/admin/reports.php" class="nav-item <?= $currentPage === 'reports' ? 'active' : '' ?>">
                    <span class="icon">📈</span> Reports
                </a>
                <a href="/admin/email-reports.php" class="nav-item <?= $currentPage === 'email-reports' ? 'active' : '' ?>">
                    <span class="icon">📧</span> Email Reports
                </a>

                <?php if (has_role('admin')): ?>
                <div class="nav-divider"></div>
                <a href="/admin/users.php" class="nav-item <?= $currentPage === 'users' ? 'active' : '' ?>">
                    <span class="icon">🔐</span> Users
                </a>
                <a href="/admin/settings.php" class="nav-item <?= $currentPage === 'settings' ? 'active' : '' ?>">
                    <span class="icon">⚙️</span> Settings
                </a>
                <?php endif; ?>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-info">
                    <span class="username"><?= htmlspecialchars($user['username']) ?></span>
                    <span class="role"><?= ucfirst($user['role']) ?></span>
                </div>
                <a href="/admin/logout.php" class="logout-btn">Logout</a>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <header class="content-header">
                <h2><?= $pageTitle ?? 'Dashboard' ?></h2>
                <div class="header-actions">
                    <a href="/" class="btn btn-secondary" target="_blank">View Wallboard</a>
                    <a href="/manager.html" class="btn btn-secondary" target="_blank">Manager View</a>
                </div>
            </header>
            
            <div class="content-body">
