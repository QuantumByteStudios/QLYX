<?php

require_once 'qlyx.php';
require_once '../db-connect.php';

$qlyx = new QLYX($pdo);

$range = $_GET['range'] ?? '24h';
$stats = $qlyx->getStats($range);
$trendData = $qlyx->getDailyTrends();

function isSelected($value, $range)
{
    return $value === $range ? 'selected' : '';
}

function displayValue($value)
{
    return ($value === "Unknown" || $value === null || $value === '') ? "N/A" : htmlspecialchars($value);
}

// Calculate visitor counts
$humanCount = 0;
$botCount = 0;
foreach ($stats['recent'] as $row) {
    if ($row['visitor_type'] == "HUMAN") {
        $humanCount++;
    } else {
        $botCount++;
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>QLYX Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #000000;
            --secondary-color: #333333;
            --light-color: #f8f9fa;
            --border-color: #dee2e6;
        }

        body {
            background-color: var(--light-color);
        }

        td,
        th {
            font-size: 12px;
            text-wrap: wrap;
        }

        .metric-card {
            transition: all 0.3s ease;
            background-color: var(--primary-color) !important;
            border: none;
        }

        .metric-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .metric-card .card-body {
            position: relative;
            overflow: hidden;
        }

        .metric-card .metric-details {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--primary-color);
            padding: 1rem;
            transform: translateY(100%);
            transition: transform 0.3s ease;
            z-index: 1;
        }

        .metric-card:hover .metric-details {
            transform: translateY(0);
        }

        .metric-details ul {
            list-style: none;
            padding: 0;
            margin: 0;
            color: var(--light-color);
        }

        .metric-details li {
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .chart-container {
            position: relative;
            margin: auto;
            height: 300px;
        }

        .visitor-icon {
            font-size: 24px;
            margin-right: 10px;
            color: var(--light-color);
        }

        .visitor-count {
            font-size: 36px;
            font-weight: bold;
            color: var(--light-color);
        }

        .visitor-label {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.8);
        }

        .card {
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .card-title {
            color: var(--primary-color);
            font-weight: 600;
        }

        .table-dark {
            background-color: var(--primary-color) !important;
        }

        .badge {
            font-weight: 500;
        }

        .badge.bg-success {
            background-color: var(--primary-color) !important;
        }

        .badge.bg-warning {
            background-color: var(--secondary-color) !important;
        }

        .cursor-pointer {
            cursor: pointer;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid var(--light-color);
            border-top: 5px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .refresh-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--primary-color);
            color: var(--light-color);
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease;
        }

        .refresh-btn:hover {
            transform: rotate(180deg);
        }
    </style>
</head>

<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay">
        <div class="loading-spinner"></div>
    </div>

    <!-- Refresh Button -->
    <button class="refresh-btn" onclick="refreshData()">
        <i class="fas fa-sync-alt"></i>
    </button>

    <div class="container py-4">
        <!-- Header Section -->
        <div class="row mb-4">
            <div class="col-6 text-start">
                <a class="text-dark text-decoration-none" href=".">
                    <h1 class="m-0">QLYX Analytics</h1>
                </a>
                <p class="text-muted"><?php echo "Current User: " . ($_COOKIE['qlyx_user_profile'] ?? 'N/A'); ?></p>
            </div>
            <div class="col-6 text-end">
                <form method="get" class="mb-4">
                    <label for="range" class="form-label">Time Range</label>
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

        <!-- Key Metrics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card h-100 metric-card cursor-pointer">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-users visitor-icon"></i>
                            <div>
                                <div class="visitor-count"><?= $stats['total'] ?></div>
                                <div class="visitor-label">Total Visitors</div>
                            </div>
                        </div>
                        <div class="metric-details">
                            <ul>
                                <li><strong>Last 24h:</strong> <?= $stats['total'] ?></li>
                                <li><strong>Last 7d:</strong> <?= array_sum(array_column($trendData, 'visits')) ?></li>
                                <li><strong>Avg Daily:</strong> <?= round($stats['total'] / 7, 1) ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 metric-card cursor-pointer">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-user visitor-icon"></i>
                            <div>
                                <div class="visitor-count"><?= $stats['by_visitor_type'][0]['count'] ?? 0 ?></div>
                                <div class="visitor-label">Human Visitors</div>
                            </div>
                        </div>
                        <div class="metric-details">
                            <ul>
                                <li><strong>Last 24h:</strong> <?= $stats['by_visitor_type'][0]['count'] ?? 0 ?></li>
                                <li><strong>Last 7d:</strong>
                                    <?= array_sum(array_column($trendData, 'human_visitors')) ?></li>
                                <li><strong>Avg Daily:</strong>
                                    <?= round(($stats['by_visitor_type'][0]['count'] ?? 0) / 7, 1) ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 metric-card cursor-pointer">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-robot visitor-icon"></i>
                            <div>
                                <div class="visitor-count"><?= $stats['by_visitor_type'][1]['count'] ?? 0 ?></div>
                                <div class="visitor-label">Bot Visitors</div>
                            </div>
                        </div>
                        <div class="metric-details">
                            <ul>
                                <li><strong>Last 24h:</strong> <?= $stats['by_visitor_type'][1]['count'] ?? 0 ?></li>
                                <li><strong>Last 7d:</strong> <?= array_sum(array_column($trendData, 'bot_visitors')) ?>
                                </li>
                                <li><strong>Avg Daily:</strong>
                                    <?= round(($stats['by_visitor_type'][1]['count'] ?? 0) / 7, 1) ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 metric-card cursor-pointer">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-globe visitor-icon"></i>
                            <div>
                                <div class="visitor-count"><?= count($stats['by_country']) ?></div>
                                <div class="visitor-label">Countries</div>
                            </div>
                        </div>
                        <div class="metric-details">
                            <ul>
                                <li><strong>Last 24h:</strong> <?= count($stats['by_country']) ?></li>
                                <li><strong>Last 7d:</strong>
                                    <?= count(array_unique(array_column($trendData, 'country'))) ?></li>
                                <li><strong>Avg Daily:</strong> <?= round(count($stats['by_country']) / 7, 1) ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Metrics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card h-100 metric-card cursor-pointer">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-building visitor-icon"></i>
                            <div>
                                <div class="visitor-count"><?= count($stats['by_org']) ?></div>
                                <div class="visitor-label">Organizations</div>
                            </div>
                        </div>
                        <div class="metric-details">
                            <ul>
                                <li><strong>Last 24h:</strong> <?= count($stats['by_org']) ?></li>
                                <li><strong>Last 7d:</strong>
                                    <?= count(array_unique(array_column($trendData, 'org'))) ?></li>
                                <li><strong>Avg Daily:</strong> <?= round(count($stats['by_org']) / 7, 1) ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 metric-card cursor-pointer">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-map-marker-alt visitor-icon"></i>
                            <div>
                                <div class="visitor-count"><?= count($stats['by_city']) ?></div>
                                <div class="visitor-label">Cities</div>
                            </div>
                        </div>
                        <div class="metric-details">
                            <ul>
                                <li><strong>Last 24h:</strong> <?= count($stats['by_city']) ?></li>
                                <li><strong>Last 7d:</strong>
                                    <?= count(array_unique(array_column($trendData, 'city'))) ?></li>
                                <li><strong>Avg Daily:</strong> <?= round(count($stats['by_city']) / 7, 1) ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 metric-card cursor-pointer">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-language visitor-icon"></i>
                            <div>
                                <div class="visitor-count"><?= count($stats['by_language']) ?></div>
                                <div class="visitor-label">Languages</div>
                            </div>
                        </div>
                        <div class="metric-details">
                            <ul>
                                <li><strong>Last 24h:</strong> <?= count($stats['by_language']) ?></li>
                                <li><strong>Last 7d:</strong>
                                    <?= count(array_unique(array_column($trendData, 'language'))) ?></li>
                                <li><strong>Avg Daily:</strong> <?= round(count($stats['by_language']) / 7, 1) ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 metric-card cursor-pointer">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-clock visitor-icon"></i>
                            <div>
                                <div class="visitor-count"><?= count($stats['by_timezone']) ?></div>
                                <div class="visitor-label">Timezones</div>
                            </div>
                        </div>
                        <div class="metric-details">
                            <ul>
                                <li><strong>Last 24h:</strong> <?= count($stats['by_timezone']) ?></li>
                                <li><strong>Last 7d:</strong>
                                    <?= count(array_unique(array_column($trendData, 'timezone'))) ?></li>
                                <li><strong>Avg Daily:</strong> <?= round(count($stats['by_timezone']) / 7, 1) ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Visitor Trends Chart -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Visitor Trends</h5>
                        <div class="chart-container">
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Distribution Charts -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Device Distribution</h5>
                        <div class="chart-container">
                            <canvas id="deviceChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Browser Usage</h5>
                        <div class="chart-container">
                            <canvas id="browserChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Top Countries</h5>
                        <div class="chart-container">
                            <canvas id="countryChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Distribution Charts -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Operating Systems</h5>
                        <div class="chart-container">
                            <canvas id="osChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Top Cities</h5>
                        <div class="chart-container">
                            <canvas id="cityChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Visitors Table -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Recent Visitors</h5>
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>IP</th>
                                <th>User</th>
                                <th>Org</th>
                                <th>Device</th>
                                <th>Browser</th>
                                <th>Country</th>
                                <th>City</th>
                                <th>OS</th>
                                <th>Visitor</th>
                                <th>Time</th>
                                <th>More</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Always show the table, even if empty, for debugging
                            if (empty($stats['recent'])): ?>
                                <tr>
                                    <td colspan="12" class="text-center">
                                        <div class="alert mb-0">
                                            <i class="fas fa-info-circle"></i> No recent visitors found
                                        </div>
                                    </td>
                                </tr>
                            <?php else:
                                $count = 1;
                                foreach ($stats['recent'] as $row): 
                                    // Prepare all fields for modal (use raw values, not displayValue)
                                    $modalId = "visitorModal" . $count;

                                    // Just get the values from the database, use null coalescing to avoid undefined index warnings
                                    $modalData = [
                                        "ID" => $row['id'] ?? '',
                                        "IP Address" => $row['user_ip_address'] ?? '',
                                        "User Profile" => $row['user_profile'] ?? '',
                                        "Organization" => $row['user_org'] ?? '',
                                        "User Browser Agent" => $row['user_browser_agent'] ?? '',
                                        "Device Type" => $row['user_device_type'] ?? '',
                                        "Operating System" => $row['user_os'] ?? '',
                                        "City" => $row['user_city'] ?? '',
                                        "Region" => $row['user_region'] ?? '',
                                        "Country" => $row['user_country'] ?? '',
                                        "Browser Name" => $row['browser_name'] ?? '',
                                        "Browser Version" => $row['browser_version'] ?? '',
                                        "Browser Language" => $row['browser_language'] ?? '',
                                        "Referring URL" => $row['referring_url'] ?? '',
                                        "Page URL" => $row['page_url'] ?? '',
                                        "Timezone" => $row['timezone'] ?? '',
                                        "Visitor Type" => $row['visitor_type'] ?? '',
                                        "Session ID" => $row['session_id'] ?? '',
                                        "Page Count" => $row['page_count'] ?? '',
                                        "Created At" => $row['created_at'] ?? '',
                                        "Last Activity" => $row['last_activity'] ?? '',
                                    ];
                                ?>
                                    <tr>
                                        <td><?= $count ?></td>
                                        <td><?= displayValue($row['user_ip_address'] ?? '') ?></td>
                                        <td><?= displayValue($row['user_profile'] ?? '') ?></td>
                                        <td><?= displayValue($row['user_org'] ?? '') ?></td>
                                        <td><?= displayValue($row['user_device_type'] ?? '') ?></td>
                                        <td><?= displayValue($row['browser_name'] ?? '') ?></td>
                                        <td><?= displayValue($row['user_country'] ?? '') ?></td>
                                        <td><?= displayValue($row['user_city'] ?? '') ?></td>
                                        <td><?= displayValue($row['user_os'] ?? '') ?></td>
                                        <td>
                                            <?php
                                            $visitorType = $row['visitor_type'] ?? '';
                                            switch ($visitorType) {
                                                case "HUMAN":
                                                    echo '<span class="badge bg-success"><i class="fas fa-user"></i> Human</span>';
                                                    break;
                                                case "BOT":
                                                    echo '<span class="badge bg-warning"><i class="fas fa-robot"></i> Bot</span>';
                                                    break;
                                                default:
                                                    echo '<span class="badge bg-secondary"><i class="fas fa-question"></i> Unknown</span>';
                                                    break;
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            $createdAt = $row['created_at'] ?? '';
                                            if (!empty($createdAt)):
                                                try {
                                                    $dt = new DateTime($createdAt, new DateTimeZone('UTC'));
                                                    $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
                                                    $date = $dt->format('jS M Y');
                                                    $time = $dt->format('g:i A');
                                                    ?>
                                                    <small><?= $date ?><br><?= $time ?> IST</small>
                                                <?php } catch (Exception $e) { ?>
                                                    N/A
                                                <?php }
                                            else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button 
                                                type="button" 
                                                class="btn btn-sm btn-outline-dark" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#<?= $modalId ?>"
                                                title="Show more details"
                                            >
                                                <i class="fas fa-info-circle"></i>
                                            </button>
                                            <!-- Modal for more details (now displays ALL fields) -->
                                            <div class="modal fade" id="<?= $modalId ?>" tabindex="-1" aria-labelledby="<?= $modalId ?>Label" aria-hidden="true">
                                              <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
                                                <div class="modal-content">
                                                  <div class="modal-header">
                                                    <h5 class="modal-title" id="<?= $modalId ?>Label">Visitor Details</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                  </div>
                                                  <div class="modal-body">
                                                    <div class="table-responsive">
                                                      <table class="table table-bordered table-sm mb-0">
                                                        <tbody>
                                                          <?php
                                                          // Debug: Show the raw $row array for troubleshooting
                                                          echo '<tr><td colspan="2"><pre style="font-size:11px;background:#f8f9fa;">';
                                                        //   print_r($row);
                                                          echo '</pre></td></tr>';
                                                          foreach ($modalData as $key => $value) {
                                                              echo "<tr><th>$key</th><td>" . htmlspecialchars((string)$value) . "</td></tr>";
                                                          }
                                                          ?>
                                                        </tbody>
                                                      </table>
                                                    </div>
                                                  </div>
                                                  <div class="modal-footer">
                                                    <button type="button" class="btn btn-sm btn-dark" data-bs-dismiss="modal">Close</button>
                                                  </div>
                                                </div>
                                              </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php $count++; ?>
                                <?php endforeach;
                            endif; ?>
                        </tbody>
                    </table>
                    <!-- Ensure Bootstrap JS is loaded for modal functionality -->
                    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Define chart colors
        const chartColors = {
            black: '#000000',
            darkGrey: '#2C3E50',
            mediumGrey: '#7F8C8D',
            lightGrey: '#BDC3C7',
            veryLightGrey: '#ECF0F1',
            fillColor: 'rgba(0, 0, 0, 0.1)'
        };

        // Visitor Trends Chart
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column($trendData, 'date')) ?>,
                datasets: [{
                    label: 'Visits',
                    data: <?= json_encode(array_map('intval', array_column($trendData, 'visits'))) ?>,
                    fill: true,
                    borderColor: chartColors.black,
                    backgroundColor: chartColors.fillColor,
                    tension: 0.4,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: chartColors.veryLightGrey
                        },
                        title: {
                            display: true,
                            text: 'Number of Visitors'
                        }
                    },
                    x: {
                        grid: {
                            color: chartColors.veryLightGrey
                        },
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    }
                }
            }
        });

        // Device Distribution Chart
        const deviceCtx = document.getElementById('deviceChart').getContext('2d');
        new Chart(deviceCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_column($stats['by_device'], 'user_device_type')) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($stats['by_device'], 'count')) ?>,
                    backgroundColor: [
                        chartColors.black,
                        chartColors.darkGrey,
                        chartColors.mediumGrey,
                        chartColors.lightGrey,
                        chartColors.veryLightGrey
                    ],
                    borderWidth: 1,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    }
                }
            }
        });

        // Browser Usage Chart
        const browserCtx = document.getElementById('browserChart').getContext('2d');
        new Chart(browserCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($stats['by_browser'], 'browser_name')) ?>,
                datasets: [{
                    label: 'Browsers',
                    data: <?= json_encode(array_column($stats['by_browser'], 'count')) ?>,
                    backgroundColor: [
                        chartColors.black,
                        chartColors.darkGrey,
                        chartColors.mediumGrey,
                        chartColors.lightGrey,
                        chartColors.veryLightGrey
                    ],
                    borderWidth: 1,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: chartColors.veryLightGrey
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Country Distribution Chart
        const countryCtx = document.getElementById('countryChart').getContext('2d');
        new Chart(countryCtx, {
            type: 'pie',
            data: {
                labels: <?= json_encode(array_column($stats['by_country'], 'user_country')) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($stats['by_country'], 'count')) ?>,
                    backgroundColor: [
                        chartColors.black,
                        chartColors.darkGrey,
                        chartColors.mediumGrey,
                        chartColors.lightGrey,
                        chartColors.veryLightGrey
                    ],
                    borderWidth: 1,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    }
                }
            }
        });

        // OS Distribution Chart
        const osCtx = document.getElementById('osChart').getContext('2d');
        const osData = <?= json_encode(array_column($stats['by_os'], 'count', 'user_os')) ?>;
        new Chart(osCtx, {
            type: 'bar',
            data: {
                labels: Object.keys(osData),
                datasets: [{
                    label: 'Operating Systems',
                    data: Object.values(osData),
                    backgroundColor: [
                        chartColors.black,
                        chartColors.darkGrey,
                        chartColors.mediumGrey,
                        chartColors.lightGrey,
                        chartColors.veryLightGrey
                    ],
                    borderWidth: 1,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: chartColors.veryLightGrey
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // City Distribution Chart
        const cityCtx = document.getElementById('cityChart').getContext('2d');
        const cityData = <?= json_encode(array_column($stats['by_city'], 'count', 'user_city')) ?>;
        new Chart(cityCtx, {
            type: 'bar',
            data: {
                labels: Object.keys(cityData),
                datasets: [{
                    label: 'Top Cities',
                    data: Object.values(cityData),
                    backgroundColor: [
                        chartColors.black,
                        chartColors.darkGrey,
                        chartColors.mediumGrey,
                        chartColors.lightGrey,
                        chartColors.veryLightGrey
                    ],
                    borderWidth: 1,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: chartColors.veryLightGrey
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Add refresh functionality
        function refreshData() {
            const overlay = document.querySelector('.loading-overlay');
            overlay.style.display = 'flex';

            // Add timestamp to prevent caching
            const timestamp = new Date().getTime();
            window.location.href = `?range=${currentRange}&_=${timestamp}`;
        }

        // Store current range
        const currentRange = '<?= $range ?>';

        // Add click handlers for metric cards
        document.querySelectorAll('.metric-card').forEach(card => {
            card.addEventListener('click', function () {
                // Add your custom click handling here
                // For example, you could show a modal with more detailed information
            });
        });

        // Add auto-refresh every 5 minutes
        setInterval(refreshData, 300000);

        // Add error handling for charts
        function handleChartError(chart, error) {
            console.error('Chart error:', error);
            const canvas = chart.canvas;
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = '#f8f9fa';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = '#000000';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText('Error loading chart data', canvas.width / 2, canvas.height / 2);
        }

        // Add error handling to all charts
        Chart.defaults.plugins.tooltip.enabled = true;
        Chart.defaults.plugins.tooltip.mode = 'index';
        Chart.defaults.plugins.tooltip.intersect = false;
        Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(0,0,0,0.8)';
        Chart.defaults.plugins.tooltip.titleColor = '#ffffff';
        Chart.defaults.plugins.tooltip.bodyColor = '#ffffff';
        Chart.defaults.plugins.tooltip.borderColor = '#ffffff';
        Chart.defaults.plugins.tooltip.borderWidth = 1;
        Chart.defaults.plugins.tooltip.padding = 10;
        Chart.defaults.plugins.tooltip.cornerRadius = 4;
        Chart.defaults.plugins.tooltip.displayColors = true;
        Chart.defaults.plugins.tooltip.boxWidth = 10;
        Chart.defaults.plugins.tooltip.boxHeight = 10;
        Chart.defaults.plugins.tooltip.usePointStyle = true;
        Chart.defaults.plugins.tooltip.callbacks = {
            label: function (context) {
                let label = context.dataset.label || '';
                if (label) {
                    label += ': ';
                }
                if (context.parsed.y !== null) {
                    label += new Intl.NumberFormat().format(context.parsed.y);
                }
                return label;
            }
        };

        // Add responsive breakpoints
        const breakpoints = {
            xs: 0,
            sm: 576,
            md: 768,
            lg: 992,
            xl: 1200
        };

        // Update chart options based on screen size
        function updateChartOptions() {
            const width = window.innerWidth;
            const isMobile = width < breakpoints.md;

            Chart.helpers.each(Chart.instances, function (instance) {
                const chart = instance.chart;
                const options = chart.options;

                // Adjust legend position for mobile
                if (options.plugins && options.plugins.legend) {
                    options.plugins.legend.position = isMobile ? 'bottom' : 'right';
                }

                // Adjust font sizes for mobile
                if (options.scales) {
                    if (options.scales.x && options.scales.x.ticks) {
                        options.scales.x.ticks.font = {
                            size: isMobile ? 10 : 12
                        };
                    }
                    if (options.scales.y && options.scales.y.ticks) {
                        options.scales.y.ticks.font = {
                            size: isMobile ? 10 : 12
                        };
                    }
                }

                chart.update();
            });
        }

        // Listen for window resize
        window.addEventListener('resize', updateChartOptions);
        // Initial call
        updateChartOptions();
    </script>
</body>

</html>