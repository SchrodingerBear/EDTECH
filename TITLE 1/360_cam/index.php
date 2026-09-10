<?php
// Get the correct base path for this folder
$basePath = dirname($_SERVER['PHP_SELF']);
if (substr($basePath, -1) !== '/') {
    $basePath .= '/';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0, user-scalable=no, viewport-fit=cover" name="viewport" />
    <meta content="yes" name="apple-mobile-web-app-capable" />
    <meta content="black-translucent" name="apple-mobile-web-app-status-bar-style" />
    <meta content="Innovatech 360" name="apple-mobile-web-app-title" />
    <!-- PWA Meta Tags -->
    <meta content="Innovatech PH - AI-Assisted AR 360° Virtual Campus Navigation - 360 Panorama Capture"
        name="description" />
    <meta content="#8C1515" name="theme-color" />
    <meta content="yes" name="mobile-web-app-capable" />
    <!-- Performance Meta Tags -->
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="format-detection" content="telephone=no">
    <!-- Apple Touch Icons -->
    <link href="<?php echo $basePath; ?>img/icon-180.png" rel="apple-touch-icon" />
    <link href="<?php echo $basePath; ?>img/icon-120.png" rel="apple-touch-icon" sizes="120x120" />
    <link href="<?php echo $basePath; ?>img/icon-152.png" rel="apple-touch-icon" sizes="152x152" />
    <link href="<?php echo $basePath; ?>img/icon-167.png" rel="apple-touch-icon" sizes="167x167" />
    <link href="<?php echo $basePath; ?>img/icon-180.png" rel="apple-touch-icon" sizes="180x180" />
    <!-- Favicon -->
    <link href="<?php echo $basePath; ?>img/favicon-32.png" rel="icon" sizes="32x32" type="image/png" />
    <link href="<?php echo $basePath; ?>img/favicon-16.png" rel="icon" sizes="16x16" type="image/png" />
    <!-- PWA Manifest -->
    <link href="<?php echo $basePath; ?>manifest.json" rel="manifest" />
    <title>Innovatech PH 360 Camera</title>
    <!-- CSS Loading -->
    <link href="<?php echo $basePath; ?>css/capture.css" rel="stylesheet" />
    <link href="<?php echo $basePath; ?>css/cards.css" rel="stylesheet" />
    <link href="<?php echo $basePath; ?>css/pannellum.css" rel="stylesheet" />
</head>

<body>
    <!-- Grid Background -->
    <div class="grid-background" id="grid-background"></div>

    <!-- App Loading Indicator -->
    <div id="app-loading">
        <div class="loader-card">
            <img alt="Photosphere" src="<?php echo $basePath; ?>img/logo_150x150.png" />
            <h2>Innovatech 360 Camera</h2>
            <div id="app-status">Initializing application...</div>
            <div class="loader-bar">
                <div id="app-progress"></div>
                <div id="app-progress-indeterminate"></div>
            </div>
            <div id="app-substatus">Please wait...</div>
        </div>
    </div>

    <!-- 3D Scene -->
    <div aria-hidden="true" id="scene-container"></div>

    <!-- Instructions -->
    <div aria-hidden="true" aria-live="polite" id="instructions" role="status"></div>

    <!-- Screen reader announcements -->
    <div aria-atomic="true" aria-live="polite" class="sr-only" id="sr-announcements" role="status"></div>

    <!-- Camera viewport -->
    <div aria-label="Camera viewfinder" id="camera-viewport">
        <div aria-hidden="true" id="alignment-indicator"></div>
        <div aria-hidden="true" aria-label="Device level indicator" id="roll-indicator">
            <div id="roll-line-static-left"></div>
            <div id="roll-line-moving"></div>
            <div id="roll-line-static-right"></div>
        </div>
    </div>

    <!-- Hidden elements -->
    <video autoplay="" id="camera-full" muted="" playsinline=""></video>
    <canvas id="capture-canvas"></canvas>

    <!-- Stitching progress overlay -->
    <div aria-hidden="true" aria-labelledby="stitch-title" aria-modal="true" class="ui-card" id="stitching-overlay"
        role="dialog">
        <button aria-label="Close stitching progress" class="card-close-btn" id="close-stitch-overlay-btn">
            <img alt="Close" src="<?php echo $basePath; ?>img/x.svg" />
        </button>
        <div class="card-header">
            <h2 class="card-title" id="stitch-title">
                <svg fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path
                        d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 14H4V6h16v12z" />
                    <path d="M4 18h16v-2H4v2zm0-5h16v-2H4v2zm0-5h16V6H4v2z" />
                </svg>
                Processor
            </h2>
        </div>
        <div class="card-content">
            <div class="stitching-progress">
                <div class="progress-bar">
                    <div id="stitch-progress-bar"></div>
                </div>
                <p id="stitch-status">Preparing images...</p>
                <p id="stitch-details">Initializing...</p>
            </div>
            <canvas data-thumbnail="<?php echo $basePath; ?>img/360_blank_thumbnail.jpg" height="300"
                id="preview-canvas" width="600"></canvas>
            <div class="action-stack">
                <div class="action-pair">
                    <button aria-label="View panorama" id="view-btn" class="action-btn-styled">
                        <img alt="View icon" src="<?php echo $basePath; ?>img/view.svg" /> View
                    </button>
                    <button aria-label="Share panorama" id="download-btn" class="action-btn-styled">
                        <img alt="Share icon" src="<?php echo $basePath; ?>img/share.svg" /> Share
                    </button>
                </div>
                <button aria-label="View Camera Roll" id="view-camera-roll-btn" class="action-btn-styled"
                    onclick="openGallery()">
                    <img alt="Camera roll icon" src="<?php echo $basePath; ?>img/camera-roll.svg" /> View Camera Roll
                </button>
                <button aria-label="Start new capture" class="start-btn-styled" id="start-new-capture-btn"
                    onclick="window.sphereCapture && window.sphereCapture.resetAndStartNewCapture()">
                    <img alt="Camera icon" class="camera-ico" src="<?php echo $basePath; ?>img/camera.svg" /> Start New
                    Capture
                </button>
            </div>
        </div>
    </div>

    <!-- Start screen -->
    <div aria-labelledby="home-title" class="ui-card home-card visible" id="start-screen" role="main">
        <div class="card-header">
            <img alt="Photosphere Camera Logo" class="logo logo-glow" src="<?php echo $basePath; ?>img/logo_150x150.png"
                width="60" />
            <h1 class="card-title" id="home-title">Innovatech 360 Camera</h1>
        </div>
        <div class="card-content">
            <div class="home-instructions">
                <div class="step-row">
                    <span class="step-num">1</span>
                    <span>Hold your device close to your face, and rotate your body to find the <span
                            class="hl-orange">orange</span> hotspots</span>
                </div>
                <div class="step-row">
                    <span class="step-num">2</span>
                    <span>Align the circle with each <span class="hl-orange">orange</span> hotspot marker</span>
                </div>
                <div class="step-row">
                    <span class="step-num">3</span>
                    <span>Images will be automatically captured when aligned</span>
                </div>
                <div class="step-row">
                    <span class="step-num">4</span>
                    <span>Watch the progress circle fill as you complete each hotspot</span>
                </div>
                <div class="step-row">
                    <span class="step-num">5</span>
                    <span>After capturing all 36 images, tap the checkmark button to continue</span>
                </div>
            </div>
            <div class="start-actions">
                <button aria-label="Start capturing photosphere" id="start-btn"
                    onclick="startApp().catch(console.error)">
                    <img alt="Camera icon" src="<?php echo $basePath; ?>img/camera.svg" /> Start Capturing
                </button>
            </div>
        </div>
        <button aria-label="Open settings menu" class="control-btn settings-btn-start-screen" id="settings-btn"
            onclick="openSettings()" title="Settings">
            <img alt="Settings icon" src="<?php echo $basePath; ?>img/settings.svg" />
        </button>
        <button aria-label="View camera roll" class="control-btn gallery-btn-start-screen" id="gallery-btn"
            onclick="openGallery()" title="View Camera Roll">
            <img alt="Camera roll icon" src="<?php echo $basePath; ?>img/camera-roll.svg" />
        </button>
    </div>

    <!-- Settings Menu Card -->
    <div aria-hidden="true" aria-labelledby="settings-title" aria-modal="true" class="ui-card" id="settings-menu-card"
        role="dialog">
        <button aria-label="Close settings menu" class="card-close-btn"
            onclick="window.sphereCapture && window.sphereCapture.cardUI && window.sphereCapture.cardUI.hideSettingsMenu()">
            <img alt="Close icon" src="<?php echo $basePath; ?>img/x.svg" />
        </button>
        <div class="card-header">
            <h1 class="card-title" id="settings-title">
                <img alt="" src="<?php echo $basePath; ?>img/settings.svg" /> Settings
            </h1>
        </div>
        <div class="card-content">
            <div class="settings-menu-list">
                <button aria-label="Field of view fine-tuning" class="settings-menu-item"
                    onclick="window.sphereCapture && window.sphereCapture.cardUI && window.sphereCapture.cardUI.openInfoCard('fov')">
                    <div class="menu-row">
                        <img alt="" src="<?php echo $basePath; ?>img/fov-calibration.svg" /> <span>FOV
                            Fine-Tuning</span>
                    </div>
                    <span>›</span>
                </button>
                <button aria-label="Clear camera roll" class="settings-menu-item"
                    onclick="window.sphereCapture && window.sphereCapture.cardUI && window.sphereCapture.cardUI.openInfoCard('clear')">
                    <div class="menu-row">
                        <img alt="" class="danger-icon" src="<?php echo $basePath; ?>img/clear-camera-roll.svg" /> <span
                            class="danger-text">Clear Camera Roll</span>
                    </div>
                    <span>›</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Info Card -->
    <div aria-hidden="true" class="ui-card" id="info-card">
        <button aria-label="Close info card" class="card-close-btn"
            onclick="window.sphereCapture && window.sphereCapture.cardUI && window.sphereCapture.cardUI.closeInfoCard()">
            <img alt="Close icon" src="<?php echo $basePath; ?>img/x.svg" />
        </button>
        <div class="card-header">
            <h1 class="card-title" id="info-card-title">Info &amp; Settings</h1>
        </div>
        <div class="card-content" id="info-card-content"></div>
    </div>


    <!-- Alert/Confirm Dialog Card -->
    <div aria-hidden="true" aria-labelledby="dialog-title" aria-modal="true" class="ui-card dialog-card"
        id="dialog-card" role="dialog" style="max-height: 80vh; overflow-y: auto;">
        <div style="padding: 20px;">
            <h3 id="dialog-title" style="margin-bottom: 15px;">Alert</h3>
            <div id="dialog-message" style="margin-bottom: 20px; overflow-y: auto; max-height: 60vh;"></div>
            <div id="dialog-buttons"></div>
        </div>
    </div>

    <!-- Orientation Warning Card -->
    <div aria-hidden="true" aria-labelledby="orientation-title" aria-live="assertive" class="ui-card dialog-card"
        id="orientation-warning-card" role="alert">
        <div>
            <img alt="Rotate Device" src="<?php echo $basePath; ?>img/mobile-rotate.svg" />
            <h3 id="orientation-title">Rotate Your Device</h3>
            <p>Please rotate your device to vertical (portrait) orientation to continue capturing.</p>
        </div>
    </div>

    <!-- Camera Roll Card -->
    <div aria-hidden="true" aria-labelledby="camera-roll-title" aria-modal="true" class="ui-card camera-roll-card"
        id="camera-roll-card" role="dialog">
        <button aria-label="Close camera roll" class="card-close-btn"
            onclick="window.sphereCapture && window.sphereCapture.cameraRoll && window.sphereCapture.cameraRoll.hide()">
            <img alt="Close icon" src="<?php echo $basePath; ?>img/x.svg" />
        </button>
        <div class="card-header">
            <h1 class="card-title" id="camera-roll-title">
                <svg fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path
                        d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z" />
                </svg>
                360 Captures
            </h1>
            <p id="camera-roll-count">Loading...</p>
        </div>
        <div class="card-content" id="camera-roll-list"></div>
        <button aria-label="Open settings menu" class="control-btn settings-btn-start-screen" id="settings-btn"
            onclick="openSettings()" title="Settings">
            <img alt="Settings icon" src="<?php echo $basePath; ?>img/settings.svg" />
        </button>
    </div>

    <!-- Bottom Capture controls -->
    <div class="bottom-controls">
        <button aria-label="Reset capture session" class="control-btn" id="clear-btn" title="Reset">
            <svg height="32" viewBox="0 0 24 24" width="32" xmlns="http://www.w3.org/2000/svg">
                <path
                    d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"
                    fill="white" />
            </svg>
        </button>
        <div id="capture-btn-container">
            <svg id="captureProgress" viewBox="0 0 36 36">
                <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none"
                    id="progressPath" stroke="#4CAF50" stroke-dasharray="100, 100" stroke-dashoffset="100"
                    stroke-width="3" />
            </svg>
            <svg id="captureCheckmark" viewBox="0 0 24 24">
                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z" />
            </svg>
        </div>
        <button aria-label="View camera roll" class="control-btn" id="gallery-btn" onclick="openGallery()"
            title="View Camera Roll">
            <img alt="View Camera Roll" src="<?php echo $basePath; ?>img/camera-roll.svg" />
        </button>
    </div>

    <!-- JavaScript Loading -->
    <script src="<?php echo $basePath; ?>js/ext/three.min.js"></script>
    <script src="<?php echo $basePath; ?>js/ext/pannellum.js"></script>
    <script src="<?php echo $basePath; ?>js/ext/piexif.min.js"></script>
    <script>
        function initializePreviewCanvas() {
            const canvas = document.getElementById('preview-canvas');
            const ctx = canvas.getContext('2d');
            const thumbnailPath = canvas.getAttribute('data-thumbnail');

            if (thumbnailPath) {
                const img = new Image();
                img.onload = function () {
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                };
                img.src = thumbnailPath;
            }
        }

        document.addEventListener('DOMContentLoaded', initializePreviewCanvas);
        
        // Check for flash messages in URL parameters (from redirect)
        function checkFlashMessages() {
            const urlParams = new URLSearchParams(window.location.search);
            const flashType = urlParams.get('flash_type');
            const flashMessage = urlParams.get('flash_message');
            
            if (flashType && flashMessage) {
                // Show alert after a short delay to ensure page is loaded
                setTimeout(() => {
                    alert(flashMessage);
                    // Clear URL parameters
                    window.history.replaceState({}, document.title, window.location.pathname);
                }, 500);
            }
        }
        
        document.addEventListener('DOMContentLoaded', checkFlashMessages);
    </script>
    <script src="<?php echo $basePath; ?>js/capture.js?v=3" type="module"></script>
</body>

</html>