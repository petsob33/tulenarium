<?php
require_once 'config.php';

// Kontrola přihlášení (pro AJAX volání)
if (!isLoggedIn()) {
    echo '<div class="error">Nejste přihlášeni. <a href="login.php">Přihlásit se</a></div>';
    exit;
}

// Kontrola, zda je poskytnut ID eventu
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<div class="error">Neplatné ID eventu.</div>';
    exit;
}

$eventId = (int)$_GET['id'];

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();
    
    if (!$event) {
        echo '<div class="error">Event nebyl nalezen.</div>';
        exit;
    }
    
    // Načtení účastníků
    $stmt = $db->prepare("SELECT name FROM participants WHERE event_id = ? ORDER BY name");
    $stmt->execute([$eventId]);
    $participants = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Dekódování media souborů
    $media = [];
    if (!empty($event['media'])) {
        $media = json_decode($event['media'], true) ?: [];
    }
    
} catch (PDOException $e) {
    echo '<div class="error">Chyba při načítání eventu: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}
?>

<style>
    .event-detail {
        max-width: 100%;
    }
    
    .event-detail-header {
        margin-bottom: 25px;
        padding-bottom: 20px;
        border-bottom: 2px solid #eee;
    }
    
    .event-detail-title {
        font-size: 2.2rem;
        color: #333;
        margin-bottom: 15px;
        line-height: 1.2;
    }
    
    .event-detail-meta {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        background: #f8f9fa;
        padding: 20px;
        border-radius: 10px;
    }
    
    .meta-item {
        display: flex;
        align-items: center;
        gap: 10px;
        color: #555;
    }
    
    .meta-icon {
        font-size: 1.2rem;
        min-width: 25px;
    }
    
    .event-detail-description {
        margin: 25px 0;
        color: #333;
        line-height: 1.8;
        font-size: 1.1rem;
    }
    
    .media-gallery {
        margin-top: 30px;
    }
    
    .media-gallery h3 {
        margin-bottom: 20px;
        color: #333;
        font-size: 1.5rem;
    }
    
    .media-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .media-item {
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .media-item:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.2);
    }
    
    .media-item img {
        width: 100%;
        height: 200px;
        object-fit: cover;
        transition: transform 0.3s ease;
    }
    
    .media-item:hover img {
        transform: scale(1.05);
    }
    
    .media-item video {
        width: 100%;
        height: 200px;
        object-fit: cover;
    }
    
    .video-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    
    .video-item {
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    
    .video-item video {
        width: 100%;
        height: auto;
        min-height: 200px;
    }
    
    .no-media {
        text-align: center;
        color: #666;
        padding: 40px;
        background: #f8f9fa;
        border-radius: 10px;
    }
    
    .participants-section {
        margin: 30px 0;
        padding: 25px;
        background: #f8f9fa;
        border-radius: 10px;
        border: 1px solid #e0e0e0;
    }
    
    .participants-section h3 {
        margin-bottom: 20px;
        color: #333;
        font-size: 1.4rem;
        font-weight: 500;
    }
    
    .participants-list {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .participant-badge {
        background: #ffffff;
        color: #333333;
        padding: 8px 16px;
        border-radius: 20px;
        border: 1px solid #ddd;
        font-size: 0.9rem;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    
    .participant-badge:hover {
        background: #e9ecef;
        border-color: #adb5bd;
    }
    
    /* Lightbox */
    .lightbox {
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
    
    .lightbox img {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        max-width: 90%;
        max-height: 90%;
        border-radius: 10px;
        box-shadow: 0 0 50px rgba(0,0,0,0.5);
    }
    
    .lightbox-close {
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
    
    .lightbox-close:hover {
        background: rgba(0,0,0,0.8);
        transform: scale(1.1);
    }
    
    .lightbox-nav {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
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
        user-select: none;
    }
    
    .lightbox-nav:hover {
        background: rgba(0,0,0,0.8);
        transform: translateY(-50%) scale(1.1);
    }
    
    .lightbox-prev {
        left: 30px;
    }
    
    .lightbox-next {
        right: 30px;
    }
    
            .error {
            background: #2d1b1f;
            color: #ff6b6b;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            border: 1px solid #ff4444;
        }
    
    @media (max-width: 768px) {
        .event-detail-title {
            font-size: 1.8rem;
        }
        
        .event-detail-meta {
            grid-template-columns: 1fr;
        }
        
        .media-grid {
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        }
        
        .video-grid {
            grid-template-columns: 1fr;
        }
        
        .lightbox-nav {
            font-size: 2rem;
            width: 50px;
            height: 50px;
        }
        
        .lightbox-close {
            font-size: 2rem;
            width: 50px;
            height: 50px;
            top: 10px;
            right: 10px;
        }
    }
</style>

<div class="event-detail">
    <div class="event-detail-header">
        <h2 class="event-detail-title"><?php echo htmlspecialchars($event['title']); ?></h2>
        
        <div class="event-detail-meta">
            <div class="meta-item">
                <span class="meta-icon">📅</span>
                <span><strong>Datum:</strong> <?php echo formatDate($event['event_date']); ?></span>
            </div>
            
            <div class="meta-item">
                <span class="meta-icon">👥</span>
                <span><strong>Počet účastníků:</strong> <?php echo count($participants); ?></span>
            </div>
            
            <?php if (!empty($event['location'])): ?>
                <div class="meta-item">
                    <span class="meta-icon">📍</span>
                    <span><strong>Místo:</strong> <?php echo htmlspecialchars($event['location']); ?></span>
                </div>
            <?php endif; ?>
            
            <div class="meta-item">
                <span class="meta-icon">🕒</span>
                <span><strong>Přidáno:</strong> <?php echo formatDate($event['created_at']); ?></span>
            </div>
        </div>
    </div>
    
    <?php if (!empty($event['description'])): ?>
        <div class="event-detail-description">
            <?php echo nl2br(htmlspecialchars($event['description'])); ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($participants)): ?>
        <div class="participants-section">
            <h3>👥 Účastníci (<?php echo count($participants); ?>)</h3>
            <div class="participants-list">
                <?php foreach ($participants as $participant): ?>
                    <span class="participant-badge"><?php echo htmlspecialchars($participant); ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="media-gallery">
        <?php if (empty($media)): ?>
            <div class="no-media">
                <h3>📷 Žádná média</h3>
                <p>K tomuto eventu nebyla přidána žádná fotka nebo video.</p>
            </div>
        <?php else: ?>
            <?php
            $images = [];
            $videos = [];
            
            foreach ($media as $file) {
                if (in_array($file['type'], ['jpg', 'jpeg', 'png', 'gif'])) {
                    $images[] = $file;
                } elseif (in_array($file['type'], ['mp4', 'mov', 'avi', 'webm'])) {
                    $videos[] = $file;
                }
            }
            ?>
            
            <?php if (!empty($images)): ?>
                <h3>📷 Fotografie (<?php echo count($images); ?>)</h3>
                <div class="media-grid">
                    <?php foreach ($images as $index => $image): ?>
                        <div class="media-item" onclick="event.stopPropagation(); openEventLightbox(<?php echo $index; ?>)">
                            <img src="<?php echo getFileUrl($image['filename']); ?>" 
                                 alt="<?php echo htmlspecialchars($image['original_name']); ?>"
                                 loading="lazy">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($videos)): ?>
                <h3>🎥 Videa (<?php echo count($videos); ?>)</h3>
                <div class="video-grid">
                    <?php foreach ($videos as $video): ?>
                        <div class="video-item">
                            <video controls preload="metadata">
                                <source src="<?php echo getFileUrl($video['filename']); ?>" 
                                        type="video/<?php echo $video['type']; ?>">
                                Váš prohlížeč nepodporuje přehrávání videa.
                            </video>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($images)): ?>
    <script>
        // Nastavení obrázků pro lightbox
        if (typeof window.currentEventImages !== 'undefined') {
            window.currentEventImages = <?php echo json_encode($images); ?>;
        }
    </script>
<?php endif; ?>
