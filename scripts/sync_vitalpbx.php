<?php
require '/var/www/html/includes/db.php';

$apiUrl = 'http://pbx1.as36001.net:3500/api/v2/extensions';
$apiKey = '97179ed1734e602e721bc171ea36605c';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'app-key: ' . $apiKey,
    'tenant: VitalPBX'
]);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
if (!$data || $data['status'] !== 'success') {
    echo "[" . date('Y-m-d H:i:s') . "] Failed to fetch extensions from VitalPBX API\n";
    exit(1);
}

// Build lookup array from VitalPBX
$vitalpbxNames = [];
foreach ($data['data'] as $ext) {
    $vitalpbxNames[$ext['extension']] = $ext['name'];
}

$db = Database::getInstance();
$updated = 0;

// Get all extensions from our database
$extensions = $db->query("SELECT extension, first_name, last_name FROM extensions")->fetchAll(PDO::FETCH_ASSOC);

foreach ($extensions as $ext) {
    $extension = $ext['extension'];
    
    // If this extension exists in VitalPBX, update the name
    if (isset($vitalpbxNames[$extension])) {
        $name = $vitalpbxNames[$extension];
        $parts = explode(' ', $name, 2);
        $firstName = $parts[0];
        $lastName = $parts[1] ?? '';
        
        // Update extension name
        $db->execute("UPDATE extensions SET first_name = ?, last_name = ? WHERE extension = ?",
            [$firstName, $lastName, $extension]);
        
        // Update agent_status name too
        $db->execute("UPDATE agent_status SET agent_name = ? WHERE extension = ?",
            [trim($name), $extension]);
        
        $updated++;
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Updated $updated extensions from VitalPBX\n";
