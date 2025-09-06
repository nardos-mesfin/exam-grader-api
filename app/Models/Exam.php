<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Exam extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'subject',
        'total_marks',
    ];
    
    public function questions()
    {
        return $this->hasMany(Question::class);
    }
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
