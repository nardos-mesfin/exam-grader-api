<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentAnswer extends Model
{
    protected $fillable = [
        'exam_submission_id',
        'question_id',
        'student_answer',
        'final_score',
    ];
    
    public function examSubmission()
    {
        return $this->belongsTo(ExamSubmission::class);
    }
}
