<?php

namespace Lereng\Fulfilment;

/**
 * Courier tracking. A real store would read checkpoints from the courier or an aggregator
 * (Biteship, Shipper, RajaOngkir's waybill API); here they are generated deterministically from
 * the tracking number, so a parcel tells the same story every time it is looked up. Pure.
 */
final class Tracking
{
    public const COURIERS = [
        'jne' => ['JNE', 'https://www.jne.co.id/tracking-package?awb=%s'],
        'sicepat' => ['SiCepat', 'https://www.sicepat.com/checkAwb?awb=%s'],
        'gosend' => ['GoSend', ''],
    ];

    /** Courier for a shipping method label like "SiCepat REG · 1–2 days". */
    public static function courierFor(string $method): string
    {
        $m = strtolower($method);
        return str_contains($m, 'gosend') ? 'gosend' : (str_contains($m, 'sicepat') ? 'sicepat' : 'jne');
    }

    /** A plausible waybill: JNE 16 digits, SiCepat 12 starting 00, GoSend GK-xxxxx. */
    public static function number(string $courier, int $orderId): string
    {
        $h = substr(hash('crc32b', 'lereng' . $orderId), 0, 8);
        $digits = str_pad((string) (hexdec($h) % 100000000), 8, '0', STR_PAD_LEFT);
        return match ($courier) {
            'sicepat' => '0040' . $digits,
            'gosend' => 'GK-' . strtoupper(substr($h, 0, 5)) . $orderId,
            default => 'CGK' . str_pad((string) $orderId, 5, '0', STR_PAD_LEFT) . $digits,
        };
    }

    /**
     * Checkpoints from the moment it shipped until now (or until delivery).
     * @return list<array{time:int,text:string,place:string}>
     */
    public static function checkpoints(string $courier, string $number, string $province, string $city, int $shippedAt, array $etaDays, int $now): array
    {
        $hub = ['bali' => 'Denpasar', 'java' => 'Surabaya', 'nusa' => 'Mataram', 'sumatra' => 'Jakarta', 'kalimantan' => 'Surabaya', 'sulawesi' => 'Makassar', 'east' => 'Makassar'][\Lereng\Shipping\RateTable::region($province) ?? 'java'] ?? 'Surabaya';
        $city = $city ?: 'destination';
        if ($courier === 'gosend') {
            $steps = [[0, 'Driver picked up the parcel', 'Lereng Roastery, Kerobokan'], [35, 'On the way', 'Badung'], [80, 'Delivered', $city]];
            $unit = 60;
        } else {
            $max = max(1, $etaDays[1] ?? 2);
            $steps = [
                [0, 'Shipment received by courier', 'Denpasar'],
                [6, 'Departed sorting centre', 'Denpasar'],
                [20, sprintf('Arrived at transit hub', $hub), $hub],
                [max(26, $max * 24 - 18), 'Out for delivery', $city],
                [max(30, $max * 24 - 12), 'Delivered', $city],
            ];
            if ($hub === 'Denpasar') {
                array_splice($steps, 2, 1);
            }
            $unit = 3600;
        }
        // Small per-parcel jitter, so not every parcel moves at the same minute.
        $jitter = hexdec(substr(hash('crc32b', $number), 0, 2)) % 40;
        $out = [];
        foreach ($steps as [$offset, $text, $place]) {
            $t = $shippedAt + $offset * $unit + ($offset ? $jitter * 60 : 0);
            if ($t <= $now) {
                $out[] = ['time' => $t, 'text' => $text, 'place' => $place];
            }
        }
        return $out;
    }

    public static function delivered(array $checkpoints): bool
    {
        return $checkpoints && end($checkpoints)['text'] === 'Delivered';
    }

    public static function url(string $courier, string $number): string
    {
        $tpl = self::COURIERS[$courier][1] ?? '';
        return $tpl ? sprintf($tpl, rawurlencode($number)) : '';
    }
}
