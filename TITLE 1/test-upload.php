<?php
/**
 * Test script for file upload API
 * Tests if the save-panorama.php API can receive and save files
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test File Upload</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: #1a1a2e;
            color: #eee;
        }
        .test-section {
            background: #16213e;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #0f3460;
        }
        h2 { color: #e94560; margin-top: 0; }
        .result {
            background: #0f3460;
            padding: 15px;
            border-radius: 4px;
            margin-top: 10px;
            font-family: monospace;
            white-space: pre-wrap;
            word-break: break-all;
        }
        .success { border-left: 4px solid #4ade80; }
        .error { border-left: 4px solid #ef4444; }
        button {
            background: #e94560;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover { background: #c7364d; }
        input[type="file"] {
            margin: 10px 0;
            width: 100%;
        }
        .info {
            background: rgba(74, 222, 128, 0.1);
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <h1>🧪 File Upload API Test</h1>
    
    <div class="test-section">
        <h2>Test 1: Upload Sample Image</h2>
        <p>Upload any image to test if the API can receive and save files.</p>
        
        <div class="info">
            <strong>Target API:</strong> api/save-panorama.php<br>
            <strong>Institution:</strong> Fallback to ID 1 (immaculada-concepcion-college)
        </div>
        
        <form id="uploadForm">
            <input type="file" id="testFile" accept="image/*" required>
            <br>
            <button type="submit">Test Upload</button>
        </form>
        
        <div id="result"></div>
    </div>
    
    <div class="test-section">
        <h2>Test 2: Check Directory Permissions</h2>
        <button onclick="checkPermissions()">Check Directory Permissions</button>
        <div id="permResult"></div>
    </div>
    
    <div class="test-section">
        <h2>Test 3: Check Existing Files</h2>
        <button onclick="checkExistingFiles()">Check Existing Panorama Files</button>
        <div id="filesResult"></div>
    </div>
    
    <div class="test-section">
        <h2>Test 4: Fix Permissions</h2>
        <div class="info">
            <strong>⚠️ Important:</strong> This attempts to fix directory permissions using PHP chmod(). 
            Run this if the permission check shows directories are not writable.
        </div>
        <button onclick="fixPermissions()">Fix Directory Permissions</button>
        <div id="fixResult"></div>
    </div>

    <script>
        document.getElementById('uploadForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const fileInput = document.getElementById('testFile');
            const file = fileInput.files[0];
            const resultDiv = document.getElementById('result');
            
            if (!file) {
                resultDiv.innerHTML = '<div class="result error">Please select a file first.</div>';
                return;
            }
            
            resultDiv.innerHTML = '<div class="result">Uploading...</div>';
            
            const formData = new FormData();
            formData.append('panorama', file);
            formData.append('title', 'Test Upload - ' + new Date().toLocaleString());
            formData.append('description', 'Test file upload to verify API functionality');
            formData.append('capture_data', JSON.stringify({
                imageCount: 1,
                width: 1920,
                height: 1080,
                capturedAt: new Date().toISOString(),
                test: true
            }));
            
            try {
                const response = await fetch('api/save-panorama.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });
                
                const data = await response.json();
                
                resultDiv.innerHTML = `
                    <div class="result ${data.success ? 'success' : 'error'}">
<strong>Status:</strong> ${response.status} ${response.statusText}
<strong>Success:</strong> ${data.success ? 'YES' : 'NO'}
${data.panorama_id ? '<strong>Panorama ID:</strong> ' + data.panorama_id : ''}
${data.equirect_path ? '<strong>File Path:</strong> ' + data.equirect_path : ''}
${data.thumbnail_path ? '<strong>Thumbnail:</strong> ' + data.thumbnail_path : ''}
${data.width ? '<strong>Dimensions:</strong> ' + data.width + 'x' + data.height : ''}
${data.error ? '<strong>Error:</strong> ' + data.error : ''}

<strong>Full Response:</strong>
${JSON.stringify(data, null, 2)}
                    </div>
                `;
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="result error">
<strong>Network Error:</strong> ${error.message}
<strong>Check:</strong> Network connection and API endpoint accessibility
                    </div>
                `;
            }
        });
        
        async function checkPermissions() {
            const resultDiv = document.getElementById('permResult');
            resultDiv.innerHTML = '<div class="result">Checking permissions...</div>';
            
            try {
                const response = await fetch('test-permissions.php');
                const data = await response.json();
                
                resultDiv.innerHTML = `
                    <div class="result ${data.success ? 'success' : 'error'}">
${JSON.stringify(data, null, 2)}
                    </div>
                `;
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="result error">
<strong>Error:</strong> ${error.message}
                    </div>
                `;
            }
        }
        
        async function checkExistingFiles() {
            const resultDiv = document.getElementById('filesResult');
            resultDiv.innerHTML = '<div class="result">Checking files...</div>';
            
            try {
                const response = await fetch('check-files.php');
                const data = await response.json();
                
                resultDiv.innerHTML = `
                    <div class="result ${data.success ? 'success' : 'error'}">
${JSON.stringify(data, null, 2)}
                    </div>
                `;
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="result error">
<strong>Error:</strong> ${error.message}
                    </div>
                `;
            }
        }
        
        async function fixPermissions() {
            const resultDiv = document.getElementById('fixResult');
            resultDiv.innerHTML = '<div class="result">Attempting to fix permissions...</div>';
            
            try {
                const response = await fetch('fix-permissions.php');
                const data = await response.json();
                
                resultDiv.innerHTML = `
                    <div class="result ${data.success ? 'success' : 'error'}">
${JSON.stringify(data, null, 2)}
                    </div>
                `;
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="result error">
<strong>Error:</strong> ${error.message}
                    </div>
                `;
            }
        }
    </script>
</body>
</html>