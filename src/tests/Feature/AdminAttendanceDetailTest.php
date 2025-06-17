<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;
use Carbon\Carbon;

class AdminAttendanceDetailTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        // 日本語ロケールにしておきたい場合
        app()->setLocale('ja');
    }

    // 勤怠詳細画面に表示されるデータが選択したものになっている ------------
    public function test_admin_attendance_detail_shows_correct_data()
    {

        /** @var \App\Models\User $admin */
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'admin');
    
        // 一般ユーザーとその勤怠データを準備
        /** @var \App\Models\User $user */
        $user = User::factory()->create(['name' => '一般太郎']);
    
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => config('constants.type.work'),
            'processing_date' => now()->toDateString(),
            'processing_start_time' => '09:00:00',
            'processing_end_time' => '18:00:00',
        ]);
    
        Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => config('constants.type.rest'),
            'processing_date' => $attendance->processing_date,
            'processing_start_time' => '12:00:00',
            'processing_end_time' => '12:30:00',
        ]);
    
        // 勤怠詳細画面
        $response = $this->get("/admin/attendance/{$attendance->id}");
        $response->assertStatus(200);
    
        $response->assertSee($user->name);
        $response->assertSee($attendance->processing_date);
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('12:00');
        $response->assertSee('12:30');
    }

    // 出勤時間が退勤時間より後になっている場合、エラーメッセージが表示される ------------
    public function test_admin_attendance_update_fails_if_clock_in_after_clock_out()
    {
        /** @var \App\Models\User $admin */
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'admin');
    
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => config('constants.type.work'),
            'processing_date' => now()->toDateString(),
            'processing_start_time' => '09:00:00',
            'processing_end_time' => '18:00:00',
        ]);
    
        // 出勤時間を退勤時間より後にする
        $response = $this->from("/admin/attendance/{$attendance->id}")
            ->post('/admin/attendance/attendanceupdateStore', [
                'id' => $attendance->id,
                'processing_date' => $attendance->processing_date,
                'work_processing_start_time' => '20:00',
                'work_processing_end_time' => '18:00',
                'comment' => 'テスト修正',
            ]);
    
        $response->assertRedirect("/admin/attendance/{$attendance->id}");
        $response->assertSessionHasErrors(['work_processing_end_time']);
    
        $this->assertEquals(
            '出勤時間もしくは退勤時間が不適切な値です',
            session('errors')->first('work_processing_end_time')
        );
    }

    // 休憩開始時間が退勤時間より後になっている場合、エラーメッセージが表示される ------------
    public function test_admin_attendance_update_fails_if_rest_start_after_clock_out()
    {
        /** @var \App\Models\User $admin */
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'admin');
    
        // 対象ユーザーと勤怠データを準備
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => config('constants.type.work'),
            'processing_date' => now()->toDateString(),
            'processing_start_time' => '09:00:00',
            'processing_end_time' => '18:00:00',
        ]);
    
        // 3️⃣ 修正で休憩開始時間を退勤後に設定
        $response = $this->from("/admin/attendance/{$attendance->id}")
            ->post('/admin/attendance/attendanceupdateStore', [
                'id' => $attendance->id,
                'processing_date' => $attendance->processing_date,
                'work_processing_start_time' => '09:00',
                'work_processing_end_time' => '18:00',
                'rest_processing_start_time' => ['19:00'], // 退勤後
                'rest_processing_end_time' => ['19:30'],
                'comment' => 'テスト修正',
            ]);
    
        $response->assertRedirect("/admin/attendance/{$attendance->id}");
        $response->assertSessionHasErrors(['rest_processing_start_time.0']);
    
        $this->assertEquals(
            '休憩時間が勤務時間外です',     // テストケースでは「出勤時間もしくは退勤時間が不適切な値です」となっているが、機能要件（44行目）に合わせる
            session('errors')->first('rest_processing_start_time.0')
        );
    }

    // 休憩終了時間が退勤時間より後になっている場合、エラーメッセージが表示される ------------
    public function test_admin_attendance_update_fails_if_rest_end_after_clock_out()
    {
        // 1️⃣ 管理者を作成してログイン
        /** @var \App\Models\User $admin */
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'admin');
    
        // 2️⃣ 対象ユーザーと勤怠データを準備
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => config('constants.type.work'),
            'processing_date' => now()->toDateString(),
            'processing_start_time' => '09:00:00',
            'processing_end_time' => '18:00:00',
        ]);
    
        // 3️⃣ 修正で休憩終了時間を退勤後に設定
        $response = $this->from("/admin/attendance/{$attendance->id}")
            ->post('/admin/attendance/attendanceupdateStore', [
                'id' => $attendance->id,
                'processing_date' => $attendance->processing_date,
                'work_processing_start_time' => '09:00',
                'work_processing_end_time' => '18:00',
                'rest_processing_start_time' => ['17:00'], // 正常範囲
                'rest_processing_end_time' => ['19:00'],   // 退勤後 → NG
                'comment' => 'テスト修正',
            ]);
    
        // 4️⃣ バリデーションエラーでリダイレクト
        $response->assertRedirect("/admin/attendance/{$attendance->id}");
    
        // 5️⃣ エラーメッセージを検証
        $response->assertSessionHasErrors(['rest_processing_end_time.0']);
    
        $this->assertEquals(
            '休憩時間が勤務時間外です',     // テストケースでは「出勤時間もしくは退勤時間が不適切な値です」となっているが、機能要件（44行目）に合わせる
            session('errors')->first('rest_processing_end_time.0')
        );
    }

    // 備考欄が未入力の場合のエラーメッセージが表示される ------------
    public function test_admin_attendance_update_fails_if_comment_is_empty()
    {
        /** @var \App\Models\User $admin */
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'admin');
    
        // 一般ユーザーと勤怠データを作成
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => config('constants.type.work'),
            'processing_date' => now()->toDateString(),
            'processing_start_time' => '09:00:00',
            'processing_end_time' => '18:00:00',
        ]);
    
        // 備考を未入力
        $response = $this->from("/admin/attendance/{$attendance->id}")
            ->post('/admin/attendance/attendanceupdateStore', [
                'id' => $attendance->id,
                'processing_date' => $attendance->processing_date,
                'work_processing_start_time' => '09:00',
                'work_processing_end_time' => '18:00',
                'rest_processing_start_time' => ['12:00'],
                'rest_processing_end_time' => ['12:30'],
                'comment' => '', // 未入力
            ]);
    
        $response->assertRedirect("/admin/attendance/{$attendance->id}");
        $response->assertSessionHasErrors(['comment']);
    
        $this->assertEquals(
            '備考を入力してください',
            session('errors')->first('comment')
        );
    }
}