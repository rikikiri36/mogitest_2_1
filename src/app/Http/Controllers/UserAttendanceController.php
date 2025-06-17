<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceRequest;
use App\Models\CommentRequest;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Http\Requests\AttendanceReqRequest;

class UserAttendanceController extends Controller
{
    // 勤怠画面表示----------------//
    public function create(){

        $loginId = auth()->id();
        $ymd = now()->format('Y-m-d');
        // 現在のステータスを取得
        $result = $this->get_status($ymd);
        $status = $result['status'];
        $label = $result['label'];

        return view('attendance', compact('status', 'label'));
    }

    // 勤怠更新処理----------------//
    public function store(Request $request){
        $loginId = auth()->id();
        $ymd = now()->format('Y-m-d');
        $hms = now()->format('H:i:s');
        $action = $request->input('action');

        switch ($action) {
            // 出勤処理
            case 'clock_in':
            
                // 勤怠テーブル作成
                $attendance = Attendance::create([
                    'user_id' => $loginId,
                    'type' => config('constants.type.work'),
                    'processing_date' => $ymd,
                    'processing_start_time' => $hms,
                ]);
                break;

            // 退勤処理
            case 'clock_out':

                // 対象の勤怠を取得
                $attendanceWork = Attendance::where('user_id', $loginId)
                        ->where('processing_date', $ymd)
                        ->where('type', config('constants.type.work'))
                        ->first();
                // 終了時間を更新
                if ($attendanceWork) {
                    $attendanceWork->processing_end_time = $hms;
                    $attendanceWork->save();
                }
                break;

            // 休憩開始
            case 'break_start':

                // 勤怠テーブル作成
                $attendance = Attendance::create([
                    'user_id' => $loginId,
                    'type' => config('constants.type.rest'),
                    'processing_date' => $ymd,
                    'processing_start_time' => $hms,
                ]);
                break;

            // 休憩終了
            case 'break_end':

                // 最新の休憩を取得
                $attendanceRest = Attendance::where('user_id', $loginId)
                        ->where('processing_date', $ymd)
                        ->where('type', config('constants.type.rest'))
                        ->whereNull('processing_end_time')
                        ->orderBy('created_at', 'desc')
                        ->first();
                // 終了時間を更新
                if ($attendanceRest) {
                    $attendanceRest->processing_end_time = $hms;
                    $attendanceRest->save();
                }

                break;
        }
        return redirect('/attendance');
    }

    // 勤怠一覧表示----------------//
    public function index(Request $request){
        $loginId = auth()->id();
        $weekdayMap = ['日', '月', '火', '水', '木', '金', '土'];
        $action = $request->input('action');
        $Ym = $request->input('Ym', Carbon::now()->format('Y/m'));

        if($action == 'prev'){
            $Ym = Carbon::createFromFormat('Y/m', $Ym)->subMonth()->format('Y/m');
        }elseif($action == 'next'){
            $Ym = Carbon::createFromFormat('Y/m', $Ym)->addMonth()->format('Y/m');
        }

        $firstDay = Carbon::parse($Ym . '/01')->startOfMonth();
        $lastDay  = Carbon::parse($Ym . '/01')->endOfMonth();

        // 勤怠・休憩をまとめて取得
        $workRecords = Attendance::where('user_id', $loginId)
            ->where('type', config('constants.type.work'))
            ->whereBetween('processing_date', [$firstDay, $lastDay])
            ->orderBy('processing_date')
            ->get()
            ->keyBy('processing_date'); // 日付をキーにする

        $restRecords = Attendance::where('user_id', $loginId)
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

        return view('attendance_list', compact('calendarData', 'Ym'));
    }

    // 勤怠詳細表示----------------//
    public function detail($id, Request $request){
        $loginId = auth()->id();
        $hasPending = false;
        $isApproved = false; 
        $attendanceRests = collect();

        // 勤怠取得
        $attendance = Attendance::with('user')->findOrFail($id);

        // 休憩取得
        $attendanceRests = Attendance::where('user_id', $loginId)
        ->where('type', config('constants.type.rest'))
        ->where('processing_date', $attendance->processing_date)
        ->orderBy('processing_start_time')
        ->get();

        // 修正申請中か？
        $attendanceRequest = CommentRequest::where('user_id', $loginId)
        ->where('processing_date', $attendance->processing_date)
        ->where('request_status', config('constants.request_status.pending'))
        ->first();

        if($attendanceRequest){
            $hasPending = true;
        }

        // 承認済か？
        $attendanceRequest = CommentRequest::where('user_id', $loginId)
        ->where('processing_date', $attendance->processing_date)
        ->where('request_status', config('constants.request_status.approved'))
        ->first();

        if($attendanceRequest){
            $isApproved = true;
        }
        
        // コメントを取得
        $commentRequest = CommentRequest::where('user_id', $loginId)
        ->where('processing_date', $attendance->processing_date)
        ->first();
        
        return view('attendance_detail' ,compact('attendance', 'attendanceRests', 'hasPending', 'isApproved', 'commentRequest'));
    }


    // 申請処理----------------//
    public function attendancerequestStore(AttendanceReqRequest $request){
        $loginId = auth()->id();
        $restStartTimes = $request->input('rest_processing_start_time', []);
        $restEndTimes = $request->input('rest_processing_end_time', []);
        
        // 備考--
        // 新規作成
        CommentRequest::create([
            'user_id' => $loginId,
            'processing_date' => $request->processing_date,
            'comment' => $request->comment,
            'request_status' => config('constants.request_status.pending'),
        ]);

        // 勤怠時間--
        // 申請テーブル作成
        AttendanceRequest::create([
            'user_id' => $loginId,
            'type' => config('constants.type.work'),
            'processing_date' => $request->processing_date,
            'processing_start_time' => $request->work_processing_start_time,
            'processing_end_time' => $request->work_processing_end_time,
        ]);

        // 勤怠テーブル更新
        $attendance = Attendance::find($request->id);
        $attendance->processing_start_time = $request->work_processing_start_time;
        $attendance->processing_end_time = $request->work_processing_end_time;
        $attendance->request_status = config('constants.request_status.pending');
    
        $attendance->save();

        // 休憩時間--
        // 勤怠テーブルの休憩レコードを削除しておく
        Attendance::where('user_id', $loginId)
            ->where('type', config('constants.type.rest'))
            ->where('processing_date', $request->processing_date)
            ->delete();

        foreach ($restStartTimes as $i => $starttime) {
            $endtime = $restEndTimes[$i] ?? null;

            if($starttime || $endtime){
                // 勤怠テーブルを作り直す
                Attendance::create([
                    'user_id' => $loginId,
                    'type' => config('constants.type.rest'),
                    'processing_date' => $request->processing_date,
                    'processing_start_time' => $starttime,
                    'processing_end_time' => $endtime,
                    'request_status' => config('constants.request_status.pending'),
                ]);

                // 申請テーブル作成
                AttendanceRequest::create([
                    'user_id' => $loginId,
                    'type' => config('constants.type.rest'),
                    'processing_date' => $request->processing_date,
                    'processing_start_time' => $starttime,
                    'processing_end_time' => $endtime,
                ]);
            }
        }

        return redirect("/attendance/{$request->id}")->with('status', '申請が完了しました');
    }

    // 勤怠状況取得　：勤務外、出勤中、休憩中、退勤済
    public function get_status($ymd){
        $loginId = auth()->id();
        $attendanceStatus = config('constants.attendance_status.off_duty');
        $attendance_status_labels = config('constants.attendance_status_labels.off_duty');

        // 勤怠を取得（必ず１件）
        $attendanceWork = Attendance::where('user_id', $loginId)
                        ->where('processing_date', $ymd)
                        ->where('type', config('constants.type.work'))
                        ->first();
        // 最新の休憩のみ取得
        $attendanceRest = Attendance::where('user_id', $loginId)
                        ->where('processing_date', $ymd)
                        ->where('type', config('constants.type.rest'))
                        ->whereNull('processing_end_time')
                        ->orderBy('created_at', 'desc')
                        ->first();

        // 当日の勤怠が存在しなければ「勤務外」
        if (!$attendanceWork){
            $attendanceStatus = config('constants.attendance_status.off_duty');
            $attendance_status_labels = config('constants.attendance_status_labels.off_duty');
        }
        else{

            // 勤怠の終了時間がはいっていれば「退勤済」
            if (!is_null($attendanceWork->processing_end_time)) {
                $attendanceStatus = config('constants.attendance_status.clocked_out');
                $attendance_status_labels = config('constants.attendance_status_labels.clocked_out');
            }
            // 休憩が存在し、一番最新の休憩の終了時間が入っていなければ「休憩中」
            elseif ($attendanceRest && is_null($attendanceRest->processing_end_time)) {
                $attendanceStatus = config('constants.attendance_status.on_break');
                $attendance_status_labels = config('constants.attendance_status_labels.on_break');
            }
            else{
                $attendanceStatus = config('constants.attendance_status.on_duty');    
                $attendance_status_labels = config('constants.attendance_status_labels.on_duty');
            }
        }
        return [
            'status' => $attendanceStatus,
            'label' => $attendance_status_labels,
        ];
    }

    // 一覧で休憩時間合計・勤怠時間合計を[1:05]形式にする
    private function formatMinutesToTime($minutes)
    {
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        return sprintf('%d:%02d', $hours, $mins);
    }
}
