<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;
use Carbon\Carbon;

class UserAttendanceDetailTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        // 日本語ロケールにしておきたい場合
        app()->setLocale('ja');
    }

    // 勤怠詳細画面の「名前」がログインユーザーの氏名になっている ------------
    public function test_attendance_detail_user_name()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create([
            'name' => 'テスト太郎',
        ]);
        $this->actingAs($user);

        $attendance = Attendance::factory()->onDuty()->create([
            'user_id' => $user->id,
            'processing_date' => Carbon::today()->toDateString(),
        ]);

        $response = $this->get("/attendance/{$attendance->id}");
        $response->assertStatus(200);

        $response->assertSee($user->name);
    }

    // 勤怠詳細画面の「日付」が選択した日付になっている ------------
    public function test_attendance_detail_selected_date()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        $targetDate = Carbon::create(2025, 6, 15);
        $attendance = Attendance::factory()->onDuty()->create([
            'user_id' => $user->id,
            'processing_date' => $targetDate->toDateString(),
        ]);

        $response = $this->get("/attendance/{$attendance->id}");
        $response->assertStatus(200);

        $expectedDate = $targetDate->format('Y年n月j日');

        $response->assertSee($expectedDate);
    }

    // 「出勤・退勤」にて記されている時間がログインユーザーの打刻と一致している ------------
    public function test_attendance_detail_shows_correct_clock_in_and_out_time()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        $clockIn = Carbon::create(2025, 6, 15, 9, 0, 0);  // 09:00
        $clockOut = Carbon::create(2025, 6, 15, 18, 0, 0); // 18:00

        // 勤怠レコードを作成
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'processing_date' => $clockIn->toDateString(),
            'processing_start_time' => $clockIn->toTimeString(),
            'processing_end_time' => $clockOut->toTimeString(),
            'type' => config('constants.type.work'),
        ]);

        $response = $this->get("/attendance/{$attendance->id}");
        $response->assertStatus(200);

        $expectedClockIn = $clockIn->format('H:i');
        $expectedClockOut = $clockOut->format('H:i');

        $response->assertSee($expectedClockIn);
        $response->assertSee($expectedClockOut);
    }

    // 「休憩」にて記されている時間がログインユーザーの打刻と一致している ------------
    public function test_attendance_detail_shows_correct_rest_times()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        $date = Carbon::create(2025, 6, 15);

        // 出勤データ
        $workAttendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => config('constants.type.work'),
            'processing_date' => $date->toDateString(),
            'processing_start_time' => '09:00:00',
            'processing_end_time' => '18:00:00',
        ]);

        // 休憩1
        $rest1Start = '12:00:00';
        $rest1End = '12:30:00';
        Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => config('constants.type.rest'),
            'processing_date' => $date->toDateString(),
            'processing_start_time' => $rest1Start,
            'processing_end_time' => $rest1End,
        ]);

        // 休憩2
        $rest2Start = '15:00:00';
        $rest2End = '15:15:00';
        Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => config('constants.type.rest'),
            'processing_date' => $date->toDateString(),
            'processing_start_time' => $rest2Start,
            'processing_end_time' => $rest2End,
        ]);

        $response = $this->get("/attendance/{$workAttendance->id}");
        $response->assertStatus(200);

        $response->assertSee(substr($rest1Start, 0, 5));
        $response->assertSee(substr($rest1End, 0, 5));
        $response->assertSee(substr($rest2Start, 0, 5));
        $response->assertSee(substr($rest2End, 0, 5));
    }

    // 出勤時間が退勤時間より後になっている場合、エラーメッセージが表示される ------------
    public function test_clock_in_after_clock_out_returns_validation_error()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        // 勤怠データ
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => config('constants.type.work'),
            'processing_date' => Carbon::today()->toDateString(),
            'processing_start_time' => '09:00:00',
            'processing_end_time' => '18:00:00',
        ]);

        // 出勤時間を 19:00、退勤時間を 18:00 にして逆転させる
        $response = $this->from("/attendance/{$attendance->id}")->post('/attendance/attendancerequestStore', [
            'id' => $attendance->id,
            'processing_date' => $attendance->processing_date,
            'work_processing_start_time' => '19:00',
            'work_processing_end_time' => '18:00',
            'rest_processing_start_time' => [],
            'rest_processing_end_time' => [],
            'comment' => 'テスト申請',
        ]);

        $response->assertRedirect("/attendance/{$attendance->id}");

        $response->assertSessionHasErrors(['work_processing_end_time']);

        $this->assertEquals(
            '出勤時間もしくは退勤時間が不適切な値です',
            session('errors')->first('work_processing_end_time')
        );
    }

    // 休憩開始時間が退勤時間より後になっている場合、エラーメッセージが表示される ------------
    public function test_rest_start_after_clock_out_returns_validation_error()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => config('constants.type.work'),
            'processing_date' => Carbon::today()->toDateString(),
            'processing_start_time' => '09:00:00',
            'processing_end_time' => '18:00:00',
        ]);

        // 休憩開始時間を退勤後にする (19:00)
        $response = $this->from("/attendance/{$attendance->id}")->post('/attendance/attendancerequestStore', [
            'id' => $attendance->id,
            'processing_date' => $attendance->processing_date,
            'work_processing_start_time' => '09:00',
            'work_processing_end_time' => '18:00',
            'rest_processing_start_time' => ['19:00'],
            'rest_processing_end_time' => ['19:30'],
            'comment' => 'テスト申請',
        ]);

        $response->assertRedirect("/attendance/{$attendance->id}");

        $response->assertSessionHasErrors(['rest_processing_start_time.0']);

        $this->assertEquals(
            '休憩時間が勤務時間外です',     // テストケースでは「出勤時間もしくは退勤時間が不適切な値です」となっているが、機能要件（34行目）に合わせる
            session('errors')->first('rest_processing_start_time.0')
        );
    }

    // 休憩終了時間が退勤時間より後になっている場合、エラーメッセージが表示される ------------
    public function test_rest_end_after_clock_out_returns_validation_error()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $this->actingAs($user);
    
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => config('constants.type.work'),
            'processing_date' => Carbon::today()->toDateString(),
            'processing_start_time' => '09:00:00',
            'processing_end_time' => '18:00:00',
        ]);
    
        // 休憩終了時間を退勤時間より後（19:00）に設定
        $response = $this->from("/attendance/{$attendance->id}")->post('/attendance/attendancerequestStore', [
            'id' => $attendance->id,
            'processing_date' => $attendance->processing_date,
            'work_processing_start_time' => '09:00',
            'work_processing_end_time' => '18:00',
            'rest_processing_start_time' => ['12:00'],
            'rest_processing_end_time' => ['19:00'],
            'comment' => 'テスト申請',
        ]);
    
        $response->assertRedirect("/attendance/{$attendance->id}");
            $response->assertSessionHasErrors(['rest_processing_end_time.0']);
    
        $this->assertEquals(
            '休憩時間が勤務時間外です', // テストケースでは「出勤時間もしくは退勤時間が不適切な値です」となっているが、機能要件（34行目）に合わせる
            session('errors')->first('rest_processing_end_time.0')
        );
    }

    // 備考欄が未入力の場合のエラーメッセージが表示される ------------
    public function test_comment_required_returns_validation_error()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => config('constants.type.work'),
            'processing_date' => Carbon::today()->toDateString(),
            'processing_start_time' => '09:00:00',
            'processing_end_time' => '18:00:00',
        ]);

        // 備考を未入力にして申請
        $response = $this->from("/attendance/{$attendance->id}")->post('/attendance/attendancerequestStore', [
            'id' => $attendance->id,
            'processing_date' => $attendance->processing_date,
            'work_processing_start_time' => '09:00',
            'work_processing_end_time' => '18:00',
            'rest_processing_start_time' => [],
            'rest_processing_end_time' => [],
            'comment' => '',
        ]);

        $response->assertRedirect("/attendance/{$attendance->id}");

        $response->assertSessionHasErrors(['comment']);

        $this->assertEquals(
            '備考を入力してください',
            session('errors')->first('comment')
        );
    }

    // 修正申請処理が実行される ------------
    public function test_attendance_request_is_created_and_visible_to_admin()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => config('constants.type.work'),
            'processing_date' => Carbon::today()->toDateString(),
            'processing_start_time' => '09:00:00',
            'processing_end_time' => '18:00:00',
        ]);

        // 修正申請
        $response = $this->post('/attendance/attendancerequestStore', [
            'id' => $attendance->id,
            'processing_date' => $attendance->processing_date,
            'work_processing_start_time' => '10:00',
            'work_processing_end_time' => '19:00',
            'rest_processing_start_time' => ['12:00'],
            'rest_processing_end_time' => ['12:30'],
            'comment' => '修正理由テスト',
        ]);

        $response->assertRedirect("/attendance/{$attendance->id}");
        $response->assertSessionHas('status', '申請が完了しました');

        // AttendanceRequest & CommentRequest が作成されていることを確認
        $this->assertDatabaseHas('attendance_requests', [
            'user_id' => $user->id,
            'processing_date' => $attendance->processing_date,
            'processing_start_time' => '10:00:00',
            'processing_end_time' => '19:00:00',
        ]);

        $this->assertDatabaseHas('comment_requests', [
            'user_id' => $user->id,
            'processing_date' => $attendance->processing_date,
            'comment' => '修正理由テスト',
            'request_status' => config('constants.request_status.pending'),
        ]);

        // 管理者ユーザーを作成
        /** @var \App\Models\User $admin */
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'admin');

        // 管理者の申請一覧画面
        $adminResponse = $this->get('/stamp_correction_request/list?tab=pending');
        $adminResponse->assertStatus(200);
        $adminResponse->assertSee('修正理由テスト');
        $expectedDate = Carbon::parse($attendance->processing_date)->format('Y/m/d');
        $adminResponse->assertSee($expectedDate);

        // 管理者の承認画面を確認
        $response = $this->get("/admin/attendance/{$attendance->id}");
        $response->assertStatus(200);

        $response->assertSee('10:00');
        $response->assertSee('19:00');
        $response->assertSee('修正理由テスト');
        $response->assertSee('承認'); // 承認ボタンが存在する
    }

    // 「承認待ち」にログインユーザーが行った申請が全て表示されていること ------------
    public function test_user_requests_are_visible_in_pending_tab()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $this->actingAs($user);
    
        // 勤怠データを作成
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => config('constants.type.work'),
            'processing_date' => Carbon::today()->toDateString(),
            'processing_start_time' => '09:00:00',
            'processing_end_time' => '18:00:00',
        ]);
    
        // 修正申請
        $this->post('/attendance/attendancerequestStore', [
            'id' => $attendance->id,
            'processing_date' => $attendance->processing_date,
            'work_processing_start_time' => '10:00',
            'work_processing_end_time' => '19:00',
            'rest_processing_start_time' => ['12:00'],
            'rest_processing_end_time' => ['12:30'],
            'comment' => '修正理由テスト',
        ]);
    
        // 申請一覧（承認待ち）
        $response = $this->get('/stamp_correction_request/list?tab=pending');
        $response->assertStatus(200);
    
        $response->assertSee($user->name);
        $response->assertSee('修正理由テスト');
        $response->assertSee(Carbon::parse($attendance->processing_date)->format('Y/m/d'));
        $response->assertSee('承認待ち');
    }

    // 「承認済み」に管理者が承認した修正申請が全て表示されている ------------
    public function test_admin_can_see_all_approved_requests_from_multiple_users()
    {
        // 一般ユーザー A
        /** @var \App\Models\User $userA */
        $userA = User::factory()->create();
        $this->actingAs($userA);
    
        $attendanceA = Attendance::factory()->create([
            'user_id' => $userA->id,
            'type' => config('constants.type.work'),
            'processing_date' => Carbon::today()->toDateString(),
        ]);
    
        $this->post('/attendance/attendancerequestStore', [
            'id' => $attendanceA->id,
            'processing_date' => $attendanceA->processing_date,
            'work_processing_start_time' => '10:00',
            'work_processing_end_time' => '19:00',
            'rest_processing_start_time' => ['12:00'],
            'rest_processing_end_time' => ['12:30'],
            'comment' => 'ユーザーAの申請',
        ]);
    
        // 一般ユーザー B
        /** @var \App\Models\User $userB */
        $userB = User::factory()->create();
        $this->actingAs($userB);
    
        $attendanceB = Attendance::factory()->create([
            'user_id' => $userB->id,
            'type' => config('constants.type.work'),
            'processing_date' => Carbon::today()->addDay()->toDateString(),
        ]);
    
        $this->post('/attendance/attendancerequestStore', [
            'id' => $attendanceB->id,
            'processing_date' => $attendanceB->processing_date,
            'work_processing_start_time' => '11:00',
            'work_processing_end_time' => '20:00',
            'rest_processing_start_time' => ['13:00'],
            'rest_processing_end_time' => ['13:30'],
            'comment' => 'ユーザーBの申請',
        ]);
    
        // 管理者ユーザー
        /** @var \App\Models\User $admin */
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'admin');
    
        // 両方の申請を承認済みに更新
        \App\Models\CommentRequest::where('user_id', $userA->id)->update(['request_status' => config('constants.request_status.approved')]);
        \App\Models\CommentRequest::where('user_id', $userB->id)->update(['request_status' => config('constants.request_status.approved')]);
    
        // 管理者の承認済みタブを確認
        $response = $this->get('/stamp_correction_request/list?tab=approved');
        $response->assertStatus(200);
    
        $response->assertSee('承認済み');
        $response->assertSee('ユーザーAの申請');
        $response->assertSee('ユーザーBの申請');
        $response->assertSee($userA->name);
        $response->assertSee($userB->name);
        $response->assertSee(Carbon::parse($attendanceA->processing_date)->format('Y/m/d'));
        $response->assertSee(Carbon::parse($attendanceB->processing_date)->format('Y/m/d'));
    }

    // 各申請の「詳細」を押下すると申請詳細画面に遷移する
    public function test_each_request_detail_link_to_detail()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $this->actingAs($user);
    
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => config('constants.type.work'),
            'processing_date' => Carbon::today()->toDateString(),
        ]);
    
        $this->post('/attendance/attendancerequestStore', [
            'id' => $attendance->id,
            'processing_date' => $attendance->processing_date,
            'work_processing_start_time' => '10:00',
            'work_processing_end_time' => '19:00',
            'rest_processing_start_time' => ['12:00'],
            'rest_processing_end_time' => ['12:30'],
            'comment' => 'テスト詳細遷移用',
        ]);
    
        // 申請一覧画面に「詳細」リンクが存在することを確認 & 遷移
        $response = $this->get('/stamp_correction_request/list?tab=pending');
        $response->assertStatus(200);
        $response->assertSee('詳細');
    
        // 一覧から詳細リンクにアクセス
        $detailUrl = "/attendance/{$attendance->id}";
        $detailResponse = $this->get($detailUrl);
        $detailResponse->assertStatus(200);
    
        // 詳細画面
        $detailResponse->assertSee($user->name);
        $detailResponse->assertSee(Carbon::parse($attendance->processing_date)->format('Y年n月j日'));
        $detailResponse->assertSee('10:00');
        $detailResponse->assertSee('19:00');
        $detailResponse->assertSee('承認待ちのため修正はできません。');
    }

}