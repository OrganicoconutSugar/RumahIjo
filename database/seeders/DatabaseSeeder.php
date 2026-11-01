<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'staging')) {
            return;
        }
        foreach ([['Standar', 850000], ['Deluxe', 1200000], ['Premium', 1600000]] as [$name,$price]) {
            DB::table('room_types')->updateOrInsert(['name' => $name], ['price_monthly' => $price, 'description' => 'Kamar '.$name, 'facilities' => json_encode(['WiFi', 'Kamar mandi dalam']), 'created_at' => now(), 'updated_at' => now()]);
        }
        for ($i = 101; $i <= 112; $i++) {
            DB::table('rooms')->updateOrInsert(['number' => (string) $i], ['room_type_id' => ($i === 102 ? 2 : ($i > 108 ? 3 : 1)), 'floor' => $i <= 106 ? 1 : 2, 'status' => $i === 103 ? 'reserved' : ($i <= 102 ? 'occupied' : 'available'), 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach ([['Pak Ijo', 'pemilik@rumahijo.test', 'Pemilik#2026', 'owner'], ['Budi Santoso', 'budi@rumahijo.test', 'Penyewa#2026', 'tenant'], ['Siti Rahmawati', 'siti@rumahijo.test', 'Penyewa#2026', 'tenant'], ['Dimas Pratama', 'dimas@rumahijo.test', 'Penyewa#2026', 'tenant']] as [$name,$email,$password,$role]) {
            DB::table('users')->updateOrInsert(['email' => $email], ['name' => $name, 'password' => Hash::make($password), 'role' => $role, 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach ([['budi@rumahijo.test', 101, 850000, 'active'], ['siti@rumahijo.test', 102, 1200000, 'active'], ['dimas@rumahijo.test', 103, 850000, 'pending']] as [$email,$room,$price,$status]) {
            $uid = DB::table('users')->where('email', $email)->value('id');
            $rid = DB::table('rooms')->where('number', $room)->value('id');
            DB::table('tenancies')->updateOrInsert(['user_id' => $uid, 'room_id' => $rid], ['start_date' => $status === 'pending' ? '2026-11-01' : '2026-08-01', 'end_date' => '2027-07-31', 'due_day' => 5, 'monthly_price' => $price, 'deposit_amount' => $price, 'status' => $status, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->call(PortalDemoSeeder::class);
    }
}
