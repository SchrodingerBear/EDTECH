/**
 * Pole Filling for Equirectangular Panoramas
 * ===========================================
 * 
 * This module fills the black "pole holes" at the top and bottom of 360° panoramas
 * where the camera cannot capture (straight up and straight down).
 * 
 * ALGORITHM:
 * ----------
 * 1. Detect where black regions end (typically around 105-150px from top/bottom)
 * 2. Sample pixels from the edge of actual panorama content
 * 3. Stretch those edge pixels to fill the black regions
 * 4. Apply WebGL Gaussian blur to smooth the caps and eliminate artifacts
 * 
 * TECHNICAL APPROACH:
 * -------------------
 * - Column-wise sampling: Each vertical column is sampled independently at y=105 
 *   (top) and y=height-105 (bottom) to preserve horizontal variation
 * - Direct pixel stretching: The sampled color is copied vertically to fill the pole
 * - WebGL Gaussian blur: Two-pass separable blur applied via fragment shader
 *   for efficient GPU-accelerated smoothing
 * 
 * MEMORY CONSIDERATIONS:
 * ----------------------
 * - Initial processing uses 2D canvas context to avoid WebGL state conflicts
 * - Pixels processed directly in TypedArrays for efficiency
 * - WebGL textures and framebuffers properly cleaned up after use
 * - Two-pass separable blur reduces computational complexity from O(n²) to O(2n)
 * 
 * WEBGL BLUR IMPLEMENTATION:
 * --------------------------
 * - Creates dedicated blur shaders and framebuffers
 * - First pass: Horizontal blur to intermediate texture
 * - Second pass: Vertical blur back to main texture
 * - Gaussian weights calculated in shader with σ = radius × 0.35
 * - Blur radius of 30px for optimal smoothing of 120px tall regions
 * - Only processes pixels within pole regions (y < 120 or y > height-120)
 * - Falls back gracefully if WebGL blur fails
 * 
 * @param {WebGLRenderingContext} gl - WebGL context from the panorama canvas
 * @param {HTMLCanvasElement} canvas - The panorama canvas to process
 */
export function fillPolesSimple(gl, canvas) {
    const w = canvas.width;
    const h = canvas.height;
    
    console.log(`Simple pole fill for ${w}x${h} panorama`);
    
    // Read pixels from WebGL canvas
    const pixels = new Uint8Array(w * h * 4);
    gl.readPixels(0, 0, w, h, gl.RGBA, gl.UNSIGNED_BYTE, pixels);
    
    // Create a 2D canvas to work with
    const tempCanvas = document.createElement('canvas');
    tempCanvas.width = w;
    tempCanvas.height = h;
    const ctx = tempCanvas.getContext('2d');
    
    // Put the pixels into an ImageData object
    const imageData = ctx.createImageData(w, h);
    imageData.data.set(pixels);
    
    // Find top and bottom edges of actual content
    let topEdge = 0;
    let bottomEdge = h - 1;
    
    // Find where black regions end (top)
    for (let y = 0; y < h / 3; y++) {
        let colorSum = 0;
        for (let x = 0; x < w; x += 10) {
            const idx = (y * w + x) * 4;
            colorSum += pixels[idx] + pixels[idx + 1] + pixels[idx + 2];
        }
        if (colorSum > w * 3) { // Found non-black content
            topEdge = y;
            break;
        }
    }
    
    // Find where black regions end (bottom)
    for (let y = h - 1; y > 2 * h / 3; y--) {
        let colorSum = 0;
        for (let x = 0; x < w; x += 10) {
            const idx = (y * w + x) * 4;
            colorSum += pixels[idx] + pixels[idx + 1] + pixels[idx + 2];
        }
        if (colorSum > w * 3) { // Found non-black content
            bottomEdge = y;
            break;
        }
    }
    
    console.log(`Edges found: top=${topEdge}, bottom=${bottomEdge}`);
    
    // Fill poles if needed - sample each column individually
    if (topEdge > 0) {
        // Sample at 105 pixels from top - this is typically where good content starts
        // after the black pole region in most panoramas
        const sampleY = 105; // Fixed sample position
        for (let x = 0; x < w; x++) {
            // Get the color at the fixed sample position for this column
            const srcIdx = (sampleY * w + x) * 4;
            const r = pixels[srcIdx];
            const g = pixels[srcIdx + 1];
            const b = pixels[srcIdx + 2];
            
            // Fill this column up to the sample point
            for (let y = 0; y < sampleY; y++) {
                const dstIdx = (y * w + x) * 4;
                imageData.data[dstIdx] = r;
                imageData.data[dstIdx + 1] = g;
                imageData.data[dstIdx + 2] = b;
                imageData.data[dstIdx + 3] = 255;
            }
        }
    }
    
    if (bottomEdge < h - 1) {
        // For each column, just grab the pixel at h-105 and stretch it
        const sampleY = h - 105; // Fixed sample position
        for (let x = 0; x < w; x++) {
            // Get the color at the fixed sample position for this column
            const srcIdx = (sampleY * w + x) * 4;
            const r = pixels[srcIdx];
            const g = pixels[srcIdx + 1];
            const b = pixels[srcIdx + 2];
            
            // Fill this column from sample point to bottom
            for (let y = sampleY; y < h; y++) {
                const dstIdx = (y * w + x) * 4;
                imageData.data[dstIdx] = r;
                imageData.data[dstIdx + 1] = g;
                imageData.data[dstIdx + 2] = b;
                imageData.data[dstIdx + 3] = 255;
            }
        }
    }
    
    // Draw the modified image back to the 2D canvas
    ctx.putImageData(imageData, 0, 0);
    
    // Now we'll apply WebGL Gaussian blur to the pole regions
    // First, upload the filled image to a texture
    const texture = gl.createTexture();
    gl.bindTexture(gl.TEXTURE_2D, texture);
    gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, tempCanvas);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
    
    // Create framebuffer for blur passes
    const framebuffer = gl.createFramebuffer();
    const blurTexture = gl.createTexture();
    gl.bindTexture(gl.TEXTURE_2D, blurTexture);
    gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, w, h, 0, gl.RGBA, gl.UNSIGNED_BYTE, null);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
    
    // Create the Gaussian blur shaders
    const blurVS = `
    attribute vec2 a_position;
    varying vec2 v_texCoord;
    void main() {
        gl_Position = vec4(a_position, 0.0, 1.0);
        v_texCoord = a_position * 0.5 + 0.5;
    }`;
    
    const blurFS = `
    precision highp float;
    varying vec2 v_texCoord;
    uniform sampler2D u_texture;
    uniform vec2 u_resolution;
    uniform vec2 u_direction;
    uniform float u_blurRadius;
    uniform float u_poleTop;
    uniform float u_poleBottom;
    
    void main() {
        vec2 texelSize = 1.0 / u_resolution;
        vec4 color = vec4(0.0);
        float total = 0.0;
        
        // Check if we're in a pole region
        float y = v_texCoord.y * u_resolution.y;
        bool inPoleRegion = (y < u_poleTop) || (y > u_poleBottom);
        
        if (!inPoleRegion) {
            // Not in pole region, just pass through
            gl_FragColor = texture2D(u_texture, v_texCoord);
            return;
        }
        
        // Apply Gaussian blur
        float radius = u_blurRadius;
        float sigma = radius * 0.35;
        
        // Use a fixed loop size to avoid issues with dynamic loops
        const int maxRadius = 30;
        for (int j = -maxRadius; j <= maxRadius; j++) {
            float i = float(j);
            if (abs(i) > radius) continue;
            
            float weight = exp(-0.5 * (i * i) / (sigma * sigma));
            vec2 offset = i * texelSize * u_direction;
            color += texture2D(u_texture, v_texCoord + offset) * weight;
            total += weight;
        }
        
        gl_FragColor = color / total;
    }`;
    
    // Compile blur shaders
    const blurVertShader = gl.createShader(gl.VERTEX_SHADER);
    gl.shaderSource(blurVertShader, blurVS);
    gl.compileShader(blurVertShader);
    
    const blurFragShader = gl.createShader(gl.FRAGMENT_SHADER);
    gl.shaderSource(blurFragShader, blurFS);
    gl.compileShader(blurFragShader);
    
    const blurProgram = gl.createProgram();
    gl.attachShader(blurProgram, blurVertShader);
    gl.attachShader(blurProgram, blurFragShader);
    gl.linkProgram(blurProgram);
    
    if (!gl.getProgramParameter(blurProgram, gl.LINK_STATUS)) {
        console.error('Blur shader link failed:', gl.getProgramInfoLog(blurProgram));
        // Fall back to non-blurred version
        gl.deleteProgram(blurProgram);
        gl.deleteShader(blurVertShader);
        gl.deleteShader(blurFragShader);
        gl.deleteTexture(blurTexture);
        gl.deleteFramebuffer(framebuffer);
    } else {
        // Set up blur program
        gl.useProgram(blurProgram);
        
        // Get uniform locations
        const blurUniforms = {
            texture: gl.getUniformLocation(blurProgram, 'u_texture'),
            resolution: gl.getUniformLocation(blurProgram, 'u_resolution'),
            direction: gl.getUniformLocation(blurProgram, 'u_direction'),
            blurRadius: gl.getUniformLocation(blurProgram, 'u_blurRadius'),
            poleTop: gl.getUniformLocation(blurProgram, 'u_poleTop'),
            poleBottom: gl.getUniformLocation(blurProgram, 'u_poleBottom')
        };
        
        // Set uniforms
        gl.uniform2f(blurUniforms.resolution, w, h);
        gl.uniform1f(blurUniforms.blurRadius, 30.0); // 30 pixel blur radius
        gl.uniform1f(blurUniforms.poleTop, 120.0); // Blur top 120 pixels
        gl.uniform1f(blurUniforms.poleBottom, h - 120.0); // Blur bottom 120 pixels
        
        // Create quad buffer for blur
        const blurQuadBuffer = gl.createBuffer();
        gl.bindBuffer(gl.ARRAY_BUFFER, blurQuadBuffer);
        gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1,-1, 1,-1, -1,1, 1,1]), gl.STATIC_DRAW);
        
        const blurPosLocation = gl.getAttribLocation(blurProgram, 'a_position');
        gl.enableVertexAttribArray(blurPosLocation);
        gl.vertexAttribPointer(blurPosLocation, 2, gl.FLOAT, false, 0, 0);
        
        // First pass: Horizontal blur to framebuffer
        gl.bindFramebuffer(gl.FRAMEBUFFER, framebuffer);
        gl.framebufferTexture2D(gl.FRAMEBUFFER, gl.COLOR_ATTACHMENT0, gl.TEXTURE_2D, blurTexture, 0);
        
        gl.activeTexture(gl.TEXTURE0);
        gl.bindTexture(gl.TEXTURE_2D, texture);
        gl.uniform1i(blurUniforms.texture, 0);
        gl.uniform2f(blurUniforms.direction, 1.0, 0.0); // Horizontal
        
        gl.viewport(0, 0, w, h);
        gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
        
        // Second pass: Vertical blur back to main texture
        gl.bindFramebuffer(gl.FRAMEBUFFER, framebuffer);
        gl.framebufferTexture2D(gl.FRAMEBUFFER, gl.COLOR_ATTACHMENT0, gl.TEXTURE_2D, texture, 0);
        
        gl.bindTexture(gl.TEXTURE_2D, blurTexture);
        gl.uniform2f(blurUniforms.direction, 0.0, 1.0); // Vertical
        
        gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
        
        // Clean up blur resources
        gl.deleteProgram(blurProgram);
        gl.deleteShader(blurVertShader);
        gl.deleteShader(blurFragShader);
        gl.deleteBuffer(blurQuadBuffer);
        gl.deleteTexture(blurTexture);
        gl.deleteFramebuffer(framebuffer);
        
        // Reset to default framebuffer
        gl.bindFramebuffer(gl.FRAMEBUFFER, null);
    }
    
    // Very simple shader just to copy texture to screen
    const vs = `
    attribute vec2 a_position;
    varying vec2 v_texCoord;
    void main() {
        gl_Position = vec4(a_position, 0.0, 1.0);
        v_texCoord = a_position * 0.5 + 0.5;
        // Don't flip Y - that was causing the upside-down issue!
    }`;
    
    const fs = `
    precision mediump float;
    varying vec2 v_texCoord;
    uniform sampler2D u_texture;
    void main() {
        gl_FragColor = texture2D(u_texture, v_texCoord);
    }`;
    
    // Create and compile shaders
    const vertShader = gl.createShader(gl.VERTEX_SHADER);
    gl.shaderSource(vertShader, vs);
    gl.compileShader(vertShader);
    
    if (!gl.getShaderParameter(vertShader, gl.COMPILE_STATUS)) {
        console.error('Vertex shader error:', gl.getShaderInfoLog(vertShader));
        gl.deleteTexture(texture);
        return;
    }
    
    const fragShader = gl.createShader(gl.FRAGMENT_SHADER);
    gl.shaderSource(fragShader, fs);
    gl.compileShader(fragShader);
    
    if (!gl.getShaderParameter(fragShader, gl.COMPILE_STATUS)) {
        console.error('Fragment shader error:', gl.getShaderInfoLog(fragShader));
        gl.deleteShader(vertShader);
        gl.deleteTexture(texture);
        return;
    }
    
    // Create program
    const program = gl.createProgram();
    gl.attachShader(program, vertShader);
    gl.attachShader(program, fragShader);
    gl.linkProgram(program);
    
    if (!gl.getProgramParameter(program, gl.LINK_STATUS)) {
        console.error('Program link error:', gl.getProgramInfoLog(program));
        gl.deleteShader(vertShader);
        gl.deleteShader(fragShader);
        gl.deleteTexture(texture);
        return;
    }
    
    // Create a buffer for the quad
    const buffer = gl.createBuffer();
    gl.bindBuffer(gl.ARRAY_BUFFER, buffer);
    gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([
        -1, -1,
         1, -1,
        -1,  1,
         1,  1
    ]), gl.STATIC_DRAW);
    
    // Use the shader program
    gl.useProgram(program);
    
    // Set up the position attribute
    const positionLocation = gl.getAttribLocation(program, 'a_position');
    gl.enableVertexAttribArray(positionLocation);
    gl.vertexAttribPointer(positionLocation, 2, gl.FLOAT, false, 0, 0);
    
    // Set the texture
    gl.activeTexture(gl.TEXTURE0);
    gl.bindTexture(gl.TEXTURE_2D, texture);
    gl.uniform1i(gl.getUniformLocation(program, 'u_texture'), 0);
    
    // Ensure we're drawing to the default framebuffer (the canvas)
    gl.bindFramebuffer(gl.FRAMEBUFFER, null);
    
    // Draw
    gl.viewport(0, 0, w, h);
    gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
    
    // Clean up
    gl.deleteTexture(texture);
    gl.deleteProgram(program);
    gl.deleteShader(vertShader);
    gl.deleteShader(fragShader);
    gl.deleteBuffer(buffer);
    
    // Clean up temp canvas
    tempCanvas.width = 0;
    tempCanvas.height = 0;
    
    console.log('Simple pole filling complete');
}