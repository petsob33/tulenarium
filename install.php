<?php
require_once 'config.php';

$message = '';

if (isset($_POST['install'])) {
    try {
        // Připojení bez specifikace databáze pro vytvoření databáze
        $pdo_root = new PDO("mysql:host=" . DB_HOST . ";charset=utf8", DB_USER, DB_PASS);
        $pdo_root->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Vytvoření databáze
        $pdo_root->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8 COLLATE utf8_general_ci");
        
        // Připojení k nové databázi
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Vytvoření tabulky events
        $sql = "CREATE TABLE IF NOT EXISTS events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            event_date DATE NOT NULL,
            people_count INT DEFAULT 0,
            location VARCHAR(255),
            media TEXT,
            thumbnail VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
        
        $pdo->exec($sql);
        
        // Vytvoření tabulky participants
        $sql_participants = "CREATE TABLE IF NOT EXISTS participants (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
        
        $pdo->exec($sql_participants);
        
        echo '<div class="success">✓ Tabulka participants byla vytvořena!</div>';
        
        // Vytvoření adresáře uploads
        if (!is_dir(UPLOAD_DIR)) {
            mkdir(UPLOAD_DIR, 0755, true);
        }
        
        $message = displaySuccess('Databáze a tabulka byly úspěšně vytvořeny!');
        
    } catch (PDOException $e) {
        $message = displayError('Chyba při vytváření databáze: ' . $e->getMessage());
    }
}

// Kontrola existence tabulky
$tableExists = false;
$tableInfo = '';
try {
    $pdo = getDB();
    $stmt = $pdo->query("SHOW TABLES LIKE 'events'");
    $tableExists = $stmt->rowCount() > 0;
    
    if ($tableExists) {
        $stmt = $pdo->query("DESCRIBE events");
        $columns = $stmt->fetchAll();
        $tableInfo = '<h3>Struktura tabulky events:</h3><ul>';
        foreach ($columns as $column) {
            $tableInfo .= '<li><strong>' . $column['Field'] . '</strong> - ' . $column['Type'] . '</li>';
        }
        $tableInfo .= '</ul>';
        
        // Kontrola tabulky participants
        $stmt = $pdo->query("SHOW TABLES LIKE 'participants'");
        $participantsExists = $stmt->rowCount() > 0;
        
        if ($participantsExists) {
            $stmt = $pdo->query("DESCRIBE participants");
            $columns = $stmt->fetchAll();
            $tableInfo .= '<h3>Struktura tabulky participants:</h3><ul>';
            foreach ($columns as $column) {
                $tableInfo .= '<li><strong>' . $column['Field'] . '</strong> - ' . $column['Type'] . '</li>';
            }
            $tableInfo .= '</ul>';
        }
        
        // Kontrola počtu záznamů
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM events");
        $count = $stmt->fetch()['count'];
        $tableInfo .= '<p>Počet eventů: <strong>' . $count . '</strong></p>';
        
        if ($participantsExists) {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM participants");
            $participantCount = $stmt->fetch()['count'];
            $tableInfo .= '<p>Počet účastníků celkem: <strong>' . $participantCount . '</strong></p>';
        }
    }
} catch (PDOException $e) {
    // Databáze nebo tabulka neexistuje
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalace - Tulenarium</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        .install-btn {
            background: #007cba;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            display: block;
            margin: 20px auto;
        }
        .install-btn:hover {
            background: #005a87;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin: 15px 0;
            border: 1px solid #f5c6cb;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            margin: 15px 0;
            border: 1px solid #c3e6cb;
        }
        .status {
            background: #e2e3e5;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .status h3 {
            margin-top: 0;
            color: #495057;
        }
        .status ul {
            margin: 10px 0;
        }
        .status li {
            margin: 5px 0;
        }
        .nav-links {
            text-align: center;
            margin-top: 30px;
        }
        .nav-links a {
            color: #007cba;
            text-decoration: none;
            margin: 0 15px;
            padding: 8px 15px;
            border: 1px solid #007cba;
            border-radius: 3px;
        }
        .nav-links a:hover {
            background: #007cba;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Instalace Tulenarium</h1>
        
        <?php echo $message; ?>
        
        <div class="status">
            <h3>Stav systému:</h3>
            <p><strong>Databáze:</strong> <?php echo DB_NAME; ?></p>
            <p><strong>Host:</strong> <?php echo DB_HOST; ?></p>
            <p><strong>Upload adresář:</strong> <?php echo UPLOAD_DIR; ?></p>
            <p><strong>Tabulka events:</strong> <?php echo $tableExists ? '<span style="color: green;">Existuje</span>' : '<span style="color: red;">Neexistuje</span>'; ?></p>
            <p><strong>Upload adresář:</strong> <?php echo is_dir(UPLOAD_DIR) ? '<span style="color: green;">Existuje</span>' : '<span style="color: red;">Neexistuje</span>'; ?></p>
        </div>
        
        <?php if ($tableExists): ?>
            <div class="status">
                <?php echo $tableInfo; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!$tableExists): ?>
            <form method="post">
                <p>Pro instalace aplikace klikněte na tlačítko níže. Vytvoří se databáze, tabulka events a upload adresář.</p>
                <button type="submit" name="install" class="install-btn">Nainstalovat aplikaci</button>
            </form>
        <?php else: ?>
            <p style="text-align: center; color: green;">✓ Aplikace je úspěšně nainstalována!</p>
        <?php endif; ?>
        
        <div class="nav-links">
            <a href="index.php">Hlavní stránka</a>
            <a href="admin.php">Administrace</a>
        </div>
    </div>
</body>
</html>
