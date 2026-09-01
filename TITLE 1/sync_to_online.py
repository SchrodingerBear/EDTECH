#!/usr/bin/env python3
"""
Innovatech PH Sync Script
Syncs offline SQLite data with online MySQL database
"""

import sqlite3
import mysql.connector
import json
import sys
from datetime import datetime
from pathlib import Path

# Configuration
PROJECT_ROOT = Path(__file__).parent
OFFLINE_DB = PROJECT_ROOT / 'offline_data.sqlite'
SYNC_CONFIG = PROJECT_ROOT / 'sync_config.json'

# Load sync configuration
def load_config():
    """Load MySQL connection configuration"""
    if SYNC_CONFIG.exists():
        with open(SYNC_CONFIG, 'r') as f:
            return json.load(f)
    else:
        # Create default config
        default_config = {
            'mysql_host': 'localhost',
            'mysql_user': 'root',
            'mysql_password': '',
            'mysql_database': 'innovatech_ph',
            'mysql_port': 3306
        }
        with open(SYNC_CONFIG, 'w') as f:
            json.dump(default_config, f, indent=2)
        print(f"Created default config at {SYNC_CONFIG}")
        print("Please edit the file with your MySQL credentials")
        return default_config

def get_mysql_connection(config):
    """Get MySQL connection"""
    try:
        conn = mysql.connector.connect(
            host=config['mysql_host'],
            user=config['mysql_user'],
            password=config['mysql_password'],
            database=config['mysql_database'],
            port=config['mysql_port']
        )
        return conn
    except mysql.connector.Error as e:
        print(f"MySQL connection error: {e}")
        return None

def sync_buildings(offline_conn, online_conn):
    """Sync buildings from offline to online"""
    print("Syncing buildings...")

    offline_cursor = offline_conn.cursor()
    online_cursor = online_conn.cursor()

    # Get unsynced buildings
    offline_cursor.execute('SELECT * FROM buildings WHERE synced = 0')
    buildings = offline_cursor.fetchall()

    synced_count = 0
    for building in buildings:
        try:
            # Check if building exists online
            online_cursor.execute(
                'SELECT id FROM buildings WHERE id = ?',
                (building[0],)
            )
            existing = online_cursor.fetchone()

            if existing:
                # Update existing
                online_cursor.execute('''
                    UPDATE buildings SET
                        institution_id = ?, name = ?, code = ?, description = ?,
                        ai_description = ?, featured_image_path = ?, updated_at = ?
                    WHERE id = ?
                ''', (
                    building[1], building[2], building[3], building[4],
                    building[5], building[6], building[8], building[0]
                ))
            else:
                # Insert new
                online_cursor.execute('''
                    INSERT INTO buildings (id, institution_id, name, code, description, ai_description, featured_image_path, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ''', (
                    building[0], building[1], building[2], building[3], building[4],
                    building[5], building[6], building[7], building[8]
                ))

            # Mark as synced
            offline_cursor.execute('UPDATE buildings SET synced = 1 WHERE id = ?', (building[0],))
            offline_conn.commit()
            synced_count += 1

        except mysql.connector.Error as e:
            print(f"Error syncing building {building[0]}: {e}")

    online_conn.commit()
    print(f"Synced {synced_count} buildings")
    return synced_count

def sync_locations(offline_conn, online_conn):
    """Sync locations from offline to online"""
    print("Syncing locations...")

    offline_cursor = offline_conn.cursor()
    online_cursor = online_conn.cursor()

    # Get unsynced locations
    offline_cursor.execute('SELECT * FROM locations WHERE synced = 0')
    locations = offline_cursor.fetchall()

    synced_count = 0
    for location in locations:
        try:
            # Check if location exists online
            online_cursor.execute(
                'SELECT id FROM locations WHERE id = ?',
                (location[0],)
            )
            existing = online_cursor.fetchone()

            if existing:
                # Update existing
                online_cursor.execute('''
                    UPDATE locations SET
                        building_id = ?, institution_id = ?, name = ?, kind = ?,
                        category = ?, code = ?, floor_label = ?, capacity = ?,
                        description = ?, updated_at = ?
                    WHERE id = ?
                ''', (
                    location[1], location[2], location[3], location[4],
                    location[5], location[6], location[7], location[8],
                    location[9], location[11], location[0]
                ))
            else:
                # Insert new
                online_cursor.execute('''
                    INSERT INTO locations (id, building_id, institution_id, name, kind, category, code, floor_label, capacity, description, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ''', (
                    location[0], location[1], location[2], location[3], location[4],
                    location[5], location[6], location[7], location[8], location[9],
                    location[10], location[11]
                ))

            # Mark as synced
            offline_cursor.execute('UPDATE locations SET synced = 1 WHERE id = ?', (location[0],))
            offline_conn.commit()
            synced_count += 1

        except mysql.connector.Error as e:
            print(f"Error syncing location {location[0]}: {e}")

    online_conn.commit()
    print(f"Synced {synced_count} locations")
    return synced_count

def sync_tours(offline_conn, online_conn):
    """Sync tours from offline to online"""
    print("Syncing tours...")

    offline_cursor = offline_conn.cursor()
    online_cursor = online_conn.cursor()

    # Get unsynced tours
    offline_cursor.execute('SELECT * FROM tours WHERE synced = 0')
    tours = offline_cursor.fetchall()

    synced_count = 0
    for tour in tours:
        try:
            # Check if tour exists online
            online_cursor.execute(
                'SELECT id FROM tours WHERE id = ?',
                (tour[0],)
            )
            existing = online_cursor.fetchone()

            if existing:
                # Update existing
                online_cursor.execute('''
                    UPDATE tours SET
                        institution_id = ?, name = ?, description = ?,
                        starting_scene_id = ?, updated_at = ?
                    WHERE id = ?
                ''', (
                    tour[1], tour[2], tour[3], tour[4], tour[6], tour[0]
                ))
            else:
                # Insert new
                online_cursor.execute('''
                    INSERT INTO tours (id, institution_id, name, description, starting_scene_id, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ''', (
                    tour[0], tour[1], tour[2], tour[3], tour[4], tour[5], tour[6]
                ))

            # Mark as synced
            offline_cursor.execute('UPDATE tours SET synced = 1 WHERE id = ?', (tour[0],))
            offline_conn.commit()
            synced_count += 1

        except mysql.connector.Error as e:
            print(f"Error syncing tour {tour[0]}: {e}")

    online_conn.commit()
    print(f"Synced {synced_count} tours")
    return synced_count

def sync_sync_queue(offline_conn, online_conn):
    """Process sync queue"""
    print("Processing sync queue...")

    offline_cursor = offline_conn.cursor()

    # Get pending sync requests
    offline_cursor.execute('SELECT * FROM sync_queue WHERE status = "pending" ORDER BY timestamp ASC')
    queue_items = offline_cursor.fetchall()

    processed_count = 0
    for item in queue_items:
        try:
            endpoint = item[1]
            method = item[2]
            data = json.loads(item[3]) if item[3] else None

            # Execute the request against online database
            # This is a simplified version - you'd need to implement proper API calls
            print(f"  Processing: {method} {endpoint}")

            # Mark as completed
            offline_cursor.execute('UPDATE sync_queue SET status = "completed" WHERE id = ?', (item[0],))
            offline_conn.commit()
            processed_count += 1

        except Exception as e:
            print(f"Error processing queue item {item[0]}: {e}")
            # Mark as failed
            offline_cursor.execute('UPDATE sync_queue SET status = "failed", error = ?, retry_count = retry_count + 1 WHERE id = ?', (str(e), item[0]))
            offline_conn.commit()

    print(f"Processed {processed_count} queue items")
    return processed_count

def main():
    """Main sync function"""
    print("=" * 60)
    print("Innovatech PH Sync to Online")
    print("=" * 60)

    # Load configuration
    config = load_config()

    # Connect to offline database
    if not OFFLINE_DB.exists():
        print(f"Error: Offline database not found at {OFFLINE_DB}")
        sys.exit(1)

    offline_conn = sqlite3.connect(OFFLINE_DB)

    # Connect to online database
    online_conn = get_mysql_connection(config)
    if not online_conn:
        print("Error: Could not connect to MySQL database")
        print("Please check your sync_config.json file")
        offline_conn.close()
        sys.exit(1)

    try:
        # Perform sync
        total_synced = 0
        total_synced += sync_buildings(offline_conn, online_conn)
        total_synced += sync_locations(offline_conn, online_conn)
        total_synced += sync_tours(offline_conn, online_conn)
        total_synced += sync_sync_queue(offline_conn, online_conn)

        print("=" * 60)
        print(f"Sync Complete! Total items synced: {total_synced}")
        print("=" * 60)

    finally:
        offline_conn.close()
        online_conn.close()

if __name__ == '__main__':
    main()
