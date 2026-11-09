<?php

namespace App\Support;

/** Stroke icon paths on a 24×24 grid, shared by the sidebar and the <x-ui.icon> component. */
class Icons
{
    public const PATHS = [
        'dashboard' => 'M3 3h7v9H3zM14 3h7v5h-7zM14 12h7v9h-7zM3 16h7v5H3z',
        'check' => 'M9 3h6v3H9zM8 4.5H6a2 2 0 0 0-2 2V19a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6.5a2 2 0 0 0-2-2h-2M9 14l2 2 4-4',
        'monitor' => 'M3 12h4l3-8 4 16 3-8h4',
        'in' => 'M12 3v12M7 10l5 5 5-5M4 21h16',
        'transfer' => 'M7 7h13M16 3l4 4-4 4M17 17H4M8 13l-4 4 4 4',
        'out' => 'M12 21V9M7 14l5-5 5 5M4 3h16',
        'return' => 'M9 14 4 9l5-5M4 9h11a5 5 0 0 1 0 10h-3',
        'special' => 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6zM12 8v4M12 16h.01',
        'medicine' => 'm10.5 20.5-7-7a4.95 4.95 0 1 1 7-7l7 7a4.95 4.95 0 1 1-7 7ZM8.5 8.5l7 7',
        'opening' => 'M21 8l-9-5-9 5 9 5 9-5zM3 8v8l9 5 9-5V8M12 13v8',
        'parameter' => 'M4 6h9M17 6h3M4 12h3M11 12h9M4 18h11M19 18h1M15 4v4M9 10v4M17 16v4',
        'report' => 'M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9zM14 3v6h6M9 17v-3M12 17v-5M15 17v-2',
        'users' => 'M16 20v-1a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v1M9 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM22 20v-1a4 4 0 0 0-3-3.9M16 5.1a3 3 0 0 1 0 5.8',
        'audit' => 'M3 12a9 9 0 1 0 3-6.7L3 8M3 3v5h5M12 7v5l3 2',
        'reference' => 'M20 13l-7 7-9-9V4h7zM7.5 7.5h.01',
        'settings' => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM4 12h2M18 12h2M12 4v2M12 18v2M6.3 6.3l1.4 1.4M16.3 16.3l1.4 1.4M6.3 17.7l1.4-1.4M16.3 7.7l1.4-1.4',
        'alert' => 'M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z',
        'trend-down' => 'M22 17l-8.5-8.5-5 5L2 7M16 17h6v-6',
        'clock' => 'M12 7v5l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z',
        'calendar' => 'M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z',
        'order' => 'M6 2h12l2 4v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6zM4 6h16M9 11h6M9 15h4',
        'search' => 'm21 21-5.2-5.2M10.5 18a7.5 7.5 0 1 0 0-15 7.5 7.5 0 0 0 0 15Z',
        'plus' => 'M12 5v14M5 12h14',
        'download' => 'M12 3v12M7 10l5 5 5-5M5 21h14',
        'upload' => 'M12 21V9M7 14l5-5 5 5M5 3h14',
        'printer' => 'M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v7H6z',
        'file' => 'M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9zM14 3v6h6M8 13h8M8 17h5',
        'list' => 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01',
        'box' => 'M21 8l-9-5-9 5 9 5 9-5zM3 8v8l9 5 9-5V8M12 13v8',
        'location' => 'M12 21s7-6.1 7-11a7 7 0 1 0-14 0c0 4.9 7 11 7 11zM12 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z',
        'shield' => 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z',
        'key' => 'M15.5 7.5a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0zM12 11v10M12 17h3M12 14h2',
        'info' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 11v5M12 8h.01',
        'ok' => 'M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0zM8 12l3 3 5-6',
        'history' => 'M3 12a9 9 0 1 0 3-6.7L3 8M3 3v5h5M12 7v5l3 2',
        'filter' => 'M3 5h18l-7 8.5V20l-4-2v-4.5z',
        'edit' => 'M4 20h4L19 9l-4-4L4 16zM13.5 6.5l4 4',
        'trash' => 'M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3',
        'chevron-right' => 'm9 6 6 6-6 6',
        'arrow-right' => 'M5 12h14M13 6l6 6-6 6',
        'lock' => 'M6 11h12v10H6zM8 11V7a4 4 0 1 1 8 0v4',
        'user' => 'M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z',
    ];
}
