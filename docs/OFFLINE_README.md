# Innovatech PH - Offline PWA System

Complete offline-first Progressive Web App system for Thesis 1. Works without internet connection and syncs data when online.

## Features

- ✅ **Offline-First Architecture**: Works completely offline
- ✅ **Auto-Sync**: Automatically syncs data when internet is available
- ✅ **Local Database**: SQLite for offline storage
- ✅ **PWA Support**: Installable as desktop/mobile app
- ✅ **Service Worker**: Caches assets for offline access
- ✅ **IndexedDB**: Browser-based local storage
- ✅ **Python Server**: Local offline server

## Quick Start

### 1. Setup (One-time)

**Windows:**
```bash
setup_offline.bat
```

**Linux/Mac:**
```bash
chmod +x setup_offline.sh
./setup_offline.sh
```

### 2. Start Offline Server

**Windows:**
```bash
start_offline.bat
```

**Linux/Mac:**
```bash
./start_offline.sh
```

Or directly:
```bash
python offline_server.py
```

### 3. Access the App

Open browser: `http://localhost:8000`

## File Structure

```
├── manifest.json              # PWA manifest
├── sw.js                      # Service worker
├── offline.html               # Offline fallback page
├── offline_server.py          # Python offline server
├── sync_to_online.py          # Sync script (offline → online)
├── setup_offline.bat          # Windows setup script
├── setup_offline.sh           # Linux/Mac setup script
├── start_offline.bat          # Windows start script
├── sync_data.bat              # Windows sync script
├── create_icons.py            # PWA icon generator
├── pwa-install.html           # PWA installation page
├── assets/
│   ├── js/
│   │   ├── offline-db.js      # IndexedDB wrapper
│   │   └── sync-manager.js    # Sync manager
│   ├── css/
│   │   └── fontawesome.min.css
│   └── webfonts/
│       └── fa-solid-900.woff2
└── offline_data.sqlite       # Local SQLite database (auto-created)
```

## How It Works

### Online Mode
1. App loads from PHP server
2. Service worker caches assets
3. Data stored in MySQL database
4. Changes synced immediately

### Offline Mode
1. App loads from Python server
2. Service worker serves cached assets
3. Data stored in SQLite database
4. Changes queued for sync

### Sync Process
1. When internet becomes available
2. Sync manager detects online status
3. Queued changes sent to MySQL database
4. MySQL data refreshed in local SQLite
5. Sync queue cleared

## Configuration

### MySQL Connection (for sync)

Edit `sync_config.json`:
```json
{
  "mysql_host": "localhost",
  "mysql_user": "root",
  "mysql_password": "your_password",
  "mysql_database": "innovatech_ph",
  "mysql_port": 3306
}
```

### Server Port

Edit `offline_server.py`:
```python
PORT = 8000  # Change to your preferred port
```

## Manual Sync

To manually sync offline data to online MySQL:

```bash
python sync_to_online.py
```

Or use the scripts:
- Windows: `sync_data.bat`
- Linux/Mac: `./sync_data.sh`

## PWA Installation

1. Open the app in Chrome/Edge/Safari
2. Look for install prompt
3. Or visit `pwa-install.html`
4. Click "Install App"

## API Endpoints (Offline Server)

The Python server provides these endpoints:

- `GET /api/buildings` - Get all buildings
- `POST /api/buildings` - Create building
- `PUT /api/buildings` - Update building
- `DELETE /api/buildings` - Delete building
- `GET /api/locations` - Get all locations
- `POST /api/locations` - Create location
- `GET /api/tours` - Get all tours
- `POST /api/tours` - Create tour
- `GET /api/institutions` - Get all institutions
- `GET /api/sync/status` - Get sync status
- `GET /api/sync/queue` - Get sync queue

## Browser Compatibility

- ✅ Chrome/Edge (Recommended)
- ✅ Firefox
- ✅ Safari (iOS)
- ⚠️ Safari (macOS) - Limited PWA support

## Troubleshooting

### Service Worker Not Registering
- Clear browser cache
- Check browser console for errors
- Ensure sw.js is in root directory

### Sync Not Working
- Check MySQL credentials in sync_config.json
- Ensure MySQL server is running
- Check sync queue in offline_data.sqlite

### Python Server Not Starting
- Ensure Python 3 is installed
- Check if port 8000 is already in use
- Try running with different port

### Icons Not Showing
- Run `python create_icons.py` to generate icons
- Ensure icons are in `public/` folder
- Check manifest.json paths

## Development

### Adding New Offline Features

1. Add table to `offline_server.py` init_database()
2. Add API handler in `offline_server.py`
3. Add sync logic in `sync_to_online.py`
4. Update IndexedDB schema in `offline-db.js`

### Testing Offline Mode

1. Start Python server
2. Open DevTools → Network tab
3. Select "Offline" throttling
4. Test app functionality
5. Check sync queue

## Performance

- **Offline**: Uses local SQLite - fast
- **Online**: Uses MySQL - fast
- **Sync**: Batch processing - efficient
- **Caching**: Service worker - instant loads

## Security

- SQLite data stored locally (safe)
- Sync uses MySQL credentials (secure)
- No data sent to external servers
- All processing happens locally

## Limitations

- File uploads work differently offline
- Some advanced features require online
- First load requires internet (for caching)
- Sync requires manual trigger or online detection

## License

This is part of the Innovatech PH Thesis 1 project.

## Support

For issues or questions, check the console logs:
- Browser console for frontend issues
- Python server terminal for backend issues
- Sync script output for sync issues
