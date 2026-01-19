<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../includes/db.php";
$db = Database::getInstance();
$agents = $db->fetchAll("
    SELECT 
        a.extension,
        a.agent_name as name,
        a.status
    FROM agent_status a
    INNER JOIN extensions e ON a.extension = e.extension
    WHERE e.is_active = 1
");
echo json_encode(["count" => count($agents), "agents" => $agents], JSON_PRETTY_PRINT);
