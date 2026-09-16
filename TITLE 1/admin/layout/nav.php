<?php
/**
 * Innovatech PH — role-aware navigation definitions + inline SVG icons.
 * The sidebar renders only what the current role is allowed to see.
 */

function ia_icon(string $name, int $size = 18): string
{
    $map = [
        'home' => 'fa-solid fa-house',
        'school' => 'fa-solid fa-school',
        'layers' => 'fa-solid fa-layer-group',
        'building' => 'fa-solid fa-building',
        'map' => 'fa-solid fa-map-location-dot',
        'compass' => 'fa-regular fa-compass',
        'camera' => 'fa-solid fa-camera',
        'sparkles' => 'fa-solid fa-wand-magic-sparkles',
        'users' => 'fa-solid fa-users',
        'user' => 'fa-regular fa-user',
        'settings' => 'fa-solid fa-gear',
        'mail' => 'fa-regular fa-envelope',
        'shield' => 'fa-solid fa-shield-halved',
        'folder' => 'fa-regular fa-folder',
        'file' => 'fa-regular fa-file',
        'clock' => 'fa-regular fa-clock',
        'globe' => 'fa-solid fa-globe',
        'logout' => 'fa-solid fa-arrow-right-from-bracket',
        'move3d' => 'fa-solid fa-cube',
        'image' => 'fa-regular fa-image',
        'palette' => 'fa-solid fa-palette',
        'rocket' => 'fa-solid fa-rocket',
        'search' => 'fa-solid fa-magnifying-glass',
        'link' => 'fa-solid fa-link',
        'menu' => 'fa-solid fa-bars',
        'sun' => 'fa-regular fa-sun',
        'moon' => 'fa-regular fa-moon',
        'info' => 'fa-solid fa-circle-info',
        'grid' => 'fa-solid fa-grip',
        'save' => 'fa-solid fa-floppy-disk',
        'paint' => 'fa-solid fa-paint-roller',
        'wand' => 'fa-solid fa-wand-magic-sparkles',
        'refresh' => 'fa-solid fa-arrow-rotate-right',
        'eye' => 'fa-regular fa-eye',
        'upload' => 'fa-solid fa-cloud-arrow-up',
        'scan-eye' => 'fa-solid fa-expand',
        'life-buoy' => 'fa-solid fa-life-ring',
        'download' => 'fa-solid fa-download',
        'check' => 'fa-solid fa-check',
        'chevron' => 'fa-solid fa-chevron-down',
        'x' => 'fa-solid fa-xmark',
        'inbox' => 'fa-solid fa-inbox',
    ];

    $class = $map[$name] ?? 'fa-solid fa-circle';
    return '<i class="' . $class . '" style="font-size: ' . $size . 'px;"></i>';
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
            [
                'title' => 'Overview',
                'items' => [
                    ['Dashboard', '/admin/owner/dashboard', 'home', 'owner.dashboard'],
                ]
            ],
            [
                'title' => 'Manage',
                'items' => [
                    ['Institutions', '/admin/owner/institutions', 'school', 'owner.institutions'],
                    ['Accounts', '/admin/owner/accounts', 'users', 'owner.accounts'],
                ]
            ],
            [
                'title' => 'Platform',
                'items' => [
                    ['Role Management', '/admin/owner/roles', 'shield', 'owner.roles'],
                    ['System Settings', '/admin/owner/settings', 'settings', 'owner.settings'],
                    ['Website Settings', '/admin/owner/website-settings', 'globe', 'owner.website'],
                    ['Support Tickets', '/admin/support', 'life-buoy'],
                    ['Audit Logs', '/admin/owner/logs', 'clock', 'owner.logs'],
                    ['File Manager', '/admin/owner/files', 'folder', 'owner.files'],
                    ['Archive & Restore', '/admin/owner/archive', 'refresh', 'owner.archive'],
                ]
            ],
            ['title' => 'Help', 'items' => array_merge($systemHelp, [])],
        ],
        'system_admin' => [
            [
                'title' => 'Overview',
                'items' => [
                    ['System Dashboard', '/admin/system/dashboard', 'home', 'system.dashboard'],
                ]
            ],
            [
                'title' => 'Manage',
                'items' => [
                    ['Institutions', '/admin/owner/institutions', 'school', 'system.institutions'],
                    ['Accounts', '/admin/owner/accounts', 'users', 'system.accounts'],
                ]
            ],
            [
                'title' => 'Platform',
                'items' => [
                    ['Audit Logs', '/admin/owner/logs', 'clock', 'system.logs'],
                    ['File Manager', '/admin/owner/files', 'folder', 'system.files'],
                    ['Support Tickets', '/admin/support', 'life-buoy'],
                ]
            ],
            ['title' => 'Help', 'items' => array_merge($systemHelp, [])],
        ],
        'system_staff' => [
            [
                'title' => 'Overview',
                'items' => [
                    ['System Dashboard', '/admin/system/dashboard', 'home', 'system.dashboard'],
                ]
            ],
            [
                'title' => 'Manage',
                'items' => [
                    ['Institutions', '/admin/owner/institutions', 'school', 'system.institutions'],
                ]
            ],
            [
                'title' => 'Platform',
                'items' => [
                    ['File Manager', '/admin/owner/files', 'folder', 'system.files'],
                    ['Support Tickets', '/admin/support', 'life-buoy'],
                ]
            ],
            ['title' => 'Help', 'items' => array_merge($systemHelp, [])],
        ],
        'admin' => [
            [
                'title' => 'Overview',
                'items' => [
                    ['Dashboard', '/admin/institution/dashboard', 'home', 'admin.dashboard'],
                ]
            ],
            [
                'title' => 'Content',
                'items' => [
                    ['Buildings', '/admin/institution/buildings', 'building', 'admin.buildings'],
                    ['Locations', '/admin/institution/locations', 'map', 'admin.locations'],
                    ['360 Tours', '/admin/institution/tours', 'camera', 'admin.tours'],
                    ['Floor Plans', '/admin/institution/floor-plans', 'compass', 'admin.floorplans'],
                ]
            ],
            [
                'title' => 'Customize',
                'items' => [
                    ['Theme & Landing', '/admin/institution/settings', 'palette', 'admin.settings'],
                    ['AI Tools', '/admin/institution/ai', 'sparkles', 'admin.ai'],
                    ['Augmented Reality', '/admin/institution/ar', 'scan-eye', 'admin.ar'],
                ]
            ],
            [
                'title' => 'Files',
                'items' => [
                    ['Organization Files', '/admin/institution/files', 'folder', 'admin.files'],
                    ['Archive & Restore', '/admin/institution/archive', 'refresh', 'admin.archive'],
                ]
            ],
            ['title' => 'Support', 'items' => [['Support Tickets', '/admin/support', 'life-buoy']]],
            ['title' => 'Help', 'items' => $systemHelp],
        ],
        'staff' => [
            [
                'title' => 'Overview',
                'items' => [
                    ['Dashboard', '/admin/staff/dashboard', 'home', 'staff.dashboard'],
                ]
            ],
            [
                'title' => 'Content',
                'items' => [
                    ['Facilities', '/admin/staff/facilities', 'building', 'staff.facilities'],
                    ['Floor Plans', '/admin/staff/floor-plans', 'map', 'staff.floorplans'],
                    ['Media Uploads', '/admin/staff/uploads', 'image', 'staff.uploads'],
                ]
            ],
            [
                'title' => 'AI Tools',
                'items' => [
                    ['AI Stitch', '/admin/staff/ai-stitch', 'camera', 'staff.aistitch'],
                    ['AI Info', '/admin/staff/ai-info', 'sparkles', 'staff.aiinfo'],
                ]
            ],
            [
                'title' => 'Files',
                'items' => [
                    ['Archive & Restore', '/admin/staff/archive', 'refresh', 'staff.archive'],
                ]
            ],
            ['title' => 'Support', 'items' => [['Support Tickets', '/admin/support', 'life-buoy']]],
            ['title' => 'Help', 'items' => $systemHelp],
        ],
        'user' => [
            [
                'title' => 'Home',
                'items' => [
                    ['Public Site', '/', 'home'],
                ]
            ],
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