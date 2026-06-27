<?php
/**
 * Public (unauthenticated) read endpoints used by the public-facing pages.
 *
 *   ?action=regions          -> active regions [{id,name,slug}]
 *   ?action=region&slug=...   -> one region with its open sessions,
 *                                active divisions and active locations
 *   ?action=all_locations     -> active locations grouped by region (locations page)
 *   ?action=poster            -> { enabled, url }
 */
require_once __DIR__ . '/db.php';

$action = $_GET['action'] ?? '';

try {
    if ($action === 'regions') {
        $rows = ns_all($db, "SELECT id, name, slug FROM ns_regions WHERE active = 1 ORDER BY sort_order, name");
        ns_json(['regions' => $rows]);
    }

    if ($action === 'region') {
        $slug = $_GET['slug'] ?? '';
        $region = ns_one($db, "SELECT id, name, slug FROM ns_regions WHERE slug = ? AND active = 1", 's', [$slug]);
        if (!$region) {
            ns_json(['error' => 'Region not found.'], 404);
        }
        $rid = (int) $region['id'];
        $region['sessions'] = ns_all($db,
            "SELECT id, name FROM ns_sessions WHERE region_id = ? AND is_open = 1 ORDER BY sort_order, name",
            'i', [$rid]);
        $region['divisions'] = ns_all($db,
            "SELECT id, name FROM ns_divisions WHERE region_id = ? AND active = 1 ORDER BY sort_order, name",
            'i', [$rid]);
        $region['locations'] = ns_all($db,
            "SELECT id, name FROM ns_locations WHERE region_id = ? AND active = 1 ORDER BY sort_order, name",
            'i', [$rid]);
        ns_json(['region' => $region]);
    }

    if ($action === 'all_locations') {
        $regions = ns_all($db, "SELECT id, name, slug FROM ns_regions WHERE active = 1 ORDER BY sort_order, name");
        foreach ($regions as &$r) {
            $r['locations'] = ns_all($db,
                "SELECT id, name, address, map_embed FROM ns_locations WHERE region_id = ? AND active = 1 ORDER BY sort_order, name",
                'i', [(int) $r['id']]);
        }
        unset($r);
        ns_json(['regions' => $regions]);
    }

    if ($action === 'poster') {
        $enabled = ns_one($db, "SELECT setting_value v FROM ns_settings WHERE setting_key = 'home_poster_enabled'");
        $url = ns_one($db, "SELECT setting_value v FROM ns_settings WHERE setting_key = 'home_poster'");
        ns_json([
            'enabled' => $enabled ? ($enabled['v'] === '1') : false,
            'url' => $url['v'] ?? null,
        ]);
    }

    if ($action === 'home_button') {
        // The configurable schedule button on the landing page.
        $enabled = ns_one($db, "SELECT setting_value v FROM ns_settings WHERE setting_key = 'home_schedule_enabled'");
        $label = ns_one($db, "SELECT setting_value v FROM ns_settings WHERE setting_key = 'home_schedule_label'");
        $sid = ns_one($db, "SELECT setting_value v FROM ns_settings WHERE setting_key = 'home_schedule_id'");
        $url = null;
        if (!empty($sid['v'])) {
            $sched = ns_one($db, "SELECT file_path FROM ns_schedules WHERE id = ?", 'i', [(int) $sid['v']]);
            $url = $sched['file_path'] ?? null;
        }
        ns_json([
            'enabled' => $enabled ? ($enabled['v'] === '1') : false,
            'label' => $label['v'] ?? 'Schedule',
            'url' => $url,
        ]);
    }

    ns_json(['error' => 'Unknown action.'], 400);
} catch (Throwable $e) {
    ns_json(['error' => $e->getMessage()], 500);
}
