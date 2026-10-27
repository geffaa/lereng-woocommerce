<?php
/**
 * One-off deploy helper, uploaded with a random name by scripts/deploy_host.py and deleted by its
 * last step. Each request does one small job so it fits a free host's 60-second limit:
 *   ?step=unzip&part=N   extract site-NN.zip into the web root, then delete it
 *   ?step=sql&part=N     run part-NNN.sql against the database in wp-config.php
 *   ?step=finish         set the admin password, create the "demo" staff account, clean up
 */
set_time_limit(60);
header('Content-Type: text/plain; charset=utf-8');
$root = __DIR__;
$step = $_GET['step'] ?? '';
$part = (int) ($_GET['part'] ?? 0);

function db(): mysqli
{
    $cfg = file_get_contents(__DIR__ . '/wp-config.php');
    $get = static fn($k) => preg_match("/define\\('$k', \"([^\"]*)\"\\)/", $cfg, $m) ? stripcslashes($m[1]) : '';
    $db = new mysqli($get('DB_HOST'), $get('DB_USER'), $get('DB_PASSWORD'), $get('DB_NAME'));
    $db->set_charset('utf8mb4');
    return $db;
}

if ($step === 'unzip') {
    $file = sprintf('%s/site-%02d.zip', $root, $part);
    if (!is_file($file)) { echo "skip: no $file"; exit; }
    $z = new ZipArchive();
    if ($z->open($file) !== true) { http_response_code(500); echo 'cannot open zip'; exit; }
    $n = $z->numFiles;
    $ok = $z->extractTo($root);
    $z->close();
    if (!$ok) { http_response_code(500); echo 'extract failed'; exit; }
    unlink($file);
    echo "ok unzip $part: $n files";
} elseif ($step === 'sql') {
    $file = sprintf('%s/part-%03d.sql', $root, $part);
    if (!is_file($file)) { echo "skip: no $file"; exit; }
    $db = db();
    if (!$db->multi_query(file_get_contents($file))) { http_response_code(500); echo 'sql error: ' . $db->error; exit; }
    $count = 0;
    do {
        $count++;
        if ($r = $db->store_result()) { $r->free(); }
        if ($db->errno) { http_response_code(500); echo "sql error after $count statements: " . $db->error; exit; }
    } while ($db->more_results() && $db->next_result());
    if ($db->errno) { http_response_code(500); echo "sql error after $count statements: " . $db->error; exit; }
    unlink($file);
    echo "ok sql $part: $count statements";
} elseif ($step === 'finish') {
    define('WP_USE_THEMES', false);
    require $root . '/wp-load.php';
    $admin = get_user_by('login', 'admin');
    if ($admin) {
        wp_set_password('__ADMIN_PASSWORD__', $admin->ID);
    }
    $demo = get_user_by('login', 'demo');
    $id = $demo ? $demo->ID : wp_insert_user(['user_login' => 'demo', 'user_pass' => 'demo', 'user_email' => 'demo@lereng.example', 'display_name' => 'Demo staff', 'role' => 'shop_manager']);
    if (!is_wp_error($id)) {
        wp_set_password('demo', $id);
        (new WP_User($id))->set_role('shop_manager');
    }
    flush_rewrite_rules();
    wp_cache_flush();
    foreach (glob($root . '/{site-*.zip,part-*.sql}', GLOB_BRACE) as $f) { unlink($f); }
    echo 'ok finish: admin password set, demo user ' . (is_wp_error($id) ? $id->get_error_message() : $id) . ', site ' . home_url('/');
    unlink(__FILE__);
} else {
    http_response_code(404);
}
