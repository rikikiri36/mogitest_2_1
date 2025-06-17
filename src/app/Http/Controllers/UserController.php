<?php

namespace App\Http\Controllers;

use App\Models\User;

class UserController extends Controller
{

    // スタッフ一覧----------------//
    public function index(){
        $loginId = auth()->id();

        $users = User::where('role', config('constants.roles.user'))
        ->orderBy('id')
        ->get();

        return view('admin.user_list', compact('users'));

    }
}
