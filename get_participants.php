<?php
require_once 'config.php';

// Kontrola přihlášení
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Nastavení JSON header
header('Content-Type: application/json');

try {
    $db = getDB();
    
    // Získání všech unikátních jmen účastníků
    $stmt = $db->query("SELECT DISTINCT name FROM participants ORDER BY name");
    $participants = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Filtrování podle query parametru (pokud je zadán)
    $query = $_GET['q'] ?? '';
    if (!empty($query)) {
        $filtered = [];
        foreach ($participants as $name) {
            if (stripos($name, $query) !== false) {
                $filtered[] = $name;
            }
        }
        $participants = $filtered;
    }
    
    // Omezení na maximálně 20 výsledků
    $participants = array_slice($participants, 0, 20);
    
    echo json_encode([
        'success' => true,
        'participants' => $participants
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
}
?>
