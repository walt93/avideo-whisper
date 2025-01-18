<?php
header('Content-Type: application/json');

$status_file = '/opt/whisper/status.json';

if (file_exists($status_file)) {
    echo file_get_contents($status_file);
} else {
    echo json_encode([
        'status' => 'unknown',
        'error' => 'Status file not found',
        'last_updated' => date('c'),
        'progress' => [
            'total_videos' => 0,
            'processed' => 0,
            'remaining' => 0,
            'percent_complete' => 0,
            'estimated_completion_time' => 'N/A',
            'average_processing_time' => null
        ]
    ]);
}
?>
