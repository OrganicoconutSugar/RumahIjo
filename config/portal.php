<?php

return [
    'qris_image' => '/payments/qris-rumahijo.jpeg',
    'qris_merchant' => '99FOODS',
    /*
     * Payload teks QRIS statis (hasil pindai QR, diawali 000201). Jika diisi, halaman
     * pembayaran menampilkan QR dengan nominal tagihan tertanam; jika kosong, gambar statis dipakai.
     */
    'qris_payload' => env('QRIS_STATIC_PAYLOAD'),
    'simulation_enabled' => env('PAYMENT_SIMULATION_ENABLED', true),
];
