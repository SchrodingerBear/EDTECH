// Simple, clean OpenCV stitching implementation
import { Hotspots } from './hotspots.js';
import { waitForOpenCV, isOpenCVReady } from './opencv-utils.js';
import { MultiStitcher } from './multi-stitcher.js';

export class Stitcher {
    constructor() {
        this.debug = true;
        this.useMultiStitcher = true; // Enable advanced stitching by default
    }

    async stitch(imageMats, imageMetadata, progressCallback) {
        // Ensure OpenCV is ready
        await waitForOpenCV();
        
        if (!isOpenCVReady()) {
            throw new Error('OpenCV components not ready');
        }

        progressCallback(0.1, 'Initializing stitching...');
        
        // Try advanced multi-stitcher first if we have cv.ImgStitch
        if (this.useMultiStitcher && cv.ImgStitch) {
            try {
                const multiStitcher = new MultiStitcher();
                const result = await multiStitcher.stitch(imageMats, imageMetadata, progressCallback);
                
                if (result && !result.empty()) {
                    return result;
                }
            } catch (error) {
                console.warn('Multi-stitcher failed, trying fallback:', error.message);
            }
        }
        
        // Check memory before trying other methods
        if (performance.memory) {
            const usedMB = performance.memory.usedJSHeapSize / 1048576;
            if (usedMB > 400) {
                console.warn('High memory usage, processing in batches');
                return await this.batchStitch(imageMats, imageMetadata, progressCallback);
            }
        }
        
        // Try standard OpenCV Stitcher if available
        if (cv.Stitcher) {
            try {
                const { OpenCVStitcher } = await import('./opencv-stitcher.js');
                const stitcher = new OpenCVStitcher();
                const result = await stitcher.stitch(imageMats, imageMetadata, progressCallback);
                
                if (result && !result.empty()) {
                    console.log('OpenCV Stitcher successful');
                    return result;
                }
            } catch (error) {
                console.error('OpenCV Stitcher error:', error);
            }
        }
        
        // Fallback to simple placement if all else fails
        console.log('Using simple equirectangular placement');
        return this.simpleEquirectangularStitch(imageMats, imageMetadata, progressCallback);
    }
    
    async batchStitch(imageMats, imageMetadata, progressCallback) {
        console.log('Batch stitching for memory efficiency');
        
        // Group images by row (pitch)
        const rows = {
            45: [],    // Upper row
            0: [],     // Middle row  
            '-45': []  // Lower row
        };
        
        for (let i = 0; i < imageMats.length; i++) {
            const pitch = imageMetadata[i].pitch || 0;
            const roundedPitch = Math.round(pitch / 45) * 45;
            
            if (!rows[roundedPitch]) {
                rows[roundedPitch] = [];
            }
            
            rows[roundedPitch].push({
                mat: imageMats[i],
                metadata: imageMetadata[i]
            });
        }
        
        // Process each row separately
        const stitchedRows = {};
        const pitches = [45, 0, -45];
        
        for (const pitch of pitches) {
            const row = rows[pitch];
            if (!row || row.length === 0) continue;
            
            progressCallback(0.3, `Processing row at ${pitch}°...`);
            
            try {
                // Fix ordering for this row
                const reordered = this.reorderForPanorama(row);
                
                // Try to stitch this row
                const rowMats = reordered.map(item => item.mat);
                const rowMetadata = reordered.map(item => item.metadata);
                
                if (cv.Stitcher) {
                    const { OpenCVStitcher } = await import('./opencv-stitcher.js');
                    const stitcher = new OpenCVStitcher();
                    const rowResult = await stitcher.stitch(rowMats, rowMetadata, (p, s) => {});
                    
                    if (rowResult && !rowResult.empty()) {
                        stitchedRows[pitch] = rowResult;
                        console.log(`Row ${pitch}° stitched successfully`);
                    }
                } else {
                    // Simple placement for this row
                    stitchedRows[pitch] = this.simpleRowStitch(reordered);
                }
                
            } catch (error) {
                console.error(`Failed to stitch row ${pitch}°:`, error);
            }
        }
        
        // Combine rows into full equirectangular
        progressCallback(0.7, 'Combining rows...');
        return this.combineRows(stitchedRows, progressCallback);
    }
    
    reorderForPanorama(items) {
        // Sort by hotspotId to get correct order
        items.sort((a, b) => (a.metadata.hotspotId || 0) - (b.metadata.hotspotId || 0));
        
        // Return in the correct order (don't reverse!)
        return items;
    }
    
    simpleRowStitch(items) {
        // Calculate total width needed
        const totalWidth = items.reduce((sum, item) => sum + item.mat.cols, 0);
        const maxHeight = Math.max(...items.map(item => item.mat.rows));
        
        // Create output canvas
        const result = new cv.Mat(maxHeight, totalWidth, cv.CV_8UC4, new cv.Scalar(0, 0, 0, 0));
        
        // Place images side by side
        let currentX = 0;
        for (const item of items) {
            const roi = result.roi(new cv.Rect(currentX, 0, item.mat.cols, item.mat.rows));
            item.mat.copyTo(roi);
            roi.delete();
            currentX += item.mat.cols;
        }
        
        return result;
    }
    
    combineRows(stitchedRows, progressCallback) {
        // Create full equirectangular canvas
        const fullWidth = 4096;
        const fullHeight = 2048;
        const fullPano = new cv.Mat(fullHeight, fullWidth, cv.CV_8UC4, new cv.Scalar(0, 0, 0, 255));
        
        // Place each row
        const pitches = [45, 0, -45];
        for (const pitch of pitches) {
            if (!stitchedRows[pitch]) continue;
            
            const row = stitchedRows[pitch];
            
            // Calculate vertical position
            let destY;
            if (pitch === 45) destY = 0;                    // Top third
            else if (pitch === 0) destY = fullHeight / 3;   // Middle third
            else destY = (fullHeight * 2) / 3;              // Bottom third
            
            const rowHeight = fullHeight / 3;
            
            // Resize row to fit width
            const scaled = new cv.Mat();
            const scaleRatio = fullWidth / row.cols;
            cv.resize(row, scaled, new cv.Size(fullWidth, Math.min(rowHeight, row.rows * scaleRatio)));
            
            // Place in panorama
            const roi = fullPano.roi(new cv.Rect(0, Math.floor(destY), scaled.cols, scaled.rows));
            scaled.copyTo(roi);
            
            // Clean up
            roi.delete();
            scaled.delete();
            row.delete();
        }
        
        progressCallback(0.95, 'Finalizing panorama...');
        return fullPano;
    }
    
    simpleEquirectangularStitch(imageMats, imageMetadata, progressCallback) {
        console.log('Creating equirectangular placement with orientation compensation');
        console.log(`Input: ${imageMats.length} images`);
        
        // Full equirectangular dimensions
        const fullWidth = 4096;
        const fullHeight = 2048;
        const panorama = new cv.Mat(fullHeight, fullWidth, cv.CV_8UC4, new cv.Scalar(0, 0, 0, 255));
        
        console.log(`Creating panorama canvas: ${fullWidth}x${fullHeight}`);
        
        // Process each image with orientation-aware placement
        imageMats.forEach((img, index) => {
            progressCallback(0.2 + (index / imageMats.length) * 0.7, 
                `Placing image ${index + 1} of ${imageMats.length}...`);
            
            const meta = imageMetadata[index];
            
            // Use actual captured orientation if available, otherwise fall back to ideal hotspot position
            const yaw = meta.actualYaw !== undefined ? meta.actualYaw : meta.yaw;
            const pitch = meta.actualPitch !== undefined ? meta.actualPitch : meta.pitch;
            
            // Apply any captured deltas for fine-tuning
            const adjustedYaw = yaw + (meta.yawDelta || 0) * 0.5; // Apply delta at 50% strength
            const adjustedPitch = pitch + (meta.pitchDelta || 0) * 0.5;
            
            // Convert spherical coordinates to equirectangular projection
            // X position based on yaw (0-360 maps to 0-width)
            let destX = (adjustedYaw / 360) * fullWidth;
            // Wrap around for continuity
            if (destX < 0) destX += fullWidth;
            if (destX >= fullWidth) destX -= fullWidth;
            
            // Y position based on pitch (-90 to 90 maps to height-0)
            // Note: pitch=90 is top, pitch=-90 is bottom
            const destY = ((90 - adjustedPitch) / 180) * fullHeight;
            
            // Account for roll if captured
            let rotatedImg = img;
            if (meta.roll && Math.abs(meta.roll) > 1) {
                // Rotate image to compensate for device tilt at capture
                rotatedImg = new cv.Mat();
                const center = new cv.Point(img.cols / 2, img.rows / 2);
                const rotMatrix = cv.getRotationMatrix2D(center, -meta.roll, 1.0);
                cv.warpAffine(img, rotatedImg, rotMatrix, new cv.Size(img.cols, img.rows));
                rotMatrix.delete();
            }
            
            // Calculate image placement with centering
            const imgWidth = rotatedImg.cols;
            const imgHeight = rotatedImg.rows;
            const centerX = Math.floor(destX - imgWidth / 2);
            const centerY = Math.floor(destY - imgHeight / 2);
            
            console.log(`Placing image ${index} (hotspot ${meta.hotspotId}) at ${centerX}, ${centerY}`);
            console.log(`  Orientation: yaw=${adjustedYaw.toFixed(1)}°, pitch=${adjustedPitch.toFixed(1)}°, roll=${(meta.roll || 0).toFixed(1)}°`);
            
            // Handle wrapping around edges for 360° continuity
            this.placeImageWithWrapping(panorama, rotatedImg, centerX, centerY);
            
            // Clean up rotated image if created
            if (rotatedImg !== img) {
                rotatedImg.delete();
            }
        });
        
        progressCallback(0.95, 'Finalizing...');
        return panorama;
    }
    
    placeImageWithWrapping(panorama, img, x, y) {
        const panoWidth = panorama.cols;
        const panoHeight = panorama.rows;
        const imgWidth = img.cols;
        const imgHeight = img.rows;
        
        // Clamp Y position
        y = Math.max(0, Math.min(y, panoHeight - imgHeight));
        
        // Handle X wrapping for 360° continuity
        if (x < 0) {
            // Wrap from left edge to right
            const leftPart = -x;
            const rightPart = imgWidth - leftPart;
            
            // Place right part at left edge
            if (rightPart > 0) {
                const roi1 = panorama.roi(new cv.Rect(0, y, rightPart, imgHeight));
                const imgRoi1 = img.roi(new cv.Rect(leftPart, 0, rightPart, imgHeight));
                imgRoi1.copyTo(roi1);
                roi1.delete();
                imgRoi1.delete();
            }
            
            // Place left part at right edge
            if (leftPart > 0 && leftPart < panoWidth) {
                const roi2 = panorama.roi(new cv.Rect(panoWidth - leftPart, y, leftPart, imgHeight));
                const imgRoi2 = img.roi(new cv.Rect(0, 0, leftPart, imgHeight));
                imgRoi2.copyTo(roi2);
                roi2.delete();
                imgRoi2.delete();
            }
        } else if (x + imgWidth > panoWidth) {
            // Wrap from right edge to left
            const rightEdge = panoWidth - x;
            const overflow = imgWidth - rightEdge;
            
            // Place left part at position
            if (rightEdge > 0) {
                const roi1 = panorama.roi(new cv.Rect(x, y, rightEdge, imgHeight));
                const imgRoi1 = img.roi(new cv.Rect(0, 0, rightEdge, imgHeight));
                imgRoi1.copyTo(roi1);
                roi1.delete();
                imgRoi1.delete();
            }
            
            // Place overflow at left edge
            if (overflow > 0) {
                const roi2 = panorama.roi(new cv.Rect(0, y, overflow, imgHeight));
                const imgRoi2 = img.roi(new cv.Rect(rightEdge, 0, overflow, imgHeight));
                imgRoi2.copyTo(roi2);
                roi2.delete();
                imgRoi2.delete();
            }
        } else {
            // Normal placement without wrapping
            const roi = panorama.roi(new cv.Rect(x, y, imgWidth, imgHeight));
            img.copyTo(roi);
            roi.delete();
        }
    }
}

