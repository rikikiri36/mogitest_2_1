@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/user_list.css')}}">
@endsection

@section('link')
<div class="header__link">
  <a class="header__link link" href="/admin/attendance/list">勤怠一覧</a>
  <a class="header__link link" href="/admin/staff/list">スタッフ一覧</a>
  <a class="header__link link" href="/admin/stamp_correction_request/list?tab=pending">申請一覧</a>
  <form action="/logout" method="POST">
    @csrf
    <button class="header-logout__btn">ログアウト</button>
  </form>
</div>
@endsection

@section('content')
<div class="list-section">
  <h1 class="title">スタッフ一覧</h1>

  @if ($users->isEmpty())
    <p class="nodata">スタッフが存在しません</p>
  @else
    <table class="user-table">
      <thead>
        <tr>
          <th>名前</th>
          <th>メールアドレス</th>
          <th>月次勤怠</th>
        </tr>
      </thead>
      <tbody>
      @foreach($users as $user)
        <tr>
          <td>{{ $user->name }}</td>
          <td>{{ $user->email }}</td>
          <td>
            <a href="/admin/attendance/staff/{{ $user->id }}" class="detail__link">詳細</a>
          </td>
        </tr>
      @endforeach
      </tbody>
    </table>
  @endif

</div>
@endsection('content')