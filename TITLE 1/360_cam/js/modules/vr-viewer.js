/**
 * VR Viewer Module
 * Provides immersive VR viewing of photospheres using WebGL2
 * Based on vr.html implementation with device orientation support
 */

export class VRViewer {
    constructor(app) {
        this.app = app;
        this.isActive = false;
        this.overlay = null;
        this.canvas = null;
        this.gl = null;
        this.prog = null;
        this.tex = null;
        this.maskImg = null;
        
        // Orientation tracking
        this.yaw = 0;
        this.pitch = 0;
        this.yawOffset = 0;
        this.q1Sign = -1;
        this.calibrated = false;
        
        // Settings
        this.fovDeg = 90;
        this.ipdYaw = this.loadIPDSetting();
        
        // Touch handling
        this.lastTap = 0;
        this.tapTimer = null;
        
        // Drag handling for desktop
        this.dragging = false;
        this.lastX = 0;
        this.lastY = 0;
    }
    
    /**
     * Load IPD setting from localStorage
     */
    loadIPDSetting() {
        const v = parseFloat(localStorage.getItem('vr_ipdYaw'));
        return Number.isFinite(v) ? Math.max(0, Math.min(0.12, v)) : 0.100;
    }
    
    /**
     * Save IPD setting to localStorage
     */
    saveIPDSetting(value) {
        this.ipdYaw = value;
        localStorage.setItem('vr_ipdYaw', value.toString());
    }
    
    /**
     * Create and show VR viewer overlay
     */
    async show(imageData) {
        console.log('VR Viewer: Starting show()');
        if (this.isActive) {
            console.log('VR Viewer: Already active, returning');
            return;
        }
        
        // Clean up any previous resources
        this.cleanup();
        
        try {
            console.log('VR Viewer: Creating overlay');
            this.createOverlay();
            
            console.log('VR Viewer: Initializing WebGL');
            this.initWebGL();  // This starts the render loop
            
            console.log('VR Viewer: Loading panorama');
            await this.loadPanorama(imageData);
            
            console.log('VR Viewer: Entering VR mode');
            await this.enterVR();
            
            // Set active AFTER everything is ready
            this.isActive = true;
            
            // Start the render loop NOW after everything is set up
            this.startRenderLoop();
            
            console.log('VR Viewer: Successfully activated');
        } catch (error) {
            console.error('VR Viewer error:', error);
            this.exitVR();
        }
    }
    
    /**
     * Clean up WebGL resources
     */
    cleanup() {
        if (this.tex && this.gl) {
            this.gl.deleteTexture(this.tex);
            this.tex = null;
        }
        if (this.prog && this.gl) {
            this.gl.deleteProgram(this.prog);
            this.prog = null;
        }
        this.gl = null;
        this.canvas = null;
        this.maskCanvas = null;
    }
    
    /**
     * Create the VR overlay DOM structure
     */
    createOverlay() {
        // Create main overlay container
        this.overlay = document.createElement('div');
        this.overlay.id = 'vr-viewer-overlay';
        this.overlay.className = 'vr-overlay visible lifting-in';
        this.overlay.innerHTML = `
            <style>
                .vr-overlay {
                    position: fixed;
                    inset: 0;
                    z-index: 10000;
                    background: #000;
                    display: block;
                    opacity: 1;
                    transition: opacity 0.3s ease;
                }
                .vr-overlay.lifting-in {
                    animation: liftIn 0.3s ease forwards;
                }
                @keyframes liftIn {
                    from { opacity: 0; }
                    to { opacity: 1; }
                }
                
                #vr-canvas {
                    position: absolute;
                    inset: 0;
                    width: 100vw;
                    height: 100vh;
                    background: #000;
                }
                
                #vr-mask-overlay {
                    position: fixed;
                    inset: 0;
                    width: 100%;
                    height: 100%;
                    pointer-events: none;
                }
                
                #vr-hud {
                    position: fixed;
                    top: calc(env(safe-area-inset-top, 0px) + 8px);
                    left: 50%;
                    transform: translateX(-50%);
                    display: none;
                    gap: 10px;
                    align-items: center;
                    background: rgba(0,0,0,.6);
                    color: #eee;
                    padding: 8px 12px;
                    border-radius: 10px;
                    z-index: 10030;
                    backdrop-filter: blur(6px);
                }
                #vr-hud.show { display: flex; }
                #vr-hud label { display: flex; gap: 8px; align-items: center; }
                #vr-hud input[type="range"] { width: 120px; }
                #vr-hud .val { min-width: 50px; text-align: right; font-variant-numeric: tabular-nums; }
                #vr-hud button { 
                    padding: 6px 10px; 
                    border-radius: 8px; 
                    border: 1px solid #444; 
                    background: #1b1b1b; 
                    color: #eee;
                    cursor: pointer;
                }
                #vr-hud button.icon { 
                    width: 36px; 
                    height: 36px; 
                    padding: 0; 
                    display: flex; 
                    align-items: center; 
                    justify-content: center; 
                }
                #vr-hud button.icon img { 
                    width: 22px; 
                    height: 22px; 
                    display: block; 
                    filter: invert(1); 
                    opacity: .9;
                }
                
                #vr-exit {
                    position: fixed;
                    right: env(safe-area-inset-right, 12px);
                    top: env(safe-area-inset-top, 12px);
                    width: 44px;
                    height: 44px;
                    z-index: 10030;
                    background: rgba(0,0,0,0.6);
                    backdrop-filter: blur(10px);
                    border: 1px solid rgba(255,255,255,0.2);
                    border-radius: 50%;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                    font-size: 24px;
                }
                
                #vr-rotate-prompt {
                    position: fixed;
                    inset: 0;
                    z-index: 10040;
                    display: none;
                    align-items: center;
                    justify-content: center;
                    text-align: center;
                    background: rgba(0,0,0,.85);
                    color: #fff;
                    padding: 24px;
                }
                #vr-rotate-prompt.show { display: flex; }
                #vr-rotate-prompt h2 { margin: 0 0 10px 0; font-size: 18px; }
                #vr-rotate-prompt p { margin: 0 0 12px 0; color: #ddd; }
                #vr-rotate-prompt button {
                    padding: 8px 16px;
                    border-radius: 8px;
                    border: 1px solid #444;
                    background: #1b1b1b;
                    color: #eee;
                    cursor: pointer;
                }
            </style>
            
            <canvas id="vr-canvas"></canvas>
            <canvas id="vr-mask-overlay"></canvas>
            
            <button id="vr-exit" aria-label="Exit VR" title="Exit VR">×</button>
            
            <!-- VR HUD -->
            <div id="vr-hud" aria-live="polite">
                <label>IPD
                    <input id="vr-ipd" type="range" min="0" max="0.12" step="0.005" value="${this.ipdYaw}">
                </label>
                <div class="val" id="vr-ipd-val">${this.ipdYaw.toFixed(3)}</div>
                <button id="vr-recenter" class="icon" aria-label="Recenter">
                    <img src="/img/recenter.svg" alt="">
                </button>
                <button id="vr-fullscreen" class="icon" aria-label="Fullscreen">
                    <img src="/img/fullscreen.svg" alt="">
                </button>
            </div>
            
            <!-- Portrait warning -->
            <div id="vr-rotate-prompt">
                <div>
                    <h2>Please rotate your phone</h2>
                    <p>VR works best in landscape mode</p>
                    <button id="vr-rotate-exit">Exit VR</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(this.overlay);
        
        // Get canvas references
        this.canvas = document.getElementById('vr-canvas');
        this.maskCanvas = document.getElementById('vr-mask-overlay');
        
        // Bind events
        this.bindEvents();
        
        // Load mask image
        this.loadMask();
        
        // Remove lifting-in animation after it completes
        setTimeout(() => {
            this.overlay.classList.remove('lifting-in');
        }, 300);
    }
    
    /**
     * Load the VR mask image
     */
    loadMask() {
        this.maskImg = new Image();
        this.maskImg.src = '/img/vr-mask.png';
        this.maskImg.crossOrigin = 'anonymous';
        this.maskImg.decoding = 'async';
        this.maskImg.onload = () => this.drawMask();
    }
    
    /**
     * Draw the mask overlay
     */
    drawMask() {
        if (!this.maskImg || !this.maskImg.complete) return;
        
        const ctx = this.maskCanvas.getContext('2d');
        this.maskCanvas.width = this.gl.canvas.width;
        this.maskCanvas.height = this.gl.canvas.height;
        
        ctx.clearRect(0, 0, this.maskCanvas.width, this.maskCanvas.height);
        ctx.drawImage(this.maskImg, 0, 0, this.maskCanvas.width, this.maskCanvas.height);
    }
    
    /**
     * Initialize WebGL2 context and shaders
     */
    initWebGL() {
        console.log('VR Viewer: Getting WebGL2 context from canvas:', this.canvas);
        if (!this.canvas) {
            throw new Error('Canvas element not found');
        }
        
        this.gl = this.canvas.getContext('webgl2', { antialias: true, alpha: false });
        if (!this.gl) {
            throw new Error('WebGL2 not available');
        }
        console.log('VR Viewer: WebGL2 context acquired');
        
        // Vertex shader
        const vs = `#version 300 es
            layout(location=0) in vec2 pos;
            out vec2 uv;
            void main() {
                uv = 0.5 * pos + 0.5;
                gl_Position = vec4(pos, 0, 1);
            }`;
        
        // Fragment shader
        const fs = `#version 300 es
            precision highp float;
            in vec2 uv;
            out vec4 frag;
            uniform sampler2D pano;
            uniform vec2 eye;
            uniform float aspect;
            uniform float fov;
            uniform float yaw;
            uniform float pitch;
            uniform float ipdYaw;
            
            void main() {
                float x0 = eye.x, w = eye.y;
                float xe = (uv.x - x0) / w;
                if (xe < 0.0 || xe > 1.0) discard;
                
                float sx = mix(-1.0, 1.0, xe);
                float sy = mix(1.0, -1.0, uv.y);
                float th = tan(0.5 * fov);
                vec3 d = normalize(vec3(sx * th * aspect, sy * th, -1.0));
                
                float cp = cos(pitch), sp = sin(pitch);
                vec3 d1 = vec3(d.x, cp * d.y - sp * d.z, sp * d.y + cp * d.z);
                
                float cy = cos(yaw + ipdYaw), syw = sin(yaw + ipdYaw);
                vec3 v = vec3(cy * d1.x + syw * d1.z, d1.y, -syw * d1.x + cy * d1.z);
                
                float lon = atan(v.x, -v.z);
                float lat = asin(clamp(v.y, -1.0, 1.0));
                vec2 t;
                t.x = (lon + 3.14159265) / 6.2831853;
                t.y = (1.5707963 - lat) / 3.14159265;
                
                frag = texture(pano, t);
            }`;
        
        // Compile shaders
        const compileShader = (type, source) => {
            const shader = this.gl.createShader(type);
            this.gl.shaderSource(shader, source);
            this.gl.compileShader(shader);
            if (!this.gl.getShaderParameter(shader, this.gl.COMPILE_STATUS)) {
                throw this.gl.getShaderInfoLog(shader);
            }
            return shader;
        };
        
        // Create program
        this.prog = this.gl.createProgram();
        this.gl.attachShader(this.prog, compileShader(this.gl.VERTEX_SHADER, vs));
        this.gl.attachShader(this.prog, compileShader(this.gl.FRAGMENT_SHADER, fs));
        this.gl.linkProgram(this.prog);
        
        if (!this.gl.getProgramParameter(this.prog, this.gl.LINK_STATUS)) {
            throw this.gl.getProgramInfoLog(this.prog);
        }
        
        this.gl.useProgram(this.prog);
        
        // Set up geometry
        const buf = this.gl.createBuffer();
        this.gl.bindBuffer(this.gl.ARRAY_BUFFER, buf);
        this.gl.bufferData(this.gl.ARRAY_BUFFER, new Float32Array([-1,-1, 1,-1, -1,1, 1,1]), this.gl.STATIC_DRAW);
        this.gl.enableVertexAttribArray(0);
        this.gl.vertexAttribPointer(0, 2, this.gl.FLOAT, false, 0, 0);
        
        // Get uniform locations
        this.uniforms = {
            pano: this.gl.getUniformLocation(this.prog, 'pano'),
            eye: this.gl.getUniformLocation(this.prog, 'eye'),
            aspect: this.gl.getUniformLocation(this.prog, 'aspect'),
            fov: this.gl.getUniformLocation(this.prog, 'fov'),
            yaw: this.gl.getUniformLocation(this.prog, 'yaw'),
            pitch: this.gl.getUniformLocation(this.prog, 'pitch'),
            ipdYaw: this.gl.getUniformLocation(this.prog, 'ipdYaw')
        };
        
        this.gl.uniform1i(this.uniforms.pano, 0);
        
        // Don't start render loop here - wait until everything is loaded
    }
    
    /**
     * Load a panorama image
     */
    async loadPanorama(imageData) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.crossOrigin = 'anonymous';
            img.decoding = 'async';
            
            img.onload = () => {
                // Always create a new texture for this context
                this.tex = this.gl.createTexture();
                
                this.gl.activeTexture(this.gl.TEXTURE0);
                this.gl.bindTexture(this.gl.TEXTURE_2D, this.tex);
                this.gl.pixelStorei(this.gl.UNPACK_FLIP_Y_WEBGL, true);
                this.gl.texImage2D(this.gl.TEXTURE_2D, 0, this.gl.RGBA, this.gl.RGBA, this.gl.UNSIGNED_BYTE, img);
                this.gl.texParameteri(this.gl.TEXTURE_2D, this.gl.TEXTURE_MIN_FILTER, this.gl.NEAREST);
                this.gl.texParameteri(this.gl.TEXTURE_2D, this.gl.TEXTURE_MAG_FILTER, this.gl.NEAREST);
                this.gl.texParameteri(this.gl.TEXTURE_2D, this.gl.TEXTURE_WRAP_S, this.gl.REPEAT);
                this.gl.texParameteri(this.gl.TEXTURE_2D, this.gl.TEXTURE_WRAP_T, this.gl.CLAMP_TO_EDGE);
                
                console.log('VR Viewer: Panorama loaded successfully');
                resolve();
            };
            
            img.onerror = () => reject(new Error('Failed to load panorama'));
            img.src = imageData;
        });
    }
    
    /**
     * Enter VR mode
     */
    async enterVR() {
        await this.goFullscreen();
        await this.startSensors();
        this.yawOffset = this.wrapPi(-this.yaw); // Recenter on entry
        this.resize();
        this.updateRotatePrompt();
    }
    
    /**
     * Exit VR mode
     */
    async exitVR() {
        console.log('VR Viewer: Exiting VR');
        this.isActive = false;
        
        if (document.fullscreenElement) {
            try { await document.exitFullscreen(); } catch {}
        }
        
        // Stop render loop
        if (this.renderFrame) {
            cancelAnimationFrame(this.renderFrame);
            this.renderFrame = null;
        }
        
        // Remove overlay with animation
        if (this.overlay) {
            this.overlay.style.opacity = '0';
            setTimeout(() => {
                if (this.overlay && this.overlay.parentNode) {
                    this.overlay.parentNode.removeChild(this.overlay);
                    this.overlay = null;
                }
                // Clean up WebGL resources after overlay is removed
                this.cleanup();
            }, 300);
        }
    }
    
    /**
     * Request fullscreen
     */
    async goFullscreen() {
        try {
            await document.documentElement.requestFullscreen({ navigationUI: "hide" });
        } catch {}
        
        try {
            if (screen.orientation?.lock) {
                await screen.orientation.lock('landscape');
            }
        } catch {}
    }
    
    /**
     * Start device orientation sensors
     */
    async startSensors() {
        const handle = (e) => {
            const orient = (screen.orientation && typeof screen.orientation.angle === 'number')
                ? screen.orientation.angle
                : (typeof window.orientation === 'number' ? window.orientation : 0);
            
            let q = this.setObjectQuaternion(e.alpha, e.beta, e.gamma, orient, this.q1Sign);
            
            if (!this.calibrated) {
                const t = this.yawPitchFromQ(q);
                if (Math.abs(t.pitch) > Math.PI * 0.45) {
                    this.q1Sign = +1;
                    q = this.setObjectQuaternion(e.alpha, e.beta, e.gamma, orient, this.q1Sign);
                }
                this.calibrated = true;
            }
            
            const {yaw, pitch} = this.yawPitchFromQ(q);
            this.yaw = yaw;
            this.pitch = this.clamp(-pitch, -Math.PI/2 + 0.05, Math.PI/2 - 0.05);
            
            this.updateRotatePrompt();
        };
        
        if (typeof DeviceOrientationEvent !== 'undefined' &&
            typeof DeviceOrientationEvent.requestPermission === 'function') {
            try {
                const r = await DeviceOrientationEvent.requestPermission();
                if (r === 'granted') {
                    window.addEventListener('deviceorientation', handle, true);
                }
            } catch {}
        } else {
            window.addEventListener('deviceorientation', handle, true);
        }
    }
    
    /**
     * Quaternion math helpers
     */
    qAxis(x, y, z, ang) {
        const s = Math.sin(ang / 2);
        return {x: x * s, y: y * s, z: z * s, w: Math.cos(ang / 2)};
    }
    
    qMul(a, b) {
        return {
            x: a.w * b.x + a.x * b.w + a.y * b.z - a.z * b.y,
            y: a.w * b.y - a.x * b.z + a.y * b.w + a.z * b.x,
            z: a.w * b.z + a.x * b.y - a.y * b.x + a.z * b.w,
            w: a.w * b.w - a.x * b.x - a.y * b.y - a.z * b.z
        };
    }
    
    yawPitchFromQ(q) {
        const sinp = 2 * (q.w * q.x - q.y * q.z);
        const pitch = Math.abs(sinp) >= 1 ? Math.sign(sinp) * Math.PI / 2 : Math.asin(sinp);
        const yaw = Math.atan2(2 * (q.w * q.y + q.x * q.z), 1 - 2 * (q.x * q.x + q.y * q.y));
        return {yaw, pitch};
    }
    
    setObjectQuaternion(alpha, beta, gamma, orient, q1Sign) {
        const a = (alpha || 0) * Math.PI / 180;
        const b = (beta || 0) * Math.PI / 180;
        const g = (gamma || 0) * Math.PI / 180;
        const o = (orient || 0) * Math.PI / 180;
        
        const qy = this.qAxis(0, 1, 0, a);
        const qx = this.qAxis(1, 0, 0, b);
        const qz = this.qAxis(0, 0, 1, -g);
        
        let q = this.qMul(this.qMul(qy, qx), qz);
        q = this.qMul(q, this.qAxis(1, 0, 0, q1Sign * Math.PI / 2));
        q = this.qMul(q, this.qAxis(0, 0, 1, -o));
        
        return q;
    }
    
    clamp(v, a, b) {
        return Math.max(a, Math.min(b, v));
    }
    
    wrapPi(a) {
        const TAU = Math.PI * 2;
        while (a > Math.PI) a -= TAU;
        while (a < -Math.PI) a += TAU;
        return a;
    }
    
    /**
     * Check if in landscape orientation
     */
    isLandscape() {
        const w = window.innerWidth;
        const h = window.innerHeight;
        const bySize = w >= h;
        
        const angle = (screen.orientation && typeof screen.orientation.angle === 'number') 
            ? screen.orientation.angle
            : (typeof window.orientation === 'number' ? window.orientation : 0);
        const byAngle = Math.abs(angle % 180) === 90;
        
        return bySize || byAngle;
    }
    
    /**
     * Update rotate prompt visibility
     */
    updateRotatePrompt() {
        const prompt = document.getElementById('vr-rotate-prompt');
        if (prompt) {
            const show = this.isActive && !this.isLandscape();
            prompt.classList.toggle('show', show);
        }
    }
    
    /**
     * Resize canvas
     */
    resize() {
        if (!this.gl || !this.gl.canvas) {
            console.log('VR Viewer: Cannot resize - no GL context');
            return;
        }
        
        const dpr = Math.max(1, devicePixelRatio || 1);
        const w = Math.floor(window.innerWidth * dpr);
        const h = Math.floor(window.innerHeight * dpr);
        
        console.log(`VR Viewer: Resizing to ${w}x${h}`);
        
        this.gl.canvas.width = w;
        this.gl.canvas.height = h;
        this.gl.viewport(0, 0, w, h);
        
        if (this.uniforms && this.uniforms.aspect) {
            this.gl.uniform1f(this.uniforms.aspect, (w * 0.5) / h); // Per-eye aspect
        }
        
        this.drawMask();
    }
    
    /**
     * Render frame
     */
    render() {
        // Always continue the render loop
        this.renderFrame = requestAnimationFrame(() => this.render());
        
        if (!this.isActive || !this.gl) {
            return;
        }
        
        const yawForShader = this.wrapPi(this.yaw + this.yawOffset);
        
        this.gl.clearColor(0, 0, 0, 1);
        this.gl.clear(this.gl.COLOR_BUFFER_BIT);
        
        if (this.tex && this.prog) {
            this.gl.useProgram(this.prog);
            this.gl.activeTexture(this.gl.TEXTURE0);
            this.gl.bindTexture(this.gl.TEXTURE_2D, this.tex);
            
            // Set all uniforms
            this.gl.uniform1i(this.uniforms.pano, 0);
            this.gl.uniform1f(this.uniforms.fov, this.fovDeg * Math.PI / 180);
            this.gl.uniform1f(this.uniforms.yaw, yawForShader);
            this.gl.uniform1f(this.uniforms.pitch, this.pitch);
            
            // Get canvas dimensions for aspect ratio
            const aspect = (this.gl.canvas.width * 0.5) / this.gl.canvas.height;
            this.gl.uniform1f(this.uniforms.aspect, aspect);
            
            // Left eye
            this.gl.uniform2f(this.uniforms.eye, 0.0, 0.5);
            this.gl.uniform1f(this.uniforms.ipdYaw, +this.ipdYaw);
            this.gl.drawArrays(this.gl.TRIANGLE_STRIP, 0, 4);
            
            // Right eye
            this.gl.uniform2f(this.uniforms.eye, 0.5, 0.5);
            this.gl.uniform1f(this.uniforms.ipdYaw, -this.ipdYaw);
            this.gl.drawArrays(this.gl.TRIANGLE_STRIP, 0, 4);
        }
    }
    
    /**
     * Start render loop
     */
    startRenderLoop() {
        console.log('VR Viewer: Starting render loop');
        this.renderFrame = requestAnimationFrame(() => this.render());
    }
    
    /**
     * Bind UI events
     */
    bindEvents() {
        // Exit button
        document.getElementById('vr-exit')?.addEventListener('click', () => this.exitVR());
        document.getElementById('vr-rotate-exit')?.addEventListener('click', () => this.exitVR());
        
        // Fullscreen button
        document.getElementById('vr-fullscreen')?.addEventListener('click', () => this.goFullscreen());
        
        // Recenter button
        document.getElementById('vr-recenter')?.addEventListener('click', () => {
            this.yawOffset = this.wrapPi(-this.yaw);
        });
        
        // IPD slider
        const ipdSlider = document.getElementById('vr-ipd');
        const ipdVal = document.getElementById('vr-ipd-val');
        
        if (ipdSlider) {
            ipdSlider.addEventListener('input', () => {
                this.ipdYaw = parseFloat(ipdSlider.value);
                if (ipdVal) ipdVal.textContent = this.ipdYaw.toFixed(3);
                this.saveIPDSetting(this.ipdYaw);
            });
        }
        
        // Touch handling: single tap = toggle HUD, double tap = exit
        window.addEventListener('touchend', (e) => {
            if (!this.isActive) return;
            
            // Ignore if tap is on a button or control
            if (e.target && (e.target.tagName === 'BUTTON' || e.target.closest('button'))) {
                return;
            }
            
            const now = Date.now();
            if (now - this.lastTap < 300) {
                // Double tap - exit
                clearTimeout(this.tapTimer);
                this.tapTimer = null;
                this.exitVR();
            } else {
                // Clear any existing timer
                if (this.tapTimer) {
                    clearTimeout(this.tapTimer);
                }
                // Single tap - toggle HUD after delay
                this.tapTimer = setTimeout(() => {
                    const hud = document.getElementById('vr-hud');
                    if (hud) hud.classList.toggle('show');
                    this.tapTimer = null;
                }, 300);
            }
            this.lastTap = now;
        }, {passive: true});
        
        // Resize handler
        window.addEventListener('resize', () => {
            if (this.isActive) {
                this.resize();
                this.updateRotatePrompt();
            }
        }, {passive: true});
        
        // Orientation change handler
        window.addEventListener('orientationchange', () => {
            if (this.isActive) {
                setTimeout(() => {
                    this.resize();
                    this.updateRotatePrompt();
                }, 60);
            }
        }, {passive: true});
        
        // Fullscreen change handler
        document.addEventListener('fullscreenchange', () => {
            if (!document.fullscreenElement && this.isActive) {
                this.exitVR();
            }
            this.drawMask();
        });
        
        // Pointer drag for desktop testing
        this.canvas?.addEventListener('pointerdown', (e) => {
            this.dragging = true;
            this.lastX = e.clientX;
            this.lastY = e.clientY;
        });
        
        window.addEventListener('pointerup', () => {
            this.dragging = false;
        });
        
        window.addEventListener('pointermove', (e) => {
            if (!this.dragging || !this.isActive) return;
            
            const dx = (e.clientX - this.lastX) / window.innerWidth;
            const dy = (e.clientY - this.lastY) / window.innerHeight;
            
            this.yaw = this.wrapPi(this.yaw - dx * Math.PI * 2 * 0.25);
            this.pitch = this.clamp(this.pitch + dy * Math.PI * 0.5, -Math.PI/2 + 0.05, Math.PI/2 - 0.05);
            
            this.lastX = e.clientX;
            this.lastY = e.clientY;
        });
    }
}