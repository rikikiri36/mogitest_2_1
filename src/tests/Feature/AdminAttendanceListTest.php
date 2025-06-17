<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;
use Carbon\Carbon;

class AdminAttendanceListTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        // 日本語ロケールにしておきたい場合
        app()->setLocale('ja');
    }

    // その日になされた全ユーザーの勤怠情報が正確に確認できる ------------
    public function test_admin_attendance_list_info_for_the_day()
    {

        /** @var \App\Models\User $admin */
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'admin');
    
        // 日付を固定
        $date = Carbon::create(2025, 6, 15)->toDateString();
    
        /** @var \App\Models\User $user1 */
        $user1 = User::factory()->create(['name' => '山田太郎']);
        /** @var \App\Models\User $user2 */
        $user2 = User::factory()->create(['name' => '佐藤花子']);
    
        // ユーザー1の勤怠データ
        Attendance::factory()->create([
            'user_id' => $user1->id,
            'type' => config('constants.type.work'),
            'processing_date' => $date,
            'processing_start_time' => '09:00:00',
            'processing_end_time' => '18:00:00',
        ]);
    
        // ユーザー2の勤怠データ
        Attendance::factory()->create([
            'user_id' => $user2->id,
            'type' => config('constants.type.work'),
            'processing_date' => $date,
            'processing_start_time' => '08:30:00',
            'processing_end_time' => '17:30:00',
        ]);
    
        // 管理者勤怠一覧画面
        $response = $this->get("/admin/attendance/list?ymd={$date}");
    
        $response->assertStatus(200);
    
        $response->assertSee('山田太郎');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('佐藤花子');
        $response->assertSee('08:30');
        $response->assertSee('17:30');
        $response->assertSee('詳細');
    }

    // 遷移した際に現在の日付が表示される ------------
    public function test_admin_attendance_list_shows_current_date()
    {
        /** @var \App\Models\User $admin */
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'admin');
    
        // 日付を固定
        $today = Carbon::create(2025, 6, 15);
        Carbon::setTestNow($today);
    
        $response = $this->get('/admin/attendance/list');    
        $response->assertStatus(200);
    
        $response->assertSee($today->format('Y/m/d'));
    }

    // 「前日」を押下した時に前の日の勤怠情報が表示される ------------
    public function test_admin_attendance_list_shows_previous_date()
    {
        /** @var \App\Models\User $admin */
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'admin');
    
        $today = Carbon::create(2025, 6, 15);
        Carbon::setTestNow($today);
    
        // 一度アクセスして当日を確認
        $response = $this->get('/admin/attendance/list');
        $response->assertStatus(200);
        $response->assertSee($today->format('Y/m/d'));
    
        // 「前日」リンクをクリック
        $responsePrev = $this->get('/admin/attendance/list?action=prev&ymd=' . $today->format('Y/m/d'));

        $expectedPrevDate = $today->copy()->subDay();
        $responsePrev->assertSee($expectedPrevDate->format('Y/m/d'));
    }

    // 「翌日」を押下した時に次の日の勤怠情報が表示される ------------
    public function test_admin_attendance_list_shows_next_date()
    {
        /** @var \App\Models\User $admin */
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'admin');
    
        $today = Carbon::create(2025, 6, 15);
        Carbon::setTestNow($today);
    
        // 「翌日」リンクをクリック
        $responseNext = $this->get('/admin/attendance/list?action=next&ymd=' . $today->format('Y/m/d'));
        $responseNext->assertStatus(200);
    
        $expectedNextDate = $today->copy()->addDay();
        $responseNext->assertSee($expectedNextDate->format('Y/m/d'));
    }

}