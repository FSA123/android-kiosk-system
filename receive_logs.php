<?php
// Pe PC: receive_logs.php
if (isset($_POST['data'])) {
    $file = 'stats_global.txt';
    // Încercăm să scriem datele
    if (file_put_contents($file, $_POST['data'], FILE_APPEND)) {
        echo "OK"; // Mesajul pe care îl așteaptă tableta
    } else {
        echo "Eroare scriere fisier pe PC";
    }
} else {
    echo "Lipsa date POST";
}
?>