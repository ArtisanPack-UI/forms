<?php

declare(strict_types=1);

namespace ArtisanPackUI\Forms\Http\Controllers;

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormUpload;
use ArtisanPackUI\Forms\Services\ExportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SubmissionController
 *
 * Handles the admin interface for viewing and managing form submissions.
 *
 * @since 1.0.0
 */
class SubmissionController extends Controller
{
    /**
     * Display a listing of all submissions (across all forms).
     */
    public function indexAll(): View
    {
        return view('forms::submissions.index-all');
    }

    /**
     * Display a listing of submissions for a specific form.
     */
    public function index(Form $form): View
    {
        return view('forms::submissions.index', [
            'form' => $form,
        ]);
    }

    /**
     * Display a specific submission.
     */
    public function show(Form $form, FormSubmission $submission): View
    {
        // Ensure the submission belongs to this form
        if ($submission->form_id !== $form->id) {
            abort(404);
        }

        return view('forms::submissions.show', [
            'form' => $form,
            'submission' => $submission,
        ]);
    }

    /**
     * Export submissions to CSV.
     */
    public function export(Request $request, Form $form, ExportService $exportService): StreamedResponse
    {
        $submissions = FormSubmission::where('form_id', $form->id)
            ->with('values')
            ->orderBy('created_at', 'desc')
            ->get();

        return $exportService->exportToCsv($form, $submissions);
    }

    /**
     * Download a file upload.
     */
    public function downloadUpload(FormUpload $upload): StreamedResponse
    {
        if (! Storage::disk($upload->disk)->exists($upload->path)) {
            abort(404, 'File not found.');
        }

        return Storage::disk($upload->disk)->download($upload->path, $upload->original_name);
    }
}
