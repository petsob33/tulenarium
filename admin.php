<?php
require_once 'config.php';

// Kontrola přihlášení pro celou aplikaci
if (!isLoggedIn()) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$message = '';

// Zpracování odhlášení
if (isset($_GET['logout'])) {
    logout();
    header('Location: login.php');
    exit;
}

// Zpracování přidání nebo úpravy eventu
if (isset($_POST['add_event']) || isset($_POST['edit_event'])) {
    $isEdit = isset($_POST['edit_event']);
    $eventId = $isEdit ? (int)$_POST['event_id'] : null;
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $event_date = $_POST['event_date'] ?? '';
    $participants_text = $_POST['participants'] ?? '';
    $location = $_POST['location'] ?? '';
    $thumbnail_index = (int)($_POST['thumbnail'] ?? 0);
    
    // Zpracování jmen účastníků
    $participants = [];
    if (!empty($participants_text)) {
        $participants = array_filter(array_map('trim', explode("\n", $participants_text)));
    }
    $people_count = count($participants);
    
    if (empty($title) || empty($event_date)) {
        $message = displayError('Název a datum jsou povinné!');
    } else {
        try {
            $db = getDB();
            
            // Zpracování existujících médií při úpravě
            $existingMedia = [];
            if ($isEdit && !empty($_POST['existing_media'])) {
                $existingMedia = json_decode($_POST['existing_media'], true) ?: [];
            }
            
            // Zpracování odstraněných médií
            $removedMedia = [];
            if (!empty($_POST['removeMedia'])) {
                $removedMedia = array_map('intval', explode(',', $_POST['removeMedia']));
            }
            
            // Filtrování existujících médií (odstranění smazaných)
            $filteredExistingMedia = [];
            foreach ($existingMedia as $index => $media) {
                if (!in_array($index, $removedMedia)) {
                    $filteredExistingMedia[] = $media;
                }
            }
            
            // Upload nových souborů
            $uploadResult = ['files' => [], 'errors' => []];
            if (!empty($_FILES['media']['tmp_name'][0])) {
                $uploadResult = uploadFiles($_FILES['media']);
            }
            
            $uploadedFiles = $uploadResult['files'];
            $uploadErrors = $uploadResult['errors'];
            
            // Zobrazení chyb uploadu
            if (!empty($uploadErrors)) {
                $message = displayError('Chyby při uploadu souborů:<br>' . implode('<br>', $uploadErrors));
            }
            
            // Kombinace existujících a nových médií
            $allMedia = array_merge($filteredExistingMedia, $uploadedFiles);
            
            // Určení náhledového obrázku
            $thumbnail = '';
            $thumbnailValue = $_POST['thumbnail'] ?? '';
            
            if (strpos($thumbnailValue, 'existing_') === 0) {
                // Náhled z existujících médií
                $existingIndex = (int)str_replace('existing_', '', $thumbnailValue);
                if (isset($filteredExistingMedia[$existingIndex])) {
                    $thumbnail = $filteredExistingMedia[$existingIndex]['filename'];
                }
            } elseif (is_numeric($thumbnailValue) && isset($uploadedFiles[$thumbnailValue])) {
                // Náhled z nových souborů
                $thumbnail = $uploadedFiles[$thumbnailValue]['filename'];
            } elseif (!empty($allMedia)) {
                // Automatický výběr prvního obrázku
                foreach ($allMedia as $file) {
                    if (in_array($file['type'], ['jpg', 'jpeg', 'png', 'gif'])) {
                        $thumbnail = $file['filename'];
                        break;
                    }
                }
            }
            
            // Uložení do databáze
            if ($isEdit) {
                // Úprava existujícího eventu
                $stmt = $db->prepare("UPDATE events SET title = ?, description = ?, event_date = ?, people_count = ?, location = ?, media = ?, thumbnail = ? WHERE id = ?");
                $stmt->execute([
                    $title,
                    $description,
                    $event_date,
                    $people_count,
                    $location,
                    json_encode($allMedia),
                    $thumbnail,
                    $eventId
                ]);
                $event_id = $eventId;
                
                // Smazání starých účastníků
                $stmt = $db->prepare("DELETE FROM participants WHERE event_id = ?");
                $stmt->execute([$event_id]);
            } else {
                // Přidání nového eventu
                $stmt = $db->prepare("INSERT INTO events (title, description, event_date, people_count, location, media, thumbnail) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $title,
                    $description,
                    $event_date,
                    $people_count,
                    $location,
                    json_encode($allMedia),
                    $thumbnail
                ]);
                $event_id = $db->lastInsertId();
            }
            
            // Uložení účastníků do tabulky participants
            if (!empty($participants)) {
                $stmt = $db->prepare("INSERT INTO participants (event_id, name) VALUES (?, ?)");
                foreach ($participants as $participant_name) {
                    $stmt->execute([$event_id, $participant_name]);
                }
            }
            
            if ($isEdit) {
                $message = displaySuccess('Event byl úspěšně upraven s ' . count($participants) . ' účastníky!');
                header('Location: admin.php');
                exit;
            } else {
                $message = displaySuccess('Event byl úspěšně přidán s ' . count($participants) . ' účastníky!');
                // Vyčištění formuláře
                $_POST = [];
            }
            
        } catch (PDOException $e) {
            $message = displayError('Chyba při ukládání: ' . $e->getMessage());
        }
    }
}

// Zpracování smazání eventu
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $db = getDB();
        
        // Získání informací o eventu před smazáním
        $stmt = $db->prepare("SELECT media, thumbnail FROM events WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        $event = $stmt->fetch();
        
        if ($event) {
            // Smazání souborů
            if (!empty($event['media'])) {
                $media = json_decode($event['media'], true);
                if (is_array($media)) {
                    foreach ($media as $file) {
                        $filePath = UPLOAD_DIR . $file['filename'];
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }
                    }
                }
            }
            
            // Smazání účastníků (CASCADE by mělo fungovat automaticky)
            $stmt = $db->prepare("DELETE FROM participants WHERE event_id = ?");
            $stmt->execute([$_GET['delete']]);
            
            // Smazání z databáze
            $stmt = $db->prepare("DELETE FROM events WHERE id = ?");
            $stmt->execute([$_GET['delete']]);
            
            $message = displaySuccess('Event byl úspěšně smazán!');
        }
        
    } catch (PDOException $e) {
        $message = displayError('Chyba při mazání: ' . $e->getMessage());
    }
}

// Kontrola úpravy eventu
$editEvent = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM events WHERE id = ?");
        $stmt->execute([$_GET['edit']]);
        $editEvent = $stmt->fetch();
        
        if ($editEvent) {
            // Načtení účastníků pro úpravu
            $stmt = $db->prepare("SELECT name FROM participants WHERE event_id = ? ORDER BY name");
            $stmt->execute([$editEvent['id']]);
            $editParticipants = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $editEvent['participants_text'] = implode("\n", $editParticipants);
        }
    } catch (PDOException $e) {
        $message = displayError('Chyba při načítání eventu: ' . $e->getMessage());
    }
}

// Načtení všech eventů pro přehled
$events = [];
try {
    $db = getDB();
    $stmt = $db->query("
        SELECT e.*, 
               (SELECT COUNT(*) FROM participants p WHERE p.event_id = e.id) as actual_participants_count
        FROM events e 
        ORDER BY event_date DESC
    ");
    $events = $stmt->fetchAll();
} catch (PDOException $e) {
    $message = displayError('Chyba při načítání eventů: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrace - Tulenarium</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #1a1a1a;
            color: #ffffff;
            line-height: 1.6;
            min-height: 100vh;
        }
        
        /* Navigation */
        .navbar {
            background: #2d2d2d;
            border-bottom: 2px solid #333333;
            padding: 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
        }
        
        .nav-logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: #ffffff;
            text-decoration: none;
            padding: 15px 0;
        }
        
        .nav-menu {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
        }
        
        .nav-item {
            margin: 0;
        }
        
        .nav-link {
            display: block;
            color: #ffffff;
            text-decoration: none;
            padding: 20px 25px;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
        }
        
        .nav-link:hover {
            background: #3a3a3a;
            border-bottom-color: #ffffff;
        }
        
        .nav-link.active {
            background: #3a3a3a;
            border-bottom-color: #ffffff;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: #2d2d2d;
            border: 1px solid #404040;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .header h1 {
            color: #ffffff;
            font-weight: 300;
            font-size: 2rem;
        }
        

        
        .login-form {
            background: #2d2d2d;
            border: 1px solid #404040;
            padding: 40px;
            border-radius: 10px;
            max-width: 450px;
            margin: 80px auto;
        }
        
        .login-form h2 {
            text-align: center;
            margin-bottom: 30px;
            color: #ffffff;
            font-weight: 300;
            font-size: 1.8rem;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #222222;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: color 0.3s ease;
        }
        
        .form-group:focus-within label {
            color: #000000;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            background: #ffffff;
            color: #333333;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            position: relative;
        }
        
        .form-group input:hover,
        .form-group textarea:hover,
        .form-group select:hover {
            border-color: #cccccc;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #222222;
            background: #f8f9fa;
            box-shadow: 0 0 0 3px rgba(34, 34, 34, 0.1);
            transform: translateY(-1px);
        }
        
        .form-group textarea {
            height: 120px;
            resize: vertical;
            font-family: inherit;
        }
        
        .btn {
            background: #222222;
            color: #ffffff;
            padding: 12px 25px;
            border: 2px solid #222222;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            background: #1a1a1a;
            color: #ffffff;
            border-color: #1a1a1a;
            transform: translateY(-2px);
        }
        
        .btn-full {
            width: 100%;
            text-align: center;
        }
        
        .btn-danger {
            background: #dc3545;
            color: #ffffff;
            border-color: #dc3545;
        }
        
        .btn-danger:hover {
            background: #c82333;
            color: #ffffff;
            border-color: #c82333;
            transform: translateY(-2px);
        }
        
        .btn-edit {
            background: #28a745;
            color: #ffffff;
            border-color: #28a745;
        }
        
        .btn-edit:hover {
            background: #218838;
            color: #ffffff;
            border-color: #218838;
            transform: translateY(-2px);
        }
        
        .error {
            background: #2d1b1f;
            color: #ff6b6b;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border: 1px solid #ff4444;
        }
        
        .success {
            background: #1a2d1a;
            color: #4caf50;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border: 1px solid #4caf50;
        }
        
        .main-content {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
        }
        
        .add-event-form,
        .events-list {
            background: #ffffff;
            color: #333333;
            border: 2px solid #e0e0e0;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(255,255,255,0.1);
        }
        
        .add-event-form h2,
        .events-list h2 {
            margin-bottom: 25px;
            color: #222222;
            font-weight: 400;
            font-size: 1.6rem;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 15px;
        }
        
        .file-preview {
            margin-top: 15px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 15px;
        }
        
        .file-preview-item {
            text-align: center;
            padding: 15px;
            border: 1px solid #333333;
            border-radius: 8px;
            background: #000000;
            transition: all 0.3s ease;
        }
        
        .file-preview-item:hover {
            border-color: #ffffff;
            background: #111111;
        }
        
        .file-preview-item img {
            max-width: 90px;
            max-height: 90px;
            border-radius: 5px;
            border: 1px solid #333333;
        }
        
        .file-preview-item input[type="radio"] {
            margin-top: 10px;
            transform: scale(1.2);
        }
        
        .file-preview-item label {
            font-size: 0.85rem;
            color: #cccccc;
        }
        
        .existing-media {
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            border: 1px solid #e0e0e0;
        }
        
        .existing-media h4 {
            margin-bottom: 15px;
            color: #333;
            font-size: 1.1rem;
        }
        
        .existing-media-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
        }
        
        .existing-media-item {
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            background: white;
            transition: all 0.3s ease;
        }
        
        .existing-media-item:hover {
            border-color: #333;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .existing-media-item img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        
        .existing-media-item img:hover {
            transform: scale(1.05);
        }
        
        .video-placeholder {
            width: 100%;
            height: 120px;
            background: #333;
            color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
        }
        
        .video-placeholder small {
            font-size: 0.8rem;
            margin-top: 5px;
            text-align: center;
            padding: 0 5px;
        }
        
        .media-controls {
            padding: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8f9fa;
        }
        
        .media-controls label {
            font-size: 0.85rem;
            color: #333;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-remove {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 4px 8px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: background 0.3s ease;
        }
        
        .btn-remove:hover {
            background: #c82333;
        }
        
        /* Lightbox pro admin */
        .admin-lightbox {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 3000;
            cursor: pointer;
        }
        
        .admin-lightbox img {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            max-width: 90%;
            max-height: 90%;
            border-radius: 10px;
            box-shadow: 0 0 50px rgba(0,0,0,0.5);
        }
        
        .admin-lightbox-close {
            position: absolute;
            top: 20px;
            right: 30px;
            color: white;
            font-size: 3rem;
            cursor: pointer;
            background: rgba(0,0,0,0.5);
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .admin-lightbox-close:hover {
            background: rgba(0,0,0,0.8);
            transform: scale(1.1);
        }
        
        .event-item {
            border: 2px solid #e0e0e0;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            background: #ffffff;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(255,255,255,0.1);
        }
        
        .event-item:hover {
            border-color: #222222;
            background: #f8f9fa;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(255,255,255,0.2);
        }
        
        .event-item h3 {
            color: #222222;
            margin-bottom: 15px;
            font-weight: 600;
            font-size: 1.3rem;
        }
        
        .event-meta {
            color: #666666;
            font-size: 14px;
            margin-bottom: 15px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .event-description {
            margin-bottom: 15px;
            color: #555555;
            line-height: 1.6;
        }
        
        .event-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .nav-toggle {
            display: none;
            flex-direction: column;
            cursor: pointer;
            padding: 12px;
            background: transparent;
            border: none;
            border-radius: 4px;
            transition: background-color 0.3s ease;
        }
        
        .nav-toggle:hover {
            background-color: rgba(255,255,255,0.1);
        }
        
        .nav-toggle span {
            width: 24px;
            height: 2px;
            background: #ffffff;
            margin: 2px 0;
            transition: all 0.3s ease;
            border-radius: 1px;
        }
        
        .nav-toggle.active span:nth-child(1) {
            transform: rotate(45deg) translate(5px, 5px);
        }
        
        .nav-toggle.active span:nth-child(2) {
            opacity: 0;
        }
        
        .nav-toggle.active span:nth-child(3) {
            transform: rotate(-45deg) translate(7px, -6px);
        }
        
        .small-text {
            font-size: 0.9rem;
            color: #666666;
            margin-top: 5px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #000000;
            border: 1px solid #333333;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #ffffff;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #cccccc;
            font-size: 0.9rem;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .navbar {
                position: relative;
            }
            
            .nav-menu {
                position: absolute;
                left: -100%;
                top: 100%;
                flex-direction: column;
                background-color: #2d2d2d;
                width: 100%;
                text-align: center;
                transition: left 0.3s ease;
                border-top: 2px solid #333333;
                box-shadow: 0 2px 10px rgba(0,0,0,0.3);
                margin: 0;
                padding: 0;
                z-index: 999;
            }
            
            .nav-menu.active {
                left: 0;
            }
            
            .nav-item {
                margin: 0;
                width: 100%;
            }
            
            .nav-link {
                padding: 18px 20px;
                border-bottom: 1px solid #333333;
                display: block;
                width: 100%;
                text-align: center;
                border-left: none;
                border-right: none;
            }
            
            .nav-link:last-child {
                border-bottom: none;
            }
            
            .nav-toggle {
                display: flex;
            }
            
            .header {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .header h1 {
                font-size: 1.5rem;
            }
            
            .container {
                padding: 10px;
            }
            
            .add-event-form,
            .events-list {
                padding: 20px;
            }
            
            .event-actions {
                justify-content: center;
            }
            
            .file-preview {
                grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
                gap: 10px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 15px;
            }
            
            .login-form {
                margin: 40px auto;
                padding: 30px 20px;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .event-meta {
                flex-direction: column;
                gap: 5px;
            }
        }
    </style>
</head>
<body>

        <!-- Navigation -->
        <nav class="navbar">
            <div class="nav-container">
                <a href="index.php" class="nav-logo">🏛️ Tulenarium</a>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="index.php" class="nav-link">Domů</a>
                    </li>
                    <li class="nav-item">
                        <a href="admin.php" class="nav-link active">Administrace</a>
                    </li>
                    <li class="nav-item">
                        <a href="stats.php" class="nav-link">Statistiky</a>
                    </li>
                </ul>
                <div class="nav-toggle">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        </nav>
    <div class="container">


            <!-- Hlavní administrace -->
            <div class="header">
                <h1>Administrace eventů</h1>
            </div>
            
            <?php echo $message; ?>
            
            <div class="main-content">
                <!-- Formulář pro přidání/úpravu eventu -->
                <div class="add-event-form">
                    <h2><?php echo $editEvent ? 'Upravit event' : 'Přidat nový event'; ?></h2>
                    <?php if ($editEvent): ?>
                        <div class="alert alert-info" style="background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                            <p><strong>Upravujete event:</strong> <?php echo htmlspecialchars($editEvent['title']); ?></p>
                            <a href="admin.php" class="btn" style="margin-top: 10px;">Zrušit úpravu</a>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" enctype="multipart/form-data" id="eventForm">
                        <?php if ($editEvent): ?>
                            <input type="hidden" name="event_id" value="<?php echo $editEvent['id']; ?>">
                            <?php if (!empty($editEvent['media'])): ?>
                                <input type="hidden" name="existing_media" value="<?php echo htmlspecialchars($editEvent['media']); ?>">
                            <?php endif; ?>
                        <?php endif; ?>
                        <div class="form-group">
                            <label for="title">Název eventu:</label>
                            <input type="text" id="title" name="title" required 
                                   value="<?php echo $editEvent ? htmlspecialchars($editEvent['title']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Popis:</label>
                            <textarea id="description" name="description"><?php echo $editEvent ? htmlspecialchars($editEvent['description']) : ''; ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="event_date">Datum:</label>
                            <input type="date" id="event_date" name="event_date" required 
                                   value="<?php echo $editEvent ? htmlspecialchars($editEvent['event_date']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="participants">Účastníci (jeden na řádek):</label>
                            <textarea id="participants" name="participants" placeholder="Zadejte jména účastníků, každé na nový řádek...&#10;Jan Novák&#10;Marie Svobodová&#10;Petr Dvořák"><?php echo $editEvent ? htmlspecialchars($editEvent['participants_text']) : ''; ?></textarea>
                            <div class="small-text">
                                Zadejte jméno každého účastníka na samostatný řádek
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="location">Místo:</label>
                            <input type="text" id="location" name="location" 
                                   value="<?php echo $editEvent ? htmlspecialchars($editEvent['location']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="media">Fotky/videa:</label>
                            <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo MAX_FILE_SIZE; ?>">
                            <input type="file" id="media" name="media[]" multiple accept="image/*,video/*" onchange="previewFiles()">
                            <div class="small-text">
                                Maximální velikost souboru: <?php echo number_format(MAX_FILE_SIZE / 1024 / 1024, 0); ?> MB<br>
                                <strong>📸 Fotky budou automaticky komprimovány na ~400 KB pro optimalizaci</strong>
                            </div>
                            <div id="filePreview" class="file-preview"></div>
                            
                            <?php if ($editEvent && !empty($editEvent['media'])): ?>
                                <div class="existing-media">
                                    <h4>Existující média:</h4>
                                    <div class="existing-media-grid">
                                        <?php 
                                        $existingMedia = json_decode($editEvent['media'], true);
                                        if (is_array($existingMedia)):
                                            foreach ($existingMedia as $index => $file): 
                                        ?>
                                            <div class="existing-media-item">
                                                <?php if (in_array($file['type'], ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                                    <img src="<?php echo getFileUrl($file['filename']); ?>" 
                                                         alt="<?php echo htmlspecialchars($file['original_name']); ?>"
                                                         onclick="openLightbox('<?php echo getFileUrl($file['filename']); ?>', '<?php echo htmlspecialchars($file['original_name']); ?>')">
                                                <?php else: ?>
                                                    <div class="video-placeholder">
                                                        <span>🎥</span>
                                                        <small><?php echo htmlspecialchars($file['original_name']); ?></small>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="media-controls">
                                                    <label>
                                                        <input type="radio" name="thumbnail" value="existing_<?php echo $index; ?>" 
                                                               <?php echo ($editEvent['thumbnail'] === $file['filename']) ? 'checked' : ''; ?>>
                                                        Náhled
                                                    </label>
                                                    <button type="button" class="btn-remove" onclick="removeExistingMedia(<?php echo $index; ?>)">🗑️</button>
                                                </div>
                                            </div>
                                        <?php 
                                            endforeach;
                                        endif;
                                        ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <button type="submit" name="<?php echo $editEvent ? 'edit_event' : 'add_event'; ?>" class="btn btn-full">
                            <?php echo $editEvent ? 'Uložit změny' : 'Přidat event'; ?>
                        </button>
                        <?php if ($editEvent): ?>
                            <a href="admin.php" class="btn" style="margin-left: 10px;">Zrušit</a>
                        <?php endif; ?>
                    </form>
                </div>
                
                <!-- Seznam eventů -->
                <div class="events-list">
                    <h2>Stávající eventy (<?php echo count($events); ?>)</h2>
                    
                    <?php if (empty($events)): ?>
                        <p>Zatím nebyly přidány žádné eventy.</p>
                    <?php else: ?>
                        <?php foreach ($events as $event): ?>
                            <div class="event-item">
                                <h3><?php echo htmlspecialchars($event['title']); ?></h3>
                                <div class="event-meta">
                                    <strong>Datum:</strong> <?php echo formatDate($event['event_date']); ?> |
                                    <strong>Účastníci:</strong> <?php echo $event['actual_participants_count']; ?> |
                                    <strong>Místo:</strong> <?php echo htmlspecialchars($event['location']); ?>
                                    <?php if (!empty($event['media'])): ?>
                                        | <strong>Média:</strong> 
                                        <?php 
                                        $media = json_decode($event['media'], true);
                                        if (is_array($media)) {
                                            $imageCount = 0;
                                            $videoCount = 0;
                                            $totalSize = 0;
                                            foreach ($media as $file) {
                                                if (in_array($file['type'], ['jpg', 'jpeg', 'png', 'gif'])) {
                                                    $imageCount++;
                                                } else {
                                                    $videoCount++;
                                                }
                                                $totalSize += $file['size'];
                                            }
                                            echo $imageCount . ' fotek, ' . $videoCount . ' videí';
                                            echo ' (' . number_format($totalSize / 1024, 0) . ' KB)';
                                        }
                                        ?>
                                    <?php endif; ?>
                                </div>
                                <div class="event-description">
                                    <?php echo nl2br(htmlspecialchars(truncateText($event['description'], 150))); ?>
                                </div>
                                <div class="event-actions">
                                    <a href="index.php#event-<?php echo $event['id']; ?>" class="btn">Zobrazit</a>
                                    <a href="?edit=<?php echo $event['id']; ?>" class="btn btn-edit">Upravit</a>
                                    <a href="?delete=<?php echo $event['id']; ?>" class="btn btn-danger" 
                                       onclick="return confirm('Opravdu chcete smazat tento event?')">Smazat</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            </div>

    <!-- Lightbox pro admin -->
    <div id="adminLightbox" class="admin-lightbox" onclick="closeAdminLightbox()">
        <span class="admin-lightbox-close" onclick="closeAdminLightbox()">&times;</span>
        <img id="adminLightboxImg" src="" alt="">
    </div>

    <script>
        // Mobile navigation toggle
        const navToggle = document.querySelector('.nav-toggle');
        const navMenu = document.querySelector('.nav-menu');
        
        if (navToggle) {
            navToggle.addEventListener('click', () => {
                navMenu.classList.toggle('active');
                navToggle.classList.toggle('active');
            });
        }
        
        // Close mobile menu when clicking on a link
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if (navMenu) {
                    navMenu.classList.remove('active');
                    navToggle.classList.remove('active');
                }
            });
        });
        
        // Close mobile menu when clicking outside
        document.addEventListener('click', (e) => {
            if (navMenu && navToggle && 
                !navMenu.contains(e.target) && 
                !navToggle.contains(e.target) && 
                navMenu.classList.contains('active')) {
                navMenu.classList.remove('active');
                navToggle.classList.remove('active');
            }
        });
        
        // File preview function
        function previewFiles() {
            const input = document.getElementById('media');
            const preview = document.getElementById('filePreview');
            preview.innerHTML = '';
            
            if (input.files) {
                Array.from(input.files).forEach((file, index) => {
                    const div = document.createElement('div');
                    div.className = 'file-preview-item';
                    
                    if (file.type.startsWith('image/')) {
                        const img = document.createElement('img');
                        img.src = URL.createObjectURL(file);
                        div.appendChild(img);
                        
                        const br = document.createElement('br');
                        div.appendChild(br);
                        
                        const radio = document.createElement('input');
                        radio.type = 'radio';
                        radio.name = 'thumbnail';
                        radio.value = index;
                        radio.id = 'thumbnail_' + index;
                        if (index === 0) radio.checked = true;
                        
                        const label = document.createElement('label');
                        label.setAttribute('for', 'thumbnail_' + index);
                        label.appendChild(radio);
                        label.appendChild(document.createTextNode(' Náhled'));
                        
                        div.appendChild(label);
                    } else {
                        div.innerHTML = '<div style="padding: 20px; color: #cccccc;">' + file.name + '<br><small>Video</small></div>';
                    }
                    
                    preview.appendChild(div);
                });
            }
        }
        
        // Close mobile menu when clicking on a link
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if (navMenu) {
                    navMenu.classList.remove('active');
                }
            });
        });
        
        // Lightbox funkce pro admin
        function openLightbox(imageSrc, imageAlt) {
            const lightbox = document.getElementById('adminLightbox');
            const img = document.getElementById('adminLightboxImg');
            
            img.src = imageSrc;
            img.alt = imageAlt;
            lightbox.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeAdminLightbox() {
            const lightbox = document.getElementById('adminLightbox');
            lightbox.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Funkce pro odstranění existujícího média
        function removeExistingMedia(index) {
            if (confirm('Opravdu chcete odstranit toto médium?')) {
                // Přidáme skryté pole pro označení odstraněných médií
                let removeField = document.getElementById('removeMedia');
                if (!removeField) {
                    removeField = document.createElement('input');
                    removeField.type = 'hidden';
                    removeField.id = 'removeMedia';
                    removeField.name = 'removeMedia';
                    document.getElementById('eventForm').appendChild(removeField);
                }
                
                const currentRemoved = removeField.value ? removeField.value.split(',') : [];
                currentRemoved.push(index);
                removeField.value = currentRemoved.join(',');
                
                // Skryjeme element
                const mediaItem = event.target.closest('.existing-media-item');
                if (mediaItem) {
                    mediaItem.style.display = 'none';
                }
            }
        }
        
        // ESC klávesa pro zavření lightboxu
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAdminLightbox();
            }
        });
    </script>
</body>
</html>
