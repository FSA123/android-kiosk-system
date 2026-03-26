<?php
$file = 'stats_global.txt';
$dailyStats = []; // [Data][Nume_Media] = Count
$mediaTotals = []; // [Nume_Media] = Total_General

if (file_exists($file)) {
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $parts = explode('|', $line);
        if (count($parts) >= 3) {
            $dateFull = trim($parts[0]);
            $day = substr($dateFull, 0, 10); // Extrage doar YYYY-MM-DD
            $media = basename(trim($parts[2])); // Doar numele fisierului, fara cale

            // Contorizăm pentru grafic
            if (!isset($dailyStats[$day][$media])) $dailyStats[$day][$media] = 0;
            $dailyStats[$day][$media]++;

            // Contorizăm totalul general
            if (!isset($mediaTotals[$media])) $mediaTotals[$media] = 0;
            $mediaTotals[$media]++;
        }
    }
}

// Pregătim datele pentru JavaScript (Ultimele 7 zile)
ksort($dailyStats);
$labels = array_keys($dailyStats);
$allMediaNames = array_keys($mediaTotals);
?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Analytics Kiosk System</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f8f9fa; margin: 20px; color: #333; }
        .container { max-width: 1100px; margin: 0 auto; }
        .grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-top: 20px; }
        .card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        h2 { margin-top: 0; color: #1a73e8; font-size: 1.2rem; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th, td { text-align: left; padding: 10px; border-bottom: 1px solid #eee; }
        .total-badge { background: #1a73e8; color: white; padding: 3px 8px; border-radius: 12px; font-weight: bold; }
    </style>
</head>
<body>

<div class="container">
    <h1>📊 Analytics Media Kiosk</h1>

    <div class="card">
        <h2>Tendințe Zilnice (Afișări per Media)</h2>
        <canvas id="mainChart" height="100"></canvas>
    </div>

    <div class="grid">
        <div class="card">
            <h2>Top Conținut (Total Afișări)</h2>
            <table>
                <thead>
                    <tr>
                        <th>Nume Fișier</th>
                        <th>Total Rulări</th>
                        <th>Cota (%)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $grandTotal = array_sum($mediaTotals);
                    arsort($mediaTotals); // Cele mai rulate sus
                    foreach ($mediaTotals as $name => $count): 
                        $percent = round(($count / $grandTotal) * 100, 1);
                    ?>
                    <tr>
                        <td><strong><?php echo $name; ?></strong></td>
                        <td><span class="total-badge"><?php echo $count; ?></span></td>
                        <td><?php echo $percent; ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card" style="text-align: center;">
            <h2>Total General</h2>
            <div style="font-size: 3rem; font-weight: bold; color: #1a73e8; margin: 20px 0;">
                <?php echo $grandTotal; ?>
            </div>
            <p>afișări înregistrate</p>
            <button onclick="location.reload()" style="padding: 10px 20px; cursor: pointer; border-radius: 5px; border: none; background: #333; color: white;">Refresh Date</button>
        </div>
    </div>
</div>

<script>
const ctx = document.getElementById('mainChart').getContext('2d');
const chartData = {
    labels: <?php echo json_encode($labels); ?>,
    datasets: [
        <?php foreach ($allMediaNames as $index => $name): ?>
        {
            label: '<?php echo $name; ?>',
            data: <?php echo json_encode(array_map(function($day) use ($dailyStats, $name) {
                return $dailyStats[$day][$name] ?? 0;
            }, $labels)); ?>,
            backgroundColor: 'hsla(<?php echo ($index * 40) % 360; ?>, 70%, 50%, 0.5)',
            borderColor: 'hsla(<?php echo ($index * 40) % 360; ?>, 70%, 50%, 1)',
            borderWidth: 2,
            tension: 0.3
        },
        <?php endforeach; ?>
    ]
};

new Chart(ctx, {
    type: 'line', // Poți schimba în 'bar' dacă preferi coloane
    data: chartData,
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});
</script>

</body>
</html>