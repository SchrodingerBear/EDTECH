<?php
/**
 * Innovatech PH — role-aware navigation definitions + inline SVG icons.
 * The sidebar renders only what the current role is allowed to see.
 */

function ia_icon(string $name, int $size = 18): string
{
    $s = (int) $size;
    $paths = [
        'home' => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        'school' => '<path d="m22 9-10-5L2 9l10 5 10-5z"/><path d="M6 11.5V17a3 3 0 0 0 12 0v-5.5"/><path d="M22 9v6"/>',
        'layers' => '<path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/>',
        'building' => '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M16 6h.01M8 10h.01M16 10h.01M8 14h.01M16 14h.01"/>',
        'map' => '<path d="m11 20.54-2.5-2.5-2.5 2.5V5a1 1 0 0 1 1-1h3a13 13 0 0 1 3 9"/><path d="M13 5a13 13 0 0 0-3 9"/><path d="M9 20.54V13"/><circle cx="17" cy="6" r="3"/><path d="m19 12 2 8-3.5-2-2 3-1.5-5"/>',
        'compass' => '<circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/>',
        'camera' => '<path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3z"/><circle cx="12" cy="13" r="3"/>',
        'sparkles' => '<path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"/><path d="M20 3v4"/><path d="M22 5h-4"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'settings' => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
        'mail' => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
        'shield' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
        'folder' => '<path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/>',
        'file' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'globe' => '<circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'move3d' => '<path d="M5 3v16h16"/><path d="m5 19 6-6"/><path d="m2 6 3-3 3 3"/><path d="m18 16 3 3-3 3"/>',
        'image' => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>',
        'palette' => '<circle cx="13.5" cy="6.5" r=".5"/><circle cx="17.5" cy="10.5" r=".5"/><circle cx="8.5" cy="7.5" r=".5"/><circle cx="6.5" cy="12.5" r=".5"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/>',
        'rocket' => '<path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/>',
        'search' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'link' => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
        'menu' => '<path d="M4 5h16"/><path d="M4 12h16"/><path d="M4 19h16"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>',
        'moon' => '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
        'info' => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
        'save' => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>',
        'paint' => '<path d="m14.622 17.897-10.68-2.913"/><path d="m13.123 17.897 3.562-3.56"/><path d="M18.461 3.248a1 1 0 0 0-1.4-.447l-8.595 4.913a2 2 0 0 0-.858 2.625l.207.43a2 2 0 0 0 .858.883l.18.091 6.1 2.68a2 2 0 0 0 2.65-.79l4.677-8.2a1 1 0 0 0-.219-1.285Z"/>',
        'wand' => '<path d="m21.64 3.64-1.28-1.28a1.21 1.21 0 0 0-1.72 0L2.36 18.64a1.21 1.21 0 0 0 0 1.72l1.28 1.28a1.2 1.2 0 0 0 1.72 0L21.64 5.36a1.2 1.2 0 0 0 0-1.72Z"/><path d="m14 7 3 3"/><path d="M5 6v4"/><path d="M19 14v4"/><path d="M10 2v2"/><path d="M7 8H3"/><path d="M21 16h-4"/><path d="M11 3H9"/>',
        'refresh' => '<path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M21 21v-5h-5"/>',
        'eye' => '<path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/>',
        'upload' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>',
        'scan-eye' => '<path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><circle cx="12" cy="12" r="1"/><path d="M18.944 12.33a1 1 0 0 0 0-.66 7.5 7.5 0 0 0-13.888 0 1 1 0 0 0 0 .66 7.5 7.5 0 0 0 13.888 0"/>',
    ];
    $common = 'xmlns="http://www.w3.org/2000/svg" width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';
    return '<svg ' . $common . '>' . ($paths[$name] ?? $paths['grid']) . '</svg>';
}

/**
 * Navigation groups. Each item: [text, href, icon, page_key?]
 * page_key (optional) is used by the owner's role-management page access matrix.
 */
function role_nav(string $role): array
{
    $systemHelp = [['Help Center', '/admin/help', 'info']];

    $nav = [
        'owner' => [
            ['title' => 'Overview', 'items' => [
                ['Dashboard', '/admin/owner/dashboard', 'home', 'owner.dashboard'],
            ]],
            ['title' => 'Manage', 'items' => [
                ['Institutions', '/admin/owner/institutions', 'school', 'owner.institutions'],
                ['Accounts', '/admin/owner/accounts', 'users', 'owner.accounts'],
            ]],
            ['title' => 'Platform', 'items' => [
                ['Role Management', '/admin/owner/roles', 'shield', 'owner.roles'],
                ['System Settings', '/admin/owner/settings', 'settings', 'owner.settings'],
                ['Website Settings', '/admin/owner/website-settings', 'globe', 'owner.website'],
                ['Email Templates', '/admin/owner/emails', 'mail', 'owner.emails'],
                ['Audit Logs', '/admin/owner/logs', 'clock', 'owner.logs'],
                ['File Manager', '/admin/owner/files', 'folder', 'owner.files'],
                ['Archive & Restore', '/admin/owner/archive', 'refresh', 'owner.archive'],
            ]],
            ['title' => 'Help', 'items' => array_merge($systemHelp, [['Roles & Features', '/admin/help/roles', 'shield']])],
        'system_admin' => [
            ['title' => 'Overview', 'items' => [
                ['System Dashboard', '/admin/system/dashboard', 'home', 'system.dashboard'],
            ]],
            ['title' => 'Manage', 'items' => [
                ['Institutions', '/admin/owner/institutions', 'school', 'system.institutions'],
                ['Accounts', '/admin/owner/accounts', 'users', 'system.accounts'],
            ]],
            ['title' => 'Platform', 'items' => [
                ['Audit Logs', '/admin/owner/logs', 'clock', 'system.logs'],
                ['File Manager', '/admin/owner/files', 'folder', 'system.files'],
            ]],
            ['title' => 'Help', 'items' => array_merge($systemHelp, [['Roles & Features', '/admin/help/roles', 'shield']])],
        ],
        'system_staff' => [
            ['title' => 'Overview', 'items' => [
                ['System Dashboard', '/admin/system/dashboard', 'home', 'system.dashboard'],
            ]],
            ['title' => 'Manage', 'items' => [
                ['Institutions', '/admin/owner/institutions', 'school', 'system.institutions'],
            ]],
            ['title' => 'Platform', 'items' => [
                ['File Manager', '/admin/owner/files', 'folder', 'system.files'],
            ]],
            ['title' => 'Help', 'items' => array_merge($systemHelp, [['Roles & Features', '/admin/help/roles', 'shield']])],
        ],
        'admin' => [
            ['title' => 'Overview', 'items' => [
                ['Dashboard', '/admin/institution/dashboard', 'home', 'admin.dashboard'],
            ]],
            ['title' => 'Content', 'items' => [
                ['Buildings', '/admin/institution/buildings', 'building', 'admin.buildings'],
                ['Rooms & Areas', '/admin/institution/locations', 'map', 'admin.locations'],
                ['360 Tours', '/admin/institution/tours', 'camera', 'admin.tours'],
                ['Floor Plans', '/admin/institution/floor-plans', 'compass', 'admin.floorplans'],
            ]],
            ['title' => 'Customize', 'items' => [
                ['Theme & Landing', '/admin/institution/settings', 'palette', 'admin.settings'],
                ['AI Tools', '/admin/institution/ai', 'sparkles', 'admin.ai'],
                ['Augmented Reality', '/admin/institution/ar', 'scan-eye', 'admin.ar'],
            ]],
            ['title' => 'Files', 'items' => [
                ['Organization Files', '/admin/institution/files', 'folder', 'admin.files'],
                ['Archive & Restore', '/admin/institution/archive', 'refresh', 'admin.archive'],
            ]],
            ['title' => 'Help', 'items' => $systemHelp],
        ],
        'staff' => [
            ['title' => 'Overview', 'items' => [
                ['Dashboard', '/admin/staff/dashboard', 'home', 'staff.dashboard'],
            ]],
            ['title' => 'Content', 'items' => [
                ['Facilities', '/admin/staff/facilities', 'building', 'staff.facilities'],
                ['Floor Plans', '/admin/staff/floor-plans', 'map', 'staff.floorplans'],
                ['Media Uploads', '/admin/staff/uploads', 'image', 'staff.uploads'],
            ]],
            ['title' => 'AI Tools', 'items' => [
                ['AI Stitch', '/admin/staff/ai-stitch', 'camera', 'staff.aistitch'],
                ['AI Info', '/admin/staff/ai-info', 'sparkles', 'staff.aiinfo'],
            ]],
            ['title' => 'Files', 'items' => [
                ['Archive & Restore', '/admin/staff/archive', 'refresh', 'staff.archive'],
            ]],
            ['title' => 'Help', 'items' => $systemHelp],
        ],
        'user' => [
            ['title' => 'Home', 'items' => [
                ['Public Site', '/', 'home'],
            ]],
        ],
    ];

    // Apply owner-controlled page access filter (only for controlled roles).
    $acc = $_SESSION['user']['page_access'] ?? null;
    if ($acc !== null && in_array($role, ['owner', 'system_admin', 'system_staff', 'admin', 'staff'], true)) {
        foreach ($nav[$role] as &$group) {
            if (!isset($group['items'])) {
                continue;
            }
            $group['items'] = array_values(array_filter($group['items'], function ($item) use ($acc) {
                return !isset($item[3]) || in_array($item[3], $acc, true);
            }));
        }
        unset($group);
        $nav[$role] = array_values(array_filter($nav[$role], function ($g) {
            return !empty($g['items']);
        }));
    }

    return $nav[$role] ?? [];
}