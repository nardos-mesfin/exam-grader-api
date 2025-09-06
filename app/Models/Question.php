<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $fillable = [
        'exam_id',
        'question_number',
        'question_type',
        'correct_answer',
        'marks',
    ];
    
    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }
}
