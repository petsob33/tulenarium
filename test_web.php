<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test BASE_URL a obrázků</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-image { max-width: 200px; border: 1px solid #ccc; margin: 10px; }
        .success { color: green; }
        .error { color: red; }
        .info { background: #f0f0f0; padding: 10px; margin: 10px 0; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>Test BASE_URL a obrázků</h1>
    
    <?php
    require_once 'config.php';
    
    echo "<div class='info'>";
    echo "<h2>BASE_URL: " . BASE_URL . "</h2>";
    echo "<p><strong>UPLOAD_DIR:</strong> " . UPLOAD_DIR . "</p>";
    echo "<p><strong>HTTP_HOST:</strong> " . ($_SERVER['HTTP_HOST'] ?? 'N/A') . "</p>";
    echo "<p><strong>SCRIPT_NAME:</strong> " . ($_SERVER['SCRIPT_NAME'] ?? 'N/A') . "</p>";
    echo "<p><strong>REQUEST_URI:</strong> " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "</p>";
    echo "</div>";
    
    echo "<h2>Test načítání obrázků:</h2>";
    
    // Test načítání jednoho obrázku
    $testImage = '68a2ea61b4296_ja.jpg';
    $imageUrl = getFileUrl($testImage);
    
    echo "<p><strong>URL obrázku:</strong> <code>$imageUrl</code></p>";
    echo "<p><strong>Fyzická cesta:</strong> <code>" . UPLOAD_DIR . $testImage . "</code></p>";
    
    // Kontrola, jestli soubor existuje
    if (file_exists(UPLOAD_DIR . $testImage)) {
        echo "<p class='success'>✅ Soubor existuje</p>";
        echo "<img src='$imageUrl' alt='Test obrázek' class='test-image'>";
    } else {
        echo "<p class='error'>❌ Soubor neexistuje</p>";
    }
    
    echo "<h2>Náhled všech obrázků:</h2>";
    $files = glob(UPLOAD_DIR . '*.jpg');
    $count = 0;
    foreach ($files as $file) {
        $filename = basename($file);
        $url = getFileUrl($filename);
        echo "<div style='display: inline-block; margin: 10px; text-align: center;'>";
        echo "<img src='$url' alt='$filename' class='test-image'><br>";
        echo "<small>$filename</small>";
        echo "</div>";
        $count++;
        if ($count >= 5) break; // Zobrazíme jen prvních 5
    }
    
    echo "<h2>Všechny obrázky (odkazy):</h2>";
    foreach ($files as $file) {
        $filename = basename($file);
        $url = getFileUrl($filename);
        echo "<p><a href='$url' target='_blank'>$filename</a></p>";
    }
    ?>
</body>
</html>
