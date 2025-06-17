<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $param = [
            'name' => '一般太郎',
            'email' => 'user1@test.com',
            'password' => Hash::make('password'),
            'role' => config('constants.roles.user'),
        ];
        User::create($param);

        $param = [
            'name' => '一般花子',
            'email' => 'user2@test.com',
            'password' => Hash::make('password'),
            'role' => config('constants.roles.user'),
        ];
        User::create($param);

        $param = [
            'name' => '管理三郎',
            'email' => 'kanri1@test.com',
            'password' => Hash::make('password'),
            'role' => config('constants.roles.admin'),
        ];
        User::create($param);

        $param = [
            'name' => '管理四郎',
            'email' => 'kanri2@test.com',
            'password' => Hash::make('password'),
            'role' => config('constants.roles.admin'),
        ];
        User::create($param);
    }
}
