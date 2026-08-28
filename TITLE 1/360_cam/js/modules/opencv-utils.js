// OpenCV utility functions for OpenCV 4.x compatibility

/**
 * Wait for OpenCV to be ready
 */
export async function waitForOpenCV() {
    // Check if already loaded (after Promise resolution)
    if (window.cv && typeof window.cv === 'object' && window.cv.Mat) {
        ensureMatFromImageData(); // Ensure helper is available
        return true;
    }
    
    // Check if cvReady flag is set
    if (window.cvReady) {
        console.log('OpenCV ready flag is set');
        return true;
    }
    
    // Wait for opencv-ready event or polling
    return new Promise((resolve, reject) => {
        let attempts = 0;
        const maxAttempts = 100; // 10 seconds
        
        // Listen for custom event
        const readyHandler = () => {
            window.removeEventListener('opencv-ready', readyHandler);
            clearInterval(checkInterval);
            ensureMatFromImageData(); // Ensure helper is available
            resolve(true);
        };
        window.addEventListener('opencv-ready', readyHandler);
        
        // Also poll in case event already fired
        const checkInterval = setInterval(() => {
            attempts++;
            
            // Check for resolved cv object
            if ((window.cv && typeof window.cv === 'object' && window.cv.Mat) || window.cvReady) {
                window.removeEventListener('opencv-ready', readyHandler);
                clearInterval(checkInterval);
                ensureMatFromImageData(); // Ensure helper is available
                resolve(true);
            } else if (attempts >= maxAttempts) {
                window.removeEventListener('opencv-ready', readyHandler);
                clearInterval(checkInterval);
                reject(new Error('OpenCV failed to load after 10 seconds'));
            }
        }, 100);
    });
}

/**
 * Ensure matFromImageData helper is available globally
 */
function ensureMatFromImageData() {
    if (window.cv && !cv.matFromImageData) {
        // Add the helper function to cv namespace
        cv.matFromImageData = function(imageData) {
            const mat = new cv.Mat(imageData.height, imageData.width, cv.CV_8UC4);
            mat.data.set(imageData.data);
            return mat;
        };
    }
}

/**
 * Create cv.Mat from ImageData (OpenCV 3.4/4.x compatible)
 * Handles both cv.matFromImageData (3.4) and manual creation (4.x)
 */
export function matFromImageData(imageData) {
    if (!window.cv || !window.cv.Mat) {
        throw new Error('OpenCV not loaded - cv.Mat is not available');
    }
    
    // First try OpenCV 3.4 custom build method if available
    if (cv.matFromImageData) {
        try {
            return cv.matFromImageData(imageData);
        } catch (error) {
            console.warn('cv.matFromImageData failed, trying alternative:', error);
        }
    }
    
    // Fallback to manual creation for OpenCV 4.x or if 3.4 method fails
    try {
        // Create Mat with proper type
        const mat = new cv.Mat(imageData.height, imageData.width, cv.CV_8UC4 || 24);
        
        // Copy the ImageData to the Mat
        const dataPtr = mat.data;
        const data = imageData.data;
        
        // Direct copy of RGBA data
        for (let i = 0; i < data.length; i++) {
            dataPtr[i] = data[i];
        }
        
        return mat;
    } catch (error) {
        console.error('Error creating Mat from ImageData:', error);
        // Last resort: use matFromArray if available
        try {
            if (cv.matFromArray) {
                const mat = cv.matFromArray(imageData.height, imageData.width, cv.CV_8UC4 || 24, Array.from(imageData.data));
                return mat;
            }
        } catch (fallbackError) {
            console.error('All methods failed:', fallbackError);
        }
        throw new Error(`Failed to create Mat from ImageData: ${error.message}`);
    }
}

/**
 * Convert canvas to cv.Mat
 */
export function matFromCanvas(canvas) {
    const ctx = canvas.getContext('2d');
    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    return matFromImageData(imageData);
}

/**
 * Convert cv.Mat to canvas
 */
export function matToCanvas(mat, canvas) {
    if (!window.cv || !window.cv.imshow) {
        throw new Error('OpenCV not loaded');
    }
    cv.imshow(canvas, mat);
}

/**
 * Helper to check if OpenCV is ready
 */
export function isOpenCVReady() {
    // Basic check for cv and Mat
    if (typeof cv === 'undefined' || !cv.Mat) {
        return false;
    }
    
    // Check for custom ImgStitch (available in our custom build)
    const hasImgStitch = !!cv.ImgStitch;
    
    if (hasImgStitch) {
        console.log('OpenCV ready with ImgStitch support');
    }
    
    // We have basic OpenCV, which is enough with our fallbacks
    return true;
}

/**
 * Get proper OpenCV constants for 4.x
 */
export function getOpenCVConstants() {
    // OpenCV 4.x constants
    return {
        CV_8UC1: cv.CV_8UC1 || 0,
        CV_8UC3: cv.CV_8UC3 || 16,
        CV_8UC4: cv.CV_8UC4 || 24,
        CV_32FC1: cv.CV_32FC1 || 5,
        COLOR_RGBA2RGB: cv.COLOR_RGBA2RGB || 3,
        COLOR_RGB2RGBA: cv.COLOR_RGB2RGBA || 2,
        COLOR_RGBA2GRAY: cv.COLOR_RGBA2GRAY || 11,
        COLOR_GRAY2RGBA: cv.COLOR_GRAY2RGBA || 12,
        INTER_LINEAR: cv.INTER_LINEAR || 1,
        INTER_CUBIC: cv.INTER_CUBIC || 2,
        INTER_AREA: cv.INTER_AREA || 3,
        BORDER_CONSTANT: cv.BORDER_CONSTANT || 0,
        BORDER_REFLECT: cv.BORDER_REFLECT || 2
    };
}