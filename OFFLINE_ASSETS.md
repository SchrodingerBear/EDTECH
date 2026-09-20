# Offline Assets Configuration

This document tracks the external CDN assets that have been downloaded for offline usage.

## Asset Structure

```
assets/
├── fonts/
│   ├── Inter-Regular.ttf
│   ├── Inter-Medium.ttf
│   ├── Inter-SemiBold.ttf
│   ├── Inter-Bold.ttf
│   └── Inter-ExtraBold.ttf
├── css/
│   ├── fonts.css (Local font declarations)
│   ├── bootstrap.min.css (Bootstrap 5.3.3)
│   └── simple-datatables.min.css (Simple DataTables 7.1.2)
└── js/
    ├── bootstrap.bundle.min.js (Bootstrap 5.3.3)
    └── simple-datatables.min.js (Simple DataTables 7.1.2)
```

## Files Updated

All external CDN references have been replaced with local asset paths:

- `admin/layout/header.php` - Fonts, Bootstrap CSS, Simple DataTables CSS
- `admin/layout/footer.php` - Bootstrap JS, Simple DataTables JS
- `receipt.php` - Fonts, Bootstrap CSS/JS
- `admin/index.php` - Fonts, Bootstrap CSS/JS
- `admin/errors/403.php` - Fonts, Bootstrap CSS/JS

## Benefits

✅ **Complete Offline Functionality** - System works without internet connection
✅ **Faster Loading** - No external network requests
✅ **Reliability** - No dependency on external CDN availability
✅ **Security** - All assets are served from trusted local sources

## Future Maintenance

When updating libraries:
1. Download new versions to the appropriate asset directories
2. Update file names in the local CSS/JS files
3. No need to modify PHP files if file names remain the same

## Asset Sources

- **Inter Font**: https://github.com/googlefonts/inter
- **Bootstrap 5.3.3**: https://getbootstrap.com/
- **Simple DataTables 7.1.2**: https://github.com/fiduswriter/Simple-DataTables
