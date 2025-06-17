<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;

class LogoutResponse implements LogoutResponseContract
{
  public function toResponse($request)
  {
    $role = session('last_role');

    if ($role === 'admin') {
        return redirect('/admin/login'); // 管理者ログイン画面へ
    }

    return redirect('/login'); // 一般ユーザー用ログイン画面へ
  }
}