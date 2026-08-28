<?php
$folderId = isset($_GET['folder']) ? preg_replace('/[^0-9]/', '', $_GET['folder']) : '0';
$folder = "3d/exhibit/$folderId/";

$message = "";

// 🔁 Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image']) && isset($_POST['folder'])) {
    $folderId = preg_replace('/[^0-9]/', '', $_POST['folder']);
    $folder = "3d/exhibit/$folderId/";

    // ✅ Try to create folder if it doesn't exist
    if (!is_dir($folder)) {
        if (!mkdir($folder, 0755, true)) {
            $message = "❌ Failed to create folder: $folder";
        }
    }

    if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        switch ($_FILES['image']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $message = "❌ File is too large.";
                break;
            case UPLOAD_ERR_PARTIAL:
                $message = "❌ File was only partially uploaded.";
                break;
            case UPLOAD_ERR_NO_FILE:
                $message = "❌ No file was uploaded.";
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $message = "❌ Missing temporary folder on server.";
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $message = "❌ Failed to write file to disk.";
                break;
            case UPLOAD_ERR_EXTENSION:
                $message = "❌ File upload stopped by PHP extension.";
                break;
            default:
                $message = "❌ Unknown upload error.";
                break;
        }
    } else {
        // ✅ Validate file type
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $originalName = basename($_FILES['image']['name']);
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $originalName);
            $target = $folder . $filename;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
                $message = "✅ Uploaded: " . htmlspecialchars($filename);
            } else {
                $message = "❌ Upload failed (move_uploaded_file error).";
            }
        } else {
            $message = "❌ Invalid file type. Allowed: " . implode(', ', $allowed);
        }
    }
}

// 🗑️ Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'], $_POST['folder'])) {
    $folderId = preg_replace('/[^0-9]/', '', $_POST['folder']);
    $folder = "3d/exhibit/$folderId/";
    $fileToDelete = $folder . basename($_POST['delete']);

    if (file_exists($fileToDelete)) {
        unlink($fileToDelete);
        $message = "🗑️ Deleted: " . htmlspecialchars($_POST['delete']);
    } else {
        $message = "⚠️ File not found!";
    }
}

$files = is_dir($folder) ? array_diff(scandir($folder), ['.', '..']) : [];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Image Manager</title>
    <style>
        body { font-family: sans-serif; padding: 20px; }
        .gallery { display: flex; flex-wrap: wrap; gap: 20px; }
        .card { border: 1px solid #ccc; padding: 10px; text-align: center; width: 200px; }
        img { max-width: 100%; height: auto; }
        form { margin-top: 10px; }
        button { background: #d9534f; color: white; border: none; padding: 5px 10px; cursor: pointer; }
        .form-inline { margin-bottom: 20px; }
        input[type="number"], input[type="file"] { padding: 5px; }
        .message { margin: 15px 0; color: #333; font-weight: bold; }
    </style>
</head>
<body>
    <h1>📁 Image Gallery: <code>3D/exhibit/<?php echo $folderId; ?>/</code></h1>

    <form method="GET" class="form-inline">
        <label for="folder">Enter Folder ID:</label>
        <input type="number" name="folder" id="folder" value="<?php echo $folderId; ?>">
        <button type="submit">View</button>
    </form>

    <form method="POST" enctype="multipart/form-data" class="form-inline">
        <input type="file" name="image" required>
        <input type="hidden" name="folder" value="<?php echo $folderId; ?>">
        <button type="submit">Upload</button>
    </form>

    <?php if (!empty($message)) echo "<p class='message'>$message</p>"; ?>

    <?php if (empty($files)): ?>
        <p>No images found in this folder.</p>
    <?php else: ?>
        <div class="gallery">
            <?php foreach ($files as $file): ?>
                <?php $filePath = $folder . $file; ?>
                <div class="card">
                    <img src="<?php echo $filePath; ?>" alt="<?php echo $file; ?>">
                    <p><?php echo htmlspecialchars($file); ?></p>
                    <form method="POST" onsubmit="return confirm('Delete this image?');">
                        <input type="hidden" name="delete" value="<?php echo $file; ?>">
                        <input type="hidden" name="folder" value="<?php echo $folderId; ?>">
                        <button type="submit">Delete</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</body>
</html>
