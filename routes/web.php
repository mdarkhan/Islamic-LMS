<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\Student;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicController::class, 'home'])->name('home');

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

    // Audit
    Route::get('audit', [Admin\AuditController::class, 'index'])->name('audit.index')->middleware('perm:audit.view');
});
