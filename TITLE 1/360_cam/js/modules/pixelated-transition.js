// Pixelated transition effect for revealing stitched panorama
export class PixelatedTransition {
    constructor(canvas, sourceImage, targetImage, duration = 1000, blockSize = 16) {
        this.canvas = canvas;
        this.ctx = canvas.getContext('2d');
        this.sourceImage = sourceImage; // Starting image (thumbnail)
        this.targetImage = targetImage; // Final image (stitched panorama)
        this.duration = duration;
        this.blockSize = blockSize;
        
        this.width = canvas.width;
        this.height = canvas.height;
        
        // Calculate grid dimensions
        this.cols = Math.ceil(this.width / this.blockSize);
        this.rows = Math.ceil(this.height / this.blockSize);
        
        // Create array of all blocks with their reveal order
        this.blocks = [];
        this.initializeBlocks();
        
        // Animation state
        this.startTime = null;
        this.animationId = null;
        this.onProgress = null;
        this.onComplete = null;
    }
    
    initializeBlocks() {
        // Create all blocks with their positions
        for (let row = 0; row < this.rows; row++) {
            for (let col = 0; col < this.cols; col++) {
                this.blocks.push({
                    x: col * this.blockSize,
                    y: row * this.blockSize,
                    col: col,
                    row: row,
                    revealed: false
                });
            }
        }
        
        // Shuffle blocks for random reveal pattern
        for (let i = this.blocks.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [this.blocks[i], this.blocks[j]] = [this.blocks[j], this.blocks[i]];
        }
        
        // Assign reveal time to each block based on random order
        this.blocks.forEach((block, index) => {
            // Normalize reveal time between 0 and 1
            block.revealTime = index / this.blocks.length;
        });
    }
    
    start() {
        return new Promise((resolve, reject) => {
            // Load both images first
            Promise.all([
                this.loadImage(this.sourceImage),
                this.loadImage(this.targetImage)
            ]).then(([source, target]) => {
                this.sourceImg = source;
                this.targetImg = target;
                
                // Start animation
                this.startTime = performance.now();
                this.animate();
                
                // Set up completion callback
                this.onComplete = resolve;
            }).catch(reject);
        });
    }
    
    loadImage(src) {
        return new Promise((resolve, reject) => {
            if (src instanceof HTMLImageElement || src instanceof HTMLCanvasElement) {
                resolve(src);
            } else {
                const img = new Image();
                img.onload = () => resolve(img);
                img.onerror = reject;
                img.src = src;
            }
        });
    }
    
    animate() {
        const currentTime = performance.now();
        const elapsed = currentTime - this.startTime;
        const progress = Math.min(elapsed / this.duration, 1);
        
        // Clear canvas
        this.ctx.clearRect(0, 0, this.width, this.height);
        
        // Draw source image as background
        this.ctx.drawImage(this.sourceImg, 0, 0, this.width, this.height);
        
        // Calculate how many blocks should be revealed
        const blocksToReveal = Math.floor(this.blocks.length * progress);
        
        // Draw revealed blocks from target image
        for (let i = 0; i < blocksToReveal; i++) {
            const block = this.blocks[i];
            if (!block.revealed) {
                block.revealed = true;
            }
            
            // Draw block from target image
            this.ctx.drawImage(
                this.targetImg,
                block.x, block.y, this.blockSize, this.blockSize,
                block.x, block.y, this.blockSize, this.blockSize
            );
        }
        
        // Add slight pixelation effect to transition boundary
        if (progress < 1 && blocksToReveal < this.blocks.length) {
            const transitionBlocks = Math.min(5, this.blocks.length - blocksToReveal);
            for (let i = blocksToReveal; i < blocksToReveal + transitionBlocks; i++) {
                if (i < this.blocks.length) {
                    const block = this.blocks[i];
                    const blockProgress = (progress * this.blocks.length - i) * 2;
                    
                    if (blockProgress > 0) {
                        // Sample average color from target for pixelated effect
                        const tempCanvas = document.createElement('canvas');
                        tempCanvas.width = this.blockSize;
                        tempCanvas.height = this.blockSize;
                        const tempCtx = tempCanvas.getContext('2d');
                        
                        tempCtx.drawImage(
                            this.targetImg,
                            block.x, block.y, this.blockSize, this.blockSize,
                            0, 0, this.blockSize, this.blockSize
                        );
                        
                        // Get average color
                        const imageData = tempCtx.getImageData(0, 0, this.blockSize, this.blockSize);
                        const avgColor = this.getAverageColor(imageData);
                        
                        // Draw pixelated block with some transparency
                        this.ctx.fillStyle = `rgba(${avgColor.r}, ${avgColor.g}, ${avgColor.b}, ${blockProgress})`;
                        this.ctx.fillRect(block.x, block.y, this.blockSize, this.blockSize);
                    }
                }
            }
        }
        
        // Report progress
        if (this.onProgress) {
            this.onProgress(progress);
        }
        
        // Continue animation or complete
        if (progress < 1) {
            this.animationId = requestAnimationFrame(() => this.animate());
        } else {
            // Draw final image completely
            this.ctx.drawImage(this.targetImg, 0, 0, this.width, this.height);
            if (this.onComplete) {
                this.onComplete();
            }
        }
    }
    
    getAverageColor(imageData) {
        const data = imageData.data;
        let r = 0, g = 0, b = 0;
        let count = 0;
        
        for (let i = 0; i < data.length; i += 4) {
            r += data[i];
            g += data[i + 1];
            b += data[i + 2];
            count++;
        }
        
        return {
            r: Math.floor(r / count),
            g: Math.floor(g / count),
            b: Math.floor(b / count)
        };
    }
    
    stop() {
        if (this.animationId) {
            cancelAnimationFrame(this.animationId);
            this.animationId = null;
        }
    }
    
    setProgressCallback(callback) {
        this.onProgress = callback;
    }
}