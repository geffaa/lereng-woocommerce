<?php
/**
 * Exports the development site (SQLite under Playground) as MySQL statements, for moving the
 * store to a normal host: `node scripts/wp.mjs php wp-content/plugins/lereng-commerce/seed/export-mysql.php https://example.com`
 *
 * Writes wp-content/lereng-export/part-NNN.sql, each small enough to import within a shared
 * host's request time limit. URLs are replaced, including inside serialized PHP values.
 */
global $wpdb;
$to = rtrim($_SERVER['argv'][1] ?? '', '/');
if (!$to) {
    lereng_out('usage: export-mysql.php https://new-site.example');
    return;
}
$from = rtrim(get_option('siteurl'), '/');
$dir = WP_CONTENT_DIR . '/lereng-export';
if (is_dir($dir)) {
    array_map('unlink', glob("$dir/*"));
} else {
    mkdir($dir);
}

$replace = static function ($value) use (&$replace, $from, $to) {
    if (is_array($value)) {
        return array_map($replace, $value);
    }
    if (is_object($value) && !$value instanceof __PHP_Incomplete_Class) {
        foreach (get_object_vars($value) as $k => $v) {
            $value->$k = $replace($v);
        }
        return $value;
    }
    if (!is_string($value)) {
        return $value;
    }
    if (is_serialized($value)) {
        $u = @unserialize($value, ['allowed_classes' => false]);
        if ($u !== false || $value === 'b:0;') {
            return serialize($replace($u));
        }
    }
    // Every local run used its own port (http://127.0.0.1:NNNNN), so match them all, JSON-escaped too.
    return preg_replace(['#https?://(?:127\.0\.0\.1|localhost)(?::\d+)?#', '#https?:\\\\/\\\\/(?:127\.0\.0\.1|localhost)(?::\d+)?#'], [$to, str_replace('/', '\\/', $to)], $value);
};
$quote = static function ($v): string {
    if ($v === null) {
        return 'NULL';
    }
    return "'" . strtr((string) $v, ["\\" => "\\\\", "'" => "\\'", "\0" => "\\0", "\n" => "\\n", "\r" => "\\r", "\x1a" => "\\Z"]) . "'";
};

$skipData = [$wpdb->prefix . 'actionscheduler_logs'];
$part = 0;
$buf = '';
$flush = static function (bool $force = false) use (&$buf, &$part, $dir) {
    if ($buf !== '' && ($force || strlen($buf) > 2_500_000)) {
        file_put_contents(sprintf('%s/part-%03d.sql', $dir, ++$part), "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n" . $buf);
        $buf = '';
    }
};
$rowsTotal = 0;
foreach ($wpdb->get_col('SHOW TABLES') as $table) {
    if (!str_starts_with($table, $wpdb->prefix) || str_starts_with($table, '_')) {
        continue;
    }
    $create = $wpdb->get_row("SHOW CREATE TABLE `$table`", ARRAY_N)[1];
    $buf .= "DROP TABLE IF EXISTS `$table`;\n$create;\n";
    if (in_array($table, $skipData, true)) {
        continue;
    }
    $where = $table === $wpdb->options ? " WHERE option_name NOT LIKE '\\_transient\\_%' AND option_name NOT LIKE '\\_site\\_transient\\_%'" : '';
    $offset = 0;
    while ($rows = $wpdb->get_results("SELECT * FROM `$table`$where LIMIT 1000 OFFSET $offset", ARRAY_A)) {
        $offset += 1000;
        $cols = '`' . implode('`,`', array_keys($rows[0])) . '`';
        $values = [];
        $size = 0;
        foreach ($rows as $row) {
            $v = '(' . implode(',', array_map(static fn($x) => $quote($replace($x)), $row)) . ')';
            $values[] = $v;
            $size += strlen($v);
            $rowsTotal++;
            if ($size > 400_000) {
                $buf .= "INSERT INTO `$table` ($cols) VALUES\n" . implode(",\n", $values) . ";\n";
                $values = [];
                $size = 0;
                $flush();
            }
        }
        if ($values) {
            $buf .= "INSERT INTO `$table` ($cols) VALUES\n" . implode(",\n", $values) . ";\n";
        }
        $flush();
    }
}
$flush(true);
lereng_out(sprintf('%d rows → %d files in wp-content/lereng-export (%s → %s)', $rowsTotal, $part, $from, $to));
