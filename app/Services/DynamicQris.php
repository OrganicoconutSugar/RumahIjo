<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Mengubah payload QRIS statis (EMVCo MPM) menjadi payload dengan nominal tertanam.
 *
 * Perubahan yang dilakukan: tag 01 menjadi "12" (dinamis), tag 54 diisi nominal,
 * lalu CRC16 pada tag 63 dihitung ulang. Tag lain dibiarkan apa adanya.
 */
class DynamicQris
{
    /**
     * @throws InvalidArgumentException jika payload bukan QRIS statis yang valid atau nominal tidak positif
     */
    public static function forAmount(string $staticPayload, int $amount): string
    {
        if ($amount < 1) {
            throw new InvalidArgumentException('Nominal QRIS harus lebih besar dari nol.');
        }

        $fields = self::parse(trim($staticPayload));
        $fields = array_values(array_filter($fields, fn (array $field) => ! in_array($field[0], ['01', '54', '63'], true)));
        $fields[] = ['01', '12'];
        $fields[] = ['54', (string) $amount];
        usort($fields, fn (array $a, array $b) => $a[0] === '00' ? -1 : ($b[0] === '00' ? 1 : strcmp($a[0], $b[0])));

        $body = '';
        foreach ($fields as [$tag, $value]) {
            $body .= $tag.str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT).$value;
        }
        $body .= '6304';

        return $body.self::crc16($body);
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private static function parse(string $payload): array
    {
        if (! str_starts_with($payload, '000201') || ! preg_match('/6304([0-9A-F]{4})$/', $payload, $match)) {
            throw new InvalidArgumentException('Payload QRIS tidak dikenali.');
        }
        if (self::crc16(substr($payload, 0, -4)) !== $match[1]) {
            throw new InvalidArgumentException('CRC payload QRIS tidak cocok.');
        }

        $fields = [];
        $body = substr($payload, 0, -8);
        $offset = 0;
        while ($offset < strlen($body)) {
            if ($offset + 4 > strlen($body) || ! ctype_digit(substr($body, $offset, 4))) {
                throw new InvalidArgumentException('Struktur payload QRIS rusak.');
            }
            $tag = substr($body, $offset, 2);
            $length = (int) substr($body, $offset + 2, 2);
            if ($offset + 4 + $length > strlen($body)) {
                throw new InvalidArgumentException('Struktur payload QRIS rusak.');
            }
            $fields[] = [$tag, substr($body, $offset + 4, $length)];
            $offset += 4 + $length;
        }

        if (! in_array(['01', '11'], $fields, true) && ! in_array(['01', '12'], $fields, true)) {
            throw new InvalidArgumentException('Payload QRIS tidak memiliki jenis inisiasi yang valid.');
        }

        return $fields;
    }

    private static function crc16(string $data): string
    {
        $crc = 0xFFFF;
        foreach (str_split($data) as $char) {
            $crc ^= ord($char) << 8;
            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}
