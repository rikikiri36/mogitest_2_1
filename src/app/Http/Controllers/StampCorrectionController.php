<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CommentRequest;

class StampCorrectionController extends Controller
{
    // 申請一覧----------------//
    public function requestList(Request $request){

        $user = $request->user();

        $loginId = auth()->id();
        $tab = $request->query('tab');
        $requestLists = collect();

        // 一般ユーザー
        if ($user->role === config('constants.roles.user')) {

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
                ->where('comment_requests.user_id', $loginId)
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
                ->where('comment_requests.user_id', $loginId)
                ->where('comment_requests.request_status', config('constants.request_status.approved'))
                ->orderBy('comment_requests.processing_date')
                ->get();

            }

            return view('attendance_requests', compact('requestLists'));

        // 管理者
        }else{

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
        
    }
}
