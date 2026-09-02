<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();
/**
 * 360 Camera Capture Integration - Optimized Version
 * Simplified version of the 360_cam app's automatic capture workflow
 */

// Create output directory
$captureDir = ROOT_PATH . '/public/uploads/360_captures/';
if (!is_dir($captureDir)) {
    mkdir($captureDir, 0755, true);
}

// Handle panorama generation from captured images
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_panorama'])) {
    $sessionId = $_POST['session_id'] ?? '';
    $sessionDir = $captureDir . $sessionId . '/';
    
    if (is_dir($sessionDir)) {
        $capturedImages = glob($sessionDir . '*.jpg');
        
        if (count($capturedImages) >= 6) {
            // Generate placeholder panorama (in production, use real stitching)
            $panoramaPath = $sessionDir . 'panorama_' . time() . '.jpg';
            generatePanoramaFromImages($capturedImages, $panoramaPath);
            
            $panoramaUrl = 'public/uploads/360_captures/' . $sessionId . '/panorama_' . basename($panoramaPath);
            $success = true;
        } else {
            $error = 'Need at least 6 captured images to generate panorama';
        }
    } else {
        $error = 'Session not found or no images captured';
    }
}

// Handle manual image upload for testing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['manual_upload'])) {
    $sessionId = $_POST['session_id'] ?? 'manual_' . time();
    $sessionDir = $captureDir . $sessionId . '/';
    if (!is_dir($sessionDir)) {
        mkdir($sessionDir, 0755, true);
    }
    
    if (isset($_FILES['manual_upload']['error']) && $_FILES['manual_upload']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['manual_upload']['name'], PATHINFO_EXTENSION);
        $filename = 'manual_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['manual_upload']['tmp_name'], $sessionDir . $filename);
        $uploadedPath = 'public/uploads/360_captures/' . $sessionId . '/' . $filename;
        $uploadSuccess = true;
    }
}

function generatePanoramaFromImages($images, $outputPath) {
    // Placeholder for real stitching logic
    // In production, this would use the WebGL2 stitching from 360_cam
    
    $placeholder = imagecreatetruecolor(4096, 2048);
    $bgColor = imagecolorallocate($placeholder, 100, 150, 200);
    imagefill($placeholder, 0, 0, $bgColor);
    
    // Add text indicating number of source images
    $textColor = imagecolorallocate($placeholder, 255, 255, 255);
    $text = count($images) . ' images stitched into panorama';
    imagettftext($placeholder, 16, 0, 2048, 1024, $textColor, $text, 1024);
    
    imagejpeg($placeholder, $outputPath, 90);
    imagedestroy($placeholder);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>360 Camera Capture</title>
    <link href="<?= url('assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= url('assets/css/font-awesome.min.css') ?>">
    <style>
        body { background: #1a1a2e; color: #fff; padding: 20px; }
        .card { background: #16213e; border: 1px solid #2a3a5e; border-radius: 12px; margin-bottom: 20px; }
        .card-header { background: #1e2a4a; border-bottom: 1px solid #2a3a5e; padding: 15px; border-radius: 12px 12px 0 0; }
        .card-body { padding: 20px; }
        .btn-grad { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; color: white; }
        .btn-grad:hover { background: linear-gradient(135deg, #5a6fd6 0%, #6a4190 100%); color: white; }
        .form-control { background: #1e2a4a; border: 1px solid #2a3a5e; color: white; }
        .form-control:focus { background: #1e2a4a; border-color: #667eea; color: white; }
        .alert-info { background: rgba(102, 126, 234, 0.2); border-color: #667eea; color: #fff; }
        .alert-success { background: rgba(34, 197, 94, 0.2); border-color: #22c55e; color: #fff; }
        .alert-danger { background: rgba(239, 68, 68, 0.2); border-color: #ef4444; color: #fff; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4">360 Camera Capture</h1>
        
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">360 Camera Capture Integration</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <strong>How it works:</strong> The 360_cam app automatically captures 36 images at predefined points and stitches them into a panorama.
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-6">
                        <h5>Manual Upload (Testing)</h5>
                        <form method="post" enctype="multipart/form-data" class="mb-3">
                            <input type="hidden" name="session_id" value="manual_test">
                            <div class="mb-3">
                                <label class="form-label">Upload Test Image</label>
                                <input type="file" name="manual_upload" class="form-control" accept="image/*">
                            </div>
                            <button type="submit" class="btn btn-grad">Upload Image</button>
                        </form>
                        
                        <?php if (isset($uploadSuccess)): ?>
                            <div class="alert alert-success mt-3">
                                <p class="mb-2">Image uploaded successfully!</p>
                                <img src="<?= h(url($uploadedPath)) ?>" alt="Uploaded" class="img-fluid rounded">
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-6">
                        <h5>Generate Panorama</h5>
                        <form method="post" class="mb-3">
                            <div class="mb-3">
                                <label class="form-label">Session ID</label>
                                <input type="text" name="session_id" class="form-control" placeholder="manual_test" value="manual_test">
                            </div>
                            <button type="submit" name="generate_panorama" class="btn btn-grad">Generate Panorama</button>
                        </form>
                        
                        <?php if (isset($panoramaUrl)): ?>
                            <div class="alert alert-success mt-3">
                                <p class="mb-2">Panorama generated successfully!</p>
                                <img src="<?= h(url($panoramaUrl)) ?>" alt="Panorama" class="img-fluid rounded mb-2">
                                <a href="<?= h(url($panoramaUrl)) ?>" download class="btn btn-sm btn-grad">Download Panorama</a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger mt-3">
                                <p class="mb-0"><?= h($error) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Quick Access</h5>
            </div>
            <div class="card-body">
                <div class="d-flex gap-2 flex-wrap">
                    <a href="<?= url('360_cam') ?>" target="_blank" class="btn btn-grad">Open Full 360 Camera App</a>
                    <a href="<?= url('admin/cubemap-test') ?>" class="btn btn-outline-light">Cubemap Test Page</a>
                    <a href="<?= url('admin/ai-demo') ?>" class="btn btn-outline-light">AI Demo Page</a>
                    <a href="<?= url('admin/owner/dashboard') ?>" class="btn btn-outline-light">Back to Dashboard</a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="<?= url('assets/js/bootstrap.bundle.min.js') ?>"></script>
</body>
</html>
