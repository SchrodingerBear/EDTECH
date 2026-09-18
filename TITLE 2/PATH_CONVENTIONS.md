# Path Conventions Documentation

## Important: Do NOT use absolute paths starting with `/`

**CRITICAL**: All paths in this application must be **relative** and **dynamic**. Never use paths that start with `/` as this breaks routing in PHP applications.

## Correct Path Format

### ✅ CORRECT - Dynamic/Relative Paths
```php
// Use the url() helper function for all URLs
<?= url('admin/dashboard') ?>
<?= url('admin/services') ?>
<?= url('admin/assets/css/dashboard.css') ?>
<?= url('admin/logout') ?>

// Redirect function
redirect('admin/dashboard');
redirect('admin/index');
```

### ❌ INCORRECT - Absolute paths
```php
// NEVER use paths starting with /
redirect('/admin/dashboard');
href="/admin/services"
src="/assets/css/dashboard.css"
```

## File Structure Context

```
TITLE 2/
├── admin/
│   ├── assets/
│   │   ├── css/
│   │   ├── js/
│   │   └── img/
│   ├── layout/
│   │   ├── header.php
│   │   ├── footer.php
│   │   └── nav.php
│   ├── dashboard.php
│   ├── services.php
│   ├── orders.php
│   ├── customers.php
│   ├── logout.php
│   └── index.php
├── includes/
│   ├── auth.php
│   ├── config.php
│   ├── functions.php
│   └── bootstrap.php
└── index.php
```

## Path Reference Guide

### Navigation Links (in nav.php)
- Admin pages: `admin/dashboard`, `admin/orders`, `admin/services`, etc.
- Always include the `admin/` prefix for admin pages

### Asset References
- CSS: `admin/assets/css/dashboard.css`
- JS: `admin/assets/js/dashboard.js`
- Images: `admin/assets/img/logo.svg`

### Redirects
- Login page: `admin/index`
- Dashboard: `admin/dashboard`
- Logout: `admin/logout`

### Role Home Pages
- Owner: `admin/dashboard`
- Staff: `admin/dashboard`

## Why This Matters

The `url()` function in `includes/functions.php` automatically handles:
- Dynamic base URL detection
- Proper path concatenation
- Environment differences (localhost vs production)
- PHP/Python routing compatibility

Using absolute paths (`/admin/dashboard`) breaks this system and causes 404 errors.

## Testing After Changes

After making any path changes, verify:
1. No console 404 errors for assets
2. Navigation links work correctly
3. Redirects function properly
4. Login/logout flow works
5. All admin pages load without errors

## Common Mistakes to Avoid

1. **Don't** mix absolute and relative paths
2. **Don't** forget the `admin/` prefix for admin pages
3. **Don't** use hardcoded full URLs
4. **Don't** assume `/` will work as a path separator
5. **Don't** skip the `url()` helper function

## Example: Correct vs Incorrect

### Navigation in nav.php
```php
// ✅ CORRECT
['Dashboard', 'admin/dashboard', 'home', 'dashboard'],

// ❌ INCORRECT
['Dashboard', '/admin/dashboard', 'home', 'dashboard'],
['Dashboard', 'dashboard', 'home', 'dashboard'], // Missing admin/ prefix
```

### Asset Loading in header.php
```php
// ✅ CORRECT
<link rel="stylesheet" href="<?= url('admin/assets/css/dashboard.css') ?>">

// ❌ INCORRECT
<link rel="stylesheet" href="/admin/assets/css/dashboard.css">
<link rel="stylesheet" href="assets/css/dashboard.css"> // Missing admin/ prefix
```

### Redirects in auth.php
```php
// ✅ CORRECT
redirect('admin/index');

// ❌ INCORRECT
redirect('/admin/index');
redirect('index'); // Missing admin/ prefix
```

## Maintenance Notes

When adding new features:
1. Always use the `url()` helper function
2. Include proper folder prefixes (`admin/` for admin pages)
3. Test all navigation paths
4. Check browser console for 404 errors
5. Verify the redirect chain works correctly

## Quick Reference

| Type | Correct Format | Example |
|------|---------------|---------|
| Admin Page | `admin/filename` | `admin/dashboard` |
| Asset CSS | `admin/assets/css/filename.css` | `admin/assets/css/dashboard.css` |
| Asset JS | `admin/assets/js/filename.js` | `admin/assets/js/dashboard.js` |
| Asset Image | `admin/assets/img/filename.ext` | `admin/assets/img/logo.svg` |
| Redirect | `admin/path` | `admin/dashboard` |
| Logout | `admin/logout` | `admin/logout` |

---
**Remember**: Dynamic paths = Robust application. Absolute paths = Broken routing.
