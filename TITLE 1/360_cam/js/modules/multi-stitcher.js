// Multi-Stitcher - Main application logic for panorama stitching
import { ImageManager } from './image-manager.js';
import { Settings } from './settings.js';
import { WorkerClient } from './worker-client.js';

export class MultiStitcher {
    constructor() {
        this.imageManager = new ImageManager();
        this.settings = new Settings();
        this.workerClient = null;
        
        this.isProcessing = false;
        this.isCancelled = false;
        this.resultImage = null;
        this.resultCanvas = null;
        
        // UI elements
        this.elements = {};
        
        // Initialize
        this.init();
    }

    async init() {
        // Check browser compatibility
        const compatibility = WorkerClient.checkCompatibility();
        if (!compatibility.compatible) {
            this.showError(`Browser compatibility issues: ${compatibility.issues.join(', ')}`);
            return;
        }
        
        // Cache UI elements
        this.cacheElements();
        
        // Setup event listeners
        this.setupEventListeners();
        this.setupImageManagerListeners();
        this.setupSettingsListeners();
        
        // Initialize worker
        await this.initializeWorker();
        
        // Update UI
        this.updateUI();
    }

    cacheElements() {
        // Buttons
        this.elements.btnStitch = document.getElementById('btn-stitch');
        this.elements.btnDeleteSelected = document.getElementById('btn-delete-selected');
        this.elements.btnClearAll = document.getElementById('btn-clear-all');
        this.elements.btnAddImages = document.getElementById('btn-add-images');
        this.elements.btnAddTestImages = document.getElementById('btn-add-test-images');
        this.elements.btnToggleSettings = document.getElementById('btn-toggle-settings');
        this.elements.btnSaveResult = document.getElementById('btn-save-result');
        this.elements.btnSaveHD = document.getElementById('btn-save-hd');
        this.elements.btnCancel = document.getElementById('btn-cancel');
        
        // File input
        this.elements.fileInput = document.getElementById('file-input');
        
        // Settings
        this.elements.settingsPanel = document.getElementById('settings-panel');
        this.elements.detectorType = document.getElementById('detector-type');
        this.elements.projectionType = document.getElementById('projection-type');
        this.elements.blendType = document.getElementById('blend-type');
        this.elements.seamFinder = document.getElementById('seam-finder');
        this.elements.confidenceThreshold = document.getElementById('confidence-threshold');
        this.elements.confidenceValue = document.getElementById('confidence-value');
        this.elements.imageScale = document.getElementById('image-scale');
        this.elements.scaleValue = document.getElementById('scale-value');
        this.elements.maxFeatures = document.getElementById('max-features');
        this.elements.waveCorrection = document.getElementById('wave-correction');
        
        // Progress
        this.elements.progressContainer = document.getElementById('progress-container');
        this.elements.progressFill = document.getElementById('progress-fill');
        this.elements.progressText = document.getElementById('progress-text');
        
        // Images
        this.elements.inputImages = document.getElementById('input-images');
        this.elements.imageCount = document.getElementById('image-count');
        
        // Result
        this.elements.resultSection = document.getElementById('result-section');
        this.elements.resultImage = document.getElementById('result-image');
        this.elements.resultCanvas = document.getElementById('result-canvas');
        this.elements.resultDimensions = document.getElementById('result-dimensions');
        this.elements.resultSize = document.getElementById('result-size');
    }

    setupEventListeners() {
        // Button events - check if elements exist before adding listeners
        if (this.elements.btnStitch) {
            this.elements.btnStitch.addEventListener('click', () => this.performStitching());
        }
        if (this.elements.btnDeleteSelected) {
            this.elements.btnDeleteSelected.addEventListener('click', () => this.deleteSelected());
        }
        if (this.elements.btnClearAll) {
            this.elements.btnClearAll.addEventListener('click', () => this.clearAll());
        }
        if (this.elements.btnAddImages) {
            this.elements.btnAddImages.addEventListener('click', () => this.selectImages());
        }
        if (this.elements.btnAddTestImages) {
            this.elements.btnAddTestImages.addEventListener('click', () => this.loadTestImages());
        }
        if (this.elements.btnToggleSettings) {
            this.elements.btnToggleSettings.addEventListener('click', () => this.toggleSettings());
        }
        if (this.elements.btnCancel) {
            this.elements.btnCancel.addEventListener('click', () => this.cancelStitching());
        }
        
        // File input
        if (this.elements.fileInput) {
            this.elements.fileInput.addEventListener('change', (e) => this.handleFileSelect(e));
        }
        
        // Settings changes - check if elements exist
        if (this.elements.detectorType) {
            this.elements.detectorType.addEventListener('change', (e) => {
                this.settings.set('detectorType', e.target.value);
            });
        }
        
        if (this.elements.projectionType) {
            this.elements.projectionType.addEventListener('change', (e) => {
                this.settings.set('projectionType', e.target.value);
            });
        }
        
        if (this.elements.blendType) {
            this.elements.blendType.addEventListener('change', (e) => {
                this.settings.set('blendType', e.target.value);
            });
        }
        
        if (this.elements.seamFinder) {
            this.elements.seamFinder.addEventListener('change', (e) => {
                this.settings.set('seamFinderType', e.target.value);
            });
        }
        
        if (this.elements.confidenceThreshold) {
            this.elements.confidenceThreshold.addEventListener('input', (e) => {
                const value = parseFloat(e.target.value);
                this.settings.set('confidenceThreshold', value);
                if (this.elements.confidenceValue) {
                    this.elements.confidenceValue.textContent = value.toFixed(1);
                }
            });
        }
        
        if (this.elements.imageScale) {
            this.elements.imageScale.addEventListener('input', (e) => {
                const value = parseFloat(e.target.value);
                this.settings.set('imageScale', value);
                if (this.elements.scaleValue) {
                    this.elements.scaleValue.textContent = value.toFixed(1);
                }
            });
        }
        
        if (this.elements.maxFeatures) {
            this.elements.maxFeatures.addEventListener('change', (e) => {
                this.settings.set('maxFeatures', parseInt(e.target.value));
            });
        }
        
        if (this.elements.waveCorrection) {
            this.elements.waveCorrection.addEventListener('change', (e) => {
                this.settings.set('waveCorrection', e.target.checked);
            });
        }
        
        // Save result buttons
        if (this.elements.btnSaveResult) {
            this.elements.btnSaveResult.addEventListener('click', () => this.saveResult());
        }
        if (this.elements.btnSaveHD) {
            this.elements.btnSaveHD.addEventListener('click', () => this.saveResultHD());
        }
        
        // Drag and drop
        this.setupDragAndDrop();
        
        // Keyboard shortcuts
        this.setupKeyboardShortcuts();
    }

    setupDragAndDrop() {
        const dropZone = document.body;
        
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.add('drag-over');
        });
        
        dropZone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.remove('drag-over');
        });
        
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.remove('drag-over');
            
            const files = Array.from(e.dataTransfer.files).filter(file => 
                file.type.startsWith('image/')
            );
            
            if (files.length > 0) {
                this.imageManager.addImages(files);
            }
        });
    }

    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + O: Open images
            if ((e.ctrlKey || e.metaKey) && e.key === 'o') {
                e.preventDefault();
                this.selectImages();
            }
            
            // Ctrl/Cmd + S: Save result
            if ((e.ctrlKey || e.metaKey) && e.key === 's' && this.resultImage) {
                e.preventDefault();
                this.saveResult();
            }
            
            // Ctrl/Cmd + A: Select all images
            if ((e.ctrlKey || e.metaKey) && e.key === 'a' && !this.isProcessing) {
                e.preventDefault();
                this.imageManager.selectAll();
            }
            
            // Delete: Delete selected images
            if (e.key === 'Delete' && !this.isProcessing) {
                this.deleteSelected();
            }
            
            // Escape: Cancel operation or clear selection
            if (e.key === 'Escape') {
                if (this.isProcessing) {
                    this.cancelStitching();
                } else {
                    this.imageManager.clearSelection();
                }
            }
        });
    }

    setupImageManagerListeners() {
        // Update UI when images change
        this.imageManager.on('imagesUpdated', (images) => {
            this.renderInputImages(images);
            this.updateUI();
        });
        
        this.imageManager.on('selectionChanged', (data) => {
            this.updateSelectionUI(data.selected);
            this.updateUI();
        });
    }

    setupSettingsListeners() {
        this.settings.on('settingChanged', ({ key, value }) => {
            console.log(`Setting changed: ${key} = ${value}`);
        });
    }

    async initializeWorker() {
        try {
            this.showProgress('Initializing...', 0);
            
            // Create worker client
            this.workerClient = new WorkerClient('js/imgalign.worker.js');
            
            // Setup worker event listeners
            this.workerClient.on('progress', (data) => {
                this.showProgress(data.message || 'Processing...', data.percentage);
            });
            
            this.workerClient.on('error', (error) => {
                console.error('Worker error:', error);
                this.showError('Processing error occurred');
            });
            
            // Load OpenCV
            this.showProgress('Loading OpenCV...', 10);
            await this.workerClient.load('opencv_3_4_custom_O3.js');
            
            this.hideProgress();
            console.log('OpenCV loaded successfully');
            
        } catch (error) {
            console.error('Failed to initialize worker:', error);
            this.hideProgress();
            this.showError('Failed to initialize. Please refresh the page.');
        }
    }

    selectImages() {
        this.elements.fileInput.click();
    }

    async loadTestImages() {
        try {
            // List of test images from the test-images directory
            const testImageNames = [
                'hotspot_01_p45_y0.jpg',
                'hotspot_02_p45_y30.jpg',
                'hotspot_03_p45_y60.jpg',
                'hotspot_04_p45_y90.jpg',
                'hotspot_05_p45_y120.jpg',
                'hotspot_06_p45_y150.jpg',
                'hotspot_07_p45_y180.jpg',
                'hotspot_08_p45_y210.jpg',
                'hotspot_09_p45_y240.jpg',
                'hotspot_10_p45_y270.jpg',
                'hotspot_11_p45_y300.jpg',
                'hotspot_12_p45_y330.jpg',
                'hotspot_13_p0_y0.jpg',
                'hotspot_14_p0_y30.jpg',
                'hotspot_15_p0_y60.jpg',
                'hotspot_16_p0_y90.jpg',
                'hotspot_17_p0_y120.jpg',
                'hotspot_18_p0_y150.jpg',
                'hotspot_19_p0_y180.jpg',
                'hotspot_20_p0_y210.jpg',
                'hotspot_21_p0_y240.jpg',
                'hotspot_22_p0_y270.jpg',
                'hotspot_23_p0_y300.jpg',
                'hotspot_24_p0_y330.jpg',
                'hotspot_25_p-45_y0.jpg',
                'hotspot_26_p-45_y30.jpg',
                'hotspot_27_p-45_y60.jpg',
                'hotspot_28_p-45_y90.jpg',
                'hotspot_29_p-45_y120.jpg',
                'hotspot_30_p-45_y150.jpg',
                'hotspot_31_p-45_y180.jpg',
                'hotspot_32_p-45_y210.jpg',
                'hotspot_33_p-45_y240.jpg',
                'hotspot_34_p-45_y270.jpg',
                'hotspot_35_p-45_y300.jpg',
                'hotspot_36_p-45_y330.jpg'
            ];

            // Convert image names to File objects
            const testFiles = await Promise.all(
                testImageNames.map(async (imageName) => {
                    const response = await fetch(`test-images/${imageName}`);
                    const blob = await response.blob();
                    return new File([blob], imageName, { type: 'image/jpeg' });
                })
            );

            // Add the test images to the image manager
            await this.imageManager.addImages(testFiles);
            
        } catch (error) {
            console.error('Failed to load test images:', error);
            alert('Failed to load test images. Make sure the test-images directory is accessible.');
        }
    }

    async handleFileSelect(e) {
        const files = Array.from(e.target.files);
        if (files.length > 0) {
            await this.imageManager.addImages(files);
            e.target.value = ''; // Reset input
        }
    }

    deleteSelected() {
        if (this.imageManager.hasSelection()) {
            if (confirm('Delete selected images?')) {
                this.imageManager.deleteSelected();
            }
        }
    }

    clearAll() {
        if (this.imageManager.getImageCount() > 0) {
            if (confirm('Remove all images?')) {
                this.imageManager.clearAll();
            }
        }
    }

    toggleSettings() {
        this.elements.settingsPanel.classList.toggle('collapsed');
    }

    renderInputImages(images) {
        const container = this.elements.inputImages;
        
        if (images.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <span class="empty-icon">📸</span>
                    <p>No images loaded yet</p>
                    <p class="hint">Click "Add Images" or drag & drop photos here</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = '';
        
        images.forEach((image, index) => {
            const div = document.createElement('div');
            div.className = `image-item ${image.selected ? 'selected' : ''}`;
            div.dataset.imageId = image.id;
            
            div.innerHTML = `
                <img src="${image.url}" alt="${image.name}">
                <div class="image-number">${index + 1}</div>
                <button class="delete-btn" data-id="${image.id}">×</button>
                <div class="image-info">
                    <div class="field-of-view">
                        <label>FOV:</label>
                        <input type="number" 
                               value="${image.fieldOfView}" 
                               data-id="${image.id}"
                               min="10" 
                               max="180"
                               onclick="event.stopPropagation()">°
                    </div>
                </div>
            `;
            
            // Click to select
            div.addEventListener('click', (e) => {
                if (!e.target.closest('.delete-btn') && !e.target.closest('input')) {
                    this.imageManager.toggleSelection(image.id);
                    div.classList.toggle('selected');
                }
            });
            
            // Delete button
            const deleteBtn = div.querySelector('.delete-btn');
            deleteBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.imageManager.deleteImage(image.id);
            });
            
            // Field of view change
            const fovInput = div.querySelector('input');
            fovInput.addEventListener('input', (e) => {
                e.stopPropagation();
                this.imageManager.updateFieldOfView(image.id, e.target.value);
            });
            
            fovInput.addEventListener('click', (e) => {
                e.stopPropagation();
            });
            
            container.appendChild(div);
        });
        
        // Update image count
        this.elements.imageCount.textContent = images.length;
    }

    updateSelectionUI(selectedIds) {
        const items = document.querySelectorAll('.image-item');
        items.forEach(item => {
            const id = parseInt(item.dataset.imageId);
            if (selectedIds.includes(id)) {
                item.classList.add('selected');
            } else {
                item.classList.remove('selected');
            }
        });
    }

    updateUI() {
        const imageCount = this.imageManager.getImageCount();
        const hasSelection = this.imageManager.hasSelection();
        
        // Update buttons
        this.elements.btnStitch.disabled = imageCount < 2 || this.isProcessing;
        this.elements.btnDeleteSelected.disabled = !hasSelection || this.isProcessing;
        this.elements.btnClearAll.disabled = imageCount === 0 || this.isProcessing;
        
        // Update image count
        this.elements.imageCount.textContent = imageCount;
    }

    async performStitching() {
        if (this.isProcessing) {
            return;
        }
        
        try {
            // Get stitching data
            const useSelected = this.imageManager.hasSelection() && 
                              this.imageManager.getSelectedImages().length >= 2;
            const { imageDataArray, fieldsOfView, names } = this.imageManager.getStitchingData(useSelected);
            
            if (imageDataArray.length < 2) {
                alert('Please add at least 2 images to create a panorama.');
                return;
            }
            
            this.isProcessing = true;
            this.isCancelled = false;
            this.clearResult();
            
            console.log(`Starting stitching with ${imageDataArray.length} images`);
            
            // Step 1: Initialize stitcher with images
            this.showProgress('Preparing images...', 5);
            await this.workerClient.multiStitchInit(imageDataArray);
            
            if (this.isCancelled) {
                throw new Error('Cancelled by user');
            }
            
            // Step 2: Start stitching (feature detection and matching)
            this.showProgress('Detecting features...', 15);
            const startResult = await this.workerClient.multiStitchStart(
                fieldsOfView,
                this.settings.toWorkerFormat()
            );
            
            if (this.isCancelled) {
                throw new Error('Cancelled by user');
            }
            
            if (!startResult.stitchIndices || startResult.stitchIndices.length === 0) {
                throw new Error('No images could be matched. Try adjusting settings or using images with more overlap.');
            }
            
            console.log(`Successfully matched ${startResult.stitchIndices.length} images`);
            
            // Display initial result
            if (startResult.imageData) {
                this.updateResult(startResult.imageData);
            }
            
            // Report which images couldn't be matched
            const failedIndices = [];
            for (let i = 0; i < imageDataArray.length; i++) {
                if (!startResult.stitchIndices.includes(i)) {
                    failedIndices.push(i);
                }
            }
            
            if (failedIndices.length > 0) {
                const failedNames = failedIndices.map(i => names[i]).join(', ');
                console.warn(`Could not match: ${failedNames}`);
            }
            
            // Step 3: Progressive stitching
            const totalImages = startResult.stitchIndices.length;
            let stitchedCount = 1;
            
            while (stitchedCount < totalImages && !this.isCancelled) {
                const progress = 20 + (stitchedCount / totalImages) * 70;
                this.showProgress(`Stitching image ${stitchedCount + 1} of ${totalImages}...`, progress);
                
                const nextResult = await this.workerClient.multiStitchNext();
                
                if (nextResult.stitchedImagesN === 0) {
                    break;
                }
                
                stitchedCount = nextResult.stitchedImagesN;
                
                // Update preview with latest result
                if (nextResult.imageData) {
                    this.updateResult(nextResult.imageData);
                } else if (nextResult.imageDataSmall) {
                    this.updateResult(nextResult.imageDataSmall);
                }
            }
            
            if (this.isCancelled) {
                throw new Error('Cancelled by user');
            }
            
            this.showProgress('Finalizing panorama...', 95);
            
            // Reset worker for next operation
            await this.workerClient.reset();
            
            // Success
            this.hideProgress();
            this.showMessage('✨ Panorama created successfully!', 'success');
            
        } catch (error) {
            console.error('Stitching failed:', error);
            this.hideProgress();
            
            if (error.message !== 'Cancelled by user') {
                this.showError(`Stitching failed: ${error.message}`);
            }
            
            // Reset worker on error
            try {
                await this.workerClient.reset();
            } catch (e) {
                console.error('Failed to reset worker:', e);
            }
            
        } finally {
            this.isProcessing = false;
            this.isCancelled = false;
            this.updateUI();
        }
    }

    async cancelStitching() {
        if (this.isProcessing) {
            this.isCancelled = true;
            try {
                await this.workerClient.cancel();
            } catch (error) {
                console.error('Failed to cancel:', error);
            }
            this.hideProgress();
            this.showMessage('Operation cancelled', 'warning');
        }
    }

    updateResult(imageData) {
        if (!imageData) return;
        
        // Create canvas if not exists
        if (!this.resultCanvas) {
            this.resultCanvas = document.createElement('canvas');
        }
        
        // Set canvas dimensions
        this.resultCanvas.width = imageData.width;
        this.resultCanvas.height = imageData.height;
        
        // Draw image data
        const ctx = this.resultCanvas.getContext('2d');
        ctx.putImageData(imageData, 0, 0);
        
        // Convert to blob and display
        this.resultCanvas.toBlob((blob) => {
            if (this.resultImage) {
                URL.revokeObjectURL(this.resultImage);
            }
            
            const url = URL.createObjectURL(blob);
            this.resultImage = blob;
            
            // Update UI
            this.elements.resultImage.src = url;
            this.elements.resultSection.style.display = 'block';
            
            // Update dimensions info
            this.elements.resultDimensions.textContent = `${imageData.width} × ${imageData.height}px`;
            const sizeMB = (blob.size / (1024 * 1024)).toFixed(2);
            this.elements.resultSize.textContent = `${sizeMB} MB`;
        }, 'image/jpeg', 0.95);
    }

    clearResult() {
        if (this.resultImage) {
            URL.revokeObjectURL(this.elements.resultImage.src);
            this.resultImage = null;
        }
        this.elements.resultSection.style.display = 'none';
    }

    saveResult() {
        if (!this.resultImage) {
            alert('No panorama to save');
            return;
        }
        
        const link = document.createElement('a');
        link.href = URL.createObjectURL(this.resultImage);
        link.download = `panorama_${new Date().toISOString().slice(0, 10)}.jpg`;
        link.click();
    }

    async saveResultHD() {
        if (!this.resultCanvas) {
            alert('No panorama to save');
            return;
        }
        
        // Save at full quality
        this.resultCanvas.toBlob((blob) => {
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `panorama_HD_${new Date().toISOString().slice(0, 10)}.jpg`;
            link.click();
        }, 'image/jpeg', 1.0);
    }

    showProgress(text, percentage) {
        if (this.elements.progressContainer) {
            this.elements.progressContainer.style.display = 'block';
        }
        if (this.elements.progressFill) {
            this.elements.progressFill.style.width = `${percentage}%`;
        }
        if (this.elements.progressText) {
            this.elements.progressText.textContent = text;
        }
    }

    hideProgress() {
        if (this.elements.progressContainer) {
            this.elements.progressContainer.style.display = 'none';
        }
    }

    showMessage(message, type = 'info') {
        // Simple message display - could be enhanced with toast notifications
        console.log(`[${type.toUpperCase()}] ${message}`);
    }

    showError(message) {
        console.error(message);
        alert(message);
    }
}

// Don't auto-initialize - let it be created when needed by pages that have the required UI elements

export default MultiStitcher;