<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class LoginUserTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    // メールアドレスが未入力の場合、バリデーションメッセージが表示される ------------------
    public function test_login_user_validate_email()
    {
        $response = $this->post('/login', [
            'email' => "",
            'password' => "password",
            'role' => config('constants.roles.user'),
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('email');

        $errors = session('errors');
        $this->assertEquals('メールアドレスを入力してください', $errors->first('email'));
    }

    // パスワードが未入力の場合、バリデーションメッセージが表示される ------------------
    public function test_login_user_validate_password()
    {
        $response = $this->post('/login', [
            'email' => "user1@test.com",
            'password' => "",
            'role' => config('constants.roles.user'),
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('password');

        $errors = session('errors');
        $this->assertEquals('パスワードを入力してください', $errors->first('password'));
    }

    // 登録内容と一致しない場合、バリデーションメッセージが表示される ------------------
    public function test_login_user_validate_user()
    {
        $response = $this->post('/login', [
            'email' => "user1@test.com",
            'password' => "123123123",
            'role' => config('constants.roles.user'),
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('email');

        $errors = session('errors');
        $this->assertEquals('ログイン情報が登録されていません', $errors->first('email'));
    }

}