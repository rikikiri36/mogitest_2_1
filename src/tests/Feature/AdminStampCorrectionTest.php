<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use App\Models\CommentRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class AdminStampCorrectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('ja');
    }

    // 承認待ちの修正申請が全て表示されている ------------
    public function test_admin_can_see_all_pending_stamp_correction_requests()
    {
        /** @var \App\Models\User $admin */
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'admin');
    
        // 一般ユーザーを2名作成
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
    
        $attendance1 = Attendance::factory()->create([
            'user_id' => $user1->id,
            'type' => config('constants.type.work'),
        ]);
        $attendance2 = Attendance::factory()->create([
            'user_id' => $user2->id,
            'type' => config('constants.type.work'),
        ]);
    
        // それぞれ未承認の申請を登録
        CommentRequest::create([
            'user_id' => $user1->id,
            'processing_date' => $attendance1->processing_date,
            'comment' => 'User1の修正申請',
            'request_status' => config('constants.request_status.pending'),
        ]);
        CommentRequest::create([
            'user_id' => $user2->id,
            'processing_date' => $attendance2->processing_date,
            'comment' => 'User2の修正申請',
            'request_status' => config('constants.request_status.pending'),
        ]);
    
        // 申請一覧「承認待ち」タブ
        $response = $this->get('stamp_correction_request/list?tab=pending');
    
        $response->assertStatus(200);
    
        $response->assertSee('User1の修正申請');
        $response->assertSee('User2の修正申請');
        $response->assertSee($user1->name);
        $response->assertSee($user2->name);
        $response->assertSee('承認待ち');
    
        $response->assertSee(\Carbon\Carbon::parse($attendance1->processing_date)->format('Y/m/d'));
        $response->assertSee(\Carbon\Carbon::parse($attendance2->processing_date)->format('Y/m/d'));
    }
    
    // 承認済みの修正申請が全て表示されている ------------
    public function test_admin_can_see_all_approved_stamp_correction_requests()
    {
        /** @var \App\Models\User $admin */
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'admin');

        // 一般ユーザーを2人作成
        $users = User::factory()->count(2)->create();

        foreach ($users as $user) {
            // 勤怠を作成
            $attendance = Attendance::factory()->create([
                'user_id' => $user->id,
                'processing_date' => Carbon::today()->toDateString(),
                'type' => config('constants.type.work'),
            ]);

            // 承認済み申請を作成
            CommentRequest::factory()->create([
                'user_id' => $user->id,
                'processing_date' => $attendance->processing_date,
                'request_status' => config('constants.request_status.approved'),
                'comment' => "承認済みテストコメント for {$user->name}",
            ]);
        }

        // 申請一覧の承認済みタブ
        $response = $this->get('/stamp_correction_request/list?tab=approved');

        $response->assertStatus(200);

        foreach ($users as $user) {
            $response->assertSee($user->name);
            $response->assertSee('承認済みテストコメント for ' . $user->name);
            $response->assertSee(Carbon::today()->format('Y/m/d'));
        }
    }

    // 修正申請の詳細内容が正しく表示されている ------------
    public function test_admin_can_see_stamp_correction_request_detail_correctly()
    {
        /** @var \App\Models\User $admin */
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'admin');

        // 一般ユーザーを作成
        $user = User::factory()->create();

        $processingDate = Carbon::today()->toDateString();

        // 勤怠を作成
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'type' => config('constants.type.work'),
            'processing_date' => $processingDate,
            'processing_start_time' => '09:00:00',
            'processing_end_time' => '18:00:00',
        ]);

        // 修正申請を作成
        $commentRequest = CommentRequest::factory()->create([
            'user_id' => $user->id,
            'processing_date' => $processingDate,
            'comment' => '修正理由のテスト',
            'request_status' => config('constants.request_status.pending'),
        ]);

        // 詳細画面
        $response = $this->get("/admin/attendance/{$attendance->id}");

        $response->assertStatus(200);

        $response->assertSee($user->name);
        $response->assertSee(Carbon::parse($processingDate)->format('Y年n月j日'));
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('修正理由のテスト');
    }

    // 修正申請の承認処理が正しく行われる ------------
    public function test_admin_can_approve_stamp_correction_request()
    {
        /** @var \App\Models\User $admin */
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'admin');

        // 一般ユーザーと勤怠
        $user = User::factory()->create();
        $processingDate = Carbon::today()->toDateString();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'processing_date' => $processingDate,
            'request_status' => config('constants.request_status.pending'),
        ]);

        $commentRequest = CommentRequest::factory()->create([
            'user_id' => $user->id,
            'processing_date' => $processingDate,
            'request_status' => config('constants.request_status.pending'),
        ]);

        // 承認処理を実行
        $response = $this->post('/admin/attendance/attendanceapproveStore', [
            'id' => $attendance->id,
            'commentrequestId' => $commentRequest->id,
        ]);

        $response->assertRedirect("/admin/attendance/{$attendance->id}");
        $response->assertSessionHas('status', '承認が完了しました');

        // データが更新されているか確認
        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'request_status' => config('constants.request_status.approved'),
        ]);

        $this->assertDatabaseHas('comment_requests', [
            'id' => $commentRequest->id,
            'request_status' => config('constants.request_status.approved'),
        ]);
    }
}