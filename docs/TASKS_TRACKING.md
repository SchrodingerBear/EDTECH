# 📋 INNOVATECH PH - TASK TRACKING
**Generated:** 2026-09-16
**Project:** AI-Assisted AR 360° Virtual Campus Navigation

---

## 🚨 CRITICAL ISSUE - Floor Plans 403 Forbidden

**Problem:** `http://localhost/G7%204D%20THESIS/TITLE%201/admin/institution/floor-plans/` returns 403 Forbidden

**Root Cause Found:**
- File: `admin/institution/floor-plans/.htaccess`
- Content: `Require all denied`
- This blocks ALL access to the directory including partial files

**Directory Structure:**
```
admin/institution/floor-plans/
├── .htaccess (BLOCKING ACCESS - CONTAINS "Require all denied")
├── actions.php
├── data-loader.php
├── list-view.php
├── studio-view.php
└── studio.js
```

**Required Fix:**
1. Delete or modify `admin/institution/floor-plans/.htaccess`
2. Change from `Require all denied` to allow access to partial files
3. OR remove the .htaccess entirely since partials should be included by PHP, not accessed directly

**Note:** The main file `admin/institution/floor-plans.php` exists separately and includes these partials.

---

## TASK 1: Fix Scene Navigation - /undefined 404 Error

**Problem:** When clicking hotspots or scene carousel, it tries to navigate to `/undefined`

**Files to Edit:**
- `organizations/index.php` (lines 558-566, 510-517)

**Root Cause:**
- Hotspot click handler expects: `hs.to_scene_title`, `hs.to_scene_equirect`, `hs.to_scene_yaw`, `hs.to_scene_pitch`
- Database only stores: `to_scene_id` (int)
- Code tries to use undefined fields instead of looking up the scene from `config.scenes[to_scene_id]`

**Required Fix:**
```javascript
// In organizations/index.php, line 558-566
entity.querySelector('a-sphere').addEventListener('click', () => {
  if (hs.hotspot_type === 'navigation' && hs.to_scene_id) {
    const targetScene = config.scenes[hs.to_scene_id];
    if (targetScene) {
      openScene({
        id: targetScene.id,
        title: targetScene.title,
        equirect_path: targetScene.equirect_path,
        initial_yaw: targetScene.initial_yaw,
        initial_pitch: targetScene.initial_pitch
      });
    }
  } else {
    // Handle other types
  }
});
```

**Also fix line 510-517** (floor plan marker click) with same pattern.

---

## TASK 2: Implement Dynamic Hotspot Type Behavior

**Problem:** Hotspots need different behaviors based on type:
- **Info type**: Show description popup
- **Navigation type**: Navigate to target scene
- **Media type**: Show media picker + centered image popup on click

**Files to Edit:**
- `organizations/index.php` (lines 558-574)
- `admin/institution/tour-studio.php` (hotspot editing UI)
- Database: `scene_hotspots` table needs `media_path` column

**Database Schema Update:**
```sql
ALTER TABLE scene_hotspots ADD COLUMN media_path VARCHAR(255) DEFAULT NULL AFTER icon_path;
```

**JavaScript Implementation:**
```javascript
entity.querySelector('a-sphere').addEventListener('click', () => {
  if (hs.hotspot_type === 'navigation' && hs.to_scene_id) {
    const targetScene = config.scenes[hs.to_scene_id];
    if (targetScene) {
      openScene(targetScene);
    }
  } else if (hs.hotspot_type === 'info') {
    openPopup({
      label: hs.label,
      popup_title: hs.label,
      popup_html: hs.body_html
    });
  } else if (hs.hotspot_type === 'media' && hs.media_path) {
    // Show centered image popup
    showMediaPopup(hs.media_path);
  } else {
    // Default fallback
    openPopup({
      label: hs.label,
      popup_title: hs.label,
      popup_html: hs.body_html || '<p>No additional info.</p>'
    });
  }
});
```

**Add Media Popup Function:**
```javascript
function showMediaPopup(mediaPath) {
  const overlay = document.createElement('div');
  overlay.className = 'media-popup-overlay';
  overlay.innerHTML = `
    <div class="media-popup-content">
      <img src="${mediaPath}" alt="Media">
    </div>
  `;
  overlay.addEventListener('click', () => overlay.remove());
  document.body.appendChild(overlay);
}
```

**Add CSS to organizations/immaculada-concepcion-college/assets/style.css:**
```css
.media-popup-overlay {
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(0,0,0,0.8);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}
.media-popup-content img {
  max-width: 90vw;
  max-height: 90vh;
  object-fit: contain;
}
```

---

## TASK 3: Fix Modal Overflow with Media Picker

**Problem:** Media picker preview bar overflows modal container when long file paths are displayed.

**Files to Edit:**
- `admin/assets/css/dashboard.css` (lines 1960-1973)
- User already added partial fix, needs refinement

**Current CSS (dashboard.css lines 1960-1973):**
```css
.ia-media-picker-wrapper .mp-bar {
  background: var(--ia-surface-2);
  border-color: var(--ia-border) !important;
}

/* Ensure picker bar never overflows a modal */
.modal .ia-media-picker-wrapper .mp-bar {
  max-width: 100%;
  overflow: hidden;
}

.modal .ia-media-picker-wrapper .mp-bar > .d-flex {
  min-width: 0;
  overflow: hidden;
}
```

**Additional CSS Needed:**
```css
.modal .ia-media-picker-wrapper .mp-preview {
  flex-shrink: 0;
  width: 52px;
  height: 52px;
}

.modal .ia-media-picker-wrapper .fw-semibold {
  max-width: 200px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.modal .ia-media-picker-wrapper .text-muted {
  max-width: 250px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.modal .ia-media-picker-wrapper .d-flex {
  flex-wrap: nowrap;
  gap: 0.5rem;
}
```

---

## TASK 4: Add Image Format Validation

**Problem:** Small or poorly formatted images can be uploaded without validation.

**Files to Edit:**
- `admin/layout/media-picker-sweetalert.php` (handleDirectFileUpload function)
- Server-side validation in upload handlers

**Client-Side Validation:**
```javascript
window.handleDirectFileUpload = function (input, pickerId) {
  if (input.files && input.files[0]) {
    const file = input.files[0];
    
    // Validate image dimensions
    const img = new Image();
    img.onload = function() {
      if (img.width < 800 || img.height < 450) {
        Swal.fire({
          icon: 'error',
          title: 'Image too small',
          text: 'Minimum dimensions: 800×450px'
        });
        input.value = '';
        return;
      }
      
      // Calculate aspect ratio
      const ratio = img.width / img.height;
      if (ratio < 0.75 || ratio > 2.0) {
        Swal.fire({
          icon: 'warning',
          title: 'Unusual aspect ratio',
          text: 'Recommended: 4:3 or 16:9'
        });
      }
      
      // Continue with upload...
      document.getElementById(pickerId + '_url').value = '';
      document.getElementById(pickerId + '_label').textContent = file.name;
      document.getElementById(pickerId + '_sub').textContent = `${img.width}×${img.height}px`;
      
      const reader = new FileReader();
      reader.onload = function (e) {
        document.getElementById(pickerId + '_preview').innerHTML = '<img src="' + e.target.result + '">';
      };
      reader.readAsDataURL(file);
      Swal.close();
    };
    img.src = URL.createObjectURL(file);
  }
};
```

---

## TASK 5: Replace All SVGs with Font Awesome Icons

**Problem:** SVGs don't load properly (403 errors) and should be replaced with FA icons.

**Files with SVGs to Replace:**
- `admin/layout/media-picker-sweetalert.php` (upload icon, folder icon)
- `admin/layout/media-picker.php` (upload icon, folder icon)
- `admin/institution/ai.php` (AI generate button icon)
- `admin/institution/locations.php` (AI generate button icon)
- `admin/institution/tours.php` (AI generate button icon)
- `admin/institution/buildings.php` (AI generate button icon)
- All inline SVG icons in buttons

**SVG Locations Found:**
- `assets/icons/` - 26 SVG files (crosshair.svg, image-placeholder.svg, etc.)
- Inline SVGs in PHP files (data-ai-gen buttons, media picker buttons)

**Replacement Pattern:**
```php
// Before (SVG):
<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="..."/></svg>

// After (Font Awesome):
<i class="fas fa-wand-magic-sparkles"></i>
```

**Common SVG → FA Mappings:**
- Wand/magic → `fa-wand-magic-sparkles`
- Upload cloud → `fa-cloud-upload-alt`
- Image → `fa-image`
- Folder → `fa-folder`
- Edit → `fa-edit`
- Trash → `fa-trash`
- Plus → `fa-plus`
- Save → `fa-save`
- Settings → `fa-cog`
- Crosshair → (remove, use CSS cursor)
- Image placeholder → (remove, use CSS background)

**Files to Update:**
1. `admin/layout/header.php` - Add Font Awesome CDN if not present
2. `admin/layout/media-picker-sweetalert.php` - Replace inline SVGs
3. `admin/layout/media-picker.php` - Replace inline SVGs
4. `admin/institution/*.php` - Replace AI button SVGs
5. Delete `assets/icons/` directory (after replacements)

**Font Awesome CDN to add to header:**
```html
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
```

---

## TASK 6: Fix SVG MIME Type (if keeping some SVGs)

**Files to Edit:**
- `.htaccess` (root)

**Add to .htaccess:**
```apache
AddType image/svg+xml .svg
AddType image/svg+xml .svgz
```

---

## TASK 7: Fix Database Absolute URL

**Files to Edit:**
- Database: `institutions` table
- Run SQL update

**SQL Fix:**
```sql
UPDATE institutions 
SET cover_image_path = REPLACE(cover_image_path, 'http://localhost/G7 4D THESIS/TITLE 1/', '')
WHERE cover_image_path LIKE 'http://localhost%';
```

---

## TASK 8: Fix Image Auto-loading in Modals (Already Partially Done)

**Status:** User already added fixes to:
- `admin/institution/buildings.php` - Added `setMediaPicker()` function with URL conversion
- `admin/institution/tours.php` - Added `setMediaPicker()` function with URL conversion
- `admin/institution/locations.php` - Added `setMediaPicker()` function with URL conversion

**CSS Fix Added:**
- `admin/assets/css/dashboard.css` - Added modal overflow CSS

**Remaining:** Verify all modals using media picker have the same pattern.

---

## TASK 9: Fix Floor Plans 403 Forbidden (CRITICAL)

**Root Cause:** `admin/institution/floor-plans/.htaccess` contains `Require all denied`

**Files to Edit:**
- `admin/institution/floor-plans/.htaccess` - DELETE this file OR change content

**Options:**
1. **DELETE the .htaccess file entirely** - Recommended since partials should only be included by PHP
2. **Change to allow directory listing prevention only:**
   ```apache
   Options -Indexes
   ```

**Note:** The main file `admin/institution/floor-plans.php` exists separately and includes these partials. The directory should not be accessed directly by users.

---

## SUMMARY OF FILES TO EDIT:

1. **admin/institution/floor-plans/.htaccess** - DELETE or modify (CRITICAL)
2. **organizations/index.php** - Fix scene navigation, add media popup
3. **admin/assets/css/dashboard.css** - Fix modal overflow CSS
4. **admin/layout/media-picker-sweetalert.php** - Replace SVGs, add validation
5. **admin/layout/media-picker.php** - Replace SVGs
6. **admin/institution/tour-studio.php** - Add media picker to hotspot edit
7. **admin/institution/locations.php** - Replace SVG
8. **admin/institution/tours.php** - Replace SVG
9. **admin/institution/buildings.php** - Replace SVG
10. **admin/institution/ai.php** - Replace SVG
11. **admin/layout/header.php** - Add Font Awesome CDN
12. **.htaccess** (root) - Add SVG MIME type
13. **Database** - Add `media_path` column, fix absolute URLs
14. **Delete assets/icons/** - After SVG replacements
15. **organizations/immaculada-concepcion-college/assets/style.css** - Add media popup CSS

---

## PRIORITY ORDER:

1. **CRITICAL:** Fix floor-plans 403 (delete .htaccess)
2. **HIGH:** Fix scene navigation /undefined error
3. **HIGH:** Implement dynamic hotspot types
4. **MEDIUM:** Fix modal overflow
5. **MEDIUM:** Add image validation
6. **MEDIUM:** Replace SVGs with Font Awesome
7. **LOW:** Fix database absolute URLs
8. **LOW:** Add SVG MIME type (if keeping SVGs)

---

## TESTING CHECKLIST:

- [x] Floor plans page loads without 403
- [x] Scene carousel navigates correctly (no /undefined)
- [x] Hotspot navigation works between scenes
- [x] Info hotspots show description popup
- [x] Media hotspots show centered image popup
- [x] Modal media picker doesn't overflow
- [x] Image validation rejects small images
- [ ] All SVGs replaced with Font Awesome icons
- [ ] Database has no absolute localhost URLs
- [ ] SVG files load correctly (if any remain)

---

**END OF TASK TRACKING**
