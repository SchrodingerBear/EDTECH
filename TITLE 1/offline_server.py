#!/usr/bin/env python3
"""
Innovatech PH Offline Server
Local Python server for running the application without internet connection
Uses SQLite for local database and syncs with MySQL when online
"""

import os
import sys
import json
import sqlite3
import subprocess
import threading
import time
from datetime import datetime
from http.server import HTTPServer, SimpleHTTPRequestHandler
from urllib.parse import urlparse, parse_qs
from pathlib import Path

# Configuration
PROJECT_ROOT = Path(__file__).parent
WEB_ROOT = PROJECT_ROOT
DB_PATH = PROJECT_ROOT / 'offline_data.sqlite'
SYNC_QUEUE_PATH = PROJECT_ROOT / 'sync_queue.json'
PORT = 8000

class OfflineServer(SimpleHTTPRequestHandler):
    """Custom HTTP server for offline mode"""

    def __init__(self, *args, **kwargs):
        self.db = self.init_database()
        super().__init__(*args, directory=str(WEB_ROOT), **kwargs)

    def init_database(self):
        """Initialize SQLite database for offline storage"""
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()

        # Create tables
        cursor.execute('''
            CREATE TABLE IF NOT EXISTS buildings (
                id INTEGER PRIMARY KEY,
                institution_id INTEGER,
                name TEXT,
                code TEXT,
                description TEXT,
                ai_description TEXT,
                featured_image_path TEXT,
                created_at TEXT,
                updated_at TEXT,
                synced INTEGER DEFAULT 0
            )
        ''')

        cursor.execute('''
            CREATE TABLE IF NOT EXISTS locations (
                id INTEGER PRIMARY KEY,
                building_id INTEGER,
                institution_id INTEGER,
                name TEXT,
                kind TEXT,
                category TEXT,
                code TEXT,
                floor_label TEXT,
                capacity INTEGER,
                description TEXT,
                created_at TEXT,
                updated_at TEXT,
                synced INTEGER DEFAULT 0
            )
        ''')

        cursor.execute('''
            CREATE TABLE IF NOT EXISTS tours (
                id INTEGER PRIMARY KEY,
                institution_id INTEGER,
                name TEXT,
                description TEXT,
                starting_scene_id INTEGER,
                created_at TEXT,
                updated_at TEXT,
                synced INTEGER DEFAULT 0
            )
        ''')

        cursor.execute('''
            CREATE TABLE IF NOT EXISTS sync_queue (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                endpoint TEXT,
                method TEXT,
                data TEXT,
                timestamp TEXT,
                status TEXT DEFAULT 'pending',
                retry_count INTEGER DEFAULT 0,
                error TEXT
            )
        ''')

        cursor.execute('''
            CREATE TABLE IF NOT EXISTS institutions (
                id INTEGER PRIMARY KEY,
                name TEXT,
                slug TEXT,
                folder_path TEXT,
                institution_type TEXT,
                city TEXT,
                landing_mode TEXT,
                is_published INTEGER DEFAULT 0,
                is_active INTEGER DEFAULT 1,
                synced INTEGER DEFAULT 0
            )
        ''')

        conn.commit()
        conn.close()

        return sqlite3.connect(DB_PATH, check_same_thread=False)

    def do_GET(self):
        """Handle GET requests"""
        parsed_path = urlparse(self.path)

        # API endpoints
        if parsed_path.path.startswith('/api/'):
            self.handle_api_request(parsed_path, 'GET')
        else:
            # Serve static files
            super().do_GET()

    def do_POST(self):
        """Handle POST requests"""
        parsed_path = urlparse(self.path)

        if parsed_path.path.startswith('/api/'):
            content_length = int(self.headers.get('Content-Length', 0))
            post_data = self.rfile.read(content_length)
            self.handle_api_request(parsed_path, 'POST', post_data)
        else:
            super().do_GET()

    def do_PUT(self):
        """Handle PUT requests"""
        parsed_path = urlparse(self.path)

        if parsed_path.path.startswith('/api/'):
            content_length = int(self.headers.get('Content-Length', 0))
            put_data = self.rfile.read(content_length)
            self.handle_api_request(parsed_path, 'PUT', put_data)
        else:
            super().do_GET()

    def do_DELETE(self):
        """Handle DELETE requests"""
        parsed_path = urlparse(self.path)

        if parsed_path.path.startswith('/api/'):
            self.handle_api_request(parsed_path, 'DELETE')
        else:
            super().do_GET()

    def handle_api_request(self, parsed_path, method, body=None):
        """Handle API requests"""
        path = parsed_path.path
        params = parse_qs(parsed_path.query)

        # CORS headers
        self.send_response(200)
        self.send_header('Content-type', 'application/json')
        self.send_header('Access-Control-Allow-Origin', '*')
        self.send_header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        self.send_header('Access-Control-Allow-Headers', 'Content-Type')
        self.end_headers()

        try:
            if path == '/api/buildings':
                response = self.handle_buildings(method, params, body)
            elif path == '/api/locations':
                response = self.handle_locations(method, params, body)
            elif path == '/api/tours':
                response = self.handle_tours(method, params, body)
            elif path == '/api/institutions':
                response = self.handle_institutions(method, params, body)
            elif path == '/api/sync/status':
                response = self.handle_sync_status()
            elif path == '/api/sync/queue':
                response = self.handle_sync_queue(method, body)
            else:
                response = {'error': 'Unknown endpoint', 'path': path}

            self.wfile.write(json.dumps(response).encode())
        except Exception as e:
            error_response = {'error': str(e)}
            self.wfile.write(json.dumps(error_response).encode())

    def handle_buildings(self, method, params, body):
        """Handle buildings API"""
        cursor = self.db.cursor()

        if method == 'GET':
            institution_id = params.get('institution_id', [None])[0]
            if institution_id:
                cursor.execute('SELECT * FROM buildings WHERE institution_id = ?', (institution_id,))
            else:
                cursor.execute('SELECT * FROM buildings')

            buildings = []
            for row in cursor.fetchall():
                buildings.append({
                    'id': row[0],
                    'institution_id': row[1],
                    'name': row[2],
                    'code': row[3],
                    'description': row[4],
                    'ai_description': row[5],
                    'featured_image_path': row[6],
                    'created_at': row[7],
                    'updated_at': row[8],
                    'synced': row[9]
                })
            return buildings

        elif method == 'POST':
            data = json.loads(body) if body else {}
            cursor.execute('''
                INSERT INTO buildings (institution_id, name, code, description, ai_description, featured_image_path, created_at, updated_at, synced)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)
            ''', (
                data.get('institution_id'),
                data.get('name'),
                data.get('code'),
                data.get('description'),
                data.get('ai_description'),
                data.get('featured_image_path'),
                datetime.now().isoformat(),
                datetime.now().isoformat()
            ))
            self.db.commit()

            # Queue for sync
            self.queue_sync_request('/api/buildings', 'POST', data)

            return {'success': True, 'id': cursor.lastrowid}

        elif method == 'PUT':
            data = json.loads(body) if body else {}
            building_id = data.get('id')
            cursor.execute('''
                UPDATE buildings SET name=?, code=?, description=?, ai_description=?, featured_image_path=?, updated_at=?, synced=0
                WHERE id=?
            ''', (
                data.get('name'),
                data.get('code'),
                data.get('description'),
                data.get('ai_description'),
                data.get('featured_image_path'),
                datetime.now().isoformat(),
                building_id
            ))
            self.db.commit()

            # Queue for sync
            self.queue_sync_request(f'/api/buildings/{building_id}', 'PUT', data)

            return {'success': True}

        elif method == 'DELETE':
            building_id = params.get('id', [None])[0]
            if building_id:
                cursor.execute('DELETE FROM buildings WHERE id=?', (building_id,))
                self.db.commit()

                # Queue for sync
                self.queue_sync_request(f'/api/buildings/{building_id}', 'DELETE', {'id': building_id})

                return {'success': True}

        return {'error': 'Invalid request'}

    def handle_locations(self, method, params, body):
        """Handle locations API"""
        cursor = self.db.cursor()

        if method == 'GET':
            building_id = params.get('building_id', [None])[0]
            if building_id:
                cursor.execute('SELECT * FROM locations WHERE building_id = ?', (building_id,))
            else:
                cursor.execute('SELECT * FROM locations')

            locations = []
            for row in cursor.fetchall():
                locations.append({
                    'id': row[0],
                    'building_id': row[1],
                    'institution_id': row[2],
                    'name': row[3],
                    'kind': row[4],
                    'category': row[5],
                    'code': row[6],
                    'floor_label': row[7],
                    'capacity': row[8],
                    'description': row[9],
                    'created_at': row[10],
                    'updated_at': row[11],
                    'synced': row[12]
                })
            return locations

        elif method == 'POST':
            data = json.loads(body) if body else {}
            cursor.execute('''
                INSERT INTO locations (building_id, institution_id, name, kind, category, code, floor_label, capacity, description, created_at, updated_at, synced)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
            ''', (
                data.get('building_id'),
                data.get('institution_id'),
                data.get('name'),
                data.get('kind'),
                data.get('category'),
                data.get('code'),
                data.get('floor_label'),
                data.get('capacity'),
                data.get('description'),
                datetime.now().isoformat(),
                datetime.now().isoformat()
            ))
            self.db.commit()

            # Queue for sync
            self.queue_sync_request('/api/locations', 'POST', data)

            return {'success': True, 'id': cursor.lastrowid}

        return {'error': 'Invalid request'}

    def handle_tours(self, method, params, body):
        """Handle tours API"""
        cursor = self.db.cursor()

        if method == 'GET':
            cursor.execute('SELECT * FROM tours')
            tours = []
            for row in cursor.fetchall():
                tours.append({
                    'id': row[0],
                    'institution_id': row[1],
                    'name': row[2],
                    'description': row[3],
                    'starting_scene_id': row[4],
                    'created_at': row[5],
                    'updated_at': row[6],
                    'synced': row[7]
                })
            return tours

        elif method == 'POST':
            data = json.loads(body) if body else {}
            cursor.execute('''
                INSERT INTO tours (institution_id, name, description, starting_scene_id, created_at, updated_at, synced)
                VALUES (?, ?, ?, ?, ?, ?, 0)
            ''', (
                data.get('institution_id'),
                data.get('name'),
                data.get('description'),
                data.get('starting_scene_id'),
                datetime.now().isoformat(),
                datetime.now().isoformat()
            ))
            self.db.commit()

            # Queue for sync
            self.queue_sync_request('/api/tours', 'POST', data)

            return {'success': True, 'id': cursor.lastrowid}

        return {'error': 'Invalid request'}

    def handle_institutions(self, method, params, body):
        """Handle institutions API"""
        cursor = self.db.cursor()

        if method == 'GET':
            cursor.execute('SELECT * FROM institutions')
            institutions = []
            for row in cursor.fetchall():
                institutions.append({
                    'id': row[0],
                    'name': row[1],
                    'slug': row[2],
                    'folder_path': row[3],
                    'institution_type': row[4],
                    'city': row[5],
                    'landing_mode': row[6],
                    'is_published': row[7],
                    'is_active': row[8],
                    'synced': row[9]
                })
            return institutions

        return {'error': 'Invalid request'}

    def handle_sync_status(self):
        """Get sync status"""
        cursor = self.db.cursor()
        cursor.execute('SELECT COUNT(*) FROM sync_queue WHERE status = "pending"')
        pending_count = cursor.fetchone()[0]

        return {
            'online': False,  # Always offline in this mode
            'pending_requests': pending_count,
            'last_sync': None
        }

    def handle_sync_queue(self, method, body):
        """Handle sync queue operations"""
        cursor = self.db.cursor()

        if method == 'GET':
            cursor.execute('SELECT * FROM sync_queue WHERE status = "pending" ORDER BY timestamp ASC')
            queue = []
            for row in cursor.fetchall():
                queue.append({
                    'id': row[0],
                    'endpoint': row[1],
                    'method': row[2],
                    'data': json.loads(row[3]) if row[3] else None,
                    'timestamp': row[4],
                    'status': row[5],
                    'retry_count': row[6],
                    'error': row[7]
                })
            return queue

        return {'error': 'Invalid request'}

    def queue_sync_request(self, endpoint, method, data):
        """Queue a request for sync when online"""
        cursor = self.db.cursor()
        cursor.execute('''
            INSERT INTO sync_queue (endpoint, method, data, timestamp, status, retry_count)
            VALUES (?, ?, ?, ?, 'pending', 0)
        ''', (
            endpoint,
            method,
            json.dumps(data) if data else None,
            datetime.now().isoformat()
        ))
        self.db.commit()

    def log_message(self, format, *args):
        """Custom log message"""
        print(f"[{datetime.now().strftime('%Y-%m-%d %H:%M:%S')}] {format % args}")


def sync_with_online_server():
    """Background thread to sync with online server when available"""
    while True:
        try:
            # Try to connect to online server
            # This would be implemented when you have an online server
            # For now, just check if sync queue has items
            time.sleep(60)  # Check every minute
        except Exception as e:
            print(f"[Sync] Error: {e}")
            time.sleep(60)


def main():
    """Main function to start the offline server"""
    print("=" * 60)
    print("Innovatech PH Offline Server")
    print("=" * 60)
    print(f"Project Root: {PROJECT_ROOT}")
    print(f"Database: {DB_PATH}")
    print(f"Port: {PORT}")
    print("=" * 60)
    print("Server starting...")
    print(f"Open http://localhost:{PORT} in your browser")
    print("Press Ctrl+C to stop")
    print("=" * 60)

    # Start sync thread
    sync_thread = threading.Thread(target=sync_with_online_server, daemon=True)
    sync_thread.start()

    # Start HTTP server
    try:
        server = HTTPServer(('0.0.0.0', PORT), OfflineServer)
        server.serve_forever()
    except KeyboardInterrupt:
        print("\nServer stopped")
        server.shutdown()


if __name__ == '__main__':
    main()
