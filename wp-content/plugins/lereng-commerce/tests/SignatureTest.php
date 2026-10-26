<?php

namespace Lereng\Tests;

use Lereng\Payments\Signature;
use PHPUnit\Framework\TestCase;

final class SignatureTest extends TestCase
{
    private const KEY = 'SB-Mid-server-test';

    private function payload(array $over = []): array
    {
        $p = ['order_id' => 'LRG-1001', 'status_code' => '200', 'gross_amount' => '155000.00', 'transaction_status' => 'settlement'] + $over;
        $p['signature_key'] ??= Signature::make($p['order_id'], $p['status_code'], $p['gross_amount'], self::KEY);
        return $p;
    }

    public function testValidSignaturePasses(): void
    {
        $this->assertTrue(Signature::verify($this->payload(), self::KEY));
    }

    public function testTamperedAmountFails(): void
    {
        $p = $this->payload();
        $p['gross_amount'] = '1.00';
        $this->assertFalse(Signature::verify($p, self::KEY));
    }

    public function testWrongKeyAndMissingFieldsFail(): void
    {
        $this->assertFalse(Signature::verify($this->payload(), 'other-key'));
        $p = $this->payload();
        unset($p['status_code']);
        $this->assertFalse(Signature::verify($p, self::KEY));
    }

    public function testStatusMapping(): void
    {
        $this->assertSame('paid', Signature::outcome('settlement'));
        $this->assertSame('paid', Signature::outcome('capture', 'accept'));
        $this->assertSame('review', Signature::outcome('capture', 'challenge'));
        $this->assertSame('expired', Signature::outcome('expire'));
        $this->assertSame('failed', Signature::outcome('deny'));
        $this->assertSame('ignore', Signature::outcome('authorize'));
    }
}
