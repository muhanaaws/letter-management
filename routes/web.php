<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\LetterTypeController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Lecturer\DashboardController as LecturerDashboardController;
use App\Http\Controllers\Lecturer\SubmissionController as LecturerSubmissionController;
use App\Http\Controllers\LetterAssetController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicVerificationController;
use App\Http\Controllers\Student\DashboardController as StudentDashboardController;
use App\Http\Controllers\Student\SubmissionController as StudentSubmissionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
})->middleware('guest');

// Public Letter Verification Route (Scan QR Code)
Route::get('/verify/{token}', [PublicVerificationController::class, 'verify'])->name('verify.letter');

// Per-approver verification route — each signer's QR points here.
Route::get('/verify/approver/{token}', [PublicVerificationController::class, 'verifyApprover'])->name('public.verify.approver');


Route::middleware('auth')
    ->group(function () {

        Route::get('/attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');
        Route::get('/letter/{filename}', [LetterAssetController::class, 'show'])->name('letter.asset');

        Route::prefix('admin')
            ->middleware('role:ADMIN')
            ->group(function () {

                Route::get(
                    '/dashboard',
                    [AdminDashboardController::class, 'index']
                )->name('admin.dashboard');

                Route::prefix('letters')->group(function () {
                    Route::get(
                        '/',
                        [LetterTypeController::class, 'index']
                    )->name('admin.letters.index');

                    Route::get(
                        '/create',
                        [LetterTypeController::class, 'create']
                    )->name('admin.letters.create');

                    Route::post(
                        '/',
                        [LetterTypeController::class, 'store']
                    )->name('admin.letters.store');

                    Route::get(
                        '/{letterType}/edit',
                        [LetterTypeController::class, 'edit']
                    )->name('admin.letters.edit');

                    Route::get(
                        '/{letterType}/preview',
                        [LetterTypeController::class, 'preview']
                    )->name('admin.letters.preview');

                    Route::put(
                        '/{letterType}',
                        [LetterTypeController::class, 'update']
                    )->name('admin.letters.update');

                    Route::delete(
                        '/{letterType}',
                        [LetterTypeController::class, 'destroy']
                    )->name('admin.letters.destroy');

                    Route::patch(
                        '/{letterType}/toggle-status',
                        [LetterTypeController::class, 'toggleStatus']
                    )->name('admin.letters.toggle-status');
                });


                Route::view(
                    '/settings',
                    'admin.settings'
                )->name('admin.settings');
            });

        Route::prefix('student')
            ->middleware('role:STUDENT')
            ->group(function () {

                Route::get(
                    '/dashboard',
                    [StudentDashboardController::class, 'index']
                )->name('student.dashboard');

                Route::prefix('submissions')->group(function () {
                    Route::get(
                        '/',
                        [StudentSubmissionController::class, 'index']
                    )->name('student.submissions.history');

                    Route::get(
                        '/create',
                        [StudentSubmissionController::class, 'create']
                    )->name('student.submissions.create');

                    Route::post(
                        '/',
                        [StudentSubmissionController::class, 'store']
                    )->name('student.submissions.store');

                    Route::get(
                        '/{submission}',
                        [StudentSubmissionController::class, 'detail']
                    )->name('student.submissions.detail');

                    Route::get(
                        '/{submission}/preview',
                        [StudentSubmissionController::class, 'preview']
                    )->name('student.submissions.preview');

                    Route::get(
                        '/{submission}/download',
                        [StudentSubmissionController::class, 'download']
                    )->name('student.submissions.download');
                });


                Route::view(
                    '/settings',
                    'student.settings'
                )->name('student.settings');
            });

        Route::prefix('lecturer')
            ->middleware('role:LECTURER')
            ->group(function () {

                Route::get(
                    '/dashboard',
                    [LecturerDashboardController::class, 'index']
                )->name('lecturer.dashboard');

                Route::prefix('submissions')->group(function () {
                    Route::get(
                        '/',
                        [LecturerSubmissionController::class, 'index']
                    )->name('lecturer.submissions.index');

                    Route::get(
                        '/history',
                        [LecturerSubmissionController::class, 'history']
                    )->name('lecturer.submissions.history');

                    Route::get(
                        '/{submission}',
                        [LecturerSubmissionController::class, 'show']
                    )->name('lecturer.submissions.show');

                    Route::post(
                        '/{submission}/approve',
                        [LecturerSubmissionController::class, 'approve']
                    )->name('lecturer.submissions.approve');

                    Route::post(
                        '/{submission}/reject',
                        [LecturerSubmissionController::class, 'reject']
                    )->name('lecturer.submissions.reject');
                });


                Route::view(
                    '/settings',
                    'lecturer.settings'
                )->name('lecturer.settings');
            });

        Route::get(
            '/profile',
            [ProfileController::class, 'edit']
        )->name('profile.edit');

        Route::patch(
            '/profile',
            [ProfileController::class, 'update']
        )->name('profile.update');

        Route::delete(
            '/profile',
            [ProfileController::class, 'destroy']
        )->name('profile.destroy');
    });

require __DIR__ . '/auth.php';
