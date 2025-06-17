<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        $today = Carbon::today();

        return [
            'user_id' => User::factory(),
            'type' => config('constants.type.work'),
            'processing_date' => $today->toDateString(),
            'processing_start_time' => null,
            'processing_end_time' => null,
            'request_status' => null,
        ];
    }

    public function onDuty()
    {
        return $this->state(function () {
            $today = Carbon::today();
            return [
                'type' => config('constants.type.work'),
                'processing_start_time' => $today->copy()->setTime(9, 0),
            ];
        });
    }

    public function onBreak()
    {
        return $this->state(function () {
            $today = Carbon::today();
            return [
                'type' => config('constants.type.rest'),
                'processing_start_time' => $today->copy()->setTime(12, 0),
                'processing_end_time' => null,
            ];
        });
    }

    public function breakEnded()
    {
        return $this->state(function () {
            $today = Carbon::today();
            return [
                'type' => config('constants.type.rest'),
                'processing_start_time' => $today->copy()->setTime(12, 0),
                'processing_end_time' => $today->copy()->setTime(12, 30),
            ];
        });
    }

    public function clockedOut()
    {
        return $this->state(function () {
            $today = Carbon::today();
            return [
                'type' => config('constants.type.work'),
                'processing_start_time' => $today->copy()->setTime(9, 0),
                'processing_end_time' => $today->copy()->setTime(18, 0),
            ];
        });
    }

}
