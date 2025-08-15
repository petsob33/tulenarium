<?php
require_once 'config.php';

// Kontrola přihlášení pro celou aplikaci
if (!isLoggedIn()) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

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
    $events = [];
    $error = 'Chyba při načítání eventů: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tulenarium - Přehled eventů</title>
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
            min-height: 100vh;
            line-height: 1.6;
            margin: 0;
        }
        
        /* Navigation Menu */
        .navbar {
            background: #2d2d2d;
            border-bottom: 3px solid #1a1a1a;
            padding: 0;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
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
        
        /* Mobile menu toggle */
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
            padding: 40px 20px 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 60px;
            color: #ffffff;
            padding: 40px 0;
        }
        
        .header h1 {
            font-size: 3rem;
            margin-bottom: 10px;
            font-weight: 300;
            letter-spacing: 2px;
            color: #ffffff;
        }
        
        .header p {
            font-size: 1.2rem;
            color: #cccccc;
        }
        
        .events-timeline {
            position: relative;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .events-timeline::before {
            content: '';
            position: absolute;
            left: 50%;
            top: 0;
            bottom: 0;
            width: 3px;
            background: #666666;
            transform: translateX(-50%);
        }
        
        .event-item {
            position: relative;
            margin-bottom: 40px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .event-item:nth-child(odd) {
            padding-right: 50%;
            text-align: right;
        }
        
        .event-item:nth-child(even) {
            padding-left: 50%;
            text-align: left;
        }
        
        .event-item::before {
            content: '';
            position: absolute;
            top: 20px;
            width: 16px;
            height: 16px;
            background: #1a1a1a;
            border: 3px solid #ffffff;
            border-radius: 50%;
            z-index: 2;
        }
        
        .event-item:nth-child(odd)::before {
            right: calc(50% - 8px);
        }
        
        .event-item:nth-child(even)::before {
            left: calc(50% - 8px);
        }
        
        .event-content {
            background: #ffffff;
            color: #333333;
            border: 2px solid #e0e0e0;
            padding: 30px;
            border-radius: 15px;
            transition: all 0.3s ease;
            position: relative;
            box-shadow: 0 4px 20px rgba(255,255,255,0.1);
        }
        
        .event-content:hover {
            background: #f8f9fa;
            border-color: #1a1a1a;
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(255,255,255,0.2);
        }
        
        .event-content::before {
            content: '';
            position: absolute;
            top: 20px;
            width: 0;
            height: 0;
            border: 15px solid transparent;
        }
        
        .event-item:nth-child(odd) .event-content::before {
            right: -30px;
            border-left-color: #ffffff;
        }
        
        .event-item:nth-child(even) .event-content::before {
            left: -30px;
            border-right-color: #ffffff;
        }
        
        .event-thumbnail {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        
        .event-title {
            font-size: 1.6rem;
            font-weight: 600;
            color: #222222;
            margin-bottom: 15px;
        }
        
        .event-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
            color: #666666;
            font-size: 0.95rem;
        }
        
        .event-meta span {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #f8f9fa;
            padding: 6px 12px;
            border-radius: 20px;
            border: 1px solid #e0e0e0;
        }
        
        .event-description {
            color: #555555;
            line-height: 1.6;
            font-size: 1rem;
        }
        
        .no-events {
            text-align: center;
            background: #ffffff;
            padding: 50px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(255,255,255,0.1);
            color: #666666;
            border: 2px solid #e0e0e0;
        }
        
        .no-events h2 {
            margin-bottom: 15px;
            color: #222222;
            font-size: 1.8rem;
            font-weight: 300;
        }
        
        /* Modal pro detail eventu */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 1000;
            backdrop-filter: blur(10px);
        }
        
        .modal-content {
            position: relative;
            background: #ffffff;
            margin: 3% auto;
            max-width: 90%;
            max-height: 90%;
            border-radius: 20px;
            overflow-y: auto;
            border: 3px solid #e0e0e0;
            box-shadow: 0 20px 60px rgba(255,255,255,0.1);
        }
        
        .modal-header {
            padding: 20px 30px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            font-size: 2rem;
            color: #333;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 2rem;
            cursor: pointer;
            color: #666;
            padding: 0;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .close-btn:hover {
            background: #f0f0f0;
            color: #333;
        }
        
        .modal-body {
            padding: 30px;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 20px;
        }
        
        /* Mobile Responsive */
        /* Event Lightbox - vyšší z-index než modal */
        .event-lightbox {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.95);
            z-index: 3000;
            cursor: pointer;
        }
        
        .event-lightbox img {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            max-width: 90%;
            max-height: 90%;
            border-radius: 10px;
            box-shadow: 0 0 50px rgba(0,0,0,0.5);
        }
        
        .event-lightbox .lightbox-close {
            position: absolute;
            top: 20px;
            right: 30px;
            color: white;
            font-size: 3rem;
            font-weight: bold;
            cursor: pointer;
            z-index: 3001;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .event-lightbox .lightbox-close:hover {
            background: rgba(0,0,0,0.8);
            transform: scale(1.1);
        }
        
        .event-lightbox .lightbox-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            color: white;
            font-size: 3rem;
            font-weight: bold;
            cursor: pointer;
            z-index: 3001;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            user-select: none;
        }
        
        .event-lightbox .lightbox-nav:hover {
            background: rgba(0,0,0,0.8);
            transform: translateY(-50%) scale(1.1);
        }
        
        .event-lightbox .lightbox-prev {
            left: 30px;
        }
        
        .event-lightbox .lightbox-next {
            right: 30px;
        }
        
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
            
            .header h1 {
                font-size: 2rem;
            }
            
            .container {
                padding: 20px 10px;
            }
            
            .events-timeline::before {
                left: 30px;
            }
            
            .event-item {
                padding-left: 60px !important;
                padding-right: 0 !important;
                text-align: left !important;
            }
            
            .event-item::before {
                left: 20px !important;
            }
            
            .event-content::before {
                left: -30px !important;
                border-right-color: #ffffff !important;
                border-left-color: transparent !important;
            }
            
            .event-meta {
                flex-direction: column;
                gap: 8px;
            }
            
            .event-lightbox .lightbox-nav {
                font-size: 2rem;
                width: 50px;
                height: 50px;
            }
            
            .event-lightbox .lightbox-close {
                font-size: 2rem;
                width: 50px;
                height: 50px;
                top: 10px;
                right: 10px;
            }
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
                    <a href="index.php" class="nav-link active">Domů</a>
                </li>
                <li class="nav-item">
                    <a href="admin.php" class="nav-link">Administrace</a>
                </li>
                <li class="nav-item">
                    <a href="stats_simple.php" class="nav-link">Statistiky</a>
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
            <h1>Tulenarium</h1>
            <p>Přehled našich společných zážitků</p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="events-timeline">
            <?php if (empty($events)): ?>
                <div class="no-events">
                    <h2>Zatím nebyly přidány žádné eventy</h2>
                    <p>Začněte přidáváním eventů v administraci.</p>
                    <p style="margin-top: 15px;">
                        <a href="admin.php" style="color: #667eea; text-decoration: none;">→ Přejít do administrace</a>
                    </p>
                </div>
            <?php else: ?>
                <?php foreach ($events as $event): ?>
                    <div class="event-item" onclick="openEventDetail(<?php echo $event['id']; ?>)" id="event-<?php echo $event['id']; ?>">
                        <div class="event-content">
                            <?php if (!empty($event['thumbnail'])): ?>
                                <img src="<?php echo getFileUrl($event['thumbnail']); ?>" 
                                     alt="<?php echo htmlspecialchars($event['title']); ?>" 
                                     class="event-thumbnail">
                            <?php endif; ?>
                            
                            <div class="event-title"><?php echo htmlspecialchars($event['title']); ?></div>
                            
                            <div class="event-meta">
                                <span>📅 <?php echo formatDate($event['event_date']); ?></span>
                                <span>👥 <?php echo $event['actual_participants_count']; ?> účastníků</span>
                                <?php if (!empty($event['location'])): ?>
                                    <span>📍 <?php echo htmlspecialchars($event['location']); ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($event['description'])): ?>
                                <div class="event-description">
                                    <?php echo nl2br(htmlspecialchars(truncateText($event['description']))); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal pro detail eventu -->
    <div id="eventModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title" id="modalTitle">Detail eventu</div>
                <button class="close-btn" onclick="closeEventDetail()">&times;</button>
            </div>
            <div class="modal-body" id="modalBody">
                <div class="loading">Načítání...</div>
            </div>
        </div>
    </div>
    
    <script>
        function openEventDetail(eventId) {
            const modal = document.getElementById('eventModal');
            const modalBody = document.getElementById('modalBody');
            const modalTitle = document.getElementById('modalTitle');
            
            modal.style.display = 'block';
            modalBody.innerHTML = '<div class="loading">Načítání...</div>';
            modalTitle.textContent = 'Detail eventu';
            
            // Načtení detailu přes AJAX
            fetch('event.php?id=' + eventId)
                .then(response => response.text())
                .then(data => {
                    modalBody.innerHTML = data;
                })
                .catch(error => {
                    modalBody.innerHTML = '<div class="error">Chyba při načítání detailu eventu.</div>';
                });
        }
        
        function closeEventDetail() {
            document.getElementById('eventModal').style.display = 'none';
        }
        
        // Zavření modalu při kliknutí mimo obsah
        window.onclick = function(event) {
            const modal = document.getElementById('eventModal');
            if (event.target === modal) {
                closeEventDetail();
            }
        }
        
        // Zavření modalu klávesou ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeEventDetail();
            }
        });

        // Globální lightbox funkce pro event obrázky
        window.currentEventImages = [];
        window.currentImageIndex = 0;
        
        window.openEventLightbox = function(index) {
            if (!window.currentEventImages || window.currentEventImages.length === 0) {
                console.error('No images available for lightbox');
                return;
            }
            
            window.currentImageIndex = index;
            
            // Vytvoření lightboxu pokud neexistuje
            let lightbox = document.getElementById('eventLightbox');
            if (!lightbox) {
                lightbox = document.createElement('div');
                lightbox.id = 'eventLightbox';
                lightbox.className = 'event-lightbox';
                lightbox.style.cssText = `
                    display: none;
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.95);
                    z-index: 3000;
                    cursor: pointer;
                `;
                lightbox.innerHTML = `
                    <span class="lightbox-close" onclick="closeEventLightbox()" style="
                        position: absolute;
                        top: 20px;
                        right: 30px;
                        color: white;
                        font-size: 3rem;
                        font-weight: bold;
                        cursor: pointer;
                        z-index: 3001;
                        width: 60px;
                        height: 60px;
                        border-radius: 50%;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        transition: all 0.3s ease;
                    ">&times;</span>
                    <span class="lightbox-nav lightbox-prev" onclick="prevEventImage()" style="
                        position: absolute;
                        top: 50%;
                        left: 30px;
                        transform: translateY(-50%);
                        color: white;
                        font-size: 3rem;
                        font-weight: bold;
                        cursor: pointer;
                        z-index: 3001;
                        width: 60px;
                        height: 60px;
                        border-radius: 50%;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        transition: all 0.3s ease;
                        user-select: none;
                    ">&#8249;</span>
                    <span class="lightbox-nav lightbox-next" onclick="nextEventImage()" style="
                        position: absolute;
                        top: 50%;
                        right: 30px;
                        transform: translateY(-50%);
                        color: white;
                        font-size: 3rem;
                        font-weight: bold;
                        cursor: pointer;
                        z-index: 3001;
                        width: 60px;
                        height: 60px;
                        border-radius: 50%;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        transition: all 0.3s ease;
                        user-select: none;
                    ">&#8250;</span>
                    <img id="eventLightboxImg" src="" alt="" style="
                        position: absolute;
                        top: 50%;
                        left: 50%;
                        transform: translate(-50%, -50%);
                        max-width: 90%;
                        max-height: 90%;
                        border-radius: 10px;
                        box-shadow: 0 0 50px rgba(0,0,0,0.5);
                    ">
                `;
                lightbox.onclick = function(e) {
                    if (e.target === lightbox) {
                        closeEventLightbox();
                    }
                };
                document.body.appendChild(lightbox);
            }
            
            const img = document.getElementById('eventLightboxImg');
            img.src = '/pokusy/tulenarium/uploads/' + window.currentEventImages[window.currentImageIndex].filename;
            img.alt = window.currentEventImages[window.currentImageIndex].original_name;
            img.onclick = function(e) { e.stopPropagation(); };
            
            lightbox.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        window.closeEventLightbox = function() {
            const lightbox = document.getElementById('eventLightbox');
            if (lightbox) {
                lightbox.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }
        
        window.nextEventImage = function() {
            if (!window.currentEventImages || window.currentEventImages.length === 0) return;
            window.currentImageIndex = (window.currentImageIndex + 1) % window.currentEventImages.length;
            const img = document.getElementById('eventLightboxImg');
            img.src = '/pokusy/tulenarium/uploads/' + window.currentEventImages[window.currentImageIndex].filename;
            img.alt = window.currentEventImages[window.currentImageIndex].original_name;
        }
        
        window.prevEventImage = function() {
            if (!window.currentEventImages || window.currentEventImages.length === 0) return;
            window.currentImageIndex = (window.currentImageIndex - 1 + window.currentEventImages.length) % window.currentEventImages.length;
            const img = document.getElementById('eventLightboxImg');
            img.src = '/pokusy/tulenarium/uploads/' + window.currentEventImages[window.currentImageIndex].filename;
            img.alt = window.currentEventImages[window.currentImageIndex].original_name;
        }
        
        // Navigace klávesnicí pro lightbox
        document.addEventListener('keydown', function(e) {
            const lightbox = document.getElementById('eventLightbox');
            if (lightbox && lightbox.style.display === 'block') {
                switch(e.key) {
                    case 'Escape':
                        closeEventLightbox();
                        break;
                    case 'ArrowLeft':
                        prevEventImage();
                        break;
                    case 'ArrowRight':
                        nextEventImage();
                        break;
                }
            }
        });
        
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
        
        // Smooth scroll k eventu při načtení stránky s hash
        if (window.location.hash) {
            const element = document.querySelector(window.location.hash);
            if (element) {
                setTimeout(() => {
                    element.scrollIntoView({ behavior: 'smooth' });
                }, 100);
            }
        }
    </script>
</body>
</html>
