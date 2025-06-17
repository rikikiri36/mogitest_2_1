<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommentRequest extends Model
{
    use HasFactory;

    protected $guarded = [
        'id',
    ];

    protected $table = 'comment_requests';

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}



