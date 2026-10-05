<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** One "student X was told about quiz Y (kind Z)" row. Written only by ExamNotificationService. */
#[Fillable(['user_id', 'quiz_id', 'kind', 'emailed'])]
class NotificationDelivery extends Model
{
    protected function casts(): array
    {
        return ['emailed' => 'boolean'];
    }
}
