@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/auth/register.css')}}">
@endsection

{{-- 
@section('link')
<a class="header__link" href="/login">login</a>
@endsection
--}}

@section('content')
<div class="register-form">
  <h2 class="content__heading">会員登録</h2>
  <div class="register-form__inner">
    <form class="register-form__form" action="/register" method="post">
      @csrf
      <div class="register-form__group">
        <label class="register-form__label" for="name">ユーザー名</label>
        <input class="register-form__input" type="text" name="name" id="name" value="{{ old('name') }}">
        <p class="register-form__error-message">
          @error('name')
          {{ $message }}
          @enderror
        </p>
      </div>
      <div class="register-form__group">
        <label class="register-form__label" for="email">メールアドレス</label>
        <input class="register-form__input" type="mail" name="email" id="email" value="{{ old('email') }}">
        <p class="register-form__error-message">
          @error('email')
          {{ $message }}
          @enderror
        </p>
      </div>
      <div class="register-form__group">
        <label class="register-form__label" for="password">パスワード</label>
        <input class="register-form__input" type="password" name="password" id="password" value="{{ old('password') }}">
        <p class="register-form__error-message">
          @error('password')
          {{ $message }}
          @enderror
        </p>
      </div>
      <div class="register-form__group">
        <label class="register-form__label" for="password">確認用パスワード</label>
        <input class="register-form__input" type="password" name="password_confirmation" id="password_confirmation" value="{{ old('password_confirmation') }}">
        <p class="register-form__error-message">
          @error('password_confirmation')
          {{ $message }}
          @enderror
        </p>
      </div>
      <input class="register-form__btn btn__big" type="submit" value="登録する">
      <a class="register-form__link link" href="/login">ログインはこちら</a>
      <input type="hidden" name="role" value="{{ config('constants.roles.user') }}">
    </form>
  </div>
</div>
@endsection('content')