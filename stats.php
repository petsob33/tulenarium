<?php
require_once 'config.php';

// Kontrola přihlášení pro celou aplikaci
if (!isLoggedIn()) {
    // Pokud je to AJAX request, vracíme JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Nejste přihlášeni', 'redirect' => 'login.php']);
        exit;
    }
    
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Debug mode - zobrazení chyb
if (isset($_GET['debug'])) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

try {
    $db = getDB();
    
    // Kontrola existence tabulky participants s error handlingem
    $participantsExists = false;
    try {
        $stmt = $db->query("SHOW TABLES LIKE 'participants'");
        $participantsExists = $stmt->rowCount() > 0;
    } catch (Exception $e) {
        // Pokud selže, pokračujeme bez participants tabulky
        $participantsExists = false;
    }
    
    // Základní statistiky s error handlingem
    $totalEvents = 0;
    $totalPeople = 0;
    $eventsThisYear = 0;
    $eventsThisMonth = 0;
    $latestEvent = null;
    $oldestEvent = null;
    $topParticipants = [];
    $biggestEvent = null;
    $avgPeople = 0;
    $monthlyStats = [];
    $totalMedia = 0;
    
    try {
        $stmt = $db->query("SELECT COUNT(*) as total_events FROM events");
        $totalEvents = $stmt->fetch()['total_events'];
    } catch (Exception $e) {
        $totalEvents = 0;
    }
    
    try {
        if ($participantsExists) {
            $stmt = $db->query("SELECT COUNT(*) as total_people FROM participants");
            $totalPeople = $stmt->fetch()['total_people'] ?: 0;
        } else {
            // Fallback na starý způsob počítání
            $stmt = $db->query("SELECT SUM(people_count) as total_people FROM events");
            $totalPeople = $stmt->fetch()['total_people'] ?: 0;
        }
    } catch (Exception $e) {
        $totalPeople = 0;
    }
    
    $stmt = $db->query("SELECT COUNT(*) as events_this_year FROM events WHERE YEAR(event_date) = YEAR(CURDATE())");
    $eventsThisYear = $stmt->fetch()['events_this_year'];
    
    $stmt = $db->query("SELECT COUNT(*) as events_this_month FROM events WHERE YEAR(event_date) = YEAR(CURDATE()) AND MONTH(event_date) = MONTH(CURDATE())");
    $eventsThisMonth = $stmt->fetch()['events_this_month'];
    
    // Nejnovější event
    $stmt = $db->query("SELECT title, event_date FROM events ORDER BY event_date DESC LIMIT 1");
    $latestEvent = $stmt->fetch();
    
    // Nejstarší event
    $stmt = $db->query("SELECT title, event_date FROM events ORDER BY event_date ASC LIMIT 1");
    $oldestEvent = $stmt->fetch();
    
    // Nejčastější účastníci (pouze pokud existuje tabulka participants)
    $topParticipants = [];
    if ($participantsExists) {
        $stmt = $db->query("
            SELECT p.name, COUNT(*) as event_count 
            FROM participants p 
            GROUP BY p.name 
            ORDER BY event_count DESC 
            LIMIT 5
        ");
        $topParticipants = $stmt->fetchAll();
    }
    
    // Největší event podle počtu lidí
    if ($participantsExists) {
        $stmt = $db->query("
            SELECT e.title, e.event_date, COUNT(p.id) as participant_count 
            FROM events e 
            LEFT JOIN participants p ON e.id = p.event_id 
            GROUP BY e.id 
            ORDER BY participant_count DESC 
            LIMIT 1
        ");
        $biggestEvent = $stmt->fetch();
    } else {
        // Fallback na starý způsob
        $stmt = $db->query("SELECT title, event_date, people_count as participant_count FROM events ORDER BY people_count DESC LIMIT 1");
        $biggestEvent = $stmt->fetch();
    }
    
    // Eventy podle měsíců v tomto roce
    $stmt = $db->query("
        SELECT MONTH(event_date) as month, COUNT(*) as count 
        FROM events 
        WHERE YEAR(event_date) = YEAR(CURDATE()) 
        GROUP BY MONTH(event_date) 
        ORDER BY MONTH(event_date)
    ");
    $monthlyStats = $stmt->fetchAll();
    
    // Průměrný počet účastníků
    if ($participantsExists) {
        $stmt = $db->query("
            SELECT AVG(participant_count) as avg_people 
            FROM (
                SELECT COUNT(p.id) as participant_count 
                FROM events e 
                LEFT JOIN participants p ON e.id = p.event_id 
                GROUP BY e.id
            ) as event_counts 
            WHERE participant_count > 0
        ");
        $avgPeople = round($stmt->fetch()['avg_people'] ?: 0, 1);
    } else {
        // Fallback na starý způsob
        $stmt = $db->query("SELECT AVG(people_count) as avg_people FROM events WHERE people_count > 0");
        $avgPeople = round($stmt->fetch()['avg_people'] ?: 0, 1);
    }
    
    // Počet nahraných médií
    $stmt = $db->query("SELECT media FROM events WHERE media IS NOT NULL AND media != ''");
    $mediaData = $stmt->fetchAll();
    $totalMedia = 0;
    $totalPhotos = 0;
    $totalVideos = 0;
    
    foreach ($mediaData as $row) {
        $media = json_decode($row['media'], true);
        if (is_array($media)) {
            $totalMedia += count($media);
            foreach ($media as $file) {
                if (in_array($file['type'], ['jpg', 'jpeg', 'png', 'gif'])) {
                    $totalPhotos++;
                } else {
                    $totalVideos++;
                }
            }
        }
    }
    
    // Nové pokročilé statistiky
    
    // Eventy podle dní v týdnu
    $stmt = $db->query("
        SELECT DAYOFWEEK(event_date) as day_of_week, COUNT(*) as count 
        FROM events 
        GROUP BY DAYOFWEEK(event_date) 
        ORDER BY DAYOFWEEK(event_date)
    ");
    $weeklyStats = $stmt->fetchAll();
    
    // Nejpopulárnější lokace
    $stmt = $db->query("
        SELECT location, COUNT(*) as count 
        FROM events 
        WHERE location IS NOT NULL AND location != ''
        GROUP BY location 
        ORDER BY count DESC 
        LIMIT 5
    ");
    $popularLocations = $stmt->fetchAll();
    
    // Eventy podle čtvrtletí
    $stmt = $db->query("
        SELECT 
            QUARTER(event_date) as quarter,
            YEAR(event_date) as year,
            COUNT(*) as count 
        FROM events 
        GROUP BY YEAR(event_date), QUARTER(event_date) 
        ORDER BY YEAR(event_date) DESC, QUARTER(event_date) DESC
        LIMIT 8
    ");
    $quarterlyStats = $stmt->fetchAll();
    
    // Růstový trend - eventy za posledních 12 měsíců
    $stmt = $db->query("
        SELECT 
            YEAR(event_date) as year,
            MONTH(event_date) as month,
            COUNT(*) as count 
        FROM events 
        WHERE event_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY YEAR(event_date), MONTH(event_date) 
        ORDER BY YEAR(event_date), MONTH(event_date)
    ");
    $trendStats = $stmt->fetchAll();
    
    // Statistiky velikosti eventů
    if ($participantsExists) {
        $stmt = $db->query("
            SELECT 
                CASE 
                    WHEN COUNT(p.id) = 0 THEN 'Bez účastníků'
                    WHEN COUNT(p.id) <= 5 THEN 'Malé (1-5)'
                    WHEN COUNT(p.id) <= 15 THEN 'Střední (6-15)'
                    WHEN COUNT(p.id) <= 30 THEN 'Velké (16-30)'
                    ELSE 'Extra velké (30+)'
                END as size_category,
                COUNT(*) as event_count
            FROM events e 
            LEFT JOIN participants p ON e.id = p.event_id 
            GROUP BY e.id
        ");
        $temp = $stmt->fetchAll();
        $sizeStats = [];
        foreach ($temp as $row) {
            $category = $row['size_category'];
            if (!isset($sizeStats[$category])) {
                $sizeStats[$category] = 0;
            }
            $sizeStats[$category]++;
        }
    } else {
        $stmt = $db->query("
            SELECT 
                CASE 
                    WHEN people_count = 0 THEN 'Bez účastníků'
                    WHEN people_count <= 5 THEN 'Malé (1-5)'
                    WHEN people_count <= 15 THEN 'Střední (6-15)'
                    WHEN people_count <= 30 THEN 'Velké (16-30)'
                    ELSE 'Extra velké (30+)'
                END as size_category,
                COUNT(*) as event_count
            FROM events 
            GROUP BY size_category
        ");
        $temp = $stmt->fetchAll();
        $sizeStats = [];
        foreach ($temp as $row) {
            $sizeStats[$row['size_category']] = $row['event_count'];
        }
    }
    
    // Statistiky aktivních účastníků podle období
    $activeParticipants = [];
    if ($participantsExists) {
        // Účastníci aktivní v posledním roce
        $stmt = $db->query("
            SELECT COUNT(DISTINCT p.name) as active_count 
            FROM participants p 
            JOIN events e ON p.event_id = e.id 
            WHERE e.event_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
        ");
        $activeParticipants['year'] = $stmt->fetch()['active_count'] ?: 0;
        
        // Účastníci aktivní v posledním měsíci
        $stmt = $db->query("
            SELECT COUNT(DISTINCT p.name) as active_count 
            FROM participants p 
            JOIN events e ON p.event_id = e.id 
            WHERE e.event_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
        ");
        $activeParticipants['month'] = $stmt->fetch()['active_count'] ?: 0;
    }
    
    // Měsíční názvy
    $monthNames = [
        1 => 'Leden', 2 => 'Únor', 3 => 'Březen', 4 => 'Duben',
        5 => 'Květen', 6 => 'Červen', 7 => 'Červenec', 8 => 'Srpen',
        9 => 'Září', 10 => 'Říjen', 11 => 'Listopad', 12 => 'Prosinec'
    ];
    
} catch (Exception $e) {
    $error = 'Chyba při načítání statistik: ' . $e->getMessage();
    // V případě chyby, nastavíme výchozí hodnoty
    $totalEvents = 0;
    $totalPeople = 0;
    $eventsThisYear = 0;
    $eventsThisMonth = 0;
    $latestEvent = null;
    $oldestEvent = null;
    $topParticipants = [];
    $biggestEvent = null;
    $avgPeople = 0;
    $monthlyStats = [];
    $totalMedia = 0;
    $weeklyStats = [];
    $popularLocations = [];
    $quarterlyStats = [];
    $trendStats = [];
    $sizeStats = [];
    $activeParticipants = [];
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiky - Tulenarium</title>
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
        
        .nav-toggle {
            display: none;
            flex-direction: column;
            cursor: pointer;
            padding: 10px;
        }
        
        .nav-toggle span {
            width: 25px;
            height: 3px;
            background: #ffffff;
            margin: 3px 0;
            transition: 0.3s;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 50px;
        }
        
        .header h1 {
            font-size: 2.5rem;
            font-weight: 300;
            margin-bottom: 10px;
            letter-spacing: 1px;
        }
        
        .header p {
            color: #cccccc;
            font-size: 1.1rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 50px;
        }
        
        .stat-card {
            background: #ffffff;
            border: 2px solid #e0e0e0;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(255,255,255,0.1);
        }
        
        .stat-card:hover {
            border-color: #1a1a1a;
            transform: translateY(-5px);
            background: #f8f9fa;
            box-shadow: 0 8px 30px rgba(255,255,255,0.2);
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: bold;
            color: #1a1a1a;
            margin-bottom: 10px;
            display: block;
        }
        
        .stat-label {
            color: #666666;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .charts-section {
            margin-bottom: 50px;
        }
        
        .section-title {
            font-size: 1.8rem;
            font-weight: 300;
            margin-bottom: 30px;
            text-align: center;
            color: #ffffff;
        }
        
        .chart-container {
            background: #ffffff;
            border: 2px solid #e0e0e0;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(255,255,255,0.1);
        }
        
        .monthly-chart {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 10px;
            margin-top: 20px;
        }
        
        .month-bar {
            text-align: center;
        }
        
        .bar {
            background: #e0e0e0;
            margin-bottom: 10px;
            border-radius: 8px;
            min-height: 20px;
            position: relative;
            transition: all 0.3s ease;
        }
        
        .bar.has-events {
            background: #2d2d2d;
        }
        
        .bar:hover {
            transform: scaleY(1.1);
        }
        
        .month-label {
            font-size: 0.8rem;
            color: #666666;
            transform: rotate(-45deg);
            margin-top: 5px;
        }
        
        .highlights-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 50px;
        }
        
        .highlight-card {
            background: #ffffff;
            border: 2px solid #e0e0e0;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(255,255,255,0.1);
            transition: all 0.3s ease;
        }
        
        .highlight-card:hover {
            border-color: #1a1a1a;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(255,255,255,0.2);
        }
        
        .highlight-title {
            font-size: 1.2rem;
            color: #1a1a1a;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .highlight-content {
            color: #666666;
        }
        
        .highlight-value {
            color: #1a1a1a;
            font-weight: bold;
            font-size: 1.1rem;
        }
        
        .error {
            background: #2d1b1f;
            color: #ff6b6b;
            padding: 20px;
            border-radius: 10px;
            margin: 30px 0;
            border: 1px solid #ff4444;
            text-align: center;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .nav-menu {
                position: fixed;
                left: -100%;
                top: 70px;
                flex-direction: column;
                background-color: #2d2d2d;
                width: 100%;
                text-align: center;
                transition: 0.3s;
                border-top: 2px solid #333333;
            }
            
            .nav-menu.active {
                left: 0;
            }
            
            .nav-item {
                margin: 0;
            }
            
            .nav-link {
                padding: 15px;
                border-bottom: 1px solid #333333;
            }
            
            .nav-toggle {
                display: flex;
            }
            
            .container {
                padding: 20px 10px;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
            }
            
            .stat-card {
                padding: 20px;
            }
            
            .stat-number {
                font-size: 2.5rem;
            }
            
            .monthly-chart {
                grid-template-columns: repeat(6, 1fr);
                gap: 5px;
            }
            
            .month-label {
                font-size: 0.7rem;
            }
            
            .highlights-section {
                grid-template-columns: 1fr;
                gap: 15px;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
            
            .monthly-chart {
                grid-template-columns: repeat(4, 1fr);
            }
        }
        
        /* Nové styly pro pokročilé statistiky */
        .progress-bar {
            background: #e0e0e0;
            border-radius: 12px;
            overflow: hidden;
            position: relative;
            transition: all 0.3s ease;
        }
        
        .progress-bar:hover {
            transform: scaleY(1.1);
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #007cba, #005a8b);
            border-radius: 12px;
            transition: width 0.8s ease-in-out;
            animation: fillAnimation 1.2s ease-in-out;
        }
        
        @keyframes fillAnimation {
            from { width: 0; }
        }
        
        .animated-counter {
            animation: countUp 1.5s ease-out;
        }
        
        @keyframes countUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .chart-tooltip {
            position: absolute;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.9rem;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 1000;
        }
        
        .interactive-chart-bar:hover + .chart-tooltip {
            opacity: 1;
        }
        
        .stat-card {
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: left 0.5s ease;
        }
        
        .stat-card:hover::before {
            left: 100%;
        }
        
        .trend-indicator {
            display: inline-block;
            margin-left: 8px;
            font-size: 0.9rem;
        }
        
        .trend-up {
            color: #28a745;
        }
        
        .trend-down {
            color: #dc3545;
        }
        
        .trend-stable {
            color: #6c757d;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="nav-logo">Tulenarium</a>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="index.php" class="nav-link">Domů</a>
                </li>
                <li class="nav-item">
                    <a href="admin.php" class="nav-link">Administrace</a>
                </li>
                <li class="nav-item">
                                            <a href="stats.php" class="nav-link active">Statistiky</a>
                    </li>
                    <li class="nav-item">
                        <a href="admin.php?logout=1" class="nav-link" onclick="return confirm('Opravdu se chcete odhlásit?')">Odhlásit se</a>
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
        <div class="header">
            <h1>Statistiky eventů</h1>
            <p>Přehled všech dat a statistik</p>
            <p class="last-updated" style="color: #999; font-size: 0.9rem; margin-top: 10px;">
                Poslední aktualizace: <?php echo date('d.m.Y H:i:s'); ?>
            </p>
        </div>

        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php else: ?>
            
            <!-- Základní statistiky -->
            <div class="stats-grid">
                <div class="stat-card">
                    <span class="stat-number"><?php echo $totalEvents; ?></span>
                    <div class="stat-label">Celkem eventů</div>
                </div>
                
                <div class="stat-card">
                    <span class="stat-number"><?php echo $totalPeople; ?></span>
                    <div class="stat-label">Celkem účastníků</div>
                </div>
                
                <div class="stat-card">
                    <span class="stat-number"><?php echo $eventsThisYear; ?></span>
                    <div class="stat-label">Eventů letos</div>
                </div>
                
                <div class="stat-card">
                    <span class="stat-number"><?php echo $eventsThisMonth; ?></span>
                    <div class="stat-label">Eventů tento měsíc</div>
                </div>
                
                <div class="stat-card">
                    <span class="stat-number"><?php echo $avgPeople; ?></span>
                    <div class="stat-label">Průměr účastníků</div>
                </div>
                
                <div class="stat-card">
                    <span class="stat-number"><?php echo $totalMedia; ?></span>
                    <div class="stat-label">Celkem médií</div>
                </div>
                
                <div class="stat-card">
                    <span class="stat-number"><?php echo $totalPhotos; ?></span>
                    <div class="stat-label">Fotek</div>
                </div>
                
                <div class="stat-card">
                    <span class="stat-number"><?php echo $totalVideos; ?></span>
                    <div class="stat-label">Videí</div>
                </div>
            </div>

            <!-- Graf eventů podle měsíců -->
            <div class="charts-section">
                <h2 class="section-title">Eventy podle měsíců (<?php echo date('Y'); ?>)</h2>
                <div class="chart-container">
                    <div class="monthly-chart">
                        <?php for ($month = 1; $month <= 12; $month++): ?>
                            <?php
                            $count = 0;
                            foreach ($monthlyStats as $stat) {
                                if ($stat['month'] == $month) {
                                    $count = $stat['count'];
                                    break;
                                }
                            }
                            $height = $count > 0 ? max(20, $count * 20) : 20;
                            ?>
                            <div class="month-bar">
                                <div class="bar <?php echo $count > 0 ? 'has-events' : ''; ?>" 
                                     style="height: <?php echo $height; ?>px;" 
                                     title="<?php echo $monthNames[$month] . ': ' . $count . ' eventů'; ?>">
                                </div>
                                <div class="month-label"><?php echo substr($monthNames[$month], 0, 3); ?></div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

            <!-- Zajímavé statistiky -->
            <div class="highlights-section">
                <?php if ($latestEvent): ?>
                    <div class="highlight-card">
                        <div class="highlight-title">🕐 Nejnovější event</div>
                        <div class="highlight-content">
                            <div class="highlight-value"><?php echo htmlspecialchars($latestEvent['title']); ?></div>
                            <div><?php echo formatDate($latestEvent['event_date']); ?></div>
                            <?php
                            if ($participantsExists) {
                                $stmt = $db->prepare("SELECT COUNT(*) as count FROM participants WHERE event_id = (SELECT id FROM events WHERE title = ? AND event_date = ?)");
                                $stmt->execute([$latestEvent['title'], $latestEvent['event_date']]);
                                $latestParticipants = $stmt->fetch()['count'];
                            } else {
                                $stmt = $db->prepare("SELECT people_count FROM events WHERE title = ? AND event_date = ?");
                                $stmt->execute([$latestEvent['title'], $latestEvent['event_date']]);
                                $latestParticipants = $stmt->fetch()['people_count'] ?: 0;
                            }
                            ?>
                            <div><?php echo $latestParticipants; ?> účastníků</div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($oldestEvent): ?>
                    <div class="highlight-card">
                        <div class="highlight-title">📅 První event</div>
                        <div class="highlight-content">
                            <div class="highlight-value"><?php echo htmlspecialchars($oldestEvent['title']); ?></div>
                            <div><?php echo formatDate($oldestEvent['event_date']); ?></div>
                            <?php
                            if ($participantsExists) {
                                $stmt = $db->prepare("SELECT COUNT(*) as count FROM participants WHERE event_id = (SELECT id FROM events WHERE title = ? AND event_date = ?)");
                                $stmt->execute([$oldestEvent['title'], $oldestEvent['event_date']]);
                                $oldestParticipants = $stmt->fetch()['count'];
                            } else {
                                $stmt = $db->prepare("SELECT people_count FROM events WHERE title = ? AND event_date = ?");
                                $stmt->execute([$oldestEvent['title'], $oldestEvent['event_date']]);
                                $oldestParticipants = $stmt->fetch()['people_count'] ?: 0;
                            }
                            ?>
                            <div><?php echo $oldestParticipants; ?> účastníků</div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($biggestEvent): ?>
                    <div class="highlight-card">
                        <div class="highlight-title">👥 Největší event</div>
                        <div class="highlight-content">
                            <div class="highlight-value"><?php echo htmlspecialchars($biggestEvent['title']); ?></div>
                            <div><?php echo formatDate($biggestEvent['event_date']); ?></div>
                            <div class="highlight-value"><?php echo $biggestEvent['participant_count']; ?> účastníků</div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($topParticipants)): ?>
                    <div class="highlight-card">
                        <div class="highlight-title">🏆 Nejaktivnější účastníci</div>
                        <div class="highlight-content">
                            <?php foreach ($topParticipants as $index => $participant): ?>
                                <div style="margin-bottom: 8px;">
                                    <span class="highlight-value"><?php echo htmlspecialchars($participant['name']); ?></span>
                                    <span> - <?php echo $participant['event_count']; ?> eventů</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Nové pokročilé statistiky -->
            
            <!-- Statistiky podle velikosti eventů -->
            <?php if (!empty($sizeStats)): ?>
            <div class="charts-section">
                <h2 class="section-title">📊 Eventy podle velikosti</h2>
                <div class="chart-container">
                    <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
                        <?php foreach ($sizeStats as $category => $count): ?>
                        <div class="stat-card" style="padding: 20px;">
                            <span class="stat-number" style="font-size: 2rem;"><?php echo $count; ?></span>
                            <div class="stat-label" style="font-size: 0.9rem;"><?php echo htmlspecialchars($category); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Statistiky podle dní v týdnu -->
            <?php if (!empty($weeklyStats)): ?>
            <div class="charts-section">
                <h2 class="section-title">📅 Eventy podle dní v týdnu</h2>
                <div class="chart-container">
                    <div class="monthly-chart" style="grid-template-columns: repeat(7, 1fr);">
                        <?php 
                        $dayNames = ['', 'Neděle', 'Pondělí', 'Úterý', 'Středa', 'Čtvrtek', 'Pátek', 'Sobota'];
                        $weekData = array_fill(1, 7, 0);
                        foreach ($weeklyStats as $day) {
                            $weekData[$day['day_of_week']] = $day['count'];
                        }
                        $maxWeekCount = max($weekData);
                        ?>
                        <?php for ($day = 1; $day <= 7; $day++): ?>
                            <?php $count = $weekData[$day]; ?>
                            <?php $height = $count > 0 ? max(20, ($count / max($maxWeekCount, 1)) * 100) : 20; ?>
                            <div class="month-bar">
                                <div class="bar <?php echo $count > 0 ? 'has-events' : ''; ?>" 
                                     style="height: <?php echo $height; ?>px;" 
                                     title="<?php echo $dayNames[$day] . ': ' . $count . ' eventů'; ?>">
                                </div>
                                <div class="month-label"><?php echo substr($dayNames[$day], 0, 3); ?></div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Nejpopulárnější lokace -->
            <?php if (!empty($popularLocations)): ?>
            <div class="charts-section">
                <h2 class="section-title">📍 Nejpopulárnější lokace</h2>
                <div class="chart-container">
                    <?php $maxLocationCount = max(array_column($popularLocations, 'count')); ?>
                    <?php foreach ($popularLocations as $location): ?>
                    <div style="display: flex; align-items: center; margin: 15px 0; font-size: 1rem;">
                        <div style="width: 200px; font-weight: bold; color: #333; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <?php echo htmlspecialchars($location['location']); ?>
                        </div>
                        <div style="background: #2d2d2d; height: 25px; border-radius: 12px; margin: 0 15px; flex-grow: 1; position: relative;">
                            <div style="background: linear-gradient(90deg, #007cba, #005a8b); height: 100%; border-radius: 12px; width: <?php echo ($location['count'] / max($maxLocationCount, 1)) * 100; ?>%; transition: width 0.3s ease;"></div>
                        </div>
                        <div style="color: #333; font-weight: bold; min-width: 40px; text-align: center;">
                            <?php echo $location['count']; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Trend za posledních 12 měsíců -->
            <?php if (!empty($trendStats)): ?>
            <div class="charts-section">
                <h2 class="section-title">📈 Trend za posledních 12 měsíců</h2>
                <div class="chart-container">
                    <div class="monthly-chart" style="grid-template-columns: repeat(<?php echo min(count($trendStats), 12); ?>, 1fr);">
                        <?php 
                        $maxTrendCount = max(array_column($trendStats, 'count'));
                        foreach ($trendStats as $trend): 
                        ?>
                            <?php $height = $trend['count'] > 0 ? max(20, ($trend['count'] / max($maxTrendCount, 1)) * 100) : 20; ?>
                            <div class="month-bar">
                                <div class="bar <?php echo $trend['count'] > 0 ? 'has-events' : ''; ?>" 
                                     style="height: <?php echo $height; ?>px;" 
                                     title="<?php echo $monthNames[$trend['month']] . ' ' . $trend['year'] . ': ' . $trend['count'] . ' eventů'; ?>">
                                </div>
                                <div class="month-label" style="font-size: 0.7rem;">
                                    <?php echo substr($monthNames[$trend['month']], 0, 3) . '<br>' . substr($trend['year'], 2); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Statistiky podle čtvrtletí -->
            <?php if (!empty($quarterlyStats)): ?>
            <div class="charts-section">
                <h2 class="section-title">📊 Eventy podle čtvrtletí</h2>
                <div class="chart-container">
                    <?php $maxQuarterCount = max(array_column($quarterlyStats, 'count')); ?>
                    <?php foreach ($quarterlyStats as $quarter): ?>
                    <div style="display: flex; align-items: center; margin: 12px 0; font-size: 1rem;">
                        <div style="width: 100px; font-weight: bold; color: #333;">
                            Q<?php echo $quarter['quarter']; ?> <?php echo $quarter['year']; ?>
                        </div>
                        <div style="background: #e0e0e0; height: 20px; border-radius: 10px; margin: 0 15px; flex-grow: 1; position: relative;">
                            <div style="background: linear-gradient(90deg, #2d2d2d, #1a1a1a); height: 100%; border-radius: 10px; width: <?php echo ($quarter['count'] / max($maxQuarterCount, 1)) * 100; ?>%; transition: width 0.3s ease;"></div>
                        </div>
                        <div style="color: #333; font-weight: bold; min-width: 30px; text-align: center;">
                            <?php echo $quarter['count']; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Aktivní účastníci podle období -->
            <?php if (!empty($activeParticipants)): ?>
            <div class="highlights-section">
                <div class="highlight-card">
                    <div class="highlight-title">👥 Aktivní účastníci</div>
                    <div class="highlight-content">
                        <?php if (isset($activeParticipants['year'])): ?>
                        <div style="margin-bottom: 10px;">
                            <span class="highlight-value"><?php echo $activeParticipants['year']; ?></span> 
                            <span>aktivních za poslední rok</span>
                        </div>
                        <?php endif; ?>
                        <?php if (isset($activeParticipants['month'])): ?>
                        <div>
                            <span class="highlight-value"><?php echo $activeParticipants['month']; ?></span> 
                            <span>aktivních za poslední měsíc</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>

    <script>
        // Mobile navigation toggle
        const navToggle = document.querySelector('.nav-toggle');
        const navMenu = document.querySelector('.nav-menu');
        
        if (navToggle) {
            navToggle.addEventListener('click', () => {
                navMenu.classList.toggle('active');
            });
        }
        
        // Close mobile menu when clicking on a link
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if (navMenu) {
                    navMenu.classList.remove('active');
                }
            });
        });
        
        // Animace počítadel při načtení stránky
        document.addEventListener('DOMContentLoaded', function() {
            const counters = document.querySelectorAll('.stat-number');
            const observerOptions = {
                threshold: 0.5,
                rootMargin: '0px 0px -50px 0px'
            };
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        animateCounter(entry.target);
                        entry.target.classList.add('animated-counter');
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);
            
            counters.forEach(counter => {
                observer.observe(counter);
            });
        });
        
        function animateCounter(element) {
            const target = parseInt(element.textContent.replace(/[^\d]/g, ''));
            if (isNaN(target)) return;
            
            let current = 0;
            const increment = target / 50;
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                element.textContent = Math.floor(current);
            }, 30);
        }
        
        // Smooth scroll pro sekce
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
        
        // Tooltip pro grafy
        function createTooltip(element, text) {
            const tooltip = document.createElement('div');
            tooltip.className = 'chart-tooltip';
            tooltip.textContent = text;
            element.appendChild(tooltip);
            
            element.addEventListener('mouseenter', function(e) {
                tooltip.style.opacity = '1';
                tooltip.style.left = e.offsetX + 'px';
                tooltip.style.top = (e.offsetY - 40) + 'px';
            });
            
            element.addEventListener('mouseleave', function() {
                tooltip.style.opacity = '0';
            });
        }
        
        // Přidání tooltipů k progress barům
        document.querySelectorAll('.progress-fill').forEach(bar => {
            const parentText = bar.closest('.chart-bar, div')?.textContent || '';
            if (parentText.trim()) {
                createTooltip(bar, parentText.trim());
            }
        });
        
        // Lazy loading pro grafy - animace při vstupu do viewport
        const chartElements = document.querySelectorAll('.chart-container, .stat-card');
        const chartObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                    chartObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.2 });
        
        chartElements.forEach(element => {
            element.style.opacity = '0';
            element.style.transform = 'translateY(30px)';
            element.style.transition = 'all 0.6s ease-out';
            chartObserver.observe(element);
        });
        
        // Aktualizace času na stránce
        function updateLastUpdated() {
            const now = new Date();
            const timeString = now.toLocaleString('cs-CZ');
            const updateElement = document.querySelector('.last-updated');
            if (updateElement) {
                updateElement.textContent = 'Poslední aktualizace: ' + timeString;
            }
        }
        
        // Aktualizace každých 30 sekund
        setInterval(updateLastUpdated, 30000);
    </script>
</body>
</html>
