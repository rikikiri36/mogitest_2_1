<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;
use Illuminate\Support\Facades\Route;

class LoginAdminTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
    }

    // メールアドレスが未入力の場合、バリデーションメッセージが表示される ---------------
    public function test_admin_login_requires_email()
    {
        // テスト用管理者を作成
        User::factory()->create([
            'email' => 'admin1@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $response = $this->from('/admin/login')->post('/login', [
            'email' => '',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $response->assertRedirect('/admin/login');
        $response->assertSessionHasErrors('email');
        $this->assertEquals('メールアドレスを入力してください', session('errors')->first('email'));
        $this->assertGuest();
    }

    // パスワードが未入力の場合、バリデーションメッセージが表示される ---------------
    public function test_admin_login_requires_password()
    {
        User::factory()->create([
            'email' => 'admin1@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $response = $this->from('/admin/login')->post('/login', [
            'email' => 'admin1@test.com',
            'password' => '',
            'role' => 'admin',
        ]);

        $response->assertRedirect('/admin/login');
        $response->assertSessionHasErrors('password');
        $this->assertEquals('パスワードを入力してください', session('errors')->first('password'));
        $this->assertGuest();
    }

    // 登録内容と一致しない場合、バリデーションメッセージが表示される ---------------
    public function test_admin_login_with_invalid_credentials()
    {
        User::factory()->create([
            'email' => 'admin1@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $response = $this->from('/admin/login')->post('/login', [
            'email' => 'admin1@test.com',
            'password' => 'wrongpassword',
            'role' => 'admin',
        ]);

        $response->assertRedirect('/admin/login');
        $response->assertSessionHasErrors('email');
        $this->assertEquals('ログイン情報が登録されていません', session('errors')->first('email'));
        $this->assertGuest();
    }
}