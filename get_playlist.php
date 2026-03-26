<?php
header('Content-Type: application/json');

// Folderul de unde tabletele își vor lua media
$dir = "media/";
$playlist = [];

if (is_dir($dir)) {
    // Scanează folderul și elimină punctele de sistem (. și ..)
    $files = array_diff(scandir($dir), array('..', '.'));

    foreach($files as $file) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $filePath = $dir . $file; 
        
        // Verificăm dacă este imagine sau video
        if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
            $playlist[] = ["type" => "image", "src" => $filePath];
        } elseif (in_array($ext, ['mp4', 'webm', 'mov'])) {
            $playlist[] = ["type" => "video", "src" => $filePath];
        }
    }
}

// Returnează lista JSON pe care sync.php de pe tabletă o va citi
echo json_encode($playlist);
?>