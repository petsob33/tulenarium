<?php
// Konfigurace databáze
define('DB_HOST', 'localhost');
define('DB_NAME', 'tulenarium');
define('DB_USER', 'root');
define('DB_PASS', 'root');

// Konfigurace administrace
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin123');

// Konfigurace aplikace
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('BASE_URL', '/pokusy/tulenarium/');

// Povolené typy souborů
define('ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'mov', 'avi', 'webm']);
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Chyba připojení k databázi: " . $e->getMessage());
}

// Funkce pro získání připojení k databázi
function getDB() {
    global $pdo;
    return $pdo;
}

// Funkce pro výpis chyb
function displayError($message) {
    return '<div class="error">' . htmlspecialchars($message) . '</div>';
}

// Funkce pro výpis úspěšných zpráv
function displaySuccess($message) {
    return '<div class="success">' . htmlspecialchars($message) . '</div>';
}

// Funkce pro kontrolu přihlášení
function isLoggedIn() {
    session_start();
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

// Funkce pro přihlášení
function login($username, $password) {
    if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
        session_start();
        $_SESSION['admin_logged_in'] = true;
        return true;
    }
    return false;
}

// Funkce pro odhlášení
function logout() {
    session_start();
    session_destroy();
}

// Funkce pro formátování data
function formatDate($date) {
    return date('d.m.Y', strtotime($date));
}

// Funkce pro nahrání souborů
function uploadFiles($files) {
    $uploadedFiles = [];
    $errors = [];
    
    // Převod $_FILES struktury na jednoduché pole
    $normalizedFiles = [];
    
    // Kontrola, jestli je to multiple upload nebo single upload
    if (isset($files['name']) && is_array($files['name'])) {
        // Multiple upload - převedeme na jednoduché pole
        $count = count($files['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                $normalizedFiles[] = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i]
                ];
            }
        }
    } else {
        // Single upload nebo jiný formát
        $normalizedFiles = [$files];
    }
    
    foreach ($normalizedFiles as $file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Chyba při nahrávání souboru ' . $file['name'] . ': ' . $file['error'];
            continue;
        }
        
        $fileName = $file['name'];
        $fileSize = $file['size'];
        $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Kontrola velikosti
        if ($fileSize > MAX_FILE_SIZE) {
            $errors[] = 'Soubor ' . $fileName . ' je příliš velký (max ' . number_format(MAX_FILE_SIZE / 1024 / 1024, 0) . ' MB)';
            continue;
        }
        
        // Kontrola typu
        if (!in_array($fileType, ALLOWED_TYPES)) {
            $errors[] = 'Nepodporovaný typ souboru ' . $fileName . ' (' . $fileType . ')';
            continue;
        }
        
        // Generování unikátního názvu
        $uniqueName = uniqid() . '_' . $fileName;
        $uploadPath = UPLOAD_DIR . $uniqueName;
        
        // Přesun souboru
        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            $uploadedFiles[] = [
                'filename' => $uniqueName,
                'original_name' => $fileName,
                'type' => $fileType,
                'size' => $fileSize
            ];
        } else {
            $errors[] = 'Nepodařilo se nahrát soubor ' . $fileName;
        }
    }
    
    return ['files' => $uploadedFiles, 'errors' => $errors];
}

// Funkce pro získání URL souboru
function getFileUrl($filename) {
    return BASE_URL . 'uploads/' . $filename;
}

// Funkce pro zkrácení textu
function truncateText($text, $length = 100) {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . '...';
}
?>
