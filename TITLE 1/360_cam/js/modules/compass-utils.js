/**
 * Compass Utilities for iOS Devices
 * Provides enhanced compass heading support for iOS devices
 */

export class CompassUtils {
    constructor() {
        this.isListening = false;
        this.currentHeading = 0;
        this.hasAbsolute = false;
        this.onHeadingUpdate = null;
        this.headingHistory = [];
        this.maxHistorySize = 10;
    }

    /**
     * Start listening for device orientation events
     */
    startListening() {
        if (this.isListening) return;
        
        this.isListening = true;
        
        if (window.DeviceOrientationEvent) {
            window.addEventListener('deviceorientation', (e) => this.handleOrientation(e));
        }
    }

    /**
     * Stop listening for device orientation events
     */
    stopListening() {
        if (!this.isListening) return;
        
        this.isListening = false;
        
        if (window.DeviceOrientationEvent) {
            window.removeEventListener('deviceorientation', (e) => this.handleOrientation(e));
        }
    }

    /**
     * Handle device orientation events
     */
    handleOrientation(event) {
        let heading = 0;
        let hasAbsolute = false;

        // iOS provides true compass heading directly
        if (typeof event.webkitCompassHeading === 'number' && !isNaN(event.webkitCompassHeading)) {
            heading = event.webkitCompassHeading;
            hasAbsolute = event.webkitCompassAccuracy !== undefined && event.webkitCompassAccuracy !== null;
        } else if (event.alpha !== null && event.alpha !== undefined) {
            // Android and other devices use alpha
            // Alpha is 0 when pointing north, but can be relative
            heading = 360 - event.alpha;
            hasAbsolute = event.absolute === true;
        }

        // Smooth the heading with moving average
        this.headingHistory.push(heading);
        if (this.headingHistory.length > this.maxHistorySize) {
            this.headingHistory.shift();
        }

        const smoothedHeading = this.calculateAverageHeading();
        this.currentHeading = Math.round(smoothedHeading);
        this.hasAbsolute = hasAbsolute;

        // Call callback if set
        if (this.onHeadingUpdate && typeof this.onHeadingUpdate === 'function') {
            this.onHeadingUpdate(this.currentHeading, this.hasAbsolute);
        }
    }

    /**
     * Calculate average heading from history
     */
    calculateAverageHeading() {
        if (this.headingHistory.length === 0) return 0;

        // Handle circular averaging for compass headings
        let sinSum = 0;
        let cosSum = 0;

        for (const heading of this.headingHistory) {
            const radians = heading * (Math.PI / 180);
            sinSum += Math.sin(radians);
            cosSum += Math.cos(radians);
        }

        const avgSin = sinSum / this.headingHistory.length;
        const avgCos = cosSum / this.headingHistory.length;
        let avgHeading = Math.atan2(avgSin, avgCos) * (180 / Math.PI);

        // Normalize to 0-360
        if (avgHeading < 0) {
            avgHeading += 360;
        }

        return avgHeading;
    }

    /**
     * Get current compass heading
     */
    getCurrentHeading() {
        return this.currentHeading;
    }

    /**
     * Check if we have absolute (true compass) heading
     */
    hasAbsoluteHeading() {
        return this.hasAbsolute;
    }

    /**
     * Calibrate compass by setting current heading as north
     */
    calibrateNorth() {
        const currentHeading = this.getCurrentHeading();
        this.calibrationOffset = currentHeading;
        return currentHeading;
    }

    /**
     * Reset calibration
     */
    resetCalibration() {
        this.calibrationOffset = 0;
    }

    /**
     * Get calibrated heading
     */
    getCalibratedHeading() {
        let heading = this.getCurrentHeading();
        if (this.calibrationOffset) {
            heading = (heading - this.calibrationOffset + 360) % 360;
        }
        return heading;
    }
}