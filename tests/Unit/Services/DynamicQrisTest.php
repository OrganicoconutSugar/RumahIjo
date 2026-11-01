<?php

namespace Tests\Unit\Services;

use App\Services\DynamicQris;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DynamicQrisTest extends TestCase
{
    private const STATIC_PAYLOAD = '00020101021126580013ID.CO.BRI.WWW01189360000200701256220208701256220303UMI51440014ID.CO.QRIS.WWW0215ID10264791656500303UMI5204581453033605802ID590799Foods6009PALEMBANG61053012162070703A0163045663';

    public function test_embeds_amount_marks_qr_as_dynamic_and_recalculates_crc(): void
    {
        $this->assertSame(
            '00020101021226580013ID.CO.BRI.WWW01189360000200701256220208701256220303UMI51440014ID.CO.QRIS.WWW0215ID10264791656500303UMI5204581453033605406850000'
            .'5802ID590799Foods6009PALEMBANG61053012162070703A0163041026',
            DynamicQris::forAmount(self::STATIC_PAYLOAD, 850000),
        );
    }

    public function test_encodes_the_length_of_longer_amounts(): void
    {
        $payload = DynamicQris::forAmount(self::STATIC_PAYLOAD, 1200000);

        $this->assertStringContainsString('540712000005802ID', $payload);
        $this->assertStringEndsWith('630445AB', $payload);
    }

    public function test_replaces_an_amount_that_is_already_present(): void
    {
        $once = DynamicQris::forAmount(self::STATIC_PAYLOAD, 850000);

        $this->assertSame(DynamicQris::forAmount(self::STATIC_PAYLOAD, 1200000), DynamicQris::forAmount($once, 1200000));
    }

    public function test_accepts_payload_with_surrounding_whitespace(): void
    {
        $this->assertSame(DynamicQris::forAmount(self::STATIC_PAYLOAD, 850000), DynamicQris::forAmount(self::STATIC_PAYLOAD."\n", 850000));
    }

    #[DataProvider('invalidAmounts')]
    public function test_rejects_amounts_below_one(int $amount): void
    {
        $this->expectException(InvalidArgumentException::class);

        DynamicQris::forAmount(self::STATIC_PAYLOAD, $amount);
    }

    public static function invalidAmounts(): array
    {
        return ['zero' => [0], 'negative' => [-5000]];
    }

    #[DataProvider('invalidPayloads')]
    public function test_rejects_payloads_that_are_not_valid_static_qris(string $payload): void
    {
        $this->expectException(InvalidArgumentException::class);

        DynamicQris::forAmount($payload, 850000);
    }

    public static function invalidPayloads(): array
    {
        return [
            'empty' => [''],
            'plain text' => ['https://example.com'],
            'tampered body, crc no longer matches' => [str_replace('Foods', 'Fooda', self::STATIC_PAYLOAD)],
            'wrong crc' => [substr(self::STATIC_PAYLOAD, 0, -4).'0000'],
        ];
    }
}
