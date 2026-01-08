<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Http\Controllers;

use ArtisanPackUI\Forms\Http\Requests\StoreFormRequest;
use ArtisanPackUI\Forms\Http\Requests\UpdateFormRequest;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Services\FormService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;

/**
 * FormController
 *
 * Handles HTTP requests for form management including listing,
 * creating, editing, and deleting forms.
 *
 * @since 1.0.0
 */
class FormController extends Controller
{
    use AuthorizesRequests;

    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected FormService $formService,
    ) {
    }

    /**
     * Display a listing of forms.
     */
    public function index(): View
    {
        $this->authorize( 'viewAny', Form::class );

        return view( 'forms::forms.index' );
    }

    /**
     * Show the form for creating a new form.
     */
    public function create(): View
    {
        $this->authorize( 'create', Form::class );

        return view( 'forms::forms.create' );
    }

    /**
     * Store a newly created form.
     */
    public function store( StoreFormRequest $request ): RedirectResponse
    {
        $this->authorize( 'create', Form::class );

        $form = $this->formService->create( $request->validated() );

        return redirect()
            ->route( 'forms.edit', $form )
            ->with( 'success', 'Form created successfully.' );
    }

    /**
     * Show the form for editing a form.
     */
    public function edit( Form $form ): View
    {
        $this->authorize( 'update', $form );

        // Eager load relationships and counts to prevent N+1 queries
        $form->load( ['fields', 'steps.fields'] );
        $form->loadCount( [
            'fields',
            'submissions as total_submissions_count',
            'submissions as unread_submissions_count' => fn ( $q ) => $q->where( 'is_read', false ),
        ] );

        return view( 'forms::forms.edit', [
            'form' => $form,
        ] );
    }

    /**
     * Update the specified form.
     */
    public function update( UpdateFormRequest $request, Form $form ): RedirectResponse
    {
        $this->authorize( 'update', $form );

        $this->formService->update( $form, $request->validated() );

        return redirect()
            ->route( 'forms.edit', $form )
            ->with( 'success', 'Form updated successfully.' );
    }

    /**
     * Remove the specified form.
     */
    public function destroy( Form $form ): RedirectResponse
    {
        $this->authorize( 'delete', $form );

        $formName = $form->name;
        $this->formService->delete( $form );

        return redirect()
            ->route( 'forms.index')
            ->with( 'success', "Form \"{$formName}\" has been deleted.");
    }
}
