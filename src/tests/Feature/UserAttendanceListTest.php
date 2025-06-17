<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;
use Carbon\Carbon;

class UserAttendanceListTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        // 日本語ロケールにしておきたい場合
        app()->setLocale('ja');
    }

    // 自分が行った勤怠情報が全て表示されている ------------
    public function test_user_attendance_list()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        // 固定日付
        $date = Carbon::create(2025, 6, 15);

        // 出勤
        $clockInTime = $date->copy()->setTime(9, 0);
        // 休憩
        $restStartTime = $date->copy()->setTime(12, 0);
        $restEndTime = $date->copy()->setTime(12, 30);
        // 退勤
        $clockOutTime = $date->copy()->setTime(18, 0);

        // 出勤
        Carbon::setTestNow($clockInTime);
        $this->post('/attendance/store', ['action' => 'clock_in'])->assertRedirect('/attendance');

        // 休憩入
        Carbon::setTestNow($restStartTime);
        $this->post('/attendance/store', ['action' => 'break_start'])->assertRedirect('/attendance');

        // 休憩戻
        Carbon::setTestNow($restEndTime);
        $this->post('/attendance/store', ['action' => 'break_end'])->assertRedirect('/attendance');

        // 退勤
        Carbon::setTestNow($clockOutTime);
        $this->post('/attendance/store', ['action' => 'clock_out'])->assertRedirect('/attendance');

        $response = $this->get('/attendance/list');
        $response->assertStatus(200);

        $dateDisplay = $date->isoFormat('MM/DD (dd)');
        $clockIn = $clockInTime->format('H:i');
        $clockOut = $clockOutTime->format('H:i');
        $restTotal = '0:30';

        $response->assertSeeInOrder([
            $dateDisplay,
            $clockIn,
            $clockOut,
            $restTotal,
        ]);
    }

    // 勤怠一覧画面に遷移した際に現在の月が表示される ------------
    public function test_attendance_list_shows_current_month()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        // 現在日時を固定
        $fixedDate = Carbon::create(2025, 6, 15);
        Carbon::setTestNow($fixedDate);

        $response = $this->get('/attendance/list');
        $response->assertStatus(200);

        $expectedYm = $fixedDate->format('Y/m'); // 2025/06

        $response->assertSee($expectedYm);
    }

    // 「前月」を押下した時に表示月の前月の情報が表示される ------------
    public function test_prev_month_link()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        $fixedDate = Carbon::create(2025, 6, 15);
        Carbon::setTestNow($fixedDate);

        $response = $this->get('/attendance/list');
        $response->assertStatus(200);
        $response->assertSee($fixedDate->format('Y/m')); // "2025/06"

        // 前月リンクを押下
        $YmToSend = $fixedDate->subMonth()->format('Y/m');
        $expectedPrev = $fixedDate->copy()->subMonth()->format('Y/m');

        $responsePrev = $this->get("/attendance/list?action=prev&Ym=$YmToSend");
        $responsePrev->assertStatus(200);
        $responsePrev->assertSee($expectedPrev);    }

    // 「翌月」を押下した時に表示月の前月の情報が表示される ------------
    public function test_next_month_link()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        $fixedDate = Carbon::create(2025, 6, 15);
        Carbon::setTestNow($fixedDate);

        $response = $this->get('/attendance/list');
        $response->assertStatus(200);
        $response->assertSee('2025/06');

        // 翌月リンクを押下
        $YmToSend = $fixedDate->format('Y/m');
        $expectedNext = $fixedDate->copy()->addMonth()->format('Y/m');

        $responseNext = $this->get("/attendance/list?action=next&Ym=$YmToSend");
        $responseNext->assertStatus(200);
        $responseNext->assertSee($expectedNext);
    }

    // 「詳細」を押下すると、その日の勤怠詳細画面に遷移する ------------
    public function test_attendance_detail_link()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        $attendance = Attendance::factory()->onDuty()->create([
            'user_id' => $user->id,
            'processing_date' => Carbon::today()->toDateString(),
        ]);

        $response = $this->get('/attendance/list');
        $response->assertStatus(200);

        // 詳細リンクが含まれているか
        $detailUrl = "/attendance/{$attendance->id}";
        $response->assertSee($detailUrl);

        // 詳細ページへ遷移
        $detailResponse = $this->get($detailUrl);
        $detailResponse->assertStatus(200);

        // 日付確認
        $detailResponse->assertSee($attendance->processing_date);
    }
}