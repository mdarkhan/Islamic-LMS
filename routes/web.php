<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AskUstazController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\Student;
use App\Http\Controllers\ZakatController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicController::class, 'home'])->name('home');

// Interface language toggle (available to everyone, including guests).
Route::post('locale', [\App\Http\Controllers\LocaleController::class, 'update'])->name('locale.update');

/*
 * Public knowledge platform (Phase 9) — no login required.
 */
Route::get('articles', [BlogController::class, 'index'])->name('blog.index');
Route::get('articles/{post:slug}', [BlogController::class, 'show'])->name('blog.show');

Route::get('books/{book:slug}', [BookController::class, 'show'])->name('books.show');

Route::get('search', [SearchController::class, 'index'])->name('search.index');

Route::get('zakat-calculator', [ZakatController::class, 'index'])->name('zakat.index');
Route::post('zakat-calculator', [ZakatController::class, 'calculate'])->name('zakat.calculate');

Route::get('ask-ustaz', [AskUstazController::class, 'show'])->name('ask-ustaz.show');
// Email-only; rate-limited per IP. The question is never persisted.
Route::post('ask-ustaz', [AskUstazController::class, 'store'])->name('ask-ustaz.store')->middleware('throttle:5,10');

Route::get('contact', [ContactController::class, 'show'])->name('contact.show');
// Email-only; rate-limited per IP. The message is never persisted.
Route::post('contact', [ContactController::class, 'store'])->name('contact.store')->middleware('throttle:5,10');

Route::get('sitemap.xml', [SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('robots.txt', [SitemapController::class, 'robots'])->name('robots');

/*
 * Authentication
 */
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');

    // Self-service reset by email (accounts that have one). Same response whatever the account.
    Route::get('password/forgot', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('password/forgot', [PasswordResetController::class, 'sendLink'])->middleware('throttle:6,1')->name('password.email');
    Route::get('password/reset/{token}', [PasswordResetController::class, 'form'])->name('password.reset');
    Route::post('password/reset', [PasswordResetController::class, 'reset'])->middleware('throttle:10,1')->name('password.update');
});

Route::middleware(['auth', 'account.active'])->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    // Forced/temporary password change — exempt from the password.changed guard.
    Route::get('password/change', [PasswordChangeController::class, 'show'])->name('password.change');
    Route::put('password/change', [PasswordChangeController::class, 'update'])->name('password.change.update');
});

/*
 * Notifications — the bell icon. Shared by both roles: a notification always belongs
 * to the authenticated user, never a role-scoped id, so one route set serves both.
 */
Route::middleware(['auth', 'account.active', 'password.changed'])->group(function () {
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/poll', [NotificationController::class, 'poll'])->name('notifications.poll');
    Route::put('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::get('notifications/{notification}/open', [NotificationController::class, 'open'])->name('notifications.open');
    Route::delete('notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
});

/*
 * Student area
 */
Route::middleware(['auth', 'account.active', 'password.changed', 'role:student'])->group(function () {
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
    Route::get('practice-attempts/{attempt}/status', [Student\PracticeController::class, 'status'])->name('student.practice.status');
    Route::put('practice-attempts/{attempt}/answers/{question}', [Student\PracticeController::class, 'saveAnswer'])->name('student.practice.answer');
    Route::post('practice-attempts/{attempt}/submit', [Student\PracticeController::class, 'submit'])->name('student.practice.submit');
    Route::get('practice-attempts/{attempt}/result', [Student\PracticeController::class, 'result'])->name('student.practice.result');

    // Leaderboards (Phase 8). Per-quiz visibility is gated server-side.
    Route::get('leaderboards', [Student\LeaderboardController::class, 'overall'])->name('student.leaderboards.overall');
    Route::get('leaderboards/{quiz}', [Student\LeaderboardController::class, 'quiz'])->name('student.leaderboards.quiz');

    Route::get('points', [Student\PointController::class, 'index'])->name('student.points');
    Route::post('rewards/seen', [Student\RewardController::class, 'seen'])->name('student.rewards.seen');

    // Messaging with the ustaz. The thread is always resolved from the authenticated
    // student, so there is no conversation id a student could tamper with.
    Route::get('messages', [Student\MessageController::class, 'index'])->name('student.messages.index');
    Route::get('messages/poll', [Student\MessageController::class, 'poll'])->name('student.messages.poll');
    Route::post('messages', [Student\MessageController::class, 'store'])->name('student.messages.store')->middleware('throttle:30,1');

    // Daily amol tracker. No amol/entry id in the URL — always the caller's own day,
    // and a checkmark can only ever be written for today (AmolService enforces this).
    Route::get('amol', [Student\AmolController::class, 'index'])->name('student.amol.index');
    Route::put('amol/{amol}/toggle', [Student\AmolController::class, 'toggle'])->name('student.amol.toggle')->middleware('throttle:120,1');

    Route::get('profile', [Student\ProfileController::class, 'edit'])->name('student.profile.edit');
    Route::put('profile', [Student\ProfileController::class, 'update'])->name('student.profile.update');
    Route::put('profile/password', [Student\ProfileController::class, 'updatePassword'])->name('student.profile.password');
});

/*
 * Admin area
 */
Route::prefix('admin')->name('admin.')->middleware(['auth', 'account.active', 'password.changed', 'role:super_admin,admin'])->group(function () {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

    // The signed-in admin's own account (password). No perm: — everyone may manage their own.
    Route::get('account', [Admin\AccountController::class, 'edit'])->name('account.edit');
    Route::put('account/password', [Admin\AccountController::class, 'updatePassword'])->name('account.password');
    Route::get('search', [Admin\SearchController::class, 'index'])->name('search.index');

    // Students — literal routes are declared before the {student} wildcard so
    // /students/create and /students/import are never captured as an id.
    Route::get('students', [Admin\StudentController::class, 'index'])->name('students.index')->middleware('perm:students.view');
    Route::get('students/export', [Admin\StudentController::class, 'export'])->name('students.export')->middleware('perm:students.view');
    Route::put('students/bulk-status', [Admin\StudentController::class, 'bulkStatus'])->name('students.bulk-status')->middleware('perm:students.suspend');
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
    Route::get('points/export', [Admin\PointController::class, 'export'])->name('points.export')->middleware('perm:points.view');
    Route::get('points/bulk', [Admin\PointController::class, 'bulkForm'])->name('points.bulk.form')->middleware('perm:points.grant');
    Route::post('points/bulk', [Admin\PointController::class, 'bulkStore'])->name('points.bulk.store')->middleware('perm:points.grant');
    Route::get('points/{student}', [Admin\PointController::class, 'show'])->name('points.show')->middleware('perm:points.view');
    Route::post('points/{student}', [Admin\PointController::class, 'store'])->name('points.store')->middleware('perm:points.grant');

    // Courses — reorder is declared before the resource so PUT courses/reorder is
    // not captured by the {course} update route.
    Route::middleware('perm:courses.manage')->group(function () {
        Route::put('courses/{course}/move/{direction}', [Admin\CourseController::class, 'move'])->name('courses.move')->whereIn('direction', ['up', 'down']);
        Route::put('courses/{course}/publish', [Admin\CourseController::class, 'togglePublish'])->name('courses.publish');
        Route::post('courses/{course}/award-toppers', [Admin\CourseController::class, 'awardToppers'])->name('courses.award-toppers');
        Route::resource('courses', Admin\CourseController::class)->except(['show']);
    });

    // Lessons
    Route::middleware('perm:lessons.manage')->group(function () {
        Route::put('lessons/{lesson}/publish', [Admin\LessonController::class, 'togglePublish'])->name('lessons.publish');
        Route::resource('lessons', Admin\LessonController::class)->except(['show']);
    });

    // Books
    Route::middleware('perm:books.manage')->group(function () {
        Route::put('books/{book}/move/{direction}', [Admin\BookController::class, 'move'])->name('books.move')->whereIn('direction', ['up', 'down']);
        Route::put('books/{book}/publish', [Admin\BookController::class, 'togglePublish'])->name('books.publish');
        Route::resource('books', Admin\BookController::class)->except(['show']);
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
    Route::put('quizzes/{quiz}/release', [Admin\QuizController::class, 'releaseNow'])->name('quizzes.release')->middleware('perm:results.release');

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
    Route::get('quizzes/{quiz}/analysis', [Admin\QuizAnalysisController::class, 'show'])->name('quizzes.analysis')->middleware('perm:results.view');
    Route::post('results/{attempt}/adjust', [Admin\ResultController::class, 'adjust'])->name('results.adjust')->middleware('perm:results.adjust');

    // Answer-key correction / regrade (the only sanctioned way to change a locked key).
    Route::get('quizzes/{quiz}/regrade', [Admin\RegradeController::class, 'create'])->name('quizzes.regrade.create')->middleware('perm:results.regrade');
    Route::post('quizzes/{quiz}/regrade/preview', [Admin\RegradeController::class, 'preview'])->name('quizzes.regrade.preview')->middleware('perm:results.regrade');
    Route::post('quizzes/{quiz}/regrade', [Admin\RegradeController::class, 'apply'])->name('quizzes.regrade.apply')->middleware('perm:results.regrade');

    // Content CMS (Phase 9). Categories + create are declared before the {post} wildcard.
    Route::middleware('perm:posts.manage')->group(function () {
        Route::get('posts', [Admin\PostController::class, 'index'])->name('posts.index');
        Route::get('posts/create', [Admin\PostController::class, 'create'])->name('posts.create');
        Route::post('posts', [Admin\PostController::class, 'store'])->name('posts.store');

        Route::get('posts/categories', [Admin\PostCategoryController::class, 'index'])->name('posts.categories.index');
        Route::post('posts/categories', [Admin\PostCategoryController::class, 'store'])->name('posts.categories.store');
        Route::put('posts/categories/{category}', [Admin\PostCategoryController::class, 'update'])->name('posts.categories.update');
        Route::delete('posts/categories/{category}', [Admin\PostCategoryController::class, 'destroy'])->name('posts.categories.destroy');

        Route::get('posts/{post}/edit', [Admin\PostController::class, 'edit'])->name('posts.edit');
        Route::put('posts/{post}', [Admin\PostController::class, 'update'])->name('posts.update');
        Route::get('posts/{post}/preview', [Admin\PostController::class, 'preview'])->name('posts.preview');
        Route::post('posts/{post}/publish', [Admin\PostController::class, 'publish'])->name('posts.publish');
        Route::post('posts/{post}/archive', [Admin\PostController::class, 'archive'])->name('posts.archive');
        Route::delete('posts/{post}', [Admin\PostController::class, 'destroy'])->name('posts.destroy');
    });

    // Notices
    Route::middleware('perm:notices.manage')->group(function () {
        Route::get('notices', [Admin\NoticeController::class, 'index'])->name('notices.index');
        Route::post('notices', [Admin\NoticeController::class, 'store'])->name('notices.store');
        Route::put('notices/{notice}', [Admin\NoticeController::class, 'update'])->name('notices.update');
        Route::put('notices/{notice}/toggle', [Admin\NoticeController::class, 'toggle'])->name('notices.toggle');
        Route::delete('notices/{notice}', [Admin\NoticeController::class, 'destroy'])->name('notices.destroy');
    });

    // FAQ
    Route::middleware('perm:faqs.manage')->group(function () {
        Route::get('faqs', [Admin\FaqController::class, 'index'])->name('faqs.index');
        Route::post('faqs', [Admin\FaqController::class, 'store'])->name('faqs.store');
        Route::put('faqs/{faq}', [Admin\FaqController::class, 'update'])->name('faqs.update');
        Route::put('faqs/{faq}/toggle', [Admin\FaqController::class, 'togglePublish'])->name('faqs.toggle');
        Route::delete('faqs/{faq}', [Admin\FaqController::class, 'destroy'])->name('faqs.destroy');
    });

    // Messaging — the ustaz-side shared inbox. `start` is declared before the
    // {conversation} wildcard so it is never captured as an id.
    Route::middleware('perm:messages.view')->group(function () {
        Route::get('messages', [Admin\MessageController::class, 'index'])->name('messages.index');
        Route::post('messages/start', [Admin\MessageController::class, 'start'])->name('messages.start');
        Route::get('messages/{conversation}', [Admin\MessageController::class, 'show'])->name('messages.show');
        Route::get('messages/{conversation}/poll', [Admin\MessageController::class, 'poll'])->name('messages.poll');
        Route::post('messages/{conversation}', [Admin\MessageController::class, 'store'])->name('messages.store')->middleware('throttle:60,1');
        Route::delete('messages/{conversation}', [Admin\MessageController::class, 'destroy'])->name('messages.destroy');
    });

    // Daily amol tracker — read-only checklists plus a per-day comment.
    Route::middleware('perm:amol.view')->group(function () {
        Route::get('amol', [Admin\AmolController::class, 'index'])->name('amol.index');
        Route::get('amol/{student}', [Admin\AmolController::class, 'show'])->name('amol.show');
        Route::post('amol/{student}/note', [Admin\AmolController::class, 'saveNote'])->name('amol.note');
    });

    // Settings (general / zakat / calendar). Secrets are never editable here.
    Route::middleware('perm:settings.manage')->group(function () {
        Route::get('settings', [Admin\SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings/general', [Admin\SettingController::class, 'updateGeneral'])->name('settings.general');
        Route::put('settings/zakat', [Admin\SettingController::class, 'updateZakat'])->name('settings.zakat');
        Route::put('settings/calendar', [Admin\SettingController::class, 'updateCalendar'])->name('settings.calendar');
        Route::put('settings/about', [Admin\SettingController::class, 'updateAbout'])->name('settings.about');
    });

    // Audit
    Route::get('audit', [Admin\AuditController::class, 'index'])->name('audit.index')->middleware('perm:audit.view');

    // Staff & role management — super_admin only, deliberately gated by role: rather
    // than perm:. Creating another admin, or editing what a role may do, is a
    // super-user capability that must never be delegable by handing out a permission.
    Route::middleware('role:super_admin')->group(function () {
        Route::get('staff', [Admin\StaffController::class, 'index'])->name('staff.index');
        Route::get('staff/create', [Admin\StaffController::class, 'create'])->name('staff.create');
        Route::post('staff', [Admin\StaffController::class, 'store'])->name('staff.store');
        Route::get('staff/{staff}/edit', [Admin\StaffController::class, 'edit'])->name('staff.edit');
        Route::put('staff/{staff}', [Admin\StaffController::class, 'update'])->name('staff.update');
        Route::put('staff/{staff}/status', [Admin\StaffController::class, 'updateStatus'])->name('staff.status');
        Route::post('staff/{staff}/reset-password', [Admin\StaffController::class, 'resetPassword'])->name('staff.reset-password');

        Route::get('roles', [Admin\RoleController::class, 'index'])->name('roles.index');
        Route::get('roles/create', [Admin\RoleController::class, 'create'])->name('roles.create');
        Route::post('roles', [Admin\RoleController::class, 'store'])->name('roles.store');
        Route::get('roles/{role}/edit', [Admin\RoleController::class, 'edit'])->name('roles.edit');
        Route::put('roles/{role}', [Admin\RoleController::class, 'update'])->name('roles.update');
        Route::delete('roles/{role}', [Admin\RoleController::class, 'destroy'])->name('roles.destroy');
    });
});
