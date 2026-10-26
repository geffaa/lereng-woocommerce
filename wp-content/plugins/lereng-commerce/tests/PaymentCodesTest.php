<?php

namespace Lereng\Tests;

use Lereng\Payments\Qris;
use Lereng\Payments\VirtualAccount;
use PHPUnit\Framework\TestCase;

if (!function_exists('__')) {
    function __($s) { return $s; }
}

final class PaymentCodesTest extends TestCase
{
    public function testCrcMatchesTheCcittFalseCheckValue(): void
    {
        $this->assertSame('29B1', Qris::crc16('123456789'));
    }

    public function testQrisPayloadIsValidTlvWithAmountAndBill(): void
    {
        $p = Qris::payload(155000, 'LRG1001');
        $this->assertStringStartsWith('000201010212', $p);
        $this->assertStringContainsString('5406155000', $p);
        $this->assertStringContainsString('5303360', $p);
        $this->assertStringContainsString('6211' . '0107LRG1001', $p);
        $this->assertTrue(Qris::valid($p));
        $this->assertFalse(Qris::valid(substr($p, 0, -1) . '0' === $p ? $p . 'X' : substr($p, 0, -1) . '0'));
    }

    public function testVaNumbersHaveTheBankLengthAndEndInTheOrder(): void
    {
        $bca = VirtualAccount::number('bca', 1042, '0812-3456-7890');
        $this->assertSame(16, strlen($bca));
        $this->assertStringStartsWith('12345', $bca);
        $this->assertStringEndsWith('001042', $bca);
        $this->assertSame(15, strlen(VirtualAccount::number('bri', 1042, '')));
        $this->assertSame($bca, VirtualAccount::number('bca', 1042, '0812-3456-7890')); // stable
        $this->assertSame('1234 5098 7654 3210', VirtualAccount::format('1234509876543210'));
    }
}
