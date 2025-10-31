<?php
// Target URL of the .wpress file
$url = "https://novela.xyz/wp-content/ai1wm-backups/novela-xyz-20250704-092853-n9v7rsloyync.wpress";

// Local path where file will be saved
$saveTo = __DIR__ . "/backup-novela.wpress";

// Start download
$ch = curl_init($url);
$fp = fopen($saveTo, 'w');

if ($ch && $fp) {
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300); // Optional: 5 min timeout
    curl_exec($ch);

    if (curl_errno($ch)) {
        echo "❌ cURL error: " . curl_error($ch);
    } else {
        echo "✅ Download Complete";
    }

    curl_close($ch);
    fclose($fp);
} else {
    echo " Could not open remote or local file.";
}

// Example Download URL: https://sitename.com/download.php
