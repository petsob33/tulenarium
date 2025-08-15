<?php
require_once 'config.php';

// Zjednodušená verze statistik bez kontroly přihlášení pro debug
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiky - Tulenarium</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: Arial, sans-serif; 
            background: #1a1a1a; 
            color: #ffffff; 
            padding: 20px; 
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
            gap: 20px; 
            margin: 20px 0; 
        }
        .stat-card { 
            background: #ffffff; 
            color: #333; 
            padding: 20px; 
            border-radius: 10px; 
            text-align: center; 
        }
        .stat-number { font-size: 2rem; font-weight: bold; color: #007cba; }
        .stat-label { font-size: 1rem; margin-top: 10px; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; }
        
        .section { margin: 40px 0; }
        .section h2 { color: #ffffff; margin-bottom: 20px; font-size: 1.5rem; }
        
        .chart-container { 
            background: #ffffff; 
            padding: 20px; 
            border-radius: 10px; 
            margin: 20px 0; 
        }
        
        .chart-bar { 
            display: flex; 
            align-items: center; 
            margin: 10px 0; 
            font-size: 0.9rem; 
        }
        .chart-label { 
            width: 80px; 
            font-weight: bold; 
            color: #333; 
        }
        .chart-value { 
            background: #007cba; 
            color: white; 
            padding: 5px 10px; 
            border-radius: 5px; 
            margin-left: 10px; 
            min-width: 40px; 
            text-align: center; 
        }
        .chart-fill { 
            background: #007cba; 
            height: 20px; 
            border-radius: 10px; 
            margin: 0 10px; 
            min-width: 20px; 
        }
        
        .participants-list { margin: 20px 0; }
        .participant-badge { 
            display: inline-block; 
            background: #007cba; 
            color: white; 
            padding: 5px 12px; 
            margin: 5px; 
            border-radius: 15px; 
            font-size: 0.9rem; 
        }
        
        .highlight-card { 
            background: #e3f2fd; 
            border-left: 4px solid #007cba; 
            padding: 15px; 
            margin: 10px 0; 
            border-radius: 5px; 
        }
        .highlight-title { font-weight: bold; color: #0277bd; }
        .highlight-value { font-size: 1.2rem; color: #01579b; margin: 5px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 Detailní statistiky Tulenarium</h1>
        <p style="color: #cccccc; margin-bottom: 30px; text-align: center;">
            Kompletní přehled všech eventů a účastníků • Poslední aktualizace: <?php echo date('d.m.Y H:i'); ?>
        </p>
        
        <?php
        try {
            $db = getDB();
            echo '<div class="success">✅ Databáze připojena úspěšně</div>';
            
            // Základní statistiky
            $stmt = $db->query("SELECT COUNT(*) as count FROM events");
            $totalEvents = $stmt->fetch()['count'];
            
            // Test participants tabulky
            $stmt = $db->query("SHOW TABLES LIKE 'participants'");
            $participantsExists = $stmt->rowCount() > 0;
            
            if ($participantsExists) {
                $stmt = $db->query("SELECT COUNT(*) as count FROM participants");
                $totalParticipants = $stmt->fetch()['count'];
                echo '<div class="success">✅ Tabulka participants nalezena</div>';
            } else {
                $stmt = $db->query("SELECT SUM(people_count) as count FROM events");
                $totalParticipants = $stmt->fetch()['count'] ?: 0;
                echo '<div class="error">⚠️ Tabulka participants neexistuje, používá se people_count</div>';
            }
            
            // Statistiky za aktuální rok
            $stmt = $db->query("SELECT COUNT(*) as count FROM events WHERE YEAR(event_date) = YEAR(CURDATE())");
            $eventsThisYear = $stmt->fetch()['count'];
            
            // Statistiky za aktuální měsíc
            $stmt = $db->query("SELECT COUNT(*) as count FROM events WHERE YEAR(event_date) = YEAR(CURDATE()) AND MONTH(event_date) = MONTH(CURDATE())");
            $eventsThisMonth = $stmt->fetch()['count'];
            
            // Nejnovější event
            $stmt = $db->query("SELECT title, event_date FROM events ORDER BY event_date DESC LIMIT 1");
            $latestEvent = $stmt->fetch();
            
            // Nejstarší event
            $stmt = $db->query("SELECT title, event_date FROM events ORDER BY event_date ASC LIMIT 1");
            $oldestEvent = $stmt->fetch();
            
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
                $stmt = $db->query("SELECT AVG(people_count) as avg_people FROM events WHERE people_count > 0");
                $avgPeople = round($stmt->fetch()['avg_people'] ?: 0, 1);
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
            
            // Nejčastější účastníci (pouze pokud existuje tabulka participants)
            $topParticipants = [];
            if ($participantsExists) {
                $stmt = $db->query("
                    SELECT p.name, COUNT(*) as event_count 
                    FROM participants p 
                    GROUP BY p.name 
                    ORDER BY event_count DESC 
                    LIMIT 10
                ");
                $topParticipants = $stmt->fetchAll();
            }
            
            // Počet nahraných médií
            $stmt = $db->query("SELECT media FROM events WHERE media IS NOT NULL AND media != ''");
            $mediaData = $stmt->fetchAll();
            $totalMedia = 0;
            $totalPhotos = 0;
            $totalVideos = 0;
            
            foreach ($mediaData as $event) {
                $media = json_decode($event['media'], true);
                if (is_array($media)) {
                    foreach ($media as $file) {
                        $totalMedia++;
                        if (in_array($file['type'], ['jpg', 'jpeg', 'png', 'gif'])) {
                            $totalPhotos++;
                        } elseif (in_array($file['type'], ['mp4', 'mov', 'avi', 'webm'])) {
                            $totalVideos++;
                        }
                    }
                }
            }
            
            ?>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalEvents; ?></div>
                    <div class="stat-label">Celkem eventů</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalParticipants; ?></div>
                    <div class="stat-label">Celkem účastníků</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $eventsThisYear; ?></div>
                    <div class="stat-label">Eventy tento rok</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $eventsThisMonth; ?></div>
                    <div class="stat-label">Eventy tento měsíc</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $avgPeople; ?></div>
                    <div class="stat-label">Průměr účastníků</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalMedia; ?></div>
                    <div class="stat-label">Celkem médií<br><?php echo $totalPhotos; ?> fotek, <?php echo $totalVideos; ?> videí</div>
                </div>
            </div>
            
            <!-- Zvýrazněné karty -->
            <div class="section">
                <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));">
                    <?php if ($latestEvent): ?>
                    <div class="highlight-card">
                        <div class="highlight-title">🕐 Nejnovější event</div>
                        <div class="highlight-value"><?php echo htmlspecialchars($latestEvent['title']); ?></div>
                        <div><?php echo date('d.m.Y', strtotime($latestEvent['event_date'])); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($oldestEvent): ?>
                    <div class="highlight-card">
                        <div class="highlight-title">📅 První event</div>
                        <div class="highlight-value"><?php echo htmlspecialchars($oldestEvent['title']); ?></div>
                        <div><?php echo date('d.m.Y', strtotime($oldestEvent['event_date'])); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($biggestEvent): ?>
                    <div class="highlight-card">
                        <div class="highlight-title">👥 Největší event</div>
                        <div class="highlight-value"><?php echo htmlspecialchars($biggestEvent['title']); ?></div>
                        <div><?php echo $biggestEvent['participant_count']; ?> účastníků • <?php echo date('d.m.Y', strtotime($biggestEvent['event_date'])); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Měsíční statistiky -->
            <?php if (!empty($monthlyStats)): ?>
            <div class="section">
                <h2>📊 Eventy podle měsíců (<?php echo date('Y'); ?>)</h2>
                <div class="chart-container">
                    <?php 
                    $monthNames = [
                        1 => 'Leden', 2 => 'Únor', 3 => 'Březen', 4 => 'Duben',
                        5 => 'Květen', 6 => 'Červen', 7 => 'Červenec', 8 => 'Srpen',
                        9 => 'Září', 10 => 'Říjen', 11 => 'Listopad', 12 => 'Prosinec'
                    ];
                    $maxCount = max(array_column($monthlyStats, 'count'));
                    ?>
                    <?php foreach ($monthlyStats as $month): ?>
                    <div class="chart-bar">
                        <div class="chart-label"><?php echo $monthNames[$month['month']]; ?></div>
                        <div class="chart-fill" style="width: <?php echo ($month['count'] / max($maxCount, 1)) * 200; ?>px;"></div>
                        <div class="chart-value"><?php echo $month['count']; ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Nejaktivnější účastníci -->
            <?php if (!empty($topParticipants)): ?>
            <div class="section">
                <h2>🏆 Nejaktivnější účastníci</h2>
                <div class="chart-container">
                    <?php $maxEvents = max(array_column($topParticipants, 'event_count')); ?>
                    <?php foreach ($topParticipants as $participant): ?>
                    <div class="chart-bar">
                        <div class="chart-label" style="width: 150px;"><?php echo htmlspecialchars($participant['name']); ?></div>
                        <div class="chart-fill" style="width: <?php echo ($participant['event_count'] / max($maxEvents, 1)) * 200; ?>px;"></div>
                        <div class="chart-value"><?php echo $participant['event_count']; ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php
            
        } catch (Exception $e) {
            echo '<div class="error">❌ Chyba: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        ?>
        
        <!-- Navigace -->
        <div class="section">
            <div style="text-align: center; margin-top: 40px;">
                <a href="index.php" style="color: #ffffff; text-decoration: none; padding: 12px 25px; background: #007cba; border-radius: 8px; margin: 0 10px; display: inline-block;">
                    🏠 Hlavní stránka
                </a>
                <a href="admin.php" style="color: #ffffff; text-decoration: none; padding: 12px 25px; background: #28a745; border-radius: 8px; margin: 0 10px; display: inline-block;">
                    ⚙️ Administrace
                </a>
                <a href="stats.php" style="color: #ffffff; text-decoration: none; padding: 12px 25px; background: #6c757d; border-radius: 8px; margin: 0 10px; display: inline-block;">
                    📈 Pokročilé statistiky
                </a>
            </div>
        </div>
    </div>
</body>
</html>
