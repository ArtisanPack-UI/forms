<?php

/**
 * Forms package API routes.
 *
 * Defines versioned REST API routes for managing forms, fields, steps,
 * submissions, notifications, and public form rendering/submission.
 * The route prefix and middleware are configurable via the forms config file.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

use ArtisanPackUI\Forms\Http\Controllers\Api\FormApiController;
use ArtisanPackUI\Forms\Http\Controllers\Api\FormFieldApiController;
use ArtisanPackUI\Forms\Http\Controllers\Api\FormStepApiController;
use ArtisanPackUI\Forms\Http\Controllers\Api\NotificationApiController;
use ArtisanPackUI\Forms\Http\Controllers\Api\SubmissionApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Forms API Routes (Authenticated)
|--------------------------------------------------------------------------
|
| These routes provide the REST API for managing forms. The route prefix
| and middleware are configurable via the forms config file.
|
*/

Route::prefix( config( 'artisanpack.forms.api.prefix', 'api/v1/forms' ) )
    ->middleware( config( 'artisanpack.forms.api.middleware', ['api', 'auth:sanctum'] ) )
    ->name( 'api.forms.' )
    ->group( function (): void {

        // Form CRUD routes
        Route::get( '/', [FormApiController::class, 'index'] )->name( 'index' );
        Route::post( '/', [FormApiController::class, 'store'] )->name( 'store' );
        Route::get( '/{form}', [FormApiController::class, 'show'] )->name( 'show' );
        Route::put( '/{form}', [FormApiController::class, 'update'] )->name( 'update' );
        Route::delete( '/{form}', [FormApiController::class, 'destroy'] )->name( 'destroy' );

        // Form fields routes
        Route::get( '/{form}/fields', [FormFieldApiController::class, 'index'] )->name( 'fields.index' );
        Route::post( '/{form}/fields', [FormFieldApiController::class, 'store'] )->name( 'fields.store' );
        Route::post( '/{form}/fields/reorder', [FormFieldApiController::class, 'reorder'] )->name( 'fields.reorder' );
        Route::put( '/{form}/fields/{field}', [FormFieldApiController::class, 'update'] )->name( 'fields.update' );
        Route::delete( '/{form}/fields/{field}', [FormFieldApiController::class, 'destroy'] )->name( 'fields.destroy' );

        // Form steps routes
        Route::get( '/{form}/steps', [FormStepApiController::class, 'index'] )->name( 'steps.index' );
        Route::post( '/{form}/steps', [FormStepApiController::class, 'store'] )->name( 'steps.store' );
        Route::post( '/{form}/steps/reorder', [FormStepApiController::class, 'reorder'] )->name( 'steps.reorder' );
        Route::put( '/{form}/steps/{step}', [FormStepApiController::class, 'update'] )->name( 'steps.update' );
        Route::delete( '/{form}/steps/{step}', [FormStepApiController::class, 'destroy'] )->name( 'steps.destroy' );

        // Submissions routes
        Route::get( '/{form}/submissions', [SubmissionApiController::class, 'index'] )->name( 'submissions.index' );
        Route::get( '/{form}/submissions/export', [SubmissionApiController::class, 'export'] )->name( 'submissions.export' );
        Route::post( '/{form}/submissions/bulk', [SubmissionApiController::class, 'bulk'] )->name( 'submissions.bulk' );
        Route::get( '/{form}/submissions/{submission}', [SubmissionApiController::class, 'show'] )->name( 'submissions.show' );
        Route::put( '/{form}/submissions/{submission}', [SubmissionApiController::class, 'update'] )->name( 'submissions.update' );
        Route::delete( '/{form}/submissions/{submission}', [SubmissionApiController::class, 'destroy'] )->name( 'submissions.destroy' );

        // Notifications routes
        Route::get( '/{form}/notifications', [NotificationApiController::class, 'index'] )->name( 'notifications.index' );
        Route::post( '/{form}/notifications', [NotificationApiController::class, 'store'] )->name( 'notifications.store' );
        Route::put( '/{form}/notifications/{notification}', [NotificationApiController::class, 'update'] )->name( 'notifications.update' );
        Route::delete( '/{form}/notifications/{notification}', [NotificationApiController::class, 'destroy'] )->name( 'notifications.destroy' );

        // File download route
        Route::get( '/submissions/{submission}/uploads/{upload}/download', [SubmissionApiController::class, 'downloadUpload'] )->name( 'uploads.download' );
    } );

/*
|--------------------------------------------------------------------------
| Public Form Routes (No Authentication Required)
|--------------------------------------------------------------------------
|
| These routes provide public access for form rendering and submission.
| They use only the 'api' middleware (no auth) so any client can
| render and submit forms.
|
*/

Route::prefix( config( 'artisanpack.forms.api.prefix', 'api/v1/forms' ) )
    ->middleware( ['api'] )
    ->name( 'api.forms.' )
    ->group( function (): void {
        // Public form rendering
        Route::get( '/{form}/render', [FormApiController::class, 'render'] )->name( 'render' );

        // Public form submission
        Route::post( '/{form}/submit', [SubmissionApiController::class, 'submit'] )->name( 'submit' );
    } );
