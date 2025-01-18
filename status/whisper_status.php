<?php
// Fetch the status file content
$status_file = '/opt/whisper/status.json';
$log_file = '/opt/whisper/logs/whisper.log';
$processed_file = '/opt/whisper/logs/files_processed.json';

function getLastNLines($file, $n = 10) {
    $lines = file($file);
    return array_slice($lines, -$n);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Whisper Processing Status</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #121212;
            color: #e0e0e0;
        }
        .card {
            background-color: #1e1e1e;
            border: 1px solid #333;
        }
        .card-header {
            background-color: #2d2d2d;
            border-bottom: 1px solid #333;
            color: #e0e0e0;  /* Ensure header text is visible */
        }
        .card-header h5 {
            color: #e0e0e0;  /* Force header titles to be light */
        }
        .card-header i {
            color: #00bc8c;  /* Make icons pop with accent color */
        }
        /* Ensure label text is visible */
        .card-body strong {
            color: #e0e0e0;
        }
        .table {
            color: #e0e0e0;
        }
        .progress {
            background-color: #2d2d2d;
        }
        .log-window {
            background-color: #1a1a1a;
            border: 1px solid #333;
            padding: 15px;
            font-family: monospace;
            height: 300px;
            overflow-y: auto;
            color: #00ff00;  /* Terminal green for better visibility */
        }
        .log-line {
            margin: 0;
            padding: 2px 0;
            border-bottom: 1px solid #2d2d2d;
            color: #00ff00;  /* Ensure log lines inherit the green color */
        }
        /* Ensure all text has good contrast */
        .text-info {
            color: #5ddef4 !important;  /* Brighter blue */
        }
        .text-warning {
            color: #ffd700 !important;  /* Brighter yellow */
        }
        .stats-value {
            text-shadow: 0 0 10px rgba(0,188,140,0.3);  /* Add glow effect */
        }
        .table-dark {
            background-color: #1a1a1a;
        }
        .table-dark td {
            color: #e0e0e0;
        }
        .status-badge {
            font-size: 0.9em;
            padding: 5px 10px;
        }
        .refresh-button {
            background-color: #2d2d2d;
            border: 1px solid #444;
            color: #e0e0e0;
        }
        .refresh-button:hover {
            background-color: #3d3d3d;
            color: #ffffff;
        }
        .stats-value {
            font-size: 1.8em;
            font-weight: bold;
            color: #00bc8c;
        }
        .stats-label {
            color: #888;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <?php
        // Check service status
        $service_status = shell_exec('systemctl is-active whisper-processor 2>&1');
        if (trim($service_status) !== 'active') {
            echo '<div class="alert" style="background-color: #440000; color: #ffffff; border: 1px solid #660000;">
                <h4><i class="fas fa-exclamation-triangle me-2"></i>Service Not Running</h4>
                <p>To start the whisper processing service:</p>
                <pre class="bg-dark text-light p-3 mt-2">sudo systemctl start whisper-processor</pre>
                <p class="mt-2">To enable automatic start on boot:</p>
                <pre class="bg-dark text-light p-3">sudo systemctl enable whisper-processor</pre>
            </div>';
        }
        
        // Load daily stats
        $daily_stats_file = '/opt/whisper/daily_stats.json';
        $daily_stats = file_exists($daily_stats_file) ? json_decode(file_get_contents($daily_stats_file), true) : null;
        ?>
        
        <div class="row mb-4">
            <div class="col">
                <h1 class="mb-4">
                    <i class="fas fa-microphone-lines me-2"></i>
                    Whisper Processing Status
                    <button class="btn refresh-button float-end" onclick="refreshPage()">
                        <i class="fas fa-sync-alt me-2"></i>
                        Refresh
                    </button>
                </h1>
            </div>
        </div>

        <div class="row mb-4">
            <!-- System Resources Card -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-server me-2"></i>
                            System Resources
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // Get CPU load
                        $load = sys_getloadavg();
                        
                        // Get memory info
                        $free = shell_exec('free');
                        $free = (string)trim($free);
                        $free_arr = explode("\n", $free);
                        $mem = explode(" ", $free_arr[1]);
                        $mem = array_filter($mem);
                        $mem = array_merge($mem);
                        $memory_usage = $mem[2]/$mem[1]*100;
                        
                        // Get CPU info
                        $cpu_info = shell_exec("top -bn1 | grep 'Cpu(s)' | sed 's/.*, *\\([0-9.]*\\)%* id.*/\\1/' | awk '{print 100 - $1}'");
                        ?>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <h6 class="text-light">CPU Usage</h6>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar bg-info" role="progressbar" 
                                         style="width: <?php echo number_format($cpu_info, 1); ?>%">
                                        <?php echo number_format($cpu_info, 1); ?>%
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <h6 class="text-light">Memory Usage</h6>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar bg-warning" role="progressbar" 
                                         style="width: <?php echo number_format($memory_usage, 1); ?>%">
                                        <?php echo number_format($memory_usage, 1); ?>%
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12">
                                <h6 class="text-light">Load Average</h6>
                                <div class="d-flex justify-content-between">
                                    <span class="badge bg-secondary">1min: <?php echo number_format($load[0], 2); ?></span>
                                    <span class="badge bg-secondary">5min: <?php echo number_format($load[1], 2); ?></span>
                                    <span class="badge bg-secondary">15min: <?php echo number_format($load[2], 2); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Main Status Card -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-line me-2"></i>
                            Current Status
                        </h5>
                        <span class="status-badge badge bg-success" id="statusBadge">Active</span>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-4 text-center mb-3">
                                <div class="stats-value" id="totalVideos">-</div>
                                <div class="stats-label">Total Videos</div>
                            </div>
                            <div class="col-md-4 text-center mb-3">
                                <div class="stats-value" id="processedVideos">-</div>
                                <div class="stats-label">Processed</div>
                            </div>
                            <div class="col-md-4 text-center mb-3">
                                <div class="stats-value" id="remainingVideos">-</div>
                                <div class="stats-label">Remaining</div>
                            </div>
                        </div>
                        <div class="progress mb-3" style="height: 20px;">
                            <div class="progress-bar bg-success" id="progressBar" role="progressbar" style="width: 0%">0%</div>
                        </div>
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <p><strong>Current Video:</strong><br><span id="currentVideo" class="text-info">-</span></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Estimated Completion:</strong><br><span id="eta" class="text-warning">-</span></p>
                            </div>
                        </div>
                        
                        <?php if ($daily_stats): ?>
                        <div class="row mt-4">
                            <div class="col-12">
                                <h6 class="text-light mb-3">Daily Utilization</h6>
                                <div class="progress mb-2" style="height: 20px;">
                                    <div class="progress-bar bg-info" role="progressbar" 
                                         style="width: <?php echo $daily_stats['utilization']; ?>">
                                        <?php echo $daily_stats['utilization']; ?>
                                    </div>
                                </div>
                                <small class="text-muted">
                                    Processing time today: <?php echo $daily_stats['processing_minutes']; ?> minutes
                                    of <?php echo $daily_stats['total_minutes']; ?> minutes
                                </small>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Processing Stats Card -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-clock me-2"></i>
                            Processing Statistics
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-dark table-hover">
                                <tbody>
                                    <tr>
                                        <td>Average Processing Time:</td>
                                        <td id="avgProcessingTime" class="text-end">-</td>
                                    </tr>
                                    <tr>
                                        <td>Last Updated:</td>
                                        <td id="lastUpdated" class="text-end">-</td>
                                    </tr>
                                    <tr>
                                        <td>Processing Rate:</td>
                                        <td id="processRate" class="text-end">-</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Process Status -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-microchip me-2"></i>
                            Process Status
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-dark table-hover">
                                <thead>
                                    <tr>
                                        <th>PID</th>
                                        <th>User</th>
                                        <th>CPU%</th>
                                        <th>MEM%</th>
                                        <th>Command</th>
                                        <th>Runtime</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Get only whisper processes (not the Python script)
                                    $cmd = "ps aux | grep '[w]hisper ' | awk '{print $1,$2,$3,$4,$9,$10,$11}'";
                                    exec($cmd, $output);
                                    foreach ($output as $line) {
                                        $parts = preg_split('/\s+/', $line, 7);
                                        echo "<tr>";
                                        echo "<td>{$parts[1]}</td>"; // PID
                                        echo "<td>{$parts[0]}</td>"; // USER
                                        echo "<td>" . number_format((float)$parts[2], 1) . "%</td>"; // CPU
                                        echo "<td>" . number_format((float)$parts[3], 1) . "%</td>"; // MEM
                                        echo "<td class='text-truncate' style='max-width: 500px;'>" . 
                                             (isset($parts[6]) ? htmlspecialchars($parts[6]) : 
                                             (isset($parts[5]) ? htmlspecialchars($parts[5]) : '')) . 
                                             "</td>"; // COMMAND
                                        echo "<td>{$parts[4]}</td>"; // START TIME
                                        echo "</tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Log Output -->
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-terminal me-2"></i>
                            Processing Log
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="log-window" id="logWindow">
                            <?php
                            if (file_exists($log_file)) {
                                $logs = getLastNLines($log_file, 50);
                                foreach ($logs as $log) {
                                    echo '<div class="log-line">' . htmlspecialchars($log) . '</div>';
                                }
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateStatus() {
            fetch('whisper_status_api.php')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('statusBadge').textContent = data.status.charAt(0).toUpperCase() + data.status.slice(1);
                    document.getElementById('currentVideo').textContent = data.current_video || '-';
                    document.getElementById('totalVideos').textContent = data.progress.total_videos;
                    document.getElementById('processedVideos').textContent = data.progress.processed;
                    document.getElementById('remainingVideos').textContent = data.progress.remaining;
                    document.getElementById('eta').textContent = data.progress.estimated_completion_time;
                    document.getElementById('avgProcessingTime').textContent = 
                        data.progress.average_processing_time ? 
                        `${data.progress.average_processing_time.toFixed(1)} seconds` : '-';
                    document.getElementById('lastUpdated').textContent = new Date(data.last_updated).toLocaleString();
                    
                    // Update progress bar
                    const progress = data.progress.percent_complete;
                    document.getElementById('progressBar').style.width = `${progress}%`;
                    document.getElementById('progressBar').textContent = `${progress.toFixed(1)}%`;
                    
                    // Calculate processing rate
                    if (data.progress.average_processing_time) {
                        const rate = 3600 / data.progress.average_processing_time;
                        document.getElementById('processRate').textContent = `${rate.toFixed(1)} videos/hour`;
                    }
                })
                .catch(error => console.error('Error fetching status:', error));
        }

        function refreshPage() {
            updateStatus();
            // Also refresh the log window
            location.reload();
        }

        // Update status every 30 seconds
        updateStatus();
        setInterval(updateStatus, 30000);
    </script>
</body>
</html>
