// Settings Manager - Handles configuration for stitching parameters
export class Settings {
    constructor() {
        this.params = {
            // Feature Detection
            detectorType: 'sift',
            maxFeatures: 500,
            
            // Matching
            matcherType: 'bruteforce',
            ratioThreshold: 0.7,
            ransacThreshold: 5.0,
            minMatches: 20,
            
            // Camera Estimation
            cameraEstimation: true,
            waveCorrection: true,
            confidenceThreshold: 0.3,
            
            // Projection
            projectionType: 'spherical',
            
            // Seam Finding
            seamFinderType: 'gc_color',
            
            // Blending
            blendType: 'multiband',
            blendStrength: 5,
            
            // Bundle Adjustment
            bundleAdjustment: true,
            bundleAdjustmentRefineAll: true,
            bundleAdjustmentMaxIterations: 100,
            
            // Optimization
            imageScale: 0.5,
            disposeInputImages: false,
            liveUpdateCycle: 1,
            tryGpu: false,
            
            // Memory Management
            limitResultPreview: 2000000, // 2MP max for preview
            maxImageDimension: 2000,
            batchSize: 10,
            
            // Advanced
            composeScale: 1.0,
            seamScale: 0.5,
            workScale: 0.5,
            registrationResolution: 0.6,
            seamEstimationResolution: 0.1,
            compositingResolution: 1.0,
            panoConfidenceThreshold: 1.0
        };
        
        // Parameter metadata for UI and validation
        this.metadata = {
            detectorType: {
                type: 'select',
                options: ['sift', 'orb', 'akaze', 'brisk'],
                label: 'Feature Detector',
                description: 'Algorithm for detecting keypoints'
            },
            maxFeatures: {
                type: 'number',
                min: 100,
                max: 5000,
                step: 100,
                label: 'Max Features',
                description: 'Maximum number of features to detect'
            },
            confidenceThreshold: {
                type: 'range',
                min: 0.1,
                max: 1.0,
                step: 0.1,
                label: 'Confidence Threshold',
                description: 'Minimum confidence for image matching'
            },
            projectionType: {
                type: 'select',
                options: ['spherical', 'cylindrical', 'plane', 'fisheye', 'stereographic'],
                label: 'Projection Type',
                description: 'Warping projection method'
            },
            seamFinderType: {
                type: 'select',
                options: ['gc_color', 'gc_colorgrad', 'dp_color', 'dp_colorgrad', 'voronoi', 'no'],
                label: 'Seam Finder',
                description: 'Algorithm for finding optimal seams'
            },
            blendType: {
                type: 'select',
                options: ['multiband', 'feather', 'no'],
                label: 'Blend Type',
                description: 'Blending method for overlapping regions'
            },
            imageScale: {
                type: 'range',
                min: 0.1,
                max: 1.0,
                step: 0.1,
                label: 'Processing Scale',
                description: 'Scale factor for processing (lower = faster)'
            }
        };
        
        // Load saved settings from localStorage
        this.loadSettings();
    }

    // Get a setting value
    get(key) {
        return this.params[key];
    }

    // Set a setting value
    set(key, value) {
        if (key in this.params) {
            const oldValue = this.params[key];
            this.params[key] = this.validateValue(key, value);
            
            if (oldValue !== this.params[key]) {
                this.saveSettings();
                this.emit('settingChanged', { key, value: this.params[key], oldValue });
            }
        }
    }

    // Get all settings
    getAll() {
        return { ...this.params };
    }

    // Update multiple settings at once
    update(updates) {
        const changes = [];
        
        for (const [key, value] of Object.entries(updates)) {
            if (key in this.params) {
                const oldValue = this.params[key];
                this.params[key] = this.validateValue(key, value);
                
                if (oldValue !== this.params[key]) {
                    changes.push({ key, value: this.params[key], oldValue });
                }
            }
        }
        
        if (changes.length > 0) {
            this.saveSettings();
            this.emit('settingsUpdated', changes);
        }
    }

    // Validate a setting value
    validateValue(key, value) {
        const meta = this.metadata[key];
        if (!meta) return value;
        
        switch (meta.type) {
            case 'number':
            case 'range':
                const num = parseFloat(value);
                if (isNaN(num)) return this.params[key];
                if (meta.min !== undefined && num < meta.min) return meta.min;
                if (meta.max !== undefined && num > meta.max) return meta.max;
                return num;
                
            case 'select':
                if (meta.options && !meta.options.includes(value)) {
                    return meta.options[0];
                }
                return value;
                
            case 'boolean':
                return Boolean(value);
                
            default:
                return value;
        }
    }

    // Convert to format expected by OpenCV worker
    toWorkerFormat() {
        // Map settings to OpenCV parameter IDs
        const paramMap = [];
        
        // Feature detection
        if (this.params.detectorType) {
            paramMap.push({ id: 'detector_type', value: this.getDetectorTypeId() });
        }
        if (this.params.maxFeatures) {
            paramMap.push({ id: 'max_features', value: this.params.maxFeatures });
        }
        
        // Matching
        if (this.params.matcherType) {
            paramMap.push({ id: 'matcher_type', value: this.getMatcherTypeId() });
        }
        if (this.params.ratioThreshold) {
            paramMap.push({ id: 'ratio_threshold', value: this.params.ratioThreshold });
        }
        if (this.params.ransacThreshold) {
            paramMap.push({ id: 'ransac_threshold', value: this.params.ransacThreshold });
        }
        
        // Camera
        paramMap.push({ id: 'camera_estimation', value: this.params.cameraEstimation ? 1 : 0 });
        paramMap.push({ id: 'wave_correction', value: this.params.waveCorrection ? 1 : 0 });
        paramMap.push({ id: 'confidence_threshold', value: this.params.confidenceThreshold });
        
        // Projection
        paramMap.push({ id: 'projection_type', value: this.getProjectionTypeId() });
        
        // Seam finding
        paramMap.push({ id: 'seam_finder_type', value: this.getSeamFinderTypeId() });
        
        // Blending
        paramMap.push({ id: 'blend_type', value: this.getBlendTypeId() });
        paramMap.push({ id: 'blend_strength', value: this.params.blendStrength });
        
        // Bundle adjustment
        paramMap.push({ id: 'bundle_adjustment', value: this.params.bundleAdjustment ? 1 : 0 });
        
        // Scales
        paramMap.push({ id: 'image_scale', value: this.params.imageScale });
        paramMap.push({ id: 'work_scale', value: this.params.workScale });
        paramMap.push({ id: 'seam_scale', value: this.params.seamScale });
        paramMap.push({ id: 'compose_scale', value: this.params.composeScale });
        
        return paramMap;
    }

    // Get detector type ID for OpenCV
    getDetectorTypeId() {
        const types = {
            'sift': 1,
            'orb': 2,
            'akaze': 3,
            'brisk': 4
        };
        return types[this.params.detectorType] || 1;
    }

    // Get matcher type ID for OpenCV
    getMatcherTypeId() {
        const types = {
            'bruteforce': 1,
            'flann': 2
        };
        return types[this.params.matcherType] || 1;
    }

    // Get projection type ID for OpenCV
    getProjectionTypeId() {
        const types = {
            'plane': 0,
            'cylindrical': 1,
            'spherical': 2,
            'fisheye': 3,
            'stereographic': 4,
            'compressedspheric': 5,
            'pannini': 6,
            'mercator': 7,
            'transversemercator': 8
        };
        return types[this.params.projectionType] || 2;
    }

    // Get seam finder type ID for OpenCV
    getSeamFinderTypeId() {
        const types = {
            'no': 0,
            'voronoi': 1,
            'gc_color': 2,
            'gc_colorgrad': 3,
            'dp_color': 4,
            'dp_colorgrad': 5
        };
        return types[this.params.seamFinderType] || 2;
    }

    // Get blend type ID for OpenCV
    getBlendTypeId() {
        const types = {
            'no': 0,
            'feather': 1,
            'multiband': 2
        };
        return types[this.params.blendType] || 2;
    }

    // Reset to default settings
    reset() {
        const defaults = {
            detectorType: 'sift',
            maxFeatures: 500,
            matcherType: 'bruteforce',
            ratioThreshold: 0.7,
            ransacThreshold: 5.0,
            minMatches: 20,
            cameraEstimation: true,
            waveCorrection: true,
            confidenceThreshold: 0.3,
            projectionType: 'spherical',
            seamFinderType: 'gc_color',
            blendType: 'multiband',
            blendStrength: 5,
            bundleAdjustment: true,
            imageScale: 0.5,
            workScale: 0.5
        };
        
        this.update(defaults);
    }

    // Save settings to localStorage
    saveSettings() {
        try {
            localStorage.setItem('multistitcher_settings', JSON.stringify(this.params));
        } catch (error) {
            console.error('Failed to save settings:', error);
        }
    }

    // Load settings from localStorage
    loadSettings() {
        try {
            const saved = localStorage.getItem('multistitcher_settings');
            if (saved) {
                const loaded = JSON.parse(saved);
                Object.keys(loaded).forEach(key => {
                    if (key in this.params) {
                        this.params[key] = this.validateValue(key, loaded[key]);
                    }
                });
            }
        } catch (error) {
            console.error('Failed to load settings:', error);
        }
    }

    // Export settings as JSON
    exportSettings() {
        return JSON.stringify(this.params, null, 2);
    }

    // Import settings from JSON
    importSettings(json) {
        try {
            const imported = JSON.parse(json);
            this.update(imported);
            return true;
        } catch (error) {
            console.error('Failed to import settings:', error);
            return false;
        }
    }

    // Event emitter functionality
    listeners = new Map();

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
                    console.error(`Error in settings event listener for ${event}:`, error);
                }
            });
        }
    }

    // Get presets for quick setup
    static getPresets() {
        return {
            quality: {
                name: 'High Quality',
                description: 'Best quality, slower processing',
                settings: {
                    detectorType: 'sift',
                    maxFeatures: 2000,
                    imageScale: 0.8,
                    blendType: 'multiband',
                    seamFinderType: 'gc_color',
                    bundleAdjustment: true
                }
            },
            balanced: {
                name: 'Balanced',
                description: 'Good quality with reasonable speed',
                settings: {
                    detectorType: 'akaze',
                    maxFeatures: 1000,
                    imageScale: 0.5,
                    blendType: 'multiband',
                    seamFinderType: 'gc_color',
                    bundleAdjustment: true
                }
            },
            fast: {
                name: 'Fast',
                description: 'Faster processing, lower quality',
                settings: {
                    detectorType: 'orb',
                    maxFeatures: 500,
                    imageScale: 0.3,
                    blendType: 'feather',
                    seamFinderType: 'voronoi',
                    bundleAdjustment: false
                }
            },
            minimal: {
                name: 'Minimal',
                description: 'Fastest, basic stitching',
                settings: {
                    detectorType: 'orb',
                    maxFeatures: 300,
                    imageScale: 0.2,
                    blendType: 'no',
                    seamFinderType: 'no',
                    bundleAdjustment: false
                }
            }
        };
    }

    // Apply a preset
    applyPreset(presetName) {
        const presets = Settings.getPresets();
        if (presets[presetName]) {
            this.update(presets[presetName].settings);
            return true;
        }
        return false;
    }
}

export default Settings;