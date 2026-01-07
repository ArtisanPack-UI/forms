<?php

declare(strict_types=1);

use ArtisanPackUI\Forms\Http\Controllers\FormController;
use ArtisanPackUI\Forms\Http\Controllers\SubmissionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Forms Admin Routes
|--------------------------------------------------------------------------
|
| These routes provide the admin interface for managing forms. The route
| prefix and middleware are configurable via the forms config file.
|
*/

Route::prefix(config('artisanpack.forms.admin.prefix', 'admin/forms'))
    ->middleware(config('artisanpack.forms.admin.middleware', ['web', 'auth']))
    ->name('forms.')
    ->group(function (): void {
        // Form management routes
        Route::get('/', [FormController::class, 'index'])->name('index');
        Route::get('/create', [FormController::class, 'create'])->name('create');
        Route::post('/', [FormController::class, 'store'])->name('store');
        Route::get('/{form}/edit', [FormController::class, 'edit'])->name('edit');
        Route::put('/{form}', [FormController::class, 'update'])->name('update');
        Route::delete('/{form}', [FormController::class, 'destroy'])->name('destroy');

        // All submissions (across all forms)
        Route::get('/submissions', [SubmissionController::class, 'indexAll'])->name('submissions.all');

        // Form-specific submission routes
        Route::get('/{form}/submissions', [SubmissionController::class, 'index'])->name('submissions.index');
        Route::get('/{form}/submissions/export', [SubmissionController::class, 'export'])->name('submissions.export');
        Route::get('/{form}/submissions/{submission}', [SubmissionController::class, 'show'])->name('submissions.show');

        // File upload download route (includes form and submission for authorization)
        Route::get('/{form}/submissions/{submission}/uploads/{upload}/download', [SubmissionController::class, 'downloadUpload'])->name('uploads.download');
    });
