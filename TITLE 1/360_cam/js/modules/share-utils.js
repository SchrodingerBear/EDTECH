/**
 * Web Share API Utility - Native Sharing for Photospheres
 * 
 * This module provides cross-platform sharing functionality for photospheres,
 * leveraging the native Web Share API on mobile devices and falling back to
 * direct download on desktop. It includes a glass-morphic share modal UI and
 * clipboard support for modern browsers.
 * 
 * ARCHITECTURE:
 * The module uses progressive enhancement, starting with the most advanced
 * sharing method and falling back gracefully:
 * 1. Web Share API with file support (best - native share sheet)
 * 2. Web Share API with data URL (fallback for limited file support)
 * 3. Direct download (desktop/unsupported browsers)
 * 4. Clipboard API (optional - for copy functionality)
 * 
 * WEB SHARE API FLOW:
 * Mobile browsers that support Web Share API will show the native share sheet,
 * allowing users to share directly to:
 * - Social media apps (Facebook, Instagram, Twitter)
 * - Messaging apps (WhatsApp, Messages, Telegram)
 * - Cloud storage (Google Photos, iCloud, Dropbox)
 * - Email clients
 * - Any app that accepts image files
 * 
 * BROWSER SUPPORT:
 * - iOS Safari 12.2+ (full support)
 * - Chrome Android 75+ (full support)
 * - Edge 93+ (desktop - limited)
 * - Firefox Android 79+ (basic support)
 * - Desktop browsers: Fallback to download
 * 
 * SHARE DATA STRUCTURE:
 * {
 *   files: [File],           // The JPEG file
 *   title: '360° Panorama',   // Share title
 *   text: 'Description',     // Share description
 *   url: dataURL            // Fallback URL if files not supported
 * }
 * 
 * GLASS-MORPHIC MODAL:
 * The share modal provides a custom UI with glass-morphic styling:
 * - Backdrop blur effect
 * - Semi-transparent background
 * - Smooth animations (lift-in/out)
 * - Success states for clipboard copy
 * - Responsive design
 * 
 * CLIPBOARD API:
 * Modern browsers support copying images to clipboard:
 * - Creates ClipboardItem with blob
 * - Allows pasting into image editors
 * - Shows success feedback
 * 
 * ERROR HANDLING:
 * - AbortError: User cancelled share (not an error)
 * - NotAllowedError: Permission denied
 * - TypeError: Invalid share data
 * - Graceful fallback at each level
 * 
 * SECURITY CONSIDERATIONS:
 * - Web Share API requires HTTPS
 * - User gesture required (click/tap)
 * - No automatic sharing without user action
 * - Clipboard requires permission
 * 
 * MEMORY MANAGEMENT:
 * - Blob URLs revoked after download
 * - Modal removed from DOM after close
 * - Event listeners cleaned up
 * 
 * SINGLETON PATTERN:
 * Exported as singleton instance to ensure single modal and consistent state
 * 
 * @module PhotoSphereSharer
 */

export class PhotoSphereSharer {
    /**
     * Initialize the sharer with capability detection
     * Checks for Web Share API and file sharing support
     */
    constructor() {
        // Check if basic Web Share API is available
        this.supportsWebShare = 'share' in navigator;
        // Check if file sharing is supported (requires canShare method)
        this.supportsFileSharing = this.supportsWebShare && 'canShare' in navigator;
    }

    /**
     * Main entry point for sharing a photosphere
     * Tries native sharing first, falls back to download
     * @param {Blob} blob - Image blob to share
     * @param {string} filename - Filename for the image
     * @param {Object} metadata - Optional metadata (currently unused)
     * @returns {Promise<boolean>} True if share was successful
     */
    async sharePhotosphere(blob, filename = 'photosphere.jpg', metadata = {}) {
        try {
            // Attempt native share on mobile
            if (await this.tryWebShare(blob, filename)) {
                return true;
            }
            
            // Fallback to download for desktop or unsupported browsers
            this.downloadBlob(blob, filename);
            return true;
            
        } catch (error) {
            console.error('Share failed:', error);
            // Final fallback - ensure file is downloaded
            this.downloadBlob(blob, filename);
            return false;
        }
    }

    /**
     * Attempt to share using Web Share API
     * Tries file sharing first, then data URL sharing
     * @param {Blob} blob - Image blob to share
     * @param {string} filename - Filename for the image
     * @returns {Promise<boolean>} True if share was initiated
     */
    async tryWebShare(blob, filename) {
        if (!this.supportsFileSharing) return false;

        // Create File object from blob
        const file = new File([blob], filename, { type: 'image/jpeg' });
        const shareData = {
            files: [file],
            title: '360° Panorama',
            text: '360° panorama captured with Innovatech PH 360 Camera'
        };

        // Verify that the browser can share this type of data
        if (navigator.canShare && navigator.canShare(shareData)) {
            try {
                await navigator.share(shareData);
                return true;
            } catch (error) {
                if (error.name === 'AbortError') {
                    // User cancelled - this is not an error
                    return true;
                }
                console.warn('Web Share failed:', error);
            }
        }
        
        // Fallback: Try sharing as data URL if file sharing isn't supported
        if (this.supportsWebShare) {
            try {
                // Convert blob to data URL
                const reader = new FileReader();
                const dataUrl = await new Promise((resolve) => {
                    reader.onloadend = () => resolve(reader.result);
                    reader.readAsDataURL(blob);
                });
                
                // Share as URL instead of file
                await navigator.share({
                    title: '360° Panorama',
                    text: '360° panorama captured with Innovatech PH 360 Camera',
                    url: dataUrl  // Some apps can handle data URLs
                });
                return true;
            } catch (error) {
                console.warn('Web Share without files failed:', error);
            }
        }
        
        return false;
    }

    /**
     * Show custom glass-morphic share modal
     * Currently unused but available for custom share UI
     * @param {Blob} blob - Image blob to share
     * @param {string} filename - Filename for the image
     */
    showShareModal(blob, filename) {
        const modal = this.createShareModal(blob, filename);
        document.body.appendChild(modal);
        
        // Trigger lift-in animation after DOM insertion
        requestAnimationFrame(() => {
            modal.classList.add('visible');
        });
    }

    /**
     * Create glass-morphic share modal UI
     * Provides download and clipboard copy options
     * @param {Blob} blob - Image blob to share
     * @param {string} filename - Filename for the image
     * @returns {HTMLElement} Modal element
     */
    createShareModal(blob, filename) {
        const modal = document.createElement('div');
        modal.className = 'share-modal-overlay';
        modal.innerHTML = `
            <div class="share-modal-card">
                <div class="card-header">
                    <h2 class="card-title">Share Panorama</h2>
                    <button class="card-close-btn">
                        <img src="./img/x.svg" style="width: 20px; height: 20px; filter: brightness(0) invert(1);" alt="Close">
                    </button>
                </div>
                <div class="card-content">
                    <div class="share-options">
                        <button class="share-option-btn download-btn">
                            <img src="./img/share.svg" style="width: 24px; height: 24px; filter: brightness(0) invert(1);" alt="">
                            <span>Save to Device</span>
                        </button>
                        <button class="share-option-btn copy-btn">
                            <img src="./img/copy.svg" style="width: 24px; height: 24px; filter: brightness(0) invert(1);" alt="">
                            <span>Copy to Clipboard</span>
                        </button>
                    </div>
                </div>
            </div>
        `;

        // Inject CSS styles once (checks for existing styles)
        if (!document.getElementById('share-modal-styles')) {
            const style = document.createElement('style');
            style.id = 'share-modal-styles';
            style.textContent = `
                .share-modal-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.7);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    z-index: 10000;
                    opacity: 0;
                    transition: opacity 0.3s ease;
                }
                
                .share-modal-overlay.visible {
                    opacity: 1;
                }
                
                .share-modal-card {
                    width: 90%;
                    max-width: 340px;
                    background: rgba(255, 255, 255, 0.1);
                    backdrop-filter: blur(25px);
                    -webkit-backdrop-filter: blur(25px);
                    border-radius: 35px;
                    border: 1px solid rgba(255, 255, 255, 0.2);
                    box-shadow: 
                        0 8px 32px rgba(0, 0, 0, 0.3),
                        inset 0 1px 0 rgba(255, 255, 255, 0.2);
                    transform: translateY(20px) scale(0.95);
                    transition: transform 0.3s cubic-bezier(.2,.8,.2,1);
                }
                
                .share-modal-overlay.visible .share-modal-card {
                    transform: translateY(0) scale(1);
                }
                
                .share-modal-card .card-header {
                    padding: 20px;
                    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
                    position: relative;
                }
                
                .share-modal-card .card-title {
                    margin: 0;
                    font-size: 20px;
                    font-weight: 600;
                    color: white;
                    text-align: center;
                }
                
                .share-modal-card .card-close-btn {
                    position: absolute;
                    top: 15px;
                    right: 15px;
                    width: 36px;
                    height: 36px;
                    border-radius: 50%;
                    border: 1px solid rgba(255, 255, 255, 0.3);
                    background: rgba(255, 255, 255, 0.15);
                    backdrop-filter: blur(10px);
                    -webkit-backdrop-filter: blur(10px);
                    cursor: pointer;
                    transition: all 0.3s ease;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                
                .share-modal-card .card-close-btn:hover {
                    background: rgba(255, 255, 255, 0.25);
                    transform: scale(1.1);
                }
                
                .share-modal-card .card-content {
                    padding: 20px;
                }
                
                .share-options {
                    display: flex;
                    flex-direction: column;
                    gap: 12px;
                }
                
                .share-option-btn {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    width: 100%;
                    padding: 14px 20px;
                    background: rgba(255, 255, 255, 0.15);
                    border: 1px solid rgba(255, 255, 255, 0.3);
                    border-radius: 16px;
                    color: white;
                    font-size: 16px;
                    font-weight: 500;
                    cursor: pointer;
                    transition: all 0.3s ease;
                }
                
                .share-option-btn:hover {
                    background: rgba(255, 255, 255, 0.25);
                    transform: scale(1.02);
                }
                
                .share-option-btn:active {
                    transform: scale(0.98);
                }
                
                .share-option-btn.success {
                    background: rgba(76, 175, 80, 0.3);
                    border-color: rgba(76, 175, 80, 0.5);
                }
            `;
            document.head.appendChild(style);
        }

        // Set up event handlers
        const closeBtn = modal.querySelector('.card-close-btn');
        const downloadBtn = modal.querySelector('.download-btn');
        const copyBtn = modal.querySelector('.copy-btn');

        // Close modal with animation
        const closeModal = () => {
            modal.classList.remove('visible');
            setTimeout(() => {
                document.body.removeChild(modal);
            }, 300);  // Wait for animation to complete
        };

        closeBtn.onclick = closeModal;
        modal.onclick = (e) => {
            if (e.target === modal) closeModal();
        };

        downloadBtn.onclick = () => {
            this.downloadBlob(blob, filename);
            closeModal();
        };

        // Handle clipboard copy with success feedback
        copyBtn.onclick = async () => {
            if (await this.copyImageToClipboard(blob)) {
                // Show success state
                copyBtn.classList.add('success');
                copyBtn.innerHTML = `
                    <img src="./img/copy.svg" style="width: 24px; height: 24px; filter: brightness(0) invert(1);" alt="">
                    <span>Copied!</span>
                `;
                // Reset after 2 seconds
                setTimeout(() => {
                    copyBtn.classList.remove('success');
                    copyBtn.innerHTML = `
                        <img src="./img/copy.svg" style="width: 24px; height: 24px; filter: brightness(0) invert(1);" alt="">
                        <span>Copy to Clipboard</span>
                    `;
                }, 2000);
            } else {
                alert('Unable to copy to clipboard');
            }
        };

        return modal;
    }

    /**
     * Download blob as file to device
     * Creates temporary object URL and triggers download
     * @param {Blob} blob - File blob to download
     * @param {string} filename - Filename for download
     */
    downloadBlob(blob, filename) {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);  // Clean up memory
    }

    /**
     * Copy image to system clipboard
     * Uses modern Clipboard API with ClipboardItem
     * @param {Blob} blob - Image blob to copy
     * @returns {Promise<boolean>} True if copy succeeded
     */
    async copyImageToClipboard(blob) {
        // Check for Clipboard API support
        if (!navigator.clipboard || !navigator.clipboard.write) {
            return false;
        }

        try {
            // Create clipboard item with MIME type
            const clipboardItem = new ClipboardItem({
                [blob.type]: blob
            });
            await navigator.clipboard.write([clipboardItem]);
            return true;
        } catch (error) {
            console.warn('Clipboard copy failed:', error);
            return false;
        }
    }

    /**
     * Convert base64 data URL to Blob
     * Utility function for data URL handling
     * @param {string} dataURL - Base64 data URL
     * @returns {Blob} Binary blob
     */
    dataURLtoBlob(dataURL) {
        const arr = dataURL.split(',');
        const mime = arr[0].match(/:(.*?);/)[1];
        const bstr = atob(arr[1]);  // Decode base64
        let n = bstr.length;
        const u8arr = new Uint8Array(n);
        // Convert to byte array
        while(n--){
            u8arr[n] = bstr.charCodeAt(n);
        }
        return new Blob([u8arr], {type: mime});
    }

    /**
     * Save panorama to Innovatech PH system
     * Uploads the panorama to the database via API
     * @param {Blob} blob - Image blob to save
     * @param {string} filename - Filename for the image
     * @param {Object} metadata - Metadata (title, description, capture data)
     * @returns {Promise<Object>} Response from API
     */
    async saveToSystem(blob, filename = 'panorama.jpg', metadata = {}) {
        try {
            const formData = new FormData();
            formData.append('panorama', blob, filename);
            formData.append('title', metadata.title || 'Untitled Panorama');
            formData.append('description', metadata.description || '');
            if (metadata.captureData) {
                formData.append('capture_data', JSON.stringify(metadata.captureData));
            }

            const response = await fetch('/api/save-panorama.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            const result = await response.json();

            if (result.success) {
                console.log('Panorama saved to system:', result);
                return result;
            } else {
                console.error('Failed to save panorama:', result.error);
                throw new Error(result.error || 'Failed to save panorama');
            }
        } catch (error) {
            console.error('Error saving panorama to system:', error);
            throw error;
        }
    }
}

/**
 * Singleton instance for consistent sharing behavior
 * Import this instance rather than creating new ones
 * @type {PhotoSphereSharer}
 */
export const photoSphereSharer = new PhotoSphereSharer();