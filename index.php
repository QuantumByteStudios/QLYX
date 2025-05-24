<?php

require_once 'qlyx.php';
require_once 'db-connect.php';

$pdo = new PDO("mysql:host=$SERVER_NAME;dbname=$DATABASE", $USERNAME, $PASSWORD);
$qlyx = new QLYX($pdo);

$qlyx->track(); // Always log the visit

$range = $_GET['range'] ?? '24h';
$stats = $qlyx->getStats($range);
$trendData = $qlyx->getDailyTrends();


function isSelected($value, $range)
{
    return $value === $range ? 'selected' : '';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>QLYX Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="bg-light">

    <div class="container py-4">
        <div class="row">
            <div class="col-6 text-start">
                <h1 class="m-0">QLYX Analytics</h1>
            </div>
            <div class="col-6 text-end">
                <form method="get" class="mb-4">
                    <label for="range" class="form-label">Filter</label>
                    <select name="range" id="range" class="form-select w-auto d-inline-block"
                        onchange="this.form.submit()">
                        <option value="24h" <?= isSelected('24h', $range) ?>>Last 24 Hours</option>
                        <option value="7d" <?= isSelected('7d', $range) ?>>Last 7 Days</option>
                        <option value="1m" <?= isSelected('1m', $range) ?>>Last 1 Month</option>
                        <option value="1y" <?= isSelected('1y', $range) ?>>Last 1 Year</option>
                    </select>
                </form>
            </div>
        </div>
        <hr>

        <div class="row my-4">
            <div class="col-6">
                <div class="card border-0 p-5 text-center">
                    <div class="card-body">
                        <h6 class="text-uppercase">Total Visitors</h6>
                        <h1 class="fw-bold"><?= $stats['total'] ?></h1>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <h6 class="text-uppercase">Visitors Trend</h6>
                <canvas id="trendChart"></canvas>
                <script>
                const trendCtx = document.getElementById('trendChart').getContext('2d');
                new Chart(trendCtx, {
                    type: 'line',
                    data: {
                        labels: <?= json_encode(array_column($trendData, 'date')) ?>,
                        datasets: [{
                            label: 'Visits',
                            data: <?= json_encode(array_map('intval', array_column($trendData, 'visits'))) ?>,
                            fill: true,
                            borderColor: '#007bff',
                            backgroundColor: 'rgba(0, 123, 255, 0.2)',
                            tension: 0.4
                        }]
                    },
                    options: {
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Number of Visitors'
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Date'
                                }
                            }
                        }
                    }
                });
                </script>
            </div>
            <div class="row">
                <div class="col-4">
                    <canvas id="browserChart"></canvas>
                </div>
                <div class="col-4">
                    <canvas id="deviceChart"></canvas>
                </div>
                <div class="col-4">
                    <canvas id="countryChart"></canvas>
                </div>
                <script>
                const deviceCtx = document.getElementById('deviceChart').getContext('2d');
                new Chart(deviceCtx, {
                    type: 'pie',
                    data: {
                        labels: <?= json_encode(array_column($stats['by_device'], 'user_device_type')) ?>,
                        datasets: [{
                            data: <?= json_encode(array_column($stats['by_device'], 'count')) ?>,
                            backgroundColor: ['#36a2eb', '#4bc0c0', '#ff6384']
                        }]
                    }
                });

                const browserCtx = document.getElementById('browserChart').getContext('2d');
                new Chart(browserCtx, {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode(array_column($stats['by_browser'], 'browser_name')) ?>,
                        datasets: [{
                            label: 'Browsers',
                            data: <?= json_encode(array_column($stats['by_browser'], 'count')) ?>,
                            backgroundColor: '#36a2eb'
                        }]
                    }
                });

                const countryCtx = document.getElementById('countryChart').getContext('2d');
                new Chart(countryCtx, {
                    type: 'doughnut',
                    data: {
                        labels: <?= json_encode(array_column($stats['by_country'], 'user_country')) ?>,
                        datasets: [{
                            data: <?= json_encode(array_column($stats['by_country'], 'count')) ?>,
                            backgroundColor: ['#ff6384', '#ff9f40', '#4bc0c0', '#9966ff', '#36a2eb']
                        }]
                    }
                });
                </script>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <h6 class="text-uppercase">Recent Visitors</h6>
        <div class="table-responsive">
            <table class="table table-striped table-bordered table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>IP</th>
                        <th>Device</th>
                        <th>Browser</th>
                        <th>Country</th>
                        <th>City</th>
                        <th>Region</th>
                        <th>OS</th>
                        <th>Lang</th>
                        <th>Referrer</th>
                        <th>Page URL</th>
                        <th>Timezone</th>
                        <th>Network</th>
                        <th>Visitor</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    function displayValue($value)
                    {
                        return ($value === "Unknown" || $value === null) ? "N/A" : htmlspecialchars($value);
                    }
                    foreach ($stats['recent'] as $row): ?>
                    <tr>
                        <td><?= displayValue($row['user_ip_address']) ?></td>
                        <td><?= displayValue($row['user_device_type']) ?></td>
                        <td><?= displayValue($row['browser_name']) ?></td>
                        <td><?= displayValue($row['user_country']) ?></td>
                        <td><?= displayValue($row['user_city']) ?></td>
                        <td><?= displayValue($row['user_region']) ?></td>
                        <td><?= displayValue($row['user_os']) ?></td>
                        <td><?= displayValue($row['browser_language']) ?></td>
                        <td><?= displayValue($row['referring_url']) ?></td>
                        <td><?= displayValue($row['page_url']) ?></td>
                        <td><?= displayValue($row['timezone']) ?></td>
                        <td><?= displayValue($row['network_connection']) ?></td>
                        <td><?= displayValue($row['visitor_type']) ?></td>
                        <td><?= $row['created_at'] ? date('jS M Y', strtotime($row['created_at'])) : "N/A" ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>

</html>


</html>