<?php
require_once 'config.php';

echo "<h1>Test BASE_URL a obrázků</h1>";

echo "<h2>BASE_URL: " . BASE_URL . "</h2>";

echo "<h2>Test načítání obrázků:</h2>";

// Test načítání jednoho obrázku
$testImage = '68a2ea61b4296_ja.jpg';
$imageUrl = getFileUrl($testImage);

echo "<p>URL obrázku: <code>$imageUrl</code></p>";
echo "<p>Fyzická cesta: <code>" . UPLOAD_DIR . $testImage . "</code></p>";

// Kontrola, jestli soubor existuje
if (file_exists(UPLOAD_DIR . $testImage)) {
    echo "<p>✅ Soubor existuje</p>";
    echo "<img src='$imageUrl' alt='Test obrázek' style='max-width: 200px; border: 1px solid #ccc;'>";
} else {
    echo "<p>❌ Soubor neexistuje</p>";
}

echo "<h2>Všechny obrázky v uploads:</h2>";
$files = glob(UPLOAD_DIR . '*.jpg');
foreach ($files as $file) {
    $filename = basename($file);
    $url = getFileUrl($filename);
    echo "<p><a href='$url' target='_blank'>$filename</a></p>";
}
?>
