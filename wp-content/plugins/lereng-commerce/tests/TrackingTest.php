<?php

namespace Lereng\Tests;

use Lereng\Fulfilment\Tracking;
use PHPUnit\Framework\TestCase;

final class TrackingTest extends TestCase
{
    public function testCourierFromTheShippingLabel(): void
    {
        $this->assertSame('sicepat', Tracking::courierFor('SiCepat REG · 1–2 days'));
        $this->assertSame('gosend', Tracking::courierFor('GoSend Same Day · today'));
        $this->assertSame('jne', Tracking::courierFor('JNE YES · next day'));
    }

    public function testWaybillsLookLikeTheCouriersAndAreStable(): void
    {
        $this->assertMatchesRegularExpression('/^0040\d{8}$/', Tracking::number('sicepat', 1042));
        $this->assertMatchesRegularExpression('/^CGK01042\d{8}$/', Tracking::number('jne', 1042));
        $this->assertSame(Tracking::number('jne', 1042), Tracking::number('jne', 1042));
        $this->assertNotSame(Tracking::number('jne', 1042), Tracking::number('jne', 1043));
    }

    public function testCheckpointsAccumulateUntilDelivered(): void
    {
        $shipped = 1_800_000_000;
        $none = Tracking::checkpoints('jne', 'X1', 'JB', 'Bandung', $shipped, [2, 3], $shipped - 1);
        $this->assertSame([], $none);
        $first = Tracking::checkpoints('jne', 'X1', 'JB', 'Bandung', $shipped, [2, 3], $shipped + 60);
        $this->assertSame('Shipment received by courier', $first[0]['text']);
        $later = Tracking::checkpoints('jne', 'X1', 'JB', 'Bandung', $shipped, [2, 3], $shipped + 30 * 3600);
        $this->assertSame('Surabaya', $later[2]['place']);
        $this->assertFalse(Tracking::delivered($later));
        $done = Tracking::checkpoints('jne', 'X1', 'JB', 'Bandung', $shipped, [2, 3], $shipped + 5 * 86400);
        $this->assertTrue(Tracking::delivered($done));
        $this->assertSame('Bandung', end($done)['place']);
    }

    public function testBaliParcelsSkipTheTransitHubAndGoSendTakesMinutes(): void
    {
        $bali = Tracking::checkpoints('sicepat', 'X2', 'BA', 'Ubud', 0, [1, 2], 10 * 86400);
        $this->assertNotContains('Arrived at transit hub', array_column($bali, 'text'));
        $go = Tracking::checkpoints('gosend', 'GK-1', 'BA', 'Canggu', 0, [0, 0], 2 * 3600);
        $this->assertTrue(Tracking::delivered($go));
    }
}
