# RumahIjo — portal penyewa (pengembangan)

Laravel 13, Inertia dan React. Portal penyewa fase 2. Pembayaran QRIS statis memakai verifikasi manual; Midtrans dan portal pemilik belum disambungkan. Lakukan verifikasi operasional dan backup sebelum dipakai untuk penghuni sungguhan.

## Menjalankan secara lokal

Persyaratan: PHP 8.3+, Composer, Node.js dan npm.

```sh
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm run build
php artisan serve
```

Jika `.env` sudah ada, jangan menyalin ulang atau membuat APP_KEY baru: ini dapat membuat data terenkripsi yang sudah tersimpan tidak bisa dibaca. Buka alamat yang ditampilkan Artisan. Jalankan `npm run dev` di terminal lain saat mengubah frontend.

Akun dummy penyewa: `budi@rumahijo.test`, `siti@rumahijo.test`, `dimas@rumahijo.test`; password `Penyewa#2026`. Akun pemilik: `pemilik@rumahijo.test`, password `Pemilik#2026`. Seeder hanya bekerja pada environment local atau staging; jangan gunakan akun contoh di produksi.

## Yang tersedia untuk dicoba

Login dengan pembatasan peran dan wajib ganti password awal, policy kepemilikan data, audit perubahan tanpa isi NIK/password, biodata dengan kelengkapan dan NIK terenkripsi, KTP privat, foto profil dengan preview, tiket teks/gambar (maks. 3), status dibaca, notifikasi dalam aplikasi dan email melalui antrean. Avatar dan lampiran hanya dapat diakses akun yang berhak.

Buka Pembayaran, pilih tagihan, pindai atau unduh QRIS, masukkan nominal tepat dan kirim bukti. Gambar yang diberikan pemilik mencantumkan merchant **99FOODS**. Bukti tersimpan privat. Mengunggah bukti mengubah tagihan menjadi menunggu verifikasi; tidak otomatis lunas.

## Verifikasi QRIS sementara tanpa portal pemilik

Periksa dana yang benar-benar masuk pada aplikasi merchant dan cocokkan nominal serta referensi. Lihat ID pembayaran dari detail tagihan atau daftar berikut:

```sh
php artisan payments:pending
php artisan payments:review ID --approve --note="Dana dan referensi sudah cocok"
php artisan payments:review ID --reject --note="Jelaskan alasan penolakan"
```

Ganti `ID` dengan ID pembayaran. Jangan menyetujui hanya berdasarkan gambar bukti. Persetujuan menerbitkan kuitansi yang dapat dicetak / disimpan PDF oleh penyewa. Penolakan mengembalikan tagihan ke belum dibayar atau terlambat dan penyewa dapat mengirim ulang bukti.

## Tagihan otomatis dan notifikasi

Tagihan sewa hanya diterbitkan untuk kontrak **aktif** yang telah disepakati. Jadwal: terbit tujuh hari sebelum jatuh tempo, pengingat H-3, lalu penandaan terlambat. Kunci unik mencegah tagihan periode yang sama terbit dua kali. Kontrak pending tidak diterbitkan otomatis. Harga berasal dari `monthly_price` pada kontrak; token listrik tidak ditambahkan.

Jalankan proses lokal berikut di terminal terpisah saat menguji:

```sh
php artisan schedule:work
php artisan queue:work --tries=3
```

Perintah dapat diuji manual tanpa menunggu jadwal:

```sh
php artisan invoices:generate
php artisan invoices:mark-overdue
php artisan invoices:remind
php artisan schedule:list
```

Zona waktu aplikasi dan jadwal adalah Asia/Jakarta. Email development memakai `MAIL_MAILER=log` dan ditulis ke `storage/logs/laravel.log` setelah worker memproses antrean. Pengingat email dapat dinonaktifkan di Preferensi; notifikasi dalam aplikasi tetap tersedia. Notifikasi balasan tiket siap untuk pesan pemilik yang masuk lewat model `TicketMessage`; UI pemilik masih menyusul.

## Simulasi lokal

Buka detail tagihan dan bagian **Pengujian lokal: simulasi pembayaran**. Pilih berhasil, gagal, atau kedaluwarsa. Tidak ada uang yang berpindah. Transaksi dan kuitansi simulasi diberi label. Tidak tersedia di production, meskipun konfigurasi `PAYMENT_SIMULATION_ENABLED=true`. Untuk mematikan simulasi lokal, set nilai tersebut ke `false`.

## Pengujian dan sisa pekerjaan

Jalankan `php artisan test --compact` serta `npm run build`. Pengujian mencakup otorisasi, berkas privat, audit, password awal, foto profil, lampiran, pembatasan pesan, notifikasi, invoice tanpa duplikat, QRIS manual dan simulasi.

Midtrans/webhook gateway, portal pemilik, bahasa English dan reset password oleh pemilik masih menyusul. Hosting perlu scheduler, queue worker, SMTP, HTTPS, dan backup database/storage. Data contoh serta akun dummy tidak digunakan untuk produksi.

## TODO landing page: detail KamarIjoku

- [x] Klik area kartu KamarIjoku membuka pop-up di tengah halaman yang sama, tanpa pindah laman.
- [x] Galeri pop-up memiliki empat slide, panah, dan thumbnail.
- [ ] Tambahkan empat foto kost asli dari pemilik. Untuk sementara setiap slot diberi label foto belum tersedia.
- [x] Pop-up Isian memiliki pilihan Kasur, Kipas, Gantungan, Lemari, serta catatan permintaan lain; hasilnya masuk ke pesan kontak. Biaya dan ketersediaan tetap dikonfirmasi pengelola.
- [x] Kosongan tidak menampilkan fasilitas/furnitur bawaan. Full mencantumkan Kasur, Kipas, Gantungan, dan Lemari.
- [x] Tombol panah tetap terpisah: klik panah hanya scroll ke kontak dan mengisi pesan WhatsApp sesuai kategori, tanpa membuka pop-up.
- [x] Pop-up mendukung tombol tutup, klik backdrop, Escape, fokus keyboard, dan tampilan mobile.
- [ ] Lengkapi ketentuan air PDAM setelah pemilik memberikan informasinya.

## Ketentuan biaya dan TODO invoice

- Listrik menggunakan token pribadi dan dibayar terpisah dari biaya sewa. Informasi ini sudah tampil pada pop-up detail kamar.
- Ketentuan air PDAM masih menunggu informasi pemilik.
- [ ] Terbitkan invoice sewa setelah calon penghuni memastikan penyewaan dan pengelola mengonfirmasi rincian kamar, durasi, serta biaya. Membuka pop-up, memilih furnitur, atau menekan tombol pertanyaan WhatsApp belum menjadi konfirmasi sewa.
- [ ] Cantumkan biaya sewa dan rincian barang tambahan yang sudah disetujui pada invoice. Jangan memasukkan pembelian token listrik sebagai biaya sewa; listrik tetap dibayar terpisah.
- [x] Penerbitan sewa bulanan otomatis tersedia untuk kontrak aktif yang sudah disepakati. Invoice awal setelah pemesanan dan rincian barang tambahan masih mengikuti pengelola / portal pemilik yang menyusul.
