<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per selected option. Retaining the student's raw selections
 * independently of the answer key is what makes regrading possible.
 */
#[Fillable(['answer_id', 'option_id'])]
class QuizAnswerOption extends Model
{
    public $timestamps = false;
}
