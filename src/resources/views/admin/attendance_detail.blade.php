@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance_detail.css')}}">
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
{{--更新完了メッセージ--}}
@if (session('status'))
  <div class="alert-success">
    {{ session('status') }}
  </div>
@endif
<div class="detail-section" >
<form action="/admin/attendance/attendanceupdateStore" method="post">
  @csrf
  <h1 class="title">勤怠詳細</h1>
  <input type="hidden" name="id" id="id" value="{{ $attendance->id }}">
  <table class="attendance-table">
    <tbody>
      <tr>
        <td class="sub_title">名前</td>
        <td class="td_content">{{ $attendance->user->name }}</td>
      </tr>
      <tr>
        <td class="sub_title">日付</td>
        <td class="td_content">
          {{ \Carbon\Carbon::parse($attendance->processing_date)->format('Y年n月j日') }}
          <input type="hidden" name="processing_date" id="processing_date" value="{{ $attendance->processing_date }}">
        </td>
      </tr>
      <tr>
        <td class="sub_title">出勤・退勤</td>
        <td class="td_content">
          <input class="{{($hasPending || $isApproved) ? 'time__request-disabled' : 'time__input' }}" type="time" id="work_processing_start_time" name="work_processing_start_time" value="{{ old('work_processing_start_time', substr($attendance->processing_start_time, 0, 5)) }}" {{ ($hasPending || $isApproved) ? 'readonly' : '' }}>  
          <span class="time-separator">〜</span>
          <input class="{{ ($hasPending || $isApproved) ? 'time__request-disabled' : 'time__input' }}" type="time" id="work_processing_end_time" name="work_processing_end_time" value="{{ old('work_processing_end_time', substr($attendance->processing_end_time, 0, 5)) }}" {{ ($hasPending || $isApproved) ? 'readonly' : '' }}>  
          <p class="comment__error-message">
            @error('work_processing_start_time')
              {{ $message }}
            @enderror
            @error('work_processing_end_time')
              {{ $message }}
            @enderror
          </p>
        </td>
      </tr>
      @if (!$attendanceRests->isEmpty())
        @foreach($attendanceRests as $index => $attendanceRest)
          <tr>
            <td class="sub_title">
              {{ $index === 0 ? '休憩' : '休憩' . ($index + 1) }}
            </td>
            <td class="td_content">
              <input class="{{ ($hasPending || $isApproved) ? 'time__request-disabled' : 'time__input' }}" type="time" name="rest_processing_start_time[]" value="{{ old('rest_processing_start_time.' . $index, substr($attendanceRest->processing_start_time, 0, 5)) }}" {{ ($hasPending || $isApproved) ? 'readonly' : '' }}>
              <span class="time-separator">〜</span>
              <input class="{{ ($hasPending || $isApproved) ? 'time__request-disabled' : 'time__input' }}" type="time" name="rest_processing_end_time[]" value="{{ old('rest_processing_end_time.' . $index, substr($attendanceRest->processing_end_time, 0, 5)) }}" {{ ($hasPending || $isApproved) ? 'readonly' : '' }}>
              <p class="comment__error-message">
                @error('rest_processing_start_time.' . $index)
                  {{ $message }}
                @enderror
                @error('rest_processing_end_time.' . $index)
                  {{ $message }}
                @enderror
              </p>
            </td>
          </tr>
        @endforeach
      @endif
      {{-- 新規追加行 --}}
      @php
        if (isset($index)) {
          $newIndex = $index + 1;
          $newDispIndex = $index + 2;
        } else {
            $newIndex = 0;
        }
      @endphp
      {{-- 承認 時は非表示 --}}
      @if (!$hasPending && !$isApproved)
      <tr>
        <td class="sub_title">休憩{{ isset($index) ? $newDispIndex : '' }}</td>
        <td class="td_content">
          <input class="time__input" type="time" name="rest_processing_start_time[]" value="{{ old('rest_processing_start_time.' . $newIndex) }}">
          <span class="time-separator">〜</span>
          <input class="time__input" type="time" name="rest_processing_end_time[]" value="{{ old('rest_processing_end_time.' . $newIndex) }}">
          <p class="comment__error-message">
            @error('rest_processing_start_time.' . $newIndex)
              {{ $message }}
            @enderror
            @error('rest_processing_end_time.' . $newIndex)
              {{ $message }}
            @enderror
          </p>
        </td>
      </tr>
      @endif
      <tr>
        <td class="sub_title">備考</td>
        <td class="td_content">
          @if ($hasPending || $isApproved)
            <div class="comment__textarea_request-disabled">
              {{ old('comment', $commentRequest->comment ?? '修正理由を記入ください') }}
            </div>
          @else
            <textarea class="comment__textarea" placeholder="修正理由を記入ください" name="comment" id="comment">{{ old('comment', $commentRequest->comment ?? '') }}</textarea>
          @endif
          <p class="comment__error-message">
            @error('comment')
              {{ $message }}
            @enderror
          </p>
        </td>
      </tr>
    </tbody>
  </table>
@if ($isApproved)
  </form>
  <p class="comment__request-message">*承認済みのため修正はできません。</p>
@elseif ($hasPending)
</form>
<form class="" action="/admin/attendance/attendanceapproveStore" method="post">
    @csrf
    <div class="btn_area">
      <button class="edit__button btn">承認</button>
    </div>
    <input type="hidden" name="id" id="id" value="{{ $attendance->id }}">
    <input type="hidden" name="commentrequestId" id="commentrequestId" value="{{ $commentrequestId }}">
  </form>
  @else
    <div class="btn_area">
      <button class="edit__button btn">修正</button>
    </div>
    <input type="hidden" name="id" id="id" value="{{ $attendance->id }}">
    <input type="hidden" name="commentrequestId" id="commentrequestId" value="{{ $commentrequestId }}">
  </form>
@endif
</div>
@endsection('content')