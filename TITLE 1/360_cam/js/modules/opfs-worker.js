/**
 * OPFS Worker for cross-browser file system operations
 * Handles both Chrome (createWritable) and Safari (createSyncAccessHandle) approaches
 */

let rootDir = null;
let panoramasDir = null;

/**
 * Initialize OPFS access
 */
async function initOPFS() {
    try {
        rootDir = await navigator.storage.getDirectory();
        
        // Create panoramas directory
        panoramasDir = await rootDir.getDirectoryHandle('panoramas', { create: true });
        
        return true;
    } catch (error) {
        // Don't log error for expected private browsing failure
        if (error.message && error.message.includes('transient')) {
            console.log('OPFS unavailable (likely private browsing)');
        } else {
            console.error('Failed to initialize OPFS in worker:', error);
        }
        return false;
    }
}

/**
 * Save a file to OPFS using the appropriate method
 */
async function saveFile(fileName, data) {
    if (!panoramasDir) {
        throw new Error('OPFS not initialized');
    }
    
    try {
        const fileHandle = await panoramasDir.getFileHandle(fileName, { create: true });
        
        // Try createWritable first (Chrome/Edge)
        if (typeof fileHandle.createWritable === 'function') {
            const writable = await fileHandle.createWritable();
            await writable.write(data);
            await writable.close();
            return true;
        }
        
        // Fall back to createSyncAccessHandle (Safari)
        if (typeof fileHandle.createSyncAccessHandle === 'function') {
            const accessHandle = await fileHandle.createSyncAccessHandle();
            
            // Convert data to ArrayBuffer if needed
            let buffer;
            if (data instanceof Blob) {
                buffer = await data.arrayBuffer();
            } else if (data instanceof ArrayBuffer) {
                buffer = data;
            } else {
                throw new Error('Unsupported data type for sync access');
            }
            
            // Write the data
            accessHandle.write(new Uint8Array(buffer), { at: 0 });
            
            // Truncate to exact size
            accessHandle.truncate(buffer.byteLength);
            
            // Close the handle
            accessHandle.close();
            
            return true;
        }
        
        throw new Error('No supported write method available');
    } catch (error) {
        console.error('Failed to save file:', error);
        throw error;
    }
}

/**
 * Load a file from OPFS
 */
async function loadFile(fileName) {
    if (!panoramasDir) {
        throw new Error('OPFS not initialized');
    }
    
    try {
        const fileHandle = await panoramasDir.getFileHandle(fileName);
        const file = await fileHandle.getFile();
        const arrayBuffer = await file.arrayBuffer();
        return arrayBuffer;
    } catch (error) {
        if (error.name === 'NotFoundError') {
            return null;
        }
        throw error;
    }
}

/**
 * Delete a file from OPFS
 */
async function deleteFile(fileName) {
    if (!panoramasDir) {
        throw new Error('OPFS not initialized');
    }
    
    try {
        await panoramasDir.removeEntry(fileName);
        return true;
    } catch (error) {
        if (error.name === 'NotFoundError') {
            return false;
        }
        throw error;
    }
}

/**
 * List all files in the panoramas directory
 */
async function listFiles() {
    if (!panoramasDir) {
        throw new Error('OPFS not initialized');
    }
    
    const files = [];
    try {
        for await (const entry of panoramasDir.values()) {
            if (entry.kind === 'file') {
                const file = await entry.getFile();
                files.push({
                    name: entry.name,
                    size: file.size,
                    lastModified: file.lastModified
                });
            }
        }
    } catch (error) {
        console.error('Error listing files:', error);
    }
    
    return files;
}

/**
 * Get storage estimate
 */
async function getStorageInfo() {
    try {
        const estimate = await navigator.storage.estimate();
        return {
            usage: estimate.usage || 0,
            quota: estimate.quota || 0
        };
    } catch (error) {
        console.error('Error getting storage info:', error);
        return { usage: 0, quota: 0 };
    }
}

/**
 * Handle messages from main thread
 */
self.addEventListener('message', async (event) => {
    const { id, action, payload } = event.data;
    
    try {
        let result;
        
        switch (action) {
            case 'init':
                result = await initOPFS();
                break;
                
            case 'save':
                const { fileName, data } = payload;
                result = await saveFile(fileName, data);
                break;
                
            case 'load':
                result = await loadFile(payload.fileName);
                break;
                
            case 'delete':
                result = await deleteFile(payload.fileName);
                break;
                
            case 'list':
                result = await listFiles();
                break;
                
            case 'storage':
                result = await getStorageInfo();
                break;
                
            default:
                throw new Error(`Unknown action: ${action}`);
        }
        
        // Send success response
        self.postMessage({
            id,
            success: true,
            result
        });
        
    } catch (error) {
        // Send error response
        self.postMessage({
            id,
            success: false,
            error: error.message
        });
    }
});

// Initialize on worker start
initOPFS().then(success => {
    console.log('OPFS Worker initialized:', success);
});