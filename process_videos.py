#!/usr/bin/env python3
import os
import json
import mysql.connector
import subprocess
import glob
import logging
from datetime import datetime
from pathlib import Path
import re

class WhisperProcessor:
    def __init__(self):
        self.base_dir = "/var/www/html/conspyre.tv/videos"
        self.whisper_dir = "/opt/whisper"
        self.status_file = os.path.join(self.whisper_dir, "status.json")
        self.log_dir = os.path.join(self.whisper_dir, "logs")
        self.processed_log = os.path.join(self.log_dir, "files_processed.json")
        self.cdn_base = "https://b-low.b-cdn.net"
        self.whisper_path = subprocess.check_output(['which', 'whisper']).decode().strip()
        
        # Ensure required directories exist
        os.makedirs(self.whisper_dir, exist_ok=True)
        os.makedirs(self.log_dir, exist_ok=True)
        
        # Set up logging
        logging.basicConfig(
            level=logging.INFO,
            format='%(asctime)s - %(levelname)s - %(message)s',
            handlers=[
                logging.FileHandler(os.path.join(self.log_dir, 'whisper.log')),
                logging.StreamHandler()
            ]
        )
        self.logger = logging.getLogger(__name__)
        
        # Initialize processing stats
        self.total_videos = 0
        self.processed_count = 0
        self.processing_times = []
        self.current_start_time = None

    def connect_db(self):
        return mysql.connector.connect(
            host="localhost",
            database="AVideo_conspyretv",
            user="conspyre",  # Update these credentials
            password="jvciX85cvOdjfg6Qcvp_vn6T"
        )

    def get_pending_videos(self):
        conn = self.connect_db()
        cursor = conn.cursor(dictionary=True)
        
        query = """
        SELECT id, filename, title 
        FROM videos 
        WHERE status = 'a' AND type = 'video'
        ORDER BY created DESC
        """
        
        cursor.execute(query)
        videos = cursor.fetchall()
        cursor.close()
        conn.close()
        return videos

    def find_video_file(self, video_dir):
        """Find the lowest resolution MP4 file in the video directory"""
        pattern = os.path.join(video_dir, "*_ext.mp4")
        mp4_files = glob.glob(pattern)
        
        if not mp4_files:
            # Try without _ext suffix
            pattern = os.path.join(video_dir, "*.mp4")
            mp4_files = glob.glob(pattern)
        
        if not mp4_files:
            return None
            
        # Extract resolutions and find lowest
        resolution_pattern = re.compile(r'_(\d+)\.mp4$')
        valid_files = []
        
        for file in mp4_files:
            match = resolution_pattern.search(file)
            if match:
                resolution = int(match.group(1))
                valid_files.append((resolution, file))
        
        if valid_files:
            return min(valid_files, key=lambda x: x[0])[1]
        
        return mp4_files[0]  # If no resolution found, return first file

    def calculate_eta(self):
        if not self.processing_times:
            return "Calculating..."
        
        avg_time = sum(self.processing_times) / len(self.processing_times)
        remaining_videos = self.total_videos - self.processed_count
        seconds_remaining = avg_time * remaining_videos
        
        hours = int(seconds_remaining // 3600)
        minutes = int((seconds_remaining % 3600) // 60)
        
        return f"{hours}h {minutes}m"

    def update_status(self, current_video=None, status="running"):
        now = datetime.now()
        
        # Calculate processing time if applicable
        if self.current_start_time and current_video:
            processing_time = (now - self.current_start_time).total_seconds()
            if processing_time > 0:  # Only add valid times
                self.processing_times.append(processing_time)
        
        status_data = {
            "status": status,
            "last_updated": now.isoformat(),
            "current_video": current_video,
            "progress": {
                "total_videos": self.total_videos,
                "processed": self.processed_count,
                "remaining": self.total_videos - self.processed_count,
                "percent_complete": round((self.processed_count / self.total_videos * 100), 2) if self.total_videos else 0,
                "estimated_completion_time": self.calculate_eta(),
                "average_processing_time": round(sum(self.processing_times) / len(self.processing_times), 2) if self.processing_times else None
            }
        }
        
        with open(self.status_file, 'w') as f:
            json.dump(status_data, f, indent=2)

    def process_video(self, video):
        video_dir = os.path.join(self.base_dir, video['filename'])
        base_output = os.path.join(video_dir, f"{video['filename']}")
        json_output = f"{base_output}.json"
        
        # Skip if already processed
        if os.path.exists(json_output):
            self.logger.info(f"Skipping {video['filename']} - already processed")
            return True
            
        # Find video file
        video_file = self.find_video_file(video_dir)
        if not video_file:
            self.logger.error(f"No video file found for {video['filename']}")
            return False
            
        # Extract filename for CDN URL
        cdn_filename = os.path.basename(video_file)
        cdn_url = f"{self.cdn_base}/{cdn_filename}"
        
        # Run whisper
        try:
            # Set up environment
            env = os.environ.copy()
            env['PATH'] = f"/usr/local/bin:/usr/bin:/bin:{os.path.dirname(self.whisper_path)}"
            
            command = [
                self.whisper_path,
                cdn_url,
                "--model", "turbo",
                "--output_dir", video_dir,
                "--output_format", "all",
                "--task", "transcribe",
                "--language", "en"
            ]
            
            result = subprocess.run(command, check=True, capture_output=True, text=True, env=env)
            self.logger.info(f"Successfully processed {video['filename']}")
            
            # Handle file renaming if necessary
            resolutions = ['240', '360', '480', '540', '720', '1080', '1440', '2160']
            cdn_basename = os.path.splitext(cdn_filename)[0]
            needs_renaming = any(f"_{res}" in cdn_basename for res in resolutions)
            
            if needs_renaming:
                # Remove resolution suffix from whisper output files
                for ext in ['.txt', '.vtt', '.srt', '.json']:
                    source_file = os.path.join(video_dir, f"{cdn_basename}{ext}")
                    target_file = os.path.join(video_dir, f"{video['filename']}{ext}")
                    if os.path.exists(source_file):
                        os.rename(source_file, target_file)
                        self.logger.info(f"Renamed {source_file} to {target_file}")
            
            # Update permissions for all output files
            for ext in ['.txt', '.vtt', '.srt', '.json']:
                output_file = os.path.join(video_dir, f"{video['filename']}{ext}")
                if os.path.exists(output_file):
                    os.chmod(output_file, 0o664)
                    subprocess.run(['chown', 'www-data:www-data', output_file])
            
            return True
            
        except subprocess.CalledProcessError as e:
            self.logger.error(f"Error processing {video['filename']}: {str(e)}")
            self.logger.error(f"Command output: {e.output}")
            self.logger.error(f"Command stderr: {e.stderr}")
            return False

    def log_processed_file(self, video, success, error=None):
        if not os.path.exists(self.processed_log):
            processed_data = {"processed_files": []}
        else:
            with open(self.processed_log, 'r') as f:
                processed_data = json.load(f)
        
        entry = {
            "filename": video['filename'],
            "id": video['id'],
            "timestamp": datetime.now().isoformat(),
            "success": success
        }
        if error:
            entry["error"] = str(error)
            
        processed_data["processed_files"].append(entry)
        
        with open(self.processed_log, 'w') as f:
            json.dump(processed_data, f, indent=2)

    def run(self):
        self.logger.info("Starting whisper processing")
        self.update_status(status="starting")
        
        videos = self.get_pending_videos()
        self.total_videos = len(videos)
        self.logger.info(f"Found {self.total_videos} videos to process")
        
        for video in videos:
            self.current_start_time = datetime.now()
            self.update_status(current_video=video['filename'])
            self.logger.info(f"Processing {video['filename']} ({self.processed_count + 1}/{self.total_videos})")
            
            try:
                success = self.process_video(video)
                self.log_processed_file(video, success)
                self.processed_count += 1
            except Exception as e:
                self.logger.error(f"Error processing {video['filename']}: {str(e)}")
                self.log_processed_file(video, False, error=str(e))
            
            self.update_status(current_video=video['filename'])
        
        self.update_status(status="completed")
        self.logger.info("Processing completed")

if __name__ == "__main__":
    processor = WhisperProcessor()
    processor.run()
