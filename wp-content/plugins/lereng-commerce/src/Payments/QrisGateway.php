<?php

namespace Lereng\Payments;

use WC_Order;

/** QRIS: one QR code every Indonesian bank app and e-wallet (GoPay, OVO, DANA, ShopeePay) can pay. */
final class QrisGateway extends SandboxGateway
{
    public function __construct()
    {
        $this->id = 'lereng_qris';
        $this->method_title = __('QRIS', 'lereng');
        $this->method_description = __('Pay by scanning a QR code with any bank app or e-wallet. Sandbox, modelled on Midtrans.', 'lereng');
        parent::__construct();
    }

    protected function defaultTitle(): string
    {
        return __('QRIS · GoPay, OVO, DANA, ShopeePay, any bank app', 'lereng');
    }

    protected function defaultDescription(): string
    {
        return __('After you place the order you get a QR code to scan. It is valid for 15 minutes.', 'lereng');
    }

    protected function defaultExpiryMinutes(): int
    {
        return 15;
    }

    protected function details(WC_Order $order): array
    {
        return ['qr' => Qris::payload((int) round((float) $order->get_total()), Payments::txnOrderId($order))];
    }
}
