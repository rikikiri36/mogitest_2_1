<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;
use Carbon\Carbon;

class AdminUserListTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('ja');
    }

    // 管理者ユーザーが全一般ユーザーの「氏名」「メールアドレス」を確認できる ------------
    public function test_admin_can_see_all_staff_names_and_emails()
    {
        /** @var \App\Models\User $admin */
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'admin');
    
        // 一般ユーザーを複数作成
        $users = User::factory()->count(3)->create();
    
        $response = $this->get('/admin/staff/list');
        $response->assertStatus(200);
    
        foreach ($users as $user) {
            $response->assertSee($user->name);
            $response->assertSee($user->email);
        }
    }

    // ユーザーの勤怠情報が正しく表示される ------------
    public function test_admin_can_see_selected_staff_attendance_correctly()
    {
        /** @var \App\Models\User $admin */
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'admin');
    
        $user = User::factory()->create();
    
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => config('constants.type.work'),
            'processing_date' => now()->startOfMonth()->toDateString(),
            'processing_start_time' => '09:00:00',
            'processing_end_time' => '18:00:00',
        ]);
    
        // 4️⃣ スタッフ別勤怠一覧画面
        $Ym = now()->format('Y/m');
        $response = $this->get("/admin/attendance/staff/{$user->id}?Ym={$Ym}");
        $response->assertStatus(200);
    
        $response->assertSee($user->name);
        $response->assertSee('09:00');
        $response->assertSee('18:00');
    
        $response->assertSee('/admin/attendance/' . $attendance->id);
    }

    // 「前月」を押下した時に表示月の前月の情報が表示される ------------
    public function test_staff_attendance_list_shows_previous_month()
    {
        /** @var \App\Models\User $admin */
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'admin');
    
        $user = User::factory()->create();
    
        // 前月の勤怠データを作成
        $prevMonth = now()->subMonth()->format('Y/m');
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => config('constants.type.work'),
            'processing_date' => now()->subMonth()->startOfMonth()->toDateString(),
            'processing_start_time' => '09:00:00',
            'processing_end_time' => '18:00:00',
        ]);
    
        // スタッフ勤怠一覧の前月を指定
        $response = $this->get("/admin/attendance/staff/{$user->id}?action=prev&Ym=" . now()->format('Y/m'));
    
        $response->assertStatus(200);
        $response->assertSee($prevMonth);
    
        $response->assertSee('09:00');
        $response->assertSee('18:00');
    }

    // 「翌月」を押下した時に表示月の前月の情報が表示される ------------
    public function test_staff_attendance_list_shows_next_month()
    {
        /** @var \App\Models\User $admin */
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'admin');
    
        $user = User::factory()->create();
    
        // 翌月の勤怠データを作成
        $nextMonth = now()->addMonth()->format('Y/m');
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => config('constants.type.work'),
            'processing_date' => now()->addMonth()->startOfMonth()->toDateString(),
            'processing_start_time' => '09:00:00',
            'processing_end_time' => '18:00:00',
        ]);
    
        // スタッフ勤怠一覧の翌月を指定
        $response = $this->get("/admin/attendance/staff/{$user->id}?action=next&Ym=" . now()->format('Y/m'));
    
        $response->assertStatus(200);
        $response->assertSee($nextMonth);
    
        $response->assertSee('09:00');
        $response->assertSee('18:00');
    }

    // 「詳細」を押下すると、その日の勤怠詳細画面に遷移する ------------
    public function test_staff_attendance_list_detail_link_navigates_to_detail_page()
    {
        /** @var \App\Models\User $admin */
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'admin');
    
        $user = User::factory()->create();
    
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => config('constants.type.work'),
            'processing_date' => now()->toDateString(),
            'processing_start_time' => '09:00:00',
            'processing_end_time' => '18:00:00',
        ]);
    
        // スタッフ別勤怠一覧
        $response = $this->get("/admin/attendance/staff/{$user->id}");
    
        $response->assertStatus(200);
    
        // 「詳細」リンクが存在することを確認
        $response->assertSee("/admin/attendance/{$attendance->id}");
    
        // 「詳細」リンクへアクセスして詳細画面が表示されることを確認
        $detailResponse = $this->get("/admin/attendance/{$attendance->id}");

        $detailResponse->assertStatus(200);
        $detailResponse->assertSee($user->name);
        $detailResponse->assertSee('09:00');
        $detailResponse->assertSee('18:00');
    }

}