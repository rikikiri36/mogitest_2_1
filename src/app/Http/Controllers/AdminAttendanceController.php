<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\User;
use App\Models\CommentRequest;
use App\Models\CommentUpdate;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Http\Requests\AttendanceReqRequest;
use App\Models\AttendanceUpdate;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminAttendanceController extends Controller
{
    
    // 勤怠一覧表示----------------//
    public function index(Request $request){
        $loginId = auth()->id();
        $action = $request->input('action');
        $ymd = $request->input('ymd', Carbon::now()->format('Y/m/d'));
        if($action == 'prev'){
            $ymd = Carbon::createFromFormat('Y/m/d', $ymd)->subDay()->format('Y/m/d');
        }elseif($action == 'next'){
            $ymd = Carbon::createFromFormat('Y/m/d', $ymd)->addDay()->format('Y/m/d');
        }

        // 勤怠・休憩をまとめてクエリで一括取得
        $results = DB::table('attendances as w')
            ->select(
                'w.id as detail_id',
                'w.user_id',
                'users.name',
                DB::raw('DATE_FORMAT(w.processing_start_time, "%H:%i") as start_time'),
                DB::raw('DATE_FORMAT(w.processing_end_time, "%H:%i") as end_time'),

                // 休憩合計時間
                DB::raw('
                    DATE_FORMAT(
                        SEC_TO_TIME(
                            SUM(TIME_TO_SEC(r.processing_end_time) - TIME_TO_SEC(r.processing_start_time))
                        ),
                        "%H:%i"
                    ) as rest_total
                '),

                // 実働時間 = 勤務時間 − 休憩時間
                DB::raw('
                    DATE_FORMAT(
                        SEC_TO_TIME(
                            TIME_TO_SEC(w.processing_end_time)
                        - TIME_TO_SEC(w.processing_start_time)
                        - IFNULL(SUM(TIME_TO_SEC(r.processing_end_time) - TIME_TO_SEC(r.processing_start_time)), 0)
                        ),
                        "%H:%i"
                    ) as work_total
                ')
            )
            ->join('users', 'users.id', '=', 'w.user_id')
            ->leftJoin('attendances as r', function ($join) use ($ymd) {
                $join->on('r.user_id', '=', 'w.user_id')
                    ->where('r.type', config('constants.type.rest'))
                    ->whereDate('r.processing_date', $ymd)
                    ->whereNotNull('r.processing_start_time')
                    ->whereNotNull('r.processing_end_time');
            })
            ->where('w.type', config('constants.type.work'))
            ->whereDate('w.processing_date', $ymd)
            ->groupBy('w.id', 'w.user_id', 'users.name', 'w.processing_start_time', 'w.processing_end_time')
            ->groupBy('users.name')
            ->get();

        return view('admin.attendance_list', compact('results', 'ymd'));
    }

    // 勤怠詳細表示----------------//
    public function detail($id){
        $hasPending = false;
        $isApproved = false; 
        $attendanceRests = collect();
        $commentrequestId = '';

        // 勤怠取得
        $attendance = Attendance::with('user')->findOrFail($id);

        // 休憩取得
        $attendanceRests = Attendance::where('user_id', $attendance->user_id)
        ->where('type', config('constants.type.rest'))
        ->where('processing_date', $attendance->processing_date)
        ->orderBy('processing_start_time')
        ->get();

        // 修正申請中か？
        $attendanceRequest = CommentRequest::where('user_id', $attendance->user_id)
        ->where('processing_date', $attendance->processing_date)
        ->where('request_status', config('constants.request_status.pending'))
        ->first();

        if($attendanceRequest){
            $hasPending = true;
            $commentrequestId = $attendanceRequest->id;
        }

        // 承認済か？
        $attendanceRequest = CommentRequest::where('user_id', $attendance->user_id)
        ->where('processing_date', $attendance->processing_date)
        ->where('request_status', config('constants.request_status.approved'))
        ->first();

        if($attendanceRequest){
            $isApproved = true;
        }
        
        // コメントを取得
        $commentRequest = CommentRequest::where('user_id', $attendance->user_id)
        ->where('processing_date', $attendance->processing_date)
        ->first();

        return view('admin.attendance_detail' ,compact('attendance', 'attendanceRests', 'hasPending', 'commentrequestId', 'isApproved', 'commentRequest'));
    }

    // スタッフ別勤怠一覧表示----------------//
    public function userIndex($userId, Request $request){

        $weekdayMap = ['日', '月', '火', '水', '木', '金', '土'];
        $action = $request->input('action');
        // $Ym = Carbon::now()->format('Y/m');
        $Ym = $request->input('Ym', Carbon::now()->format('Y/m'));
        if($action == 'prev'){
            $Ym = Carbon::createFromFormat('Y/m', $Ym)->subMonth()->format('Y/m');
        }elseif($action == 'next'){
            $Ym = Carbon::createFromFormat('Y/m', $Ym)->addMonth()->format('Y/m');
        }
        $firstDay = Carbon::parse($Ym . '/01')->startOfMonth();
        $lastDay  = Carbon::parse($Ym . '/01')->endOfMonth();

        // スタッフ名を取得
        $user = User::find($userId);

        // 勤怠・休憩をまとめて取得
        $workRecords = Attendance::where('user_id', $userId)
            ->where('type', config('constants.type.work'))
            ->whereBetween('processing_date', [$firstDay, $lastDay])
            ->orderBy('processing_date')
            ->get()
            ->keyBy('processing_date'); // 日付をキーにする

        $restRecords = Attendance::where('user_id', $userId)
            ->where('type', config('constants.type.rest'))
            ->whereBetween('processing_date', [$firstDay, $lastDay])
            ->whereNotNull('processing_start_time')
            ->whereNotNull('processing_end_time')
            ->orderBy('processing_date')
            ->get()
            ->groupBy('processing_date');

        // 日ごとの情報
        $calendarData = [];

        // 対象月の初日
        $day = $firstDay->copy();
        // 対象月の1日〜最終日までループする
        for ($day = $firstDay->copy(); $day <= $lastDay; $day->addDay()) {
            $date = $day->format('Y-m-d');
            // 対象日の勤怠情報取得
            $work = $workRecords->get($date);
            // 対象日の休憩情報取得
            $rests = $restRecords->get($date, collect());

            // 休憩合計時間（分）
            $restMinutes = 0;

            // すべての休憩時間を合算する
            foreach ($rests as $rest) {
                $start = Carbon::parse($rest->processing_start_time);
                $end = Carbon::parse($rest->processing_end_time);

                $restMinutes += $end->diffInMinutes($start);
            }

            // 勤務時間（分）
            $workMinutes = 0;
            if ($work && $work->processing_start_time && $work->processing_end_time) {
                $workStart = Carbon::parse($work->processing_start_time);
                $workEnd = Carbon::parse($work->processing_end_time);

                $workMinutes = $workEnd->diffInMinutes($workStart);
                $workMinutes = $workMinutes-$restMinutes; // 休憩時間を引く
            }

            $calendarData[] = [
                'date' => $date,
                'date_display' => Carbon::parse($date)->format('m/d') . ' (' . $weekdayMap[Carbon::parse($date)->dayOfWeek] . ')',  //表示用:06/01(木)に成型
                'start' => $work && $work->processing_start_time
                    ? Carbon::parse($work->processing_start_time)->format('H:i')
                    : '',
                'end' => $work && $work->processing_end_time
                    ? Carbon::parse($work->processing_end_time)->format('H:i')
                    : '',
                'rest_time' => $restMinutes > 0 ? $this->formatMinutesToTime($restMinutes) : '',
                'work_time' => $workMinutes > 0 ? $this->formatMinutesToTime($workMinutes) : '',
                'detail_id' => isset($work) ? $work->id : '',
            ];
        }

        return view('admin.attendance_list_user', compact('user', 'calendarData', 'Ym'));
    }

    // 申請一覧----------------//
    public function requestList(Request $request){
        $tab = $request->query('tab');
        $requestLists = collect();

        // 承認待ちタブ
        if($tab =='pending'){

            $requestLists = CommentRequest::select(
                'comment_requests.*',
                'attendances.id as detail_id'   // 詳細遷移用
            )
            ->join('attendances', function ($join) {
                $join->on('comment_requests.user_id', '=', 'attendances.user_id')
                     ->on('comment_requests.processing_date', '=', 'attendances.processing_date')
                     ->where('attendances.type', '=', config('constants.type.work'));
            })
            ->where('comment_requests.request_status', config('constants.request_status.pending'))
            ->orderBy('comment_requests.processing_date')
            ->get();

        // 承認済みタブ
        }else{
            $requestLists = CommentRequest::select(
                'comment_requests.*',
                'attendances.id as detail_id'   // 詳細遷移用
            )
            ->join('attendances', function ($join) {
                $join->on('comment_requests.user_id', '=', 'attendances.user_id')
                     ->on('comment_requests.processing_date', '=', 'attendances.processing_date')
                     ->where('attendances.type', '=', config('constants.type.work'));
            })
            ->where('comment_requests.request_status', config('constants.request_status.approved'))
            ->orderBy('comment_requests.processing_date')
            ->get();
        }

        return view('admin.attendance_requests', compact('requestLists'));

    }

    // 承認処理----------------//
    public function attendanceapproveStore(Request $request){

        // ステータス更新
        $attendance = Attendance::find($request->id);
        
        $affected = Attendance::where('user_id', $attendance->user_id)
            ->where('processing_date', $attendance->processing_date)
            ->update([
                'request_status' => config('constants.request_status.approved'),
            ]);

        $commentRequest = CommentRequest::find($request->commentrequestId);
        $commentRequest->request_status = config('constants.request_status.approved');        
        $commentRequest->save();

        return redirect("/admin/attendance/{$request->id}")->with('status', '承認が完了しました');
    }

    // 修正処理----------------//
    public function attendanceupdateStore(AttendanceReqRequest $request){
        $restStartTimes = $request->input('rest_processing_start_time', []);
        $restEndTimes = $request->input('rest_processing_end_time', []);
        $loginId = auth()->id();

        // ユーザーIDと日付を取得
        $attendanceUser = Attendance::find($request->id);

        // 備考--
        // 新規作成
        CommentUpdate::create([
            'user_id' => $attendanceUser->user_id,
            'processing_date' => $attendanceUser->processing_date,
            'comment' => $request->comment,
        ]);

        // 勤怠時間--
        // 修正テーブル作成
        AttendanceUpdate::create([
            'user_id' => $attendanceUser->user_id,
            'type' => config('constants.type.work'),
            'processing_date' => $attendanceUser->processing_date,
            'processing_start_time' => $request->work_processing_start_time,
            'processing_end_time' => $request->work_processing_end_time,
            'updated_user_id' => $loginId,
        ]);

        // 勤怠テーブル更新
        $attendance = Attendance::find($request->id);
        $attendance->user_id = $attendanceUser->user_id;
        $attendance->processing_start_time = $request->work_processing_start_time;
        $attendance->processing_end_time = $request->work_processing_end_time;    
        $attendance->save();

        // 休憩時間--
        // 勤怠テーブルの休憩レコードを削除しておく
        Attendance::where('user_id', $attendanceUser->user_id)
            ->where('type', config('constants.type.rest'))
            ->where('processing_date', $attendanceUser->processing_date)
            ->delete();

        foreach ($restStartTimes as $i => $starttime) {
            $endtime = $restEndTimes[$i] ?? null;

            if($starttime || $endtime){
                // 勤怠テーブルを作り直す
                Attendance::create([
                    'user_id' => $attendanceUser->user_id,
                    'type' => config('constants.type.rest'),
                    'processing_date' => $attendanceUser->processing_date,
                    'processing_start_time' => $starttime,
                    'processing_end_time' => $endtime,
                    'request_status' => $attendanceUser->request_status,
                ]);

                // 申請テーブル作成
                AttendanceUpdate::create([
                    'user_id' => $attendanceUser->user_id,
                    'type' => config('constants.type.rest'),
                    'processing_date' => $attendanceUser->processing_date,
                    'processing_start_time' => $starttime,
                    'processing_end_time' => $endtime,
                    'updated_user_id' => $loginId,
                ]);
            }
        }

        return redirect("/admin/attendance/{$request->id}")->with('status', '修正が完了しました');
    }

    // ユーザー別月次勤怠CSV出力
    public function exportCsv(Request $request){
        $user_name = $request->input('user_name');
        $Ym = $request->input('Ym');
        $serialized = $request->input('results');

        $results = unserialize(base64_decode($serialized));

        $hasData = collect($results)->contains(function ($row) {
            return !empty($row['start']) || !empty($row['end']) || !empty($row['work_time']);
        });
        
        if (!$hasData) {
            return back()->with('status', '出力対象の勤怠データがありません');
        }

        $filename = 'attendance_report_' . $user_name . '_' . $Ym . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($results) {
            $handle = fopen('php://output', 'w');
    
            // ヘッダー行
            fputcsv($handle, ['日付', '出勤', '退勤', '休憩', '勤務時間']);
    
            foreach ($results as $row) {
                fputcsv($handle, [
                    $row['date_display'] ?? '',
                    $row['start'] ?? '',
                    $row['end'] ?? '',
                    $row['rest_time'] ?? '',
                    $row['work_time'] ?? '',
                ]);
            }
    
            fclose($handle);
        };
        return new StreamedResponse($callback, 200, $headers);
    }

    // 一覧で休憩時間合計・勤怠時間合計を[1:05]形式にする
    private function formatMinutesToTime($minutes)
    {
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        return sprintf('%d:%02d', $hours, $mins);
    }
}
