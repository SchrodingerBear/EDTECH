<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();
/**
 * Cubemap Creation Test Page
 * Simple demonstration of cubemap capture and export functionality
 */
$pageTitle = 'Cubemap Creation Test';
$pageSub = 'Test panoramic cubemap capture and export';
$active = 'Cubemap Test';
$bodyClass = 'page-cubemap-test';
require_once __DIR__ . '/layout/header.php';

// Handle cubemap upload and processing
$cubemapFaces = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['cubemap_faces'])) {
    $uploadDir = ROOT_PATH . '/public/uploads/cubemap_test/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $faces = ['front', 'back', 'left', 'right', 'top', 'bottom'];
    foreach ($faces as $face) {
        if (isset($_FILES[$face]) && $_FILES[$face]['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES[$face]['name'], PATHINFO_EXTENSION);
            $filename = $face . '_' . time() . '.' . $ext;
            move_uploaded_file($_FILES[$face]['tmp_name'], $uploadDir . $filename);
            $cubemapFaces[$face] = 'public/uploads/cubemap_test/' . $filename;
        }
    }
    
    // Generate equirectangular panorama from cubemap faces
    if (count($cubemapFaces) === 6) {
        $panoramaPath = generateEquirectangularFromCubemap($cubemapFaces, $uploadDir);
    }
}

/**
 * Simple cubemap to equirectangular conversion (placeholder)
 * In production, this would use proper image processing libraries
 */
function generateEquirectangularFromCubemap($faces, $outputDir) {
    // This is a placeholder - in production you'd use OpenCV or a WebGL solution
    // For now, we'll just indicate where the processing would happen
    
    $equirectPath = $outputDir . 'panorama_' . time() . '.jpg';
    
    // Create a simple placeholder image
    $placeholder = imagecreatetruecolor(4096, 2048);
    $bgColor = imagecolorallocate($placeholder, 200, 200, 200);
    imagefill($placeholder, 0, 0, $bgColor);
    
    // Add text indicating this is a placeholder
    $textColor = imagecolorallocate($placeholder, 50, 50, 50);
    imagettftext($placeholder, 20, 0, 2048, 1024, $textColor, 'Equirectangular Placeholder', 1024);
    
    imagejpeg($placeholder, $equirectPath, 90);
    imagedestroy($placeholder);
    
    return 'public/uploads/cubemap_test/' . basename($equirectPath);
}
?>
<div class="ia-card">
  <div class="card-head">
    <h3>Cubemap Creation Test</h3>
    <p class="mb-0 text-muted small">Upload 6 cubemap faces to create exportable panorama</p>
  </div>
  <div class="card-body">
    <div class="row g-4">
      <div class="col-md-6">
        <form method="post" enctype="multipart/form-data" class="d-grid gap-3">
          <div class="alert alert-info">
            <strong>Instructions:</strong> Upload 6 images representing the cubemap faces (front, back, left, right, top, bottom) to generate an equirectangular panorama.
          </div>
          
          <div class="row g-3">
            <div class="col-6">
              <label class="form-label">Front Face</label>
              <input type="file" name="front" class="form-control" accept="image/*" required>
            </div>
            <div class="col-6">
              <label class="form-label">Back Face</label>
              <input type="file" name="back" class="form-control" accept="image/*" required>
            </div>
          </div>
          
          <div class="row g-3">
            <div class="col-6">
              <label class="form-label">Left Face</label>
              <input type="file" name="left" class="form-control" accept="image/*" required>
            </div>
            <div class="col-6">
              <label class="form-label">Right Face</label>
              <input type="file" name="right" class="form-control" accept="image/*" required>
            </div>
          </div>
          
          <div class="row g-3">
            <div class="col-6">
              <label class="form-label">Top Face</label>
              <input type="file" name="top" class="form-control" accept="image/*" required>
            </div>
            <div class="col-6">
              <label class="form-label">Bottom Face</label>
              <input type="file" name="bottom" class="form-control" accept="image/*" required>
            </div>
          </div>
          
          <button type="submit" class="btn btn-grad">Generate Panorama</button>
        </form>
      </div>
      
      <div class="col-md-6">
        <div class="border rounded-3 p-3 bg-light">
          <h5 class="mb-3">Uploaded Faces</h5>
          <?php if (!empty($cubemapFaces)): ?>
            <div class="row g-2">
              <?php foreach ($cubemapFaces as $face => $path): ?>
                <div class="col-4">
                  <div class="text-center">
                    <div class="mb-1">
                      <img src="<?= h(url($path)) ?>" alt="<?= h($face) ?>" class="img-fluid rounded">
                    </div>
                    <small class="text-muted"><?= h($face) ?></small>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="text-muted mb-0">No faces uploaded yet</p>
          <?php endif; ?>
        </div>
        
        <?php if (isset($panoramaPath)): ?>
          <div class="mt-3 p-3 border rounded-3 bg-success bg-opacity-10">
            <h5 class="mb-2">Generated Panorama</h5>
            <img src="<?= h(url($panoramaPath)) ?>" alt="Panorama" class="img-fluid rounded mb-2">
            <a href="<?= h(url($panoramaPath)) ?>" download class="btn btn-sm btn-outline-ia">Download Panorama</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="ia-card mt-3">
  <div class="card-head">
    <h3>360 Camera Integration Notes</h3>
  </div>
  <div class="card-body">
    <p class="mb-2"><strong>Current Implementation:</strong> The 360_cam folder has full cubemap capture functionality using device camera.</p>
    <p class="mb-2"><strong>Key Files:</strong></p>
    <ul class="mb-2">
      <li><code>360_cam/js/capture.js</code> - Main capture entry point</li>
      <li><code>360_cam/js/modules/camera.js</code> - Camera operations and photo capture</li>
      <li><code>360_cam/js/modules/stitching.js</code> - Equirectangular panorama stitching</li>
      <li><code>360_cam/js/modules/share-utils.js</code> - Export and sharing functionality</li>
    </ul>
    <p class="mb-2"><strong>Integration Path:</strong></p>
    <ol class="mb-0">
      <li>Integrate camera capture from 360_cam into admin dashboard</li>
      <li>Add cubemap face upload interface (like this test page)</li>
      <li>Implement server-side stitching using the same logic</li>
      <li>Add export functionality for generated panoramas</li>
    </ol>
  </div>
</div>

<div class="ia-card mt-3">
  <div class="card-head">
    <h3>Quick Access</h3>
  </div>
  <div class="card-body">
    <div class="d-flex gap-2 flex-wrap">
      <a href="<?= url('360_cam') ?>" target="_blank" class="btn btn-outline-ia">Open 360 Camera App</a>
      <a href="<?= url('admin/staff/ai-stitch') ?>" class="btn btn-outline-ia">AI Stitch Page</a>
      <a href="<?= url('admin/ai-demo') ?>" class="btn btn-outline-ia">AI Demo Page</a>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
