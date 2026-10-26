<?php

namespace Lereng\Payments;

use WC_Order;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Payment plumbing shared by the sandbox gateways:
 *   /pay/<order key>/                          the payment page (QR code or account number)
 *   POST /wp-json/lereng/v1/payments/notify    gateway webhook, signature checked
 *   GET  /wp-json/lereng/v1/payments/<id>      status, polled by the payment page
 *   POST /wp-json/lereng/v1/payments/<id>/simulate   sandbox only: sends a signed notification
 * Unpaid orders are cancelled by an Action Scheduler job when their window closes.
 */
final class Payments
{
    public const GATEWAYS = [QrisGateway::class, VaGateway::class];

    public function register(): void
    {
        add_filter('woocommerce_payment_gateways', static fn(array $g): array => array_merge($g, self::GATEWAYS));
        add_action('woocommerce_blocks_payment_method_type_registration', static function ($registry): void {
            foreach (['lereng_qris', 'lereng_va'] as $id) {
                $registry->register(new BlocksIntegration($id));
            }
        });
        add_action('init', [$this, 'rewrite']);
        add_filter('query_vars', static fn(array $v): array => [...$v, 'lereng_pay']);
        add_action('template_redirect', [$this, 'payPage']);
        add_action('rest_api_init', [$this, 'routes']);
        add_action('lereng_payment_expire', [$this, 'expire']);
        // The thank-you page links back to the payment page while an order is still unpaid.
        add_action('woocommerce_thankyou', [$this, 'unpaidReminder'], 5);
    }

    public static function serverKey(): string
    {
        $key = get_option('lereng_payments_server_key');
        if (!$key) {
            $key = 'SB-Lereng-' . wp_generate_password(24, false);
            update_option('lereng_payments_server_key', $key);
        }
        return $key;
    }

    public static function sandbox(): bool
    {
        return get_option('lereng_payments_sandbox', 'yes') === 'yes';
    }

    public static function txnOrderId(WC_Order $order): string
    {
        return 'LRG' . $order->get_order_number();
    }

    public static function payUrl(WC_Order $order): string
    {
        return home_url('/pay/' . $order->get_order_key() . '/');
    }

    public function rewrite(): void
    {
        add_rewrite_rule('^pay/(wc_order_[A-Za-z0-9]+)/?$', 'index.php?lereng_pay=$matches[1]', 'top');
    }

    private static function orderFromKey(string $key): ?WC_Order
    {
        $id = wc_get_order_id_by_order_key($key);
        $order = $id ? wc_get_order($id) : null;
        return $order instanceof WC_Order ? $order : null;
    }

    public function payPage(): void
    {
        $key = get_query_var('lereng_pay');
        if (!$key) {
            return;
        }
        $order = self::orderFromKey($key);
        if (!$order || !$order->get_meta('_lereng_payment')) {
            wp_safe_redirect(home_url('/'));
            exit;
        }
        if ($order->is_paid()) {
            wp_safe_redirect($order->get_checkout_order_received_url());
            exit;
        }
        wp_enqueue_script('lereng-qrcode', LERENG_URL . 'assets/vendor/qrcode.js', [], '1.4.4', true);
        wp_enqueue_script('lereng-pay', LERENG_URL . 'assets/js/pay.js', ['lereng-qrcode'], LERENG_VERSION, true);
        wp_enqueue_style('lereng-pay', LERENG_URL . 'assets/css/pay.css', [], LERENG_VERSION);
        status_header(200);
        nocache_headers();
        include LERENG_DIR . '/templates/pay.php';
        exit;
    }

    public function routes(): void
    {
        register_rest_route('lereng/v1', '/payments/notify', [
            'methods' => 'POST',
            'permission_callback' => '__return_true', // authenticated by the signature
            'callback' => fn(WP_REST_Request $r) => $this->notify($r->get_json_params() ?: $r->get_body_params()),
        ]);
        register_rest_route('lereng/v1', '/payments/(?P<id>\d+)', [
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => [$this, 'status'],
        ]);
        register_rest_route('lereng/v1', '/payments/(?P<id>\d+)/simulate', [
            'methods' => 'POST',
            'permission_callback' => static fn() => self::sandbox(),
            'callback' => [$this, 'simulate'],
        ]);
    }

    /** Order by id, but only for someone holding its key (the payment page has it). */
    private static function authorizedOrder(WP_REST_Request $r): ?WC_Order
    {
        $order = wc_get_order((int) $r['id']);
        return $order instanceof WC_Order && hash_equals($order->get_order_key(), (string) $r->get_param('key')) ? $order : null;
    }

    public function status(WP_REST_Request $r): WP_REST_Response
    {
        $order = self::authorizedOrder($r);
        if (!$order) {
            return new WP_REST_Response(['error' => 'not found'], 404);
        }
        $state = $order->is_paid() ? 'paid' : match ($order->get_status()) {
            'cancelled' => 'expired',
            'failed' => 'failed',
            default => 'pending',
        };
        return new WP_REST_Response(['status' => $state, 'redirect' => $order->get_checkout_order_received_url()]);
    }

    /** Sandbox: build the notification a gateway would send, sign it, and process it like a real one. */
    public function simulate(WP_REST_Request $r): WP_REST_Response
    {
        $order = self::authorizedOrder($r);
        if (!$order) {
            return new WP_REST_Response(['error' => 'not found'], 404);
        }
        $status = in_array($r->get_param('status'), ['settlement', 'expire', 'deny'], true) ? $r->get_param('status') : 'settlement';
        return $this->notify(self::notification($order, $status));
    }

    public static function notification(WC_Order $order, string $transactionStatus): array
    {
        $txn = (array) $order->get_meta('_lereng_payment');
        $codes = ['settlement' => '200', 'capture' => '200', 'pending' => '201', 'expire' => '407', 'deny' => '202', 'cancel' => '200'];
        $payload = [
            'transaction_status' => $transactionStatus,
            'status_code' => $codes[$transactionStatus] ?? '200',
            'order_id' => $txn['order_id'] ?? self::txnOrderId($order),
            'gross_amount' => number_format((float) $order->get_total(), 2, '.', ''),
            'transaction_id' => $txn['transaction_id'] ?? '',
            'payment_type' => ($txn['method'] ?? '') === 'lereng_va' ? 'bank_transfer' : 'qris',
            'fraud_status' => 'accept',
            'transaction_time' => wp_date('Y-m-d H:i:s'),
        ];
        $payload['signature_key'] = Signature::make($payload['order_id'], $payload['status_code'], $payload['gross_amount'], self::serverKey());
        return $payload;
    }

    /** The webhook. Idempotent: replays and out-of-order notifications don't change a settled order. */
    public function notify(array $payload): WP_REST_Response
    {
        if (!Signature::verify($payload, self::serverKey())) {
            return new WP_REST_Response(['error' => 'invalid signature'], 401);
        }
        $order = wc_get_order((int) preg_replace('/\D/', '', $payload['order_id']));
        if (!$order instanceof WC_Order || self::txnOrderId($order) !== $payload['order_id']) {
            return new WP_REST_Response(['error' => 'unknown order'], 404);
        }
        if (number_format((float) $order->get_total(), 2, '.', '') !== $payload['gross_amount']) {
            $order->add_order_note(sprintf(__('Ignored a payment notification for %s: the amount does not match the order.', 'lereng'), $payload['gross_amount']));
            return new WP_REST_Response(['error' => 'amount mismatch'], 409);
        }
        $outcome = Signature::outcome((string) ($payload['transaction_status'] ?? ''), (string) ($payload['fraud_status'] ?? 'accept'));
        $label = ($payload['payment_type'] ?? '') === 'bank_transfer' ? __('virtual account', 'lereng') : 'QRIS';

        if ($order->is_paid() && $outcome !== 'refunded') {
            return new WP_REST_Response(['ok' => true, 'note' => 'already paid']);
        }
        switch ($outcome) {
            case 'paid':
                $order->payment_complete((string) ($payload['transaction_id'] ?? ''));
                $order->add_order_note(sprintf(__('Payment received by %s.', 'lereng'), $label) . (self::sandbox() ? ' ' . __('(sandbox)', 'lereng') : ''));
                break;
            case 'expired':
                if ($order->has_status('pending')) {
                    $order->update_status('cancelled', sprintf(__('The %s payment window closed without payment.', 'lereng'), $label));
                }
                break;
            case 'failed':
                if ($order->has_status('pending')) {
                    $order->update_status('failed', __('The payment was declined.', 'lereng'));
                }
                break;
            case 'review':
                $order->update_status('on-hold', __('The payment is held for a fraud review by the gateway.', 'lereng'));
                break;
        }
        return new WP_REST_Response(['ok' => true, 'outcome' => $outcome]);
    }

    public function expire(int $order_id): void
    {
        $order = wc_get_order($order_id);
        if ($order instanceof WC_Order && $order->has_status('pending') && $order->get_meta('_lereng_payment')) {
            $this->notify(self::notification($order, 'expire'));
        }
    }

    public function unpaidReminder(int $orderId): void
    {
        $order = wc_get_order($orderId);
        if ($order && $order->has_status('pending') && $order->get_meta('_lereng_payment')) {
            printf('<p class="lereng-unpaid"><a class="button" href="%s">%s</a></p>', esc_url(self::payUrl($order)), esc_html__('Finish paying for this order', 'lereng'));
        }
    }
}
