<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Support\Facades\Auth;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        $user = $request->user();

        if ($user->role === config('constants.roles.admin')) {
            Auth::guard('admin')->login($user);
            return redirect()->to('/admin/attendance/list');
        }
        return redirect()->to('/attendance');
    }
}