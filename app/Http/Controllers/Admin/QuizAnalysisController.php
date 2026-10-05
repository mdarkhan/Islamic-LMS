<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Services\Quiz\QuestionAnalysisService;
use Illuminate\View\View;

/** Which questions did students miss? Read-only; gated by results.view on the route. */
class QuizAnalysisController extends Controller
{
    public function show(Quiz $quiz, QuestionAnalysisService $analysis): View
    {
        abort_if($quiz->status === Quiz::STATUS_DRAFT && ! $quiz->hasOfficialAttempts(), 404);

        return view('admin.quizzes.analysis', [
            'quiz' => $quiz,
            'report' => $analysis->forQuiz($quiz),
        ]);
    }
}
