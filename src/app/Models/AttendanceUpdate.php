<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceUpdate extends Model
{
    use HasFactory;

    protected $guarded = [
        'id',
    ];

    protected $table = 'attendance_updates';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
