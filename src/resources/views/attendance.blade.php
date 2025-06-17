@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance.css')}}">
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
<div class="status-section">
  <div class="status__tab">
    {{ $label }}
  </div>
  <div class="date__tab">
    @php
    $weekMap = ['日', '月', '火', '水', '木', '金', '土'];
    $weekday = $weekMap[now()->dayOfWeek];
    @endphp
    {{ now()->format('Y年n月j日') }}（{{ $weekday }}）
  </div>
  <div class="time__tab">
    {{ now()->format('H:i') }}
  </div>

  <form action="/attendance/store/" class="button-container" method="post">
  @csrf
    @switch($status) 
      @case (config('constants.attendance_status.off_duty'))
        <button class="check__button btn" name="action" value="clock_in">出勤</button>
        @break
      @case (config('constants.attendance_status.on_break'))
        <button class="rest__button btn" name="action" value="break_end">休憩戻</button>
        @break
      @case (config('constants.attendance_status.on_duty'))
        <button class="check__button btn" name="action" value="clock_out">退勤</button>
        <button class="rest__button btn" name="action" value="break_start">休憩入</button>
        @break
      @case (config('constants.attendance_status.clocked_out'))
        <span class="out_message">お疲れ様でした</span>
        @break
    @endswitch
  </form>
</div>
@endsection('content')