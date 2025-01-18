# Whisper Video Processing Service

A microservice that processes videos through OpenAI's Whisper speech-to-text model. Built specifically for the AVideo CMS platform.

## Overview

This service monitors an AVideo installation's database for new videos and processes them through Whisper to generate:
- Text transcripts (.txt)
- Subtitles (.vtt, .srt)
- JSON metadata output

## Requirements

- Python 3.10+
- OpenAI Whisper
- MySQL/MariaDB
- Apache2
- Systemd
- AVideo CMS

## Installation

1. Set up the service directory:
```bash
sudo mkdir -p /opt/whisper
sudo chown www-data:www-data /opt/whisper
```

2. Install the Python script:
```bash
sudo cp process_videos.py /opt/whisper/
sudo chmod +x /opt/whisper/process_videos.py
```

3. Install systemd service:
```bash
sudo cp whisper-processor.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable whisper-processor
sudo systemctl start whisper-processor
```

4. Install status page:
```bash
sudo cp whisper_status.php /var/www/html/AVideo/management
sudo cp whisper_status_api.php /var/www/html/AVideo/management
```

5. Set up password protection:
```bash
sudo htpasswd -c /etc/apache2/.htpasswd admin
sudo cp whisper-htaccess /var/www/html/conspyre.tv/whisper_status/.htaccess
sudo chown www-data:www-data /etc/apache2/.htpasswd
sudo chmod 640 /etc/apache2/.htpasswd
```

## Configuration

Update the MySQL connection details in `process_videos.py`:
```python
def connect_db(self):
    return mysql.connector.connect(
        host="localhost",
        database="AVideo_conspyretv",
        user="your_username",
        password="your_password"
    )
```

## Operation

The service:
- Runs as a daemon under systemd
- Processes videos in order of most recent first
- Skips already processed videos
- Maintains a status page at `/whisper_status.php`
- Logs activity to `/opt/whisper/logs/`

### Status Page Access

Access the status page at: `https://your-domain/whisper_status.php`
- Shows current processing status
- Displays CPU/memory usage
- Shows service health
- Provides instructions if service is down

### Service Control

Start the service:
```bash
sudo systemctl start whisper-processor
```

Stop the service:
```bash
sudo systemctl stop whisper-processor
```

View logs:
```bash
sudo journalctl -u whisper-processor -f
```

## File Structure

```
/opt/whisper/
├── process_videos.py
├── status.json
├── daily_stats.json
├── whisper.lock
└── logs/
    ├── whisper.log
    ├── service.log
    ├── service.error.log
    └── files_processed.json
```

## Output Files

For each processed video at `/var/www/html/conspyre.tv/videos/<video_filename>/`:
- `<filename>.txt` - Plain text transcript
- `<filename>.vtt` - WebVTT subtitles
- `<filename>.srt` - SRT subtitles
- `<filename>.json` - Whisper metadata

## Limitations

- CPU-only processing (no GPU acceleration)
- Single video processing at a time
- English language only
- No retry mechanism for failed transcriptions

## Monitoring

The status page shows:
- Current processing status
- Service health
- System resource usage
- Daily utilization rates
- Processing logs

## Troubleshooting

1. Service won't start:
   - Check logs: `sudo journalctl -u whisper-processor -f`
   - Verify permissions on /opt/whisper
   - Check MySQL connection

2. Processing fails:
   - Check whisper.log for errors
   - Verify video file exists and is accessible
   - Check disk space

3. Status page shows no data:
   - Verify service is running
   - Check file permissions
   - Verify PHP can read status files
