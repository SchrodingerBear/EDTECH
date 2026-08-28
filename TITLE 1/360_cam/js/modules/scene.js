/**
 * Three.js 3D Scene Management - Real-time Photosphere Visualization
 * 
 * This module creates and manages the 3D wireframe sphere that guides users through
 * the capture process. It visualizes the 36 capture positions, shows captured patches
 * on the sphere surface, and provides real-time feedback as the user rotates their device.
 * 
 * ARCHITECTURE:
 * The scene uses Three.js to render a 3D sphere from the user's perspective (inside looking out).
 * As they capture images, those images are mapped onto spherical segments at the correct
 * positions, building up a preview of the final panorama in real-time.
 * 
 * KEY COMPONENTS:
 * 1. WIREFRAME SPHERE: Semi-transparent guide sphere (radius 100) showing structure
 * 2. HOTSPOT MARKERS: 36 orange dots at capture positions that turn green when captured
 * 3. CAPTURED PATCHES: Spherical segments with captured images texture-mapped
 * 4. GRID LINES: Equator and meridian lines for orientation reference
 * 5. PREVIEW SPHERE: Optional background sphere for showing sample panorama
 * 
 * COORDINATE SYSTEM:
 * - Three.js uses Y-up coordinate system
 * - Yaw: Horizontal rotation (0-360°), 0° = North
 * - Pitch: Vertical angle (-90° to +90°), 0° = Horizon
 * - Conversion: Spherical (yaw, pitch, radius) → Cartesian (x, y, z)
 * 
 * SPHERICAL SEGMENT PATCHES:
 * Each captured image is mapped onto a curved spherical segment (not flat plane!):
 * - Horizontal FOV: 40° (calculated for good overlap with 12 points per row)
 * - Vertical FOV: 71° (40° * 16/9 for portrait orientation)
 * - Radius: 96 units (slightly inside wireframe to prevent z-fighting)
 * - Segments: 32×24 for smooth curvature
 * - Edge fade: Subtle alpha gradient at edges for seamless blending
 * 
 * TEXTURE MAPPING PROCESS:
 * 1. Image loaded into HTML Image element
 * 2. Edge fade applied using canvas manipulation
 * 3. Texture created from modified canvas
 * 4. Mapped onto spherical segment with UV coordinates
 * 5. Stamping animation scales from 0.1 to 1.0
 * 
 * CAMERA ROTATION:
 * The camera rotation follows device orientation:
 * - Alpha (compass): Y-axis rotation
 * - Beta (tilt): X-axis rotation  
 * - Gamma (roll): Z-axis rotation
 * - Euler order: YXZ with 90° X offset for portrait mode
 * 
 * ENHANCED EQUIRECTANGULAR CAPTURE:
 * The captureEnhancedEquirectangular() method creates panoramas by:
 * 1. Creating 800×400 canvas (reduced from 3200×1600 for performance)
 * 2. For each pixel, calculating its spherical coordinates
 * 3. Finding all patches that cover that point
 * 4. Weighted blending based on distance from patch center
 * 5. Gaussian falloff for smooth transitions
 * 
 * MEMORY MANAGEMENT:
 * - Textures disposed when patches cleared
 * - Geometry disposed to prevent memory leaks
 * - Single sampler canvas reused for pixel sampling
 * - Chunk processing to prevent UI blocking
 * 
 * ANIMATION:
 * - Hotspot markers scale when highlighted (1.0 → 1.5)
 * - Captured patches "stamp" onto sphere (0.1 → 1.0 scale)
 * - Patches "breathe" subtly after capture (±1% scale)
 * - Smooth transitions using requestAnimationFrame
 * 
 * @module Scene
 * @requires Hotspots - For 36-point capture pattern configuration
 */

import { Hotspots } from './hotspots.js';

export class Scene {
    /**
     * Initialize the 3D scene with sphere and hotspots
     * @param {HTMLElement} container - DOM element to render into
     */
    constructor(container) {
        this.container = container;           // DOM container for canvas
        this.scene = null;                   // Three.js scene object
        this.camera = null;                  // Perspective camera (user viewpoint)
        this.renderer = null;                // WebGL renderer
        this.sphere = null;                  // Wireframe guide sphere
        this.hotspotMarkers = [];            // Array of 36 marker meshes
        this.capturedPatches = [];           // Array of captured image patches
        
        this.init();
    }

    /**
     * Initialize Three.js scene components
     * Sets up camera, renderer, sphere, hotspots, and lighting
     */
    init() {
        console.log('Initializing Three.js scene...');
        
        // Create scene
        this.scene = new THREE.Scene();
        
        // Camera at origin (user stands in center of sphere)
        this.camera = new THREE.PerspectiveCamera(
            75,                                      // FOV in degrees
            window.innerWidth / window.innerHeight,  // Aspect ratio
            0.1,                                     // Near plane
            1000                                     // Far plane
        );
        this.camera.position.set(0, 0, 0);          // User at center
        
        // Renderer
        this.renderer = new THREE.WebGLRenderer({ 
            antialias: true, 
            alpha: true 
        });
        this.renderer.setSize(window.innerWidth, window.innerHeight);
        this.renderer.setPixelRatio(window.devicePixelRatio);
        this.container.appendChild(this.renderer.domElement);
        
        // Create sphere
        this.createSphere();
        
        // Create hotspot markers
        this.createHotspotMarkers();
        
        // Lighting
        const ambientLight = new THREE.AmbientLight(0xffffff, 0.6);
        this.scene.add(ambientLight);
        
        const directionalLight = new THREE.DirectionalLight(0xffffff, 0.4);
        directionalLight.position.set(5, 5, 5);
        this.scene.add(directionalLight);
        
        // Handle resize
        window.addEventListener('resize', () => this.handleResize());
        
        // Initial render
        this.handleResize();
        this.render();
    }

    /**
     * Create the wireframe guide sphere
     * User stands inside this sphere looking out at capture points
     */
    createSphere() {
        // Wireframe sphere with 24 horizontal × 16 vertical segments
        const geometry = new THREE.SphereGeometry(
            100,  // Radius in world units
            24,   // Width segments (longitude lines)
            16    // Height segments (latitude lines)
        );
        const material = new THREE.MeshBasicMaterial({
            color: 0x444444,  // Gray color for subtle visibility
            wireframe: true,  // Show only edges, not faces
            transparent: true,
            opacity: 0.5      // Semi-transparent
        });
        this.sphere = new THREE.Mesh(geometry, material);
        this.scene.add(this.sphere);
        
        // Add reference grid lines
        this.addGridLines();
    }

    /**
     * Add equator and meridian lines for orientation reference
     * Helps users understand their position in 3D space
     */
    addGridLines() {
        // Equator line (horizontal ring at 0° pitch)
        const equatorGeometry = new THREE.RingGeometry(
            99.5,  // Inner radius (slightly inside sphere)
            100.5, // Outer radius (creates thin ring)
            64     // Segments for smooth circle
        );
        const equatorMaterial = new THREE.MeshBasicMaterial({
            color: 0x8C1515,      // Cardinal red
            opacity: 0.5,
            transparent: true,
            side: THREE.DoubleSide
        });
        const equator = new THREE.Mesh(equatorGeometry, equatorMaterial);
        equator.rotation.x = Math.PI / 2;  // Rotate to horizontal
        this.scene.add(equator);
        
        // Meridian lines every 45 degrees
        for (let i = 0; i < 4; i++) {
            const meridianGeometry = new THREE.RingGeometry(99.5, 100.5, 2, 32);
            const meridian = new THREE.Mesh(meridianGeometry, equatorMaterial);
            meridian.rotation.y = (i * Math.PI) / 4;
            this.scene.add(meridian);
        }
    }

    /**
     * Create 36 orange sphere markers at capture positions
     * These guide users to the correct capture angles
     */
    createHotspotMarkers() {
        const hotspots = Hotspots.getHotspots();
        console.log(`Creating ${hotspots.length} hotspot markers...`);
        const markerGeometry = new THREE.SphereGeometry(
            2,   // Radius (small dots)
            8,   // Width segments
            8    // Height segments
        );
        
        hotspots.forEach((hotspot) => {
            const material = new THREE.MeshBasicMaterial({
                color: 0xFFA726,  // Orange color for visibility
                transparent: true,
                opacity: 0.8      // Higher opacity
            });
            
            const marker = new THREE.Mesh(markerGeometry, material);
            const position = this.hotspotToPosition(hotspot.yaw, hotspot.pitch, 98);
            marker.position.copy(position);
            marker.userData = { hotspotId: hotspot.id };
            
            // Store position on hotspot for patch placement
            hotspot.position = position.clone();
            hotspot.mesh = marker;
            
            this.scene.add(marker);
            this.hotspotMarkers.push(marker);
        });
    }

    /**
     * Convert spherical coordinates (yaw/pitch) to 3D position
     * Uses standard spherical coordinate conversion with Y-up
     * @param {number} yaw - Horizontal angle in degrees (0-360)
     * @param {number} pitch - Vertical angle in degrees (-90 to +90)
     * @param {number} radius - Distance from origin (default 100)
     * @returns {THREE.Vector3} 3D position in world space
     */
    hotspotToPosition(yaw, pitch, radius = 100) {
        // Phi: angle from vertical axis (0° at north pole)
        const phi = (90 - pitch) * Math.PI / 180;
        // Theta: angle around vertical axis
        const theta = yaw * Math.PI / 180;
        
        // Convert to Cartesian (Y-up coordinate system)
        return new THREE.Vector3(
            radius * Math.sin(phi) * Math.sin(theta),  // X
            radius * Math.cos(phi),                    // Y (up)
            radius * Math.sin(phi) * Math.cos(theta)   // Z
        );
    }

    /**
     * Highlight a hotspot when user approaches it
     * Changes color and scale to provide visual feedback
     * @param {number} hotspotId - ID of hotspot to highlight (1-36)
     * @param {boolean} isAligned - True if perfectly aligned for capture
     */
    highlightHotspot(hotspotId, isAligned = false) {
        this.hotspotMarkers.forEach(marker => {
            if (marker.userData.hotspotId === hotspotId) {
                // Highlight the approached hotspot
                marker.material.color.setHex(isAligned ? 0x4CAF50 : 0xFFA726);  // Green when aligned, orange when near
                marker.material.opacity = isAligned ? 0.9 : 0.7;
                marker.scale.setScalar(isAligned ? 1.5 : 1.2);  // Grow when aligned
            } else {
                // Dim other hotspots
                marker.material.color.setHex(0xFFA726);  // Orange
                marker.material.opacity = 0.6;           // Dimmer
                marker.scale.setScalar(1);               // Normal size
            }
        });
    }
    
    resetAllHotspots() {
        // Reset all hotspots to default state (no highlighting)
        this.hotspotMarkers.forEach(marker => {
            marker.material.color.setHex(0xFFA726);  // Orange
            marker.material.opacity = 0.8;           // Default opacity
            marker.scale.setScalar(1);               // Default scale
            marker.visible = true;                   // Make sure it's visible
        });
    }

    /**
     * Hide a hotspot marker after successful capture
     * Visual confirmation that position has been captured
     * @param {number} hotspotId - ID of captured hotspot
     */
    markHotspotCaptured(hotspotId) {
        const marker = this.hotspotMarkers.find(m => m.userData.hotspotId === hotspotId);
        if (marker) {
            // Hide marker to show it's been captured
            marker.visible = false;
        }
    }

    /**
     * Add a captured image as a spherical patch on the sphere
     * Creates curved geometry and maps the image as texture
     * @param {Object} hotspot - Hotspot object with yaw/pitch/id
     * @param {string} imageData - Base64 image data URL
     */
    addCapturedPatch(hotspot, imageData) {
        // Create image element to load the captured photo
        const img = new Image();
        
        // Set up onload handler before setting src
        img.onload = () => {
            console.log(`Creating texture for hotspot ${hotspot.id}`);
            
            // Create texture from the loaded image
            const texture = new THREE.Texture(img);
            texture.needsUpdate = true;
            texture.minFilter = THREE.LinearFilter;
            texture.magFilter = THREE.LinearFilter;
            
            // Force texture update to ensure it's properly loaded
            texture.image = img;
            texture.needsUpdate = true;
            
            // Create the patch mesh with this texture
            const patch = this.createPatchMesh(hotspot, texture);
            
            // Store hotspot reference on the patch for later use
            patch.userData.hotspot = hotspot;
            
            // Add to scene and array
            this.scene.add(patch);
            this.capturedPatches.push(patch);
        };
        
        img.onerror = (error) => {
            console.error(`Failed to load image for hotspot ${hotspot.id}:`, error);
        };
        
        // Set the image source - this triggers the load
        img.src = imageData;
    }
    
    /**
     * Create a spherical segment mesh for a captured image
     * This is the core of the 3D visualization - each image becomes
     * a curved patch on the sphere surface
     * @param {Object} hotspot - Hotspot with position data
     * @param {THREE.Texture} texture - Image texture to apply
     * @returns {THREE.Mesh} Spherical patch mesh
     */
    createPatchMesh(hotspot, texture) {
        // Calculate FOV for proper overlap
        // 12 points per row at 30° spacing
        // 40° FOV gives ~33% overlap between adjacent images
        const horizontalFOV = 40;                        // Horizontal field of view
        const verticalFOV = horizontalFOV * (16/9);      // ~71° for portrait aspect
        
        // Small safety margin to ensure coverage
        const overlapFactor = 1.05; // Just 5% extra to avoid gaps
        const hSegments = 32;
        const vSegments = 24;
        
        // Create spherical segment geometry
        const geometry = this.createSphericalSegment(
            hotspot.yaw,
            hotspot.pitch,
            horizontalFOV * overlapFactor,
            verticalFOV * overlapFactor,
            96, // radius slightly inside the sphere but not too close
            hSegments,
            vSegments
        );
        
        // Add UV coordinates for texture mapping
        this.addSphericalUVs(geometry);
        
        // Create material with edge fade - using standard material with custom alpha
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        canvas.width = texture.image.width;
        canvas.height = texture.image.height;
        
        // Draw the image
        ctx.drawImage(texture.image, 0, 0);
        
        // Create gradient mask for edge fade
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const data = imageData.data;
        
        for (let y = 0; y < canvas.height; y++) {
            for (let x = 0; x < canvas.width; x++) {
                const idx = (y * canvas.width + x) * 4;
                
                // Calculate distance from center (0 to 1)
                const distX = Math.abs(x / canvas.width - 0.5) * 2;
                const distY = Math.abs(y / canvas.height - 0.5) * 2;
                const dist = Math.max(distX, distY);
                
                // Apply very subtle edge fade
                if (dist > 0.9) {
                    const fade = 1 - Math.min((dist - 0.9) / 0.1, 1);
                    data[idx + 3] *= fade; // Multiply alpha channel
                }
            }
        }
        
        ctx.putImageData(imageData, 0, 0);
        
        // Create texture from modified canvas
        const fadedTexture = new THREE.CanvasTexture(canvas);
        fadedTexture.needsUpdate = true;
        
        const material = new THREE.MeshBasicMaterial({
            map: fadedTexture,
            side: THREE.DoubleSide,
            transparent: true,
            opacity: 0.95,
            depthWrite: false
        });
        
        // Create mesh
        const patch = new THREE.Mesh(geometry, material);
        
        // Add stamping effect
        patch.scale.set(0.1, 0.1, 0.1);
        this.animateStamp(patch);
        
        return patch;
    }
    
    /**
     * Create custom spherical segment geometry
     * Generates a curved mesh that follows the sphere surface
     * @param {number} centerYaw - Center yaw in degrees
     * @param {number} centerPitch - Center pitch in degrees  
     * @param {number} widthDeg - Width in degrees
     * @param {number} heightDeg - Height in degrees
     * @param {number} radius - Sphere radius
     * @param {number} hSegments - Horizontal subdivisions
     * @param {number} vSegments - Vertical subdivisions
     * @returns {THREE.BufferGeometry} Curved segment geometry
     */
    createSphericalSegment(centerYaw, centerPitch, widthDeg, heightDeg, radius, hSegments, vSegments) {
        const geometry = new THREE.BufferGeometry();
        
        // Convert to radians
        const yawRad = centerYaw * Math.PI / 180;
        const pitchRad = centerPitch * Math.PI / 180;
        const widthRad = widthDeg * Math.PI / 180;
        const heightRad = heightDeg * Math.PI / 180;
        
        const vertices = [];
        const normals = [];
        const uvs = [];
        const indices = [];
        
        // Generate vertices
        for (let v = 0; v <= vSegments; v++) {
            const vNorm = v / vSegments;
            // Calculate pitch for this vertex
            const vertexPitch = centerPitch - heightDeg/2 + heightDeg * vNorm;
            // Convert pitch to phi (0 at north pole, PI at south pole)
            const phi = (90 - vertexPitch) * Math.PI / 180;
            
            for (let h = 0; h <= hSegments; h++) {
                const hNorm = h / hSegments;
                const theta = yawRad - widthRad/2 + widthRad * hNorm;
                
                // Convert spherical to cartesian
                // Note: phi is from vertical axis (0 at top)
                const sinPhi = Math.sin(phi);
                const cosPhi = Math.cos(phi);
                const sinTheta = Math.sin(theta);
                const cosTheta = Math.cos(theta);
                
                // Three.js coordinate system: Y is up
                const x = radius * sinPhi * sinTheta;
                const y = radius * cosPhi;
                const z = radius * sinPhi * cosTheta;
                
                vertices.push(x, y, z);
                
                // Normal points inward (for inside of sphere)
                normals.push(-x/radius, -y/radius, -z/radius);
                
                // UV coordinates - flip horizontally to fix mirroring
                uvs.push(1 - hNorm, vNorm);
            }
        }
        
        // Generate indices
        for (let v = 0; v < vSegments; v++) {
            for (let h = 0; h < hSegments; h++) {
                const a = v * (hSegments + 1) + h;
                const b = a + hSegments + 1;
                const c = a + 1;
                const d = b + 1;
                
                indices.push(a, b, c);
                indices.push(b, d, c);
            }
        }
        
        geometry.setAttribute('position', new THREE.Float32BufferAttribute(vertices, 3));
        geometry.setAttribute('normal', new THREE.Float32BufferAttribute(normals, 3));
        geometry.setAttribute('uv', new THREE.Float32BufferAttribute(uvs, 2));
        geometry.setIndex(indices);
        
        return geometry;
    }
    
    addSphericalUVs(geometry) {
        // UV coordinates are already added in createSphericalSegment
        // This method is kept for compatibility
    }

    /**
     * Animate patch "stamping" onto sphere
     * Scales from 0.1 to 1.0 with easing for visual feedback
     * @param {THREE.Mesh} patch - Patch mesh to animate
     */
    animateStamp(patch) {
        const targetScale = 1;
        const duration = 300;  // milliseconds
        const startTime = Date.now();
        
        const animate = () => {
            const elapsed = Date.now() - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            // Easing function for stamp effect
            const easeOut = 1 - Math.pow(1 - progress, 3);
            patch.scale.setScalar(0.1 + (targetScale - 0.1) * easeOut);
            
            if (progress < 1) {
                requestAnimationFrame(animate);
            }
        };
        
        animate();
    }

    /**
     * Update camera rotation based on device orientation
     * Converts device gyroscope data to 3D camera rotation
     * @param {number} deviceAlpha - Compass heading (0-360°)
     * @param {number} deviceBeta - Front-to-back tilt (-180 to 180°)
     * @param {number} deviceGamma - Left-to-right tilt (-90 to 90°)
     */
    updateCameraRotation(deviceAlpha, deviceBeta, deviceGamma) {
        // Convert degrees to radians
        const alpha = (deviceAlpha || 0) * Math.PI / 180;
        const beta = (deviceBeta || 0) * Math.PI / 180;
        const gamma = (deviceGamma || 0) * Math.PI / 180;
        
        // Create Euler rotation with YXZ order (important for device orientation)
        const euler = new THREE.Euler(beta, alpha, -gamma, 'YXZ');
        this.camera.quaternion.setFromEuler(euler);
        
        // Rotate 90° for portrait phone orientation
        this.camera.rotateX(-Math.PI / 2);
    }

    /**
     * Render the scene with animations
     * Called every frame to update the 3D view
     */
    render() {
        // Subtle "breathing" animation for captured patches
        this.capturedPatches.forEach((patch, i) => {
            // Oscillate scale by ±1% with offset per patch
            const breathe = 1 + Math.sin(Date.now() * 0.0005 + i) * 0.01;
            patch.scale.set(breathe, breathe, breathe);
        });
        
        this.renderer.render(this.scene, this.camera);
    }

    handleResize() {
        this.camera.aspect = window.innerWidth / window.innerHeight;
        this.camera.updateProjectionMatrix();
        this.renderer.setSize(window.innerWidth, window.innerHeight);
    }

    /**
     * Clear all captured patches and reset scene
     * Properly disposes of textures and geometry to prevent memory leaks
     */
    clearCapturedPatches() {
        this.capturedPatches.forEach(patch => {
            this.scene.remove(patch);
            // Dispose of GPU resources
            if (patch.material.map) patch.material.map.dispose();
            patch.material.dispose();
            patch.geometry.dispose();
        });
        this.capturedPatches = [];
        
        // Reset hotspot markers to visible orange state
        this.resetAllHotspots();
    }

    // Show start screen state - hide capture UI elements
    showStartScreenState() {
        this.hotspotMarkers.forEach(marker => marker.visible = false);
        this.capturedPatches.forEach(patch => patch.visible = false);
    }

    // Show capture mode state - show capture UI elements
    showCaptureState() {
        this.hotspotMarkers.forEach(marker => marker.visible = true);
        this.capturedPatches.forEach(patch => patch.visible = true);
        
        // Reset hotspot colors/opacity but keep them visible
        this.resetAllHotspots();
    }

    /**
     * Generate equirectangular panorama from captured patches
     * This is the advanced method that creates seamless panoramas by:
     * 1. Projecting patches onto equirectangular canvas
     * 2. Finding overlapping patches for each pixel
     * 3. Blending with distance-based weights
     * @param {Function} progressCallback - Called with (progress, message)
     * @returns {Promise<Blob>} JPEG blob of panorama
     */
    async captureEnhancedEquirectangular(progressCallback) {
        console.log('Starting enhanced 3D capture');
        console.log('Captured patches:', this.capturedPatches.length);
        
        // Debug: Check if patches have hotspot data
        this.capturedPatches.forEach((patch, i) => {
            const hotspot = patch.userData.hotspot;
            if (hotspot) {
                console.log(`Patch ${i}: hotspot id=${hotspot.id}, yaw=${hotspot.yaw}, pitch=${hotspot.pitch}`);
            } else {
                console.log(`Patch ${i}: NO HOTSPOT DATA`);
            }
        });
        
        // Test: Check coverage at equator where images actually are
        if (this.capturedPatches.length > 0) {
            const testHotspot = this.capturedPatches[0].userData.hotspot;
            console.log(`Testing coverage at equator (yaw=${testHotspot.yaw}, pitch=${testHotspot.pitch})`);
            const testContributors = this.findContributingPatches(testHotspot.yaw, testHotspot.pitch);
            console.log(`Found ${testContributors.length} contributors at equator`);
        }
        
        // Reduced resolution for testing
        const width = 800; // was 3200
        const height = 400; // was 1600
        
        // Create output canvas
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext('2d');
        
        // Initialize with black
        ctx.fillStyle = '#000';
        ctx.fillRect(0, 0, width, height);
        
        // Create accumulator for weighted blending
        const imageData = ctx.createImageData(width, height);
        const weights = new Float32Array(width * height);
        
        let processedPixels = 0;
        const totalPixels = width * height;
        
        // Process in chunks for better performance
        const chunkSize = 100; // Process 100 rows at a time
        
        for (let startY = 0; startY < height; startY += chunkSize) {
            const endY = Math.min(startY + chunkSize, height);
            
            // Process chunk
            await this.processChunk(startY, endY, width, height, imageData, weights);
            
            // Update progress
            processedPixels = endY * width;
            const progress = processedPixels / totalPixels;
            if (progressCallback) {
                progressCallback(progress, `Capturing from 3D: ${Math.round(progress * 100)}%`);
            }
            
            // Allow UI to update
            await new Promise(resolve => setTimeout(resolve, 10));
        }
        
        console.log('Processing complete, normalizing weights');
        
        // Count pixels with data
        let pixelsWithData = 0;
        
        // Normalize by weights
        for (let i = 0; i < imageData.data.length; i += 4) {
            const pixelIndex = i / 4;
            const weight = weights[pixelIndex];
            
            if (weight > 0) {
                imageData.data[i] /= weight;     // R
                imageData.data[i + 1] /= weight; // G
                imageData.data[i + 2] /= weight; // B
                imageData.data[i + 3] = 255;     // A
                pixelsWithData++;
            }
        }
        
        console.log(`Pixels with data: ${pixelsWithData} / ${totalPixels}`);
        
        // Put result on canvas
        ctx.putImageData(imageData, 0, 0);
        
        // Convert to blob
        return new Promise(resolve => {
            canvas.toBlob(blob => {
                console.log('Blob created:', blob ? blob.size : 'null');
                resolve(blob);
            }, 'image/jpeg', 0.95);
        });
    }

    /**
     * Process a chunk of pixels for the equirectangular projection
     * Finds contributing patches and blends their colors
     * @param {number} startY - Starting Y coordinate
     * @param {number} endY - Ending Y coordinate  
     * @param {number} width - Canvas width
     * @param {number} height - Canvas height
     * @param {ImageData} imageData - Output image data
     * @param {Float32Array} weights - Weight accumulator
     */
    async processChunk(startY, endY, width, height, imageData, weights) {
        let contributorsFound = 0;
        let pixelsProcessed = 0;
        
        for (let y = startY; y < endY; y++) {
            for (let x = 0; x < width; x++) {
                pixelsProcessed++;
                
                // Convert pixel to spherical coordinates
                const theta = (x / width) * Math.PI * 2; // 0 to 2π
                const phi = (y / height) * Math.PI;      // 0 to π
                
                // Convert to yaw/pitch
                // In equirectangular: top = +90°, middle = 0°, bottom = -90°
                const yaw = theta * 180 / Math.PI;
                const pitch = 90 - (phi * 180 / Math.PI);
                
                // Find contributing patches
                const contributors = this.findContributingPatches(yaw, pitch);
                
                if (contributors.length > 0) {
                    contributorsFound++;
                    
                    // Calculate weighted color
                    let r = 0, g = 0, b = 0;
                    let totalWeight = 0;
                    
                    for (const contributor of contributors) {
                        const weight = this.calculateWeight(contributor, yaw, pitch);
                        const color = this.samplePatchColor(contributor, yaw, pitch);
                        
                        if (color) {
                            r += color.r * weight;
                            g += color.g * weight;
                            b += color.b * weight;
                            totalWeight += weight;
                        }
                    }
                    
                    if (totalWeight > 0) {
                        const idx = (y * width + x) * 4;
                        imageData.data[idx] += r;
                        imageData.data[idx + 1] += g;
                        imageData.data[idx + 2] += b;
                        weights[y * width + x] += totalWeight;
                    }
                }
            }
        }
        
        if (startY === 0) {
            console.log(`First chunk: ${contributorsFound} pixels with contributors out of ${pixelsProcessed}`);
        }
    }

    /**
     * Find all patches that cover a given spherical coordinate
     * Used during panorama generation to blend overlapping images
     * @param {number} yaw - Target yaw in degrees
     * @param {number} pitch - Target pitch in degrees
     * @returns {Array} Array of contributing patches with coverage info
     */
    findContributingPatches(yaw, pitch) {
        const contributors = [];
        
        // Debug only very first call
        if (!this._debugCounter) {
            this._debugCounter = 0;
            console.log(`First pixel: Finding contributors for yaw: ${yaw.toFixed(1)}, pitch: ${pitch.toFixed(1)}`);
        }
        this._debugCounter++;
        
        // Check each captured patch
        this.capturedPatches.forEach((patch, index) => {
            const hotspot = patch.userData.hotspot;
            if (!hotspot) {
                console.warn('Patch missing hotspot reference');
                return;
            }
            
            // Debug removed to prevent spam
            
            // Check if this patch covers the given yaw/pitch
            const coverage = this.checkPatchCoverage(hotspot, yaw, pitch);
            if (coverage > 0) {
                contributors.push({
                    patch: patch,
                    hotspot: hotspot,
                    coverage: coverage,
                    texture: patch.material.map
                });
            }
        });
        
        return contributors;
    }

    /**
     * Check if a patch covers a given point
     * Returns coverage strength (1 at center, 0 at edge)
     * @param {Object} hotspot - Hotspot with position
     * @param {number} yaw - Point yaw in degrees
     * @param {number} pitch - Point pitch in degrees
     * @returns {number} Coverage strength (0-1)
     */
    checkPatchCoverage(hotspot, yaw, pitch) {
        // FOV of each patch (must match createPatchMesh)
        const horizontalFOV = 40;
        const verticalFOV = horizontalFOV * (16/9);
        
        // Calculate angular distance
        const yawDiff = Math.abs(this.normalizeAngle(yaw - hotspot.yaw));
        const pitchDiff = Math.abs(pitch - hotspot.pitch);
        
        // Check if within FOV
        if (yawDiff > horizontalFOV / 2 || pitchDiff > verticalFOV / 2) {
            return 0;
        }
        
        // Return coverage strength (1 at center, 0 at edge)
        const yawCoverage = 1 - (yawDiff / (horizontalFOV / 2));
        const pitchCoverage = 1 - (pitchDiff / (verticalFOV / 2));
        
        return yawCoverage * pitchCoverage;
    }

    normalizeAngle(angle) {
        while (angle > 180) angle -= 360;
        while (angle < -180) angle += 360;
        return angle;
    }

    /**
     * Calculate blending weight for a contributing patch
     * Uses Gaussian falloff from patch center
     * @param {Object} contributor - Contributing patch info
     * @param {number} yaw - Target yaw
     * @param {number} pitch - Target pitch
     * @returns {number} Blending weight
     */
    calculateWeight(contributor, yaw, pitch) {
        const { hotspot, coverage } = contributor;
        
        // Distance from patch center
        const yawDiff = Math.abs(this.normalizeAngle(yaw - hotspot.yaw));
        const pitchDiff = Math.abs(pitch - hotspot.pitch);
        const normalizedDist = Math.sqrt(
            Math.pow(yawDiff / 20, 2) + 
            Math.pow(pitchDiff / 35, 2)
        ) / Math.sqrt(2);
        
        // Weight based on distance from center (gaussian falloff)
        const distanceWeight = Math.exp(-normalizedDist * normalizedDist * 2);
        
        // Additional quality factors could be added here:
        // - Sharpness score
        // - Exposure matching
        // - Timestamp (prefer newer)
        
        return distanceWeight * coverage;
    }

    /**
     * Sample color from a patch at given coordinates
     * Converts spherical coords to UV and samples texture
     * @param {Object} contributor - Patch contributor
     * @param {number} yaw - Sample yaw
     * @param {number} pitch - Sample pitch
     * @returns {Object|null} RGBA color object
     */
    samplePatchColor(contributor, yaw, pitch) {
        const { patch, hotspot, texture } = contributor;
        
        if (!texture || !texture.image) return null;
        
        // Convert yaw/pitch to UV coordinates on this patch
        const horizontalFOV = 40;
        const verticalFOV = horizontalFOV * (16/9);
        
        // Calculate relative position within patch
        const relativeYaw = yaw - hotspot.yaw;
        const relativePitch = pitch - hotspot.pitch;
        
        // Normalize to 0-1 UV space
        const u = 0.5 + (relativeYaw / horizontalFOV);
        const v = 0.5 - (relativePitch / verticalFOV); // Flip V for correct orientation
        
        // Check bounds
        if (u < 0 || u > 1 || v < 0 || v > 1) return null;
        
        // Sample texture
        return this.sampleTexture(texture, u, v);
    }

    /**
     * Sample a pixel from texture at UV coordinates
     * Uses canvas to extract pixel color
     * @param {THREE.Texture} texture - Texture to sample
     * @param {number} u - U coordinate (0-1)
     * @param {number} v - V coordinate (0-1)
     * @returns {Object|null} RGBA color object
     */
    sampleTexture(texture, u, v) {
        if (!texture.image) return null;
        
        // Create temporary canvas to sample pixel
        if (!this.samplerCanvas) {
            this.samplerCanvas = document.createElement('canvas');
            this.samplerCtx = this.samplerCanvas.getContext('2d');
        }
        
        const img = texture.image;
        this.samplerCanvas.width = img.width;
        this.samplerCanvas.height = img.height;
        this.samplerCtx.drawImage(img, 0, 0);
        
        // Get pixel at UV coordinate
        const x = Math.floor(u * img.width);
        const y = Math.floor(v * img.height);
        
        const pixel = this.samplerCtx.getImageData(x, y, 1, 1).data;
        
        return {
            r: pixel[0],
            g: pixel[1],
            b: pixel[2],
            a: pixel[3]
        };
    }
}