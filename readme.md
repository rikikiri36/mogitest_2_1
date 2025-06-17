# coachtech 勤怠管理アプリ

## プロジェクト概要

- **サービス名**：coachtech 勤怠管理アプリ
- **サービス概要**：企業が開発した独自の勤怠管理アプリ
- **制作の背景・目的**：ユーザーの勤怠管理を効率化する
- **制作の目標**：初年度でのユーザー数 1,000 人達成

## 環境構築手順

### 1.Dockerビルド

1. git clone https://github.com/rikikiri36/mogitest_2_1
2. docker compose up -d --build

### 2.Laravel環境構築
1. docker compose exec php composer install
2. 「.env.example」をコピーして「.env」ファイルを作成し、下記環境変数の変更をする
-  DB_HOST=mysql
-  DB_DATABASE=laravel_db
-  DB_USERNAME=laravel_user
-  DB_PASSWORD=laravel_pass
3. php artisan key:generate
4. php artisan migrate:fresh
5. php artisan db:seed

## 使用技術

- PHP 8.1.32
- Laravel Framework 8.83.29
- MySQL 8.0.41
- Docker 27.5.1

## URL

### 開発環境
1. 一般ユーザーログイン

    http://localhost/login

2. 管理者ログイン

    http://localhost/admin/login

### phpMyAdmin
   http://localhost:8080/

## テストアカウント

### 一般ユーザー

| 名前   | メールアドレス                                | パスワード    |
| ---- | --------------------------------------- | -------- |
| 一般太郎 | [user1@test.com](mailto:user1@test.com) | password |
| 一般花子 | [user2@test.com](mailto:user2@test.com) | password |

### 管理者

| 名前   | メールアドレス                                | パスワード    |
| ---- | ----------------------------------------- | -------- |
| 管理三郎 | [kanri1@test.com](mailto:kanri1@test.com) | password |
| 管理四郎 | [kanri2@test.com](mailto:kanri2@test.com) | password |
