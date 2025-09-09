<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamSubmission extends Model
{
    protected $fillable = [
        'exam_id',
        'user_id',
        'student_name',
        'final_score',
        'total_possible_marks',
        'ai_raw_grades',
    ];
    
    protected $casts = [
        'ai_raw_grades' => 'array',
    ];
    
    public function studentAnswers()
    {
        return $this->hasMany(StudentAnswer::class);
    }
    
    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }
}
