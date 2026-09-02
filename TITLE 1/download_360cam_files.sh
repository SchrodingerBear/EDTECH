#!/bin/bash

# Download all missing 360_cam files for offline use
# This script downloads all external dependencies for the 360 camera app

echo "=== Downloading 360 Camera App Dependencies ==="

# Navigate to 360_cam directory
cd 360_cam

# Create necessary directories
mkdir -p js/modules
mkdir -p js/workers
mkdir -p js/ext
mkdir -p img

echo "Downloading JavaScript modules..."

# Download all JS modules from Stanford.edu
curl -L -o js/modules/app.js https://360cam.stanford.edu/js/modules/app.js
curl -L -o js/modules/database.js https://360cam.stanford.edu/js/modules/database.js
curl -L -o js/modules/camera.js https://360cam.stanford.edu/js/modules/camera.js
curl -L -o js/modules/scene.js https://360cam.stanford.edu/js/modules/scene.js
curl -L -o js/modules/hotspots.js https://360cam.stanford.edu/js/modules/hotspots.js
curl -L -o js/modules/stitching.js https://360cam.stanford.edu/js/modules/stitching.js
curl -L -o js/modules/memory-utils.js https://360cam.stanford.edu/js/modules/memory-utils.js
curl -L -o js/modules/debug.js https://360cam.stanford.edu/js/modules/debug.js
curl -L -o js/modules/metadata-utils.js https://360cam.stanford.edu/js/modules/metadata-utils.js
curl -L -o js/modules/camera-roll.js https://360cam.stanford.edu/js/modules/camera-roll.js
curl -L -o js/modules/card-ui.js https://360cam.stanford.edu/js/modules/card-ui.js
curl -L -o js/modules/pixelated-transition.js https://360cam.stanford.edu/js/modules/pixelated-transition.js
curl -L -o js/modules/share-utils.js https://360cam.stanford.edu/js/modules/share-utils.js
curl -L -o js/modules/device-detector.js https://360cam.stanford.edu/js/modules/device-detector.js
curl -L -o js/modules/pole-fill-simple.js https://360cam.stanford.edu/js/modules/pole-fill-simple.js
curl -L -o js/modules/permissions.js https://360cam.stanford.edu/js/modules/permissions.js
curl -L -o js/modules/stitch-processor.js https://360cam.stanford.edu/js/modules/stitch-processor.js
curl -L -o js/modules/opfs-storage.js https://360cam.stanford.edu/js/modules/opfs-storage.js
curl -L -o js/modules/opencv-utils.js https://360cam.stanford.edu/js/modules/opencv-utils.js
curl -L -o js/modules/multi-stitcher.js https://360cam.stanford.edu/js/modules/multi-stitcher.js
curl -L -o js/modules/vr-viewer.js https://360cam.stanford.edu/js/modules/vr-viewer.js
curl -L -o js/modules/image-manager.js https://360cam.stanford.edu/js/modules/image-manager.js
curl -L -o js/modules/settings.js https://360cam.stanford.edu/js/modules/settings.js
curl -L -o js/modules/worker-client.js https://360cam.stanford.edu/js/modules/worker-client.js
curl -L -o js/modules/stitch-recovery.js https://360cam.stanford.edu/js/modules/stitch-recovery.js

echo "Downloading worker files..."
curl -L -o js/workers/opfs-worker.js https://360cam.stanford.edu/js/workers/opfs-worker.js

echo "Downloading external libraries..."
curl -L -o js/ext/three.min.js https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js
curl -L -o js/ext/piexif.min.js https://cdn.jsdelivr.net/npm/piexifjs@1.0.6/piexif.min.js
curl -L -o js/ext/pannellum.js https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.js

echo "Downloading images..."
curl -L -o img/360_blank_thumbnail.jpg https://360cam.stanford.edu/img/360_blank_thumbnail.jpg

echo "=== Download Complete ==="
echo "All 360 Camera app dependencies have been downloaded locally"
