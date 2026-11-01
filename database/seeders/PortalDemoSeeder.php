<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PortalDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'staging')) {
            return;
        }
        $owner = DB::table('users')->where('email', 'pemilik@rumahijo.test')->value('id');
        if (! $owner) {
            return;
        }
        foreach ([['Jadwal kebersihan', 'Area bersama dibersihkan setiap Sabtu pagi. Terima kasih sudah menjaga kerapian.'], ['Informasi listrik', 'Matikan lampu dan perangkat listrik saat tidak digunakan.']] as [$title,$body]) {
            if (! DB::table('announcements')->where('author_id', $owner)->where('title', $title)->exists()) {
                DB::table('announcements')->insert(['author_id' => $owner, 'title' => $title, 'body' => $body, 'published_at' => now()->subDays(2), 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        foreach ([['budi@rumahijo.test', 'BUDI', 850000], ['siti@rumahijo.test', 'SITI', 1200000], ['dimas@rumahijo.test', 'DIMAS', 850000]] as [$email,$tag,$amount]) {
            $uid = DB::table('users')->where('email', $email)->value('id');
            $tenancy = DB::table('tenancies')->where('user_id', $uid)->first();
            if (! $tenancy) {
                continue;
            }
            $months = $tag === 'DIMAS' ? ['2026-11-01'] : ['2026-08-01', '2026-09-01', '2026-10-01', '2026-11-01'];
            foreach ($months as $month) {
                $status = ($tag === 'BUDI' && $month === '2026-11-01') || ($tag === 'DIMAS') ? 'unpaid' : (($tag === 'SITI' && $month === '2026-10-01') ? 'overdue' : ($tag === 'SITI' && $month === '2026-11-01' ? 'unpaid' : 'paid'));
                DB::table('invoices')->insertOrIgnore(['tenancy_id' => $tenancy->id, 'number' => 'RIJ-'.$tag.'-'.substr($month, 0, 7), 'type' => 'rent', 'period_start' => $month, 'period_end' => date('Y-m-t', strtotime($month)), 'amount' => $amount, 'due_date' => substr($month, 0, 8).'05', 'status' => $status, 'paid_at' => $status === 'paid' ? substr($month, 0, 8).'03 10:00:00' : null, 'created_at' => now(), 'updated_at' => now()]);
            }
            if ($tag === 'DIMAS') {
                DB::table('invoices')->insertOrIgnore(['tenancy_id' => $tenancy->id, 'number' => 'RIJ-DIMAS-DEPOSIT', 'type' => 'deposit', 'period_start' => '2026-11-01', 'period_end' => '2026-11-01', 'amount' => $amount, 'due_date' => '2026-11-01', 'status' => 'unpaid', 'created_at' => now(), 'updated_at' => now()]);
            }
            if ($tag !== 'DIMAS') {
                $subject = $tag === 'BUDI' ? 'Lampu kamar berkedip' : 'Pertanyaan tagihan Oktober';
                if (! DB::table('tickets')->where('user_id', $uid)->where('subject', $subject)->exists()) {
                    $id = DB::table('tickets')->insertGetId(['user_id' => $uid, 'subject' => $subject, 'category' => $tag === 'BUDI' ? 'Fasilitas' : 'Pembayaran', 'priority' => 'normal', 'status' => 'in_progress', 'last_message_at' => now()->subDay(), 'created_at' => now()->subDays(2), 'updated_at' => now()]);
                    DB::table('ticket_messages')->insert([['ticket_id' => $id, 'sender_id' => $uid, 'body' => $tag === 'BUDI' ? 'Halo Pak, lampu kamar saya berkedip. Mohon dibantu, terima kasih.' : 'Halo Pak, saya ingin bertanya tentang tagihan Oktober.', 'created_at' => now()->subDays(2), 'updated_at' => now()], ['ticket_id' => $id, 'sender_id' => $owner, 'body' => 'Halo, terima kasih sudah menghubungi. Saya akan bantu memeriksanya.', 'created_at' => now()->subDay(), 'updated_at' => now()]]);
                }
            }
        }
    }
}
