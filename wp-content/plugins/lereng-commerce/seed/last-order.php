<?php
// Prints the latest orders with status, payment meta and notes (dev helper).
foreach (wc_get_orders(['limit' => 2, 'orderby' => 'date', 'order' => 'DESC']) as $o) {
    $t = (array) $o->get_meta('_lereng_payment');
    lereng_out(sprintf('#%d %s %s total=%s va=%s bank=%s', $o->get_id(), $o->get_status(), $o->get_payment_method(), $o->get_total(), $t['va'] ?? '-', $t['bank'] ?? '-'));
    foreach (wc_get_order_notes(['order_id' => $o->get_id()]) as $n) {
        lereng_out('   · ' . wp_strip_all_tags($n->content));
    }
}
