<?php

namespace Lereng\Tests;

use Lereng\Bundles\Pricing;
use Lereng\GiftCards\Code;
use PHPUnit\Framework\TestCase;

final class BundleAndGiftCardTest extends TestCase
{
    public function testDiscoveryBoxIsThree100gBagsLessFifteenPercent(): void
    {
        // 155k, 125k and 145k for 200 g → 77.5k + 62.5k + 72.5k = 212.5k, less 15% = 180.6k → 181k
        $this->assertSame(181000, Pricing::pick([155000, 125000, 145000], 15));
    }

    public function testFixedBundle(): void
    {
        // Brew starter kit: dripper 285k, filters 65k, server 225k, 200 g Pagi blend 120k, less 12%
        $this->assertSame(612000, Pricing::fixed([[285000, 1], [65000, 1], [225000, 1], [120000, 1]], 12));
    }

    public function testGiftCardCodesUseTheSafeAlphabet(): void
    {
        $code = Code::generate();
        $this->assertMatchesRegularExpression('/^LRG-[2-9A-HJKMNP-Z]{4}-[2-9A-HJKMNP-Z]{4}$/', $code);
        $seq = [0, 1, 2, 3, 4, 5, 6, 7];
        $this->assertSame('LRG-2345-6789', Code::generate(function () use (&$seq) { return array_shift($seq); }));
    }

    public function testCodesAreForgivingWhenTyped(): void
    {
        $this->assertSame('LRG-7KQ3-MZ9P', Code::normalize(' lrg 7kq3mz9p '));
        $this->assertSame('LRG-7KQ3-MZ9P', Code::normalize('LRG-7KQ3-MZ9P'));
        $this->assertNull(Code::normalize('LRG-7KQ0-MZ9P')); // 0 is never issued
        $this->assertNull(Code::normalize('WELCOME10'));
    }
}
