// Image Manager - Handles image state and operations
export class ImageManager {
    constructor() {
        this.images = [];
        this.selectedIndices = new Set();
        this.listeners = new Map();
        this.nextId = 0;
    }

    // Event system
    on(event, callback) {
        if (!this.listeners.has(event)) {
            this.listeners.set(event, []);
        }
        this.listeners.get(event).push(callback);
    }

    emit(event, data) {
        if (this.listeners.has(event)) {
            this.listeners.get(event).forEach(callback => {
                try {
                    callback(data);
                } catch (error) {
                    console.error(`Error in event listener for ${event}:`, error);
                }
            });
        }
    }

    // Add images from files
    async addImages(files) {
        const newImages = [];
        
        for (const file of files) {
            try {
                // Check file type
                if (!file.type.startsWith('image/')) {
                    console.warn(`Skipping non-image file: ${file.name}`);
                    continue;
                }

                const imageData = await this.loadImage(file);
                const fieldOfView = await this.extractFieldOfView(file);
                const url = URL.createObjectURL(file);
                
                const image = {
                    id: this.nextId++,
                    file: file,
                    name: file.name,
                    url: url,
                    imageData: imageData,
                    fieldOfView: fieldOfView || 90,
                    selected: false,
                    width: imageData.width,
                    height: imageData.height,
                    size: file.size
                };
                
                this.images.push(image);
                newImages.push(image);
                
            } catch (error) {
                console.error(`Failed to load image ${file.name}:`, error);
            }
        }
        
        if (newImages.length > 0) {
            this.emit('imagesAdded', newImages);
            this.emit('imagesUpdated', this.images);
        }
        
        return newImages;
    }

    // Load image and convert to ImageData
    async loadImage(file, maxSize = 2000) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            const url = URL.createObjectURL(file);
            
            img.onload = () => {
                URL.revokeObjectURL(url);
                
                // Calculate scaling to limit size
                let width = img.width;
                let height = img.height;
                const scale = Math.min(1, maxSize / Math.max(width, height));
                
                if (scale < 1) {
                    width = Math.floor(width * scale);
                    height = Math.floor(height * scale);
                }
                
                // Draw to canvas and get ImageData
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                canvas.width = width;
                canvas.height = height;
                
                ctx.drawImage(img, 0, 0, width, height);
                const imageData = ctx.getImageData(0, 0, width, height);
                
                resolve(imageData);
            };
            
            img.onerror = () => {
                URL.revokeObjectURL(url);
                reject(new Error(`Failed to load image: ${file.name}`));
            };
            
            img.src = url;
        });
    }

    // Extract field of view from EXIF data
    async extractFieldOfView(file) {
        // Check if we have EXIF.js available
        if (typeof EXIF !== 'undefined') {
            return new Promise((resolve) => {
                EXIF.getData(file, function() {
                    const make = EXIF.getTag(this, 'Make');
                    const model = EXIF.getTag(this, 'Model');
                    const focalLength = EXIF.getTag(this, 'FocalLength');
                    const focalLength35 = EXIF.getTag(this, 'FocalLengthIn35mmFilm');
                    
                    // Calculate FOV from focal length if available
                    if (focalLength35) {
                        // Approximate horizontal FOV from 35mm equivalent focal length
                        const fov = 2 * Math.atan(36 / (2 * focalLength35)) * (180 / Math.PI);
                        resolve(Math.round(fov));
                    } else if (focalLength) {
                        // Rough approximation if we don't have 35mm equivalent
                        const sensorWidth = 36; // Assume full frame
                        const fov = 2 * Math.atan(sensorWidth / (2 * focalLength)) * (180 / Math.PI);
                        resolve(Math.round(fov));
                    } else {
                        // Default FOV for common cameras
                        if (model && model.toLowerCase().includes('gopro')) {
                            resolve(118); // GoPro wide angle
                        } else if (model && model.toLowerCase().includes('iphone')) {
                            resolve(75); // iPhone main camera
                        } else {
                            resolve(90); // Default
                        }
                    }
                });
            });
        }
        
        // Return default if EXIF not available
        return 69;
    }

    // Toggle image selection
    toggleSelection(id) {
        const image = this.images.find(img => img.id === id);
        if (!image) return;
        
        if (this.selectedIndices.has(id)) {
            this.selectedIndices.delete(id);
            image.selected = false;
        } else {
            this.selectedIndices.add(id);
            image.selected = true;
        }
        
        this.emit('selectionChanged', {
            selected: Array.from(this.selectedIndices),
            image: image
        });
    }

    // Select all images
    selectAll() {
        this.images.forEach(image => {
            this.selectedIndices.add(image.id);
            image.selected = true;
        });
        
        this.emit('selectionChanged', {
            selected: Array.from(this.selectedIndices),
            image: null
        });
    }

    // Clear selection
    clearSelection() {
        this.selectedIndices.clear();
        this.images.forEach(image => {
            image.selected = false;
        });
        
        this.emit('selectionChanged', {
            selected: [],
            image: null
        });
    }

    // Delete selected images
    deleteSelected() {
        if (this.selectedIndices.size === 0) return;
        
        const toDelete = Array.from(this.selectedIndices);
        
        // Clean up URLs and remove from array
        this.images = this.images.filter(img => {
            if (toDelete.includes(img.id)) {
                URL.revokeObjectURL(img.url);
                return false;
            }
            return true;
        });
        
        this.selectedIndices.clear();
        
        this.emit('imagesDeleted', toDelete);
        this.emit('imagesUpdated', this.images);
    }

    // Delete specific image
    deleteImage(id) {
        const index = this.images.findIndex(img => img.id === id);
        if (index === -1) return;
        
        const image = this.images[index];
        URL.revokeObjectURL(image.url);
        this.images.splice(index, 1);
        this.selectedIndices.delete(id);
        
        this.emit('imagesDeleted', [id]);
        this.emit('imagesUpdated', this.images);
    }

    // Clear all images
    clearAll() {
        this.images.forEach(img => URL.revokeObjectURL(img.url));
        this.images = [];
        this.selectedIndices.clear();
        
        this.emit('imagesCleared');
        this.emit('imagesUpdated', this.images);
    }

    // Update field of view for an image
    updateFieldOfView(id, value) {
        const image = this.images.find(img => img.id === id);
        if (!image) return;
        
        const fov = parseFloat(value);
        if (isNaN(fov) || fov < 10 || fov > 180) return;
        
        image.fieldOfView = fov;
        this.emit('fieldOfViewChanged', { id, fieldOfView: fov });
    }

    // Get data for stitching
    getStitchingData(useSelected = false) {
        const imagesToUse = useSelected && this.selectedIndices.size > 0
            ? this.images.filter(img => this.selectedIndices.has(img.id))
            : this.images;
        
        if (imagesToUse.length < 2) {
            throw new Error('At least 2 images are required for stitching');
        }
        
        return {
            images: imagesToUse,
            imageDataArray: imagesToUse.map(img => img.imageData),
            fieldsOfView: imagesToUse.map(img => img.fieldOfView),
            names: imagesToUse.map(img => img.name)
        };
    }

    // Reorder images
    reorderImages(fromId, toId) {
        const fromIndex = this.images.findIndex(img => img.id === fromId);
        const toIndex = this.images.findIndex(img => img.id === toId);
        
        if (fromIndex === -1 || toIndex === -1) return;
        
        // Remove and insert
        const [image] = this.images.splice(fromIndex, 1);
        this.images.splice(toIndex, 0, image);
        
        this.emit('imagesReordered', { fromId, toId });
        this.emit('imagesUpdated', this.images);
    }

    // Get image by ID
    getImage(id) {
        return this.images.find(img => img.id === id);
    }

    // Get selected images
    getSelectedImages() {
        return this.images.filter(img => this.selectedIndices.has(img.id));
    }

    // Get image count
    getImageCount() {
        return this.images.length;
    }

    // Check if any images are selected
    hasSelection() {
        return this.selectedIndices.size > 0;
    }

    // Export state for saving/loading
    exportState() {
        return {
            images: this.images.map(img => ({
                id: img.id,
                name: img.name,
                fieldOfView: img.fieldOfView,
                width: img.width,
                height: img.height,
                size: img.size
            })),
            selectedIndices: Array.from(this.selectedIndices)
        };
    }

    // Import state (for loading saved sessions)
    async importState(state, files) {
        // Match files with saved state
        const fileMap = new Map();
        files.forEach(file => {
            fileMap.set(file.name, file);
        });
        
        for (const savedImage of state.images) {
            const file = fileMap.get(savedImage.name);
            if (file) {
                const imageData = await this.loadImage(file);
                const url = URL.createObjectURL(file);
                
                const image = {
                    ...savedImage,
                    file: file,
                    url: url,
                    imageData: imageData,
                    selected: state.selectedIndices.includes(savedImage.id)
                };
                
                this.images.push(image);
                if (image.selected) {
                    this.selectedIndices.add(image.id);
                }
            }
        }
        
        this.emit('imagesUpdated', this.images);
    }
}

export default ImageManager;