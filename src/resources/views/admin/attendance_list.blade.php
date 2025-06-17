@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance_list.css')}}">
@endsection

@section('link')
<div class="header__link">
  <a class="header__link link" href="/admin/attendance/list">勤怠一覧</a>
  <a class="header__link link" href="/admin/staff/list">スタッフ一覧</a>
  <a class="header__link link" href="/stamp_correction_request/list?tab=pending">申請</a>
  <form action="/logout" method="POST">
    @csrf
    <button class="header-logout__btn">ログアウト</button>
  </form>
</div>
@endsection

@section('content')
<div class="list-section">
  <h1 class="title">勤怠一覧</h1>

  <div class="select-month">
    <a class="prev-month" href="/admin/attendance/list?action=prev&ymd={{ $ymd }}">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#0073CC" class="size-6 left-arrow">
        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
      </svg>
      前日
    </a>
    <span class="month-display">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#000000" class="icon-calendar">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
      </svg>
      {{ $ymd }}
      <input type="hidden" name="ymd" value="{{ $ymd }}">
    </span>
    <a class="next-month" href="/admin/attendance/list?action=next&ymd={{ $ymd }}">
      次日
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#0073CC" class="size-6 right-arrow">
        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
      </svg>
    </a>
  </div>
  @if ($results->isEmpty())
    <p class="nodata">勤怠が存在しません</p>
  @else
    <table class="attendance-table">
      <thead>
        <tr>
          <th>名前</th>
          <th>出勤</th>
          <th>退勤</th>
          <th>休憩</th>
          <th>合計</th>
          <th>詳細</th>
        </tr>
      </thead>
      <tbody>
      @foreach($results as $result)
        <tr>
          <td>{{ $result->name }}</td>
          <td>{{ $result->start_time }}</td>
          <td>{{ $result->end_time }}</td>
          <td>{{ $result->rest_total }}</td>
          <td>{{ $result->work_total }}</td>
          <td>
            <a href="/admin/attendance/{{ $result->detail_id }}" class="detail__link">詳細</a>
          </td>
        </tr>
      @endforeach
      </tbody>
    </table>
  @endif

</div>
@endsection('content')