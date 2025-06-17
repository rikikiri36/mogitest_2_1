<?php

namespace Database\Factories;

use App\Models\CommentRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommentRequestFactory extends Factory
{
    protected $model = CommentRequest::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'processing_date' => now()->toDateString(),
            'comment' => $this->faker->sentence(),
            'request_status' => config('constants.request_status.pending'),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
