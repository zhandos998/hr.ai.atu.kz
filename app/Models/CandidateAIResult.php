<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidateAIResult extends Model
{
    protected $table = 'candidate_ai_results';
    protected $fillable = [
        'worker_id',
        'position_id',
        'lang',
        'score',
        'decision',
        'education_match',
        'experience_match',
        'soft_skills_match',
        'summary_kk',
        'summary_ru',
        'summary_en'
    ];
}
