<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\Student;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicController::class, 'home'])->name('home');

// Interface language toggle (available to everyone, including guests).
Route::post('locale', [\App\Http\Controllers\LocaleController::class, 'update'])->name('locale.update');

/*
 * Authentication
 */
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    // Forced/temporary password change — exempt from the password.changed guard.
    Route::get('password/change', [PasswordChangeController::class, 'show'])->name('password.change');
    Route::put('password/change', [PasswordChangeController::class, 'update'])->name('password.change.update');
});

/*
 * Student area
 */
Route::middleware(['auth', 'password.changed', 'role:student'])->group(function () {
    Route::get('dashboard', [Student\DashboardController::class, 'index'])->name('student.dashboard');

    Route::get('courses', [Student\CourseController::class, 'index'])->name('student.courses.index');
    Route::get('courses/{lesson:slug}', [Student\CourseController::class, 'show'])->name('student.courses.show');

    Route::get('exams', [Student\ExamController::class, 'index'])->name('student.exams.index');

    // Live official exam (Phase 7). Start/resume is idempotent in the service; the
    // attempt id — not a client-supplied user id — identifies every later request.
    Route::post('exams/{quiz}/start', [Student\ExamAttemptController::class, 'start'])->name('student.exams.start');
    Route::get('exam-attempts/{attempt}', [Student\ExamAttemptController::class, 'show'])->name('student.attempts.show');
    Route::get('exam-attempts/{attempt}/status', [Student\ExamAttemptController::class, 'status'])->name('student.attempts.status');
    Route::put('exam-attempts/{attempt}/answers/{question}', [Student\ExamAttemptController::class, 'saveAnswer'])->name('student.attempts.answer');
    Route::post('exam-attempts/{attempt}/submit', [Student\ExamAttemptController::class, 'submit'])->name('student.attempts.submit');
    Route::get('exam-attempts/{attempt}/result', [Student\ExamAttemptController::class, 'result'])->name('student.attempts.result');

    // Results & detailed answer sheets (Phase 8). Release is enforced in the controller.
    Route::get('results', [Student\ResultController::class, 'index'])->name('student.results.index');
    Route::get('results/{attempt}', [Student\ResultController::class, 'show'])->name('student.results.show');

    // Practice Mode (Phase 8). Availability (the official key being safe to reveal) is
    // enforced on start; practice is free, untimed and unranked.
    Route::get('practice', [Student\PracticeController::class, 'index'])->name('student.practice.index');
    Route::post('practice/{quiz}/start', [Student\PracticeController::class, 'start'])->name('student.practice.start');
    Route::get('practice-attempts/{attempt}', [Student\PracticeController::class, 'show'])->name('student.practice.show');
    Route::put('practice-attempts/{attempt}/answers/{question}', [Student\PracticeController::class, 'saveAnswer'])->name('student.practice.answer');
    Route::post('practice-attempts/{attempt}/submit', [Student\PracticeController::class, 'submit'])->name('student.practice.submit');
    Route::get('practice-attempts/{attempt}/result', [Student\PracticeController::class, 'result'])->name('student.practice.result');

    // Leaderboards (Phase 8). Per-quiz visibility is gated server-side.
    Route::get('leaderboards', [Student\LeaderboardController::class, 'overall'])->name('student.leaderboards.overall');
    Route::get('leaderboards/{quiz}', [Student\LeaderboardController::class, 'quiz'])->name('student.leaderboards.quiz');

    Route::get('points', [Student\PointController::class, 'index'])->name('student.points');

    Route::get('profile', [Student\ProfileController::class, 'edit'])->name('student.profile.edit');
    Route::put('profile', [Student\ProfileController::class, 'update'])->name('student.profile.update');
    Route::put('profile/password', [Student\ProfileController::class, 'updatePassword'])->name('student.profile.password');
});

/*
 * Admin area
 */
Route::prefix('admin')->name('admin.')->middleware(['auth', 'password.changed', 'role:super_admin,admin'])->group(function () {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

    // Students — literal routes are declared before the {student} wildcard so
    // /students/create and /students/import are never captured as an id.
    Route::get('students', [Admin\StudentController::class, 'index'])->name('students.index')->middleware('perm:students.view');
    Route::get('students/create', [Admin\StudentController::class, 'create'])->name('students.create')->middleware('perm:students.create');
    Route::post('students', [Admin\StudentController::class, 'store'])->name('students.store')->middleware('perm:students.create');
    Route::get('students/import', [Admin\StudentImportController::class, 'form'])->name('students.import.form')->middleware('perm:students.create');
    Route::post('students/import/preview', [Admin\StudentImportController::class, 'preview'])->name('students.import.preview')->middleware('perm:students.create');
    Route::get('students/import/credentials/{token}', [Admin\StudentImportController::class, 'downloadCredentials'])->name('students.import.credentials')->middleware('perm:students.create');
    Route::post('students/import', [Admin\StudentImportController::class, 'confirm'])->name('students.import.confirm')->middleware('perm:students.create');
    Route::get('students/{student}', [Admin\StudentController::class, 'show'])->name('students.show')->middleware('perm:students.view');
    Route::get('students/{student}/edit', [Admin\StudentController::class, 'edit'])->name('students.edit')->middleware('perm:students.update');
    Route::put('students/{student}', [Admin\StudentController::class, 'update'])->name('students.update')->middleware('perm:students.update');
    Route::put('students/{student}/status', [Admin\StudentController::class, 'updateStatus'])->name('students.status')->middleware('perm:students.suspend');
    Route::post('students/{student}/reset-password', [Admin\StudentController::class, 'resetPassword'])->name('students.reset-password')->middleware('perm:students.reset_password');

    // Points
    Route::get('points', [Admin\PointController::class, 'index'])->name('points.index')->middleware('perm:points.view');
    Route::get('points/bulk', [Admin\PointController::class, 'bulkForm'])->name('points.bulk.form')->middleware('perm:points.grant');
    Route::post('points/bulk', [Admin\PointController::class, 'bulkStore'])->name('points.bulk.store')->middleware('perm:points.grant');
    Route::get('points/{student}', [Admin\PointController::class, 'show'])->name('points.show')->middleware('perm:points.view');
    Route::post('points/{student}', [Admin\PointController::class, 'store'])->name('points.store')->middleware('perm:points.grant');

    // Courses — reorder is declared before the resource so PUT courses/reorder is
    // not captured by the {course} update route.
    Route::middleware('perm:courses.manage')->group(function () {
        Route::put('courses/reorder', [Admin\CourseController::class, 'reorder'])->name('courses.reorder');
        Route::put('courses/{course}/publish', [Admin\CourseController::class, 'togglePublish'])->name('courses.publish');
        Route::resource('courses', Admin\CourseController::class)->except(['show']);
    });

    // Lessons
    Route::middleware('perm:lessons.manage')->group(function () {
        Route::put('lessons/{lesson}/publish', [Admin\LessonController::class, 'togglePublish'])->name('lessons.publish');
        Route::resource('lessons', Admin\LessonController::class)->except(['show']);
    });

    // Quizzes — literal routes (create, import) are declared before the {quiz}
    // wildcard so they are never captured as an id.
    Route::get('quizzes', [Admin\QuizController::class, 'index'])->name('quizzes.index')->middleware('perm:quizzes.view');
    Route::get('quizzes/create', [Admin\QuizController::class, 'create'])->name('quizzes.create')->middleware('perm:quizzes.create');
    Route::post('quizzes', [Admin\QuizController::class, 'store'])->name('quizzes.store')->middleware('perm:quizzes.create');

    Route::get('quizzes/import', [Admin\QuizImportController::class, 'form'])->name('quizzes.import.form')->middleware('perm:quizzes.import');
    Route::post('quizzes/import/upload', [Admin\QuizImportController::class, 'upload'])->name('quizzes.import.upload')->middleware('perm:quizzes.import');
    Route::post('quizzes/import/preview', [Admin\QuizImportController::class, 'preview'])->name('quizzes.import.preview')->middleware('perm:quizzes.import');
    Route::post('quizzes/import/confirm', [Admin\QuizImportController::class, 'confirm'])->name('quizzes.import.confirm')->middleware('perm:quizzes.import');

    Route::get('quizzes/{quiz}/edit', [Admin\QuizController::class, 'edit'])->name('quizzes.edit')->middleware('perm:quizzes.update');
    Route::put('quizzes/{quiz}', [Admin\QuizController::class, 'update'])->name('quizzes.update')->middleware('perm:quizzes.update');
    Route::delete('quizzes/{quiz}', [Admin\QuizController::class, 'destroy'])->name('quizzes.destroy')->middleware('perm:quizzes.update');
    Route::get('quizzes/{quiz}/preview', [Admin\QuizController::class, 'preview'])->name('quizzes.preview')->middleware('perm:quizzes.view');
    Route::post('quizzes/{quiz}/duplicate', [Admin\QuizController::class, 'duplicate'])->name('quizzes.duplicate')->middleware('perm:quizzes.create');
    Route::put('quizzes/{quiz}/status', [Admin\QuizController::class, 'updateStatus'])->name('quizzes.status')->middleware('perm:quizzes.publish');

    // Questions (nested). reorder is declared before the {question} wildcard.
    Route::middleware('perm:quizzes.update')->group(function () {
        Route::get('quizzes/{quiz}/questions/create', [Admin\QuizQuestionController::class, 'create'])->name('quizzes.questions.create');
        Route::post('quizzes/{quiz}/questions', [Admin\QuizQuestionController::class, 'store'])->name('quizzes.questions.store');
        Route::put('quizzes/{quiz}/questions/reorder', [Admin\QuizQuestionController::class, 'reorder'])->name('quizzes.questions.reorder');
        Route::get('quizzes/{quiz}/questions/{question}/edit', [Admin\QuizQuestionController::class, 'edit'])->name('quizzes.questions.edit');
        Route::put('quizzes/{quiz}/questions/{question}', [Admin\QuizQuestionController::class, 'update'])->name('quizzes.questions.update');
        Route::delete('quizzes/{quiz}/questions/{question}', [Admin\QuizQuestionController::class, 'destroy'])->name('quizzes.questions.destroy');
        Route::post('quizzes/{quiz}/questions/{question}/duplicate', [Admin\QuizQuestionController::class, 'duplicate'])->name('quizzes.questions.duplicate');
    });

    // Results management (Phase 8). export is declared before the {attempt} wildcard.
    Route::get('results', [Admin\ResultController::class, 'index'])->name('results.index')->middleware('perm:results.view');
    Route::get('results/export', [Admin\ResultController::class, 'export'])->name('results.export')->middleware('perm:results.view');
    Route::get('results/{attempt}', [Admin\ResultController::class, 'show'])->name('results.show')->middleware('perm:results.view');
    Route::post('results/{attempt}/adjust', [Admin\ResultController::class, 'adjust'])->name('results.adjust')->middleware('perm:results.adjust');

    // Answer-key correction / regrade (the only sanctioned way to change a locked key).
    Route::get('quizzes/{quiz}/regrade', [Admin\RegradeController::class, 'create'])->name('quizzes.regrade.create')->middleware('perm:results.regrade');
    Route::post('quizzes/{quiz}/regrade/preview', [Admin\RegradeController::class, 'preview'])->name('quizzes.regrade.preview')->middleware('perm:results.regrade');
    Route::post('quizzes/{quiz}/regrade', [Admin\RegradeController::class, 'apply'])->name('quizzes.regrade.apply')->middleware('perm:results.regrade');

    // Audit
    Route::get('audit', [Admin\AuditController::class, 'index'])->name('audit.index')->middleware('perm:audit.view');
});
