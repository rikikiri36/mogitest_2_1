<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AttendancesTableSeeder extends Seeder
{
    public function run()
    {
        // 必要に応じて初期化
        DB::table('attendances')->truncate();

        // 対象ユーザーを取得
        $users = User::whereIn('email', [
            'user1@test.com',
            'user2@test.com'
        ])->get();

        $mayBaseDate = '2025-05-01';

        for ($i = 0; $i < 5; $i++) {
            $date = date('Y-m-d', strtotime($mayBaseDate . " +{$i} days"));

            foreach ($users as $user) {
                // 勤務
                DB::table('attendances')->insert([
                    'user_id' => $user->id,
                    'type' => 'work',
                    'processing_date' => $date,
                    'processing_start_time' => '09:00:00',
                    'processing_end_time' => '18:00:00',
                    'request_status' => '',
                ]);

                // 休憩1件
                DB::table('attendances')->insert([
                    'user_id' => $user->id,
                    'type' => 'rest',
                    'processing_date' => $date,
                    'processing_start_time' => '12:00:00',
                    'processing_end_time' => '13:00:00',
                    'request_status' => '',
                ]);
            }
        }

        $juneBaseDate = '2025-06-01';

        // === 休憩なし  ===
        for ($i = 0; $i < 3; $i++) {
            $date = date('Y-m-d', strtotime($juneBaseDate . " +{$i} days"));

            foreach ($users as $user) {
                DB::table('attendances')->insert([
                    'user_id' => $user->id,
                    'type' => 'work',
                    'processing_date' => $date,
                    'processing_start_time' => '09:00:00',
                    'processing_end_time' => '18:00:00',
                    'request_status' => '',
                ]);
            }
        }

        // === 休憩1件  ===
        for ($i = 3; $i < 8; $i++) {
            $date = date('Y-m-d', strtotime($juneBaseDate . " +{$i} days"));

            foreach ($users as $user) {
                // 勤務
                DB::table('attendances')->insert([
                    'user_id' => $user->id,
                    'type' => 'work',
                    'processing_date' => $date,
                    'processing_start_time' => '09:00:00',
                    'processing_end_time' => '18:00:00',
                    'request_status' => 'approved',
                ]);

                // 休憩1件
                DB::table('attendances')->insert([
                    'user_id' => $user->id,
                    'type' => 'rest',
                    'processing_date' => $date,
                    'processing_start_time' => '12:00:00',
                    'processing_end_time' => '13:00:00',
                    'request_status' => '',
                ]);
            }
        }

        // === 休憩2件  ===
        for ($i = 8; $i < 10; $i++) {
            $date = date('Y-m-d', strtotime($juneBaseDate . " +{$i} days"));

            foreach ($users as $user) {
                // 勤務
                DB::table('attendances')->insert([
                    'user_id' => $user->id,
                    'type' => 'work',
                    'processing_date' => $date,
                    'processing_start_time' => '09:00:00',
                    'processing_end_time' => '18:00:00',
                    'request_status' => '',
                ]);

                // 休憩1件目
                DB::table('attendances')->insert([
                    'user_id' => $user->id,
                    'type' => 'rest',
                    'processing_date' => $date,
                    'processing_start_time' => '12:00:00',
                    'processing_end_time' => '12:30:00',
                    'request_status' => '',
                ]);

                // 休憩2件目
                DB::table('attendances')->insert([
                    'user_id' => $user->id,
                    'type' => 'rest',
                    'processing_date' => $date,
                    'processing_start_time' => '15:00:00',
                    'processing_end_time' => '15:15:00',
                    'request_status' => '',
                ]);
            }
        }
    }
}
