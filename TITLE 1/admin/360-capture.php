<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();
/**
 * 360 Camera Capture Integration
 * Simplified version of the 360_cam app's automatic capture workflow
 */
$pageTitle = '360 Camera Capture';
$pageSub = 'Automatic panoramic capture and stitching';
$active = '360 Capture';
$bodyClass = 'page-360-capture';
require_once __DIR__ . '/layout/header.php';

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
<div class="ia-card">
  <div class="card-head">
    <h3>360 Camera Capture Integration</h3>
    <p class="mb-0 text-muted small">Automatic panoramic capture using device camera (inspired by 360_cam)</p>
  </div>
  <div class="card-body">
    <div class="alert alert-info">
      <strong>How it works:</strong> The 360_cam app automatically captures 36 images at predefined points and stitches them into a panorama. This page will integrate that workflow.
    </div>
    
    <div class="row g-4 mt-3">
      <div class="col-md-6">
        <h5>Manual Upload (Testing)</h5>
        <form method="post" enctype="multipart/form-data" class="d-grid gap-3">
          <input type="hidden" name="session_id" value="manual_test">
          <div>
            <label class="form-label">Upload Test Image</label>
            <input type="file" name="manual_upload" class="form-control" accept="image/*">
          </div>
          <button type="submit" class="btn btn-grad">Upload Image</button>
        </form>
        
        <?php if (isset($uploadSuccess)): ?>
          <div class="mt-3 p-3 border rounded-3 bg-success bg-opacity-10">
            <p class="mb-2">Image uploaded successfully!</p>
            <img src="<?= h(url($uploadedPath)) ?>" alt="Uploaded" class="img-fluid rounded">
          </div>
        <?php endif; ?>
      </div>
      
      <div class="col-md-6">
        <h5>Generate Panorama</h5>
        <form method="post" class="d-grid gap-3">
          <div>
            <label class="form-label">Session ID</label>
            <input type="text" name="session_id" class="form-control" placeholder="manual_test" value="manual_test">
          </div>
          <button type="submit" name="generate_panorama" class="btn btn-grad">Generate Panorama</button>
        </form>
        
        <?php if (isset($panoramaUrl)): ?>
          <div class="mt-3 p-3 border rounded-3 bg-success bg-opacity-10">
            <p class="mb-2">Panorama generated successfully!</p>
            <img src="<?= h(url($panoramaUrl)) ?>" alt="Panorama" class="img-fluid rounded mb-2">
            <a href="<?= h(url($panoramaUrl)) ?>" download class="btn btn-sm btn-outline-ia">Download Panorama</a>
          </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
          <div class="mt-3 p-3 border rounded-3 bg-danger bg-opacity-10">
            <p class="mb-0 text-danger"><?= h($error) ?></p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="ia-card mt-3">
  <div class="card-head">
    <h3>360 Camera App Integration Notes</h3>
  </div>
  <div class="card-body">
    <p class="mb-2"><strong>Current 360_cam Workflow:</strong></p>
    <ol class="mb-2">
      <li>Shows 36 orange dots in a sphere around user</li>
      <li>Dots turn green when aligned (±4.6°)</li>
      <li>Auto-captures when aligned and level for 1 second</li>
      <li>After 36 captures, stitches into 4096×2048 equirectangular panorama</li>
      <li>Uses WebGL2 for GPU-accelerated stitching</li>
    </ol>
    
    <p class="mb-2"><strong>Key Files to Integrate:</strong></p>
    <ul class="mb-2">
      <li><code>360_cam/js/modules/app.js</code> - Main orchestrator (36 hotspots, auto-capture)</li>
      <li><code>360_cam/js/modules/camera.js</code> - Camera operations and photo capture</li>
      <li><code>360_cam/js/modules/stitching.js</code> - WebGL2 equirectangular stitching</li>
      <li><code>360_cam/js/modules/share-utils.js</code> - Export functionality</li>
    </ul>
    
    <p class="mb-2"><strong>Integration Strategy:</strong></p>
    <ol class="mb-0">
      <li>Extract the 36-hotspot capture logic from app.js</li>
      <li>Integrate camera.js for device camera access</li>
<li>Port the WebGL2 stitching from stitching.js</li>
      <li>Add export functionality from share-utils.js</li>
      <li>Create admin UI to trigger capture workflow</li>
    </ol>
  </div>
</div>

<div class="ia-card mt-3">
  <div class="card-head">
    <h3>Quick Access</h3>
  </div>
  <div class="card-body">
    <div class="d-flex gap-2 flex-wrap">
      <a href="<?= url('360_cam') ?>" target="_blank" class="btn btn-grad">Open Full 360 Camera App</a>
      <a href="<?= url('admin/cubemap-test') ?>" class="btn btn-outline-ia">Cubemap Test Page</a>
      <a href="<?= url('admin/ai-demo') ?>" class="btn btn-outline-ia">AI Demo Page</a>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
