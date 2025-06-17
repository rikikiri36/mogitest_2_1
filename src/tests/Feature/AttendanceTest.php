<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;
use Carbon\Carbon;

class AttendanceTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        // 日本語ロケールにしておきたい場合
        app()->setLocale('ja');
    }

    // 現在の日時情報がUIと同じ形式で出力されている ----------------
    public function test_attendance_view_shows_current_date_and_time()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        // テスト時刻を固定
        $fixed = Carbon::create(2025, 6, 14, 8, 5, 0, 'Asia/Tokyo');
        Carbon::setTestNow($fixed);

        $response = $this->get('/attendance');
        $response->assertStatus(200);

        $expectedDate = $fixed->format('Y年n月j日');
        $weekMap = ['日', '月', '火', '水', '木', '金', '土'];
        $expectedWeekday = $weekMap[$fixed->dayOfWeek]; // 0=日～6=土
        $expectedDateWithWeek = "{$expectedDate}（{$expectedWeekday}）";
        $expectedTime = $fixed->format('H:i');

        $response->assertSee($expectedDateWithWeek, false);
        $response->assertSee($expectedTime, false);

        Carbon::setTestNow();

    }

    // 勤務外の場合、勤怠ステータスが正しく表示される ----------------
    public function test_status_off_duty()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        // Attendance が無い
        Attendance::where('user_id', $user->id)->delete();

        $response = $this->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('勤務外');
    }

    // 出勤中の場合、勤怠ステータスが正しく表示される ----------------
    public function test_status_on_duty()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        // type=work, 開始のみ、終了なし
        Attendance::factory()->onDuty()->create([
            'user_id' => $user->id,
            'processing_date' => Carbon::today()->toDateString(),
        ]);

        $response = $this->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('出勤中');
    }

    // 休憩中の場合、勤怠ステータスが正しく表示される ----------------
    public function test_status_on_break()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        // workを作る
        Attendance::factory()->onDuty()->create([
            'user_id' => $user->id,
            'processing_date' => Carbon::today()->toDateString(),
        ]);

        // restを作る
        Attendance::factory()->onBreak()->create([
            'user_id' => $user->id,
            'processing_date' => Carbon::today()->toDateString(),
        ]);

        $response = $this->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('休憩中');
    }

    // 退勤済の場合、勤怠ステータスが正しく表示される ----------------
    public function test_status_clocked_out()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        // type=work, 開始と終了あり
        Attendance::factory()->clockedOut()->create([
            'user_id' => $user->id,
            'processing_date' => Carbon::today()->toDateString(),
        ]);

        $response = $this->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('退勤済');
    }

    // 出勤ボタンが正しく機能する ----------------
    public function test_changes_duty()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        // 勤務外
        Attendance::where('user_id', $user->id)
            ->where('processing_date', Carbon::today()->toDateString())
            ->delete();

        // 勤怠画面に出勤ボタンがあることを確認
        $response = $this->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('出勤');

        //出勤処理
        $postResponse = $this->post('/attendance/store', [
            'action' => 'clock_in',
        ]);

        // 出勤処理後にリダイレクト
        $postResponse->assertRedirect('/attendance');

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'type' => config('constants.type.work'),
            'processing_date' => Carbon::today()->toDateString(),
        ]);

        // 勤怠画面のステータスが「出勤中」
        $afterResponse = $this->get('/attendance');
        $afterResponse->assertStatus(200);
        $afterResponse->assertSee('出勤中');
    }
    
    // 出勤は一日一回のみできる ----------------
    public function test_clocked_out()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        // 退勤済
        Attendance::factory()->clockedOut()->create([
            'user_id' => $user->id,
            'processing_date' => Carbon::today()->toDateString(),
        ]);

        $response = $this->get('/attendance');
        $response->assertStatus(200);

        // 「出勤」ボタンが表示されない
        $response->assertDontSee('出勤');
    }

    // 出勤時刻が管理画面で確認できる ----------------
    public function test_clock_in_time_visible()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        // 勤務外
        Attendance::where('user_id', $user->id)
            ->where('processing_date', Carbon::today()->toDateString())
            ->delete();

        // 出勤時刻を固定
        $fixedTime = Carbon::create(2025, 6, 15, 9, 30, 0);
        Carbon::setTestNow($fixedTime);

        // 出勤処理
        $postResponse = $this->post('/attendance/store', [
            'action' => 'clock_in',
        ]);
        $postResponse->assertRedirect('/attendance');

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'processing_date' => $fixedTime->toDateString(),
            'processing_start_time' => $fixedTime->format('H:i:s'),
        ]);

        // 管理画面：勤怠一覧
        /** @var \App\Models\User $admin */
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'admin');

        $adminResponse = $this->get('/admin/attendance/list');
        $adminResponse->assertStatus(200);

        // 固定した時刻が表示されているか
        $adminResponse->assertSee($fixedTime->format('H:i'));
    }

    // 休憩ボタンが正しく機能する ----------------
    public function test_rest_button()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        Attendance::factory()->onDuty()->create([
            'user_id' => $user->id,
            'processing_date' => Carbon::today()->toDateString(),
        ]);

        // 休憩入ボタン
        $response = $this->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('休憩入');

        // 休憩開始処理
        $postResponse = $this->post('/attendance/store', [
            'action' => 'break_start',
        ]);
        $postResponse->assertRedirect('/attendance');

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'type' => config('constants.type.rest'),
            'processing_date' => Carbon::today()->toDateString(),
        ]);

        // ステータスが「休憩中」
        $afterResponse = $this->get('/attendance');
        $afterResponse->assertStatus(200);
        $afterResponse->assertSee('休憩中');
    }

    // 休憩は一日に何回でもできる ----------------
    public function test_multiple_rests_in_a_day()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        Attendance::factory()->onDuty()->create([
            'user_id' => $user->id,
            'processing_date' => Carbon::today()->toDateString(),
        ]);

        $this->post('/attendance/store', [
            'action' => 'break_start',
        ])->assertRedirect('/attendance');

        $this->post('/attendance/store', [
            'action' => 'break_end',
        ])->assertRedirect('/attendance');

        $response = $this->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('休憩入');
    }

    // 休憩戻ボタンが正しく機能する ----------------
    public function test_rest_end()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        Attendance::factory()->onDuty()->create([
            'user_id' => $user->id,
            'processing_date' => Carbon::today()->toDateString(),
        ]);

        $this->post('/attendance/store', [
            'action' => 'break_start',
        ])->assertRedirect('/attendance');

        // 休憩戻ボタンの表示
        $response = $this->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('休憩戻');

        $this->post('/attendance/store', [
            'action' => 'break_end',
        ])->assertRedirect('/attendance');

        // ステータスが「出勤中」
        $afterResponse = $this->get('/attendance');
        $afterResponse->assertStatus(200);
        $afterResponse->assertSee('出勤中');
    }

    // 休憩戻は一日に何回でもできる ----------------
    public function test_rest_ends_allowed_in_a_day()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        Attendance::factory()->onDuty()->create([
            'user_id' => $user->id,
            'processing_date' => Carbon::today()->toDateString(),
        ]);

        // 1回目
        $this->post('/attendance/store', [
            'action' => 'break_start',
        ])->assertRedirect('/attendance');

        $this->post('/attendance/store', [
            'action' => 'break_end',
        ])->assertRedirect('/attendance');

        // 2回目
        $this->post('/attendance/store', [
            'action' => 'break_start',
        ])->assertRedirect('/attendance');

        $response = $this->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('休憩戻');
    }

    // 休憩時刻が勤怠一覧画面で確認できる ----------------
    public function test_rest_time_is_visible()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        Attendance::factory()->onDuty()->create([
            'user_id' => $user->id,
            'processing_date' => Carbon::today()->toDateString(),
        ]);

        // 休憩開始時間を固定
        $restStart = Carbon::create(2025, 6, 15, 12, 0, 0);
        $restEnd = Carbon::create(2025, 6, 15, 12, 30, 0);

        Carbon::setTestNow($restStart);
        $this->post('/attendance/store', [
            'action' => 'break_start',
        ])->assertRedirect('/attendance');

        Carbon::setTestNow($restEnd);
        $this->post('/attendance/store', [
            'action' => 'break_end',
        ])->assertRedirect('/attendance');

        $response = $this->get('/attendance/list');
        $response->assertStatus(200);

        // 日付と休憩合計時間が含まれているか
        $ymd = Carbon::parse($restStart)->format('m/d');
        
        // 休憩合計時間
        $restTotal = '0:30';

        $response->assertSee($ymd);
        $response->assertSee($restTotal);
    }

    // 退勤ボタンが正しく機能する ----------------
    public function test_clock_out_button()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        Attendance::factory()->onDuty()->create([
            'user_id' => $user->id,
            'processing_date' => Carbon::today()->toDateString(),
        ]);

        // 勤怠画面に「退勤」ボタン
        $response = $this->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('退勤');

        $postResponse = $this->post('/attendance/store', [
            'action' => 'clock_out',
        ]);
        $postResponse->assertRedirect('/attendance');

        // ステータスが「退勤済」
        $afterResponse = $this->get('/attendance');
        $afterResponse->assertStatus(200);
        $afterResponse->assertSee('退勤済');
    }

    // 退勤時刻が管理画面で確認できる ----------------
    public function test_clock_out_time_is_visible()
    {
        /** @var \App\Models\User $admin */
        $admin = User::factory()->admin()->create();

        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        // 出勤と退勤の時刻を固定
        $clockInTime = Carbon::create(2025, 6, 15, 9, 0, 0);
        $clockOutTime = Carbon::create(2025, 6, 15, 18, 0, 0);

        Carbon::setTestNow($clockInTime);
        $this->post('/attendance/store', ['action' => 'clock_in'])->assertRedirect('/attendance');

        Carbon::setTestNow($clockOutTime);
        $this->post('/attendance/store', ['action' => 'clock_out'])->assertRedirect('/attendance');

        // 管理画面にアクセス
        $this->actingAs($admin, 'admin');

        $response = $this->get('/admin/attendance/list');
        $response->assertStatus(200);

        $response->assertSee($clockOutTime->format('H:i'));
    }


}