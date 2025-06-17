@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance_requests.css')}}">
@endsection

@section('link')
<div class="header__link">
  <a class="header__link link" href="/attendance">勤怠</a>
  <a class="header__link link" href="/attendance/list">勤怠一覧</a>
  <a class="header__link link" href="/stamp_correction_request/list?tab=pending">申請</a>
  <form action="/logout" method="POST">
    @csrf
    <button class="header-logout__btn">ログアウト</button>
  </form>
</div>
@endsection

@section('content')
<div class="list-section">
  <h1 class="title">申請一覧</h1>

<div class="requests-list">
  <a href="/stamp_correction_request/list?tab=pending" class="requests-list__tab link {{ request('tab') != 'pending' ? 'notactive' : '' }}">承認待ち</a>
  <a href="/stamp_correction_request/list?tab=approved" class="requests-list__tab link {{ request('tab') != 'approved' ? 'notactive' : '' }}">承認済み</a>
</div>

  @if ($requestLists->isEmpty())          
    <p class="nodata">勤怠がありません</p>
  @else
  <table class="attendance-table">
    <thead>
      <tr>
        <th>状態</th>
        <th>名前</th>
        <th>対象日</th>
        <th>申請理由</th>
        <th>申請日</th>
        <th>詳細</th>
      </tr>
    </thead>
    <tbody>
    @foreach($requestLists as $requestList)
      <tr>
        <td>{{ $requestList->request_status === 'approved' ? '承認済み' : '承認待ち' }}</td>
        <td>{{ $requestList->user->name }}</td>
        <td>{{ \Carbon\Carbon::parse($requestList->processing_date)->format('Y/m/d') }}</td>
        <td class="ellipsis">{{ $requestList->comment }}</td>
        <td>{{ \Carbon\Carbon::parse($requestList->created_at)->format('Y/m/d') }}</td>
        <td>
          <a href="/attendance/{{$requestList->detail_id}}" class="detail__link">詳細</a></td>
        </tr>
    @endforeach
    </tbody>
  </table>
  @endif
  <input type="hidden" name="tab" id="tab" value="{{ request('tab') }}">
@endsection('content')