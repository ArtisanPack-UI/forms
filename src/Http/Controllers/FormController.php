<?php

/**
 * Form controller.
 *
 * Handles HTTP requests for form management including listing,
 * creating, editing, and deleting forms.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

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
 * Form controller class.
 *
 * Handles HTTP requests for form management including listing,
 * creating, editing, and deleting forms.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.0.0
 */
class FormController extends Controller
{
    use AuthorizesRequests;

    /**
     * Creates a new controller instance.
     *
     * @since 1.0.0
     *
     * @param FormService $formService The form service instance.
     */
    public function __construct(
        protected FormService $formService,
    ) {
    }

    /**
     * Displays a listing of forms.
     *
     * @since 1.0.0
     *
     * @return View The forms index view.
     */
    public function index(): View
    {
        $this->authorize( 'viewAny', Form::class );

        return view( 'forms::forms.index' );
    }

    /**
     * Shows the form for creating a new form.
     *
     * @since 1.0.0
     *
     * @return View The form creation view.
     */
    public function create(): View
    {
        $this->authorize( 'create', Form::class );

        return view( 'forms::forms.create' );
    }

    /**
     * Stores a newly created form.
     *
     * @since 1.0.0
     *
     * @param StoreFormRequest $request The validated form request.
     *
     * @return RedirectResponse Redirect to the form edit page.
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
     * Shows the form for editing a form.
     *
     * @since 1.0.0
     *
     * @param Form $form The form to edit.
     *
     * @return View The form edit view.
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
     * Updates the specified form.
     *
     * @since 1.0.0
     *
     * @param UpdateFormRequest $request The validated form request.
     * @param Form              $form    The form to update.
     *
     * @return RedirectResponse Redirect to the form edit page.
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
     * Removes the specified form.
     *
     * @since 1.0.0
     *
     * @param Form $form The form to delete.
     *
     * @return RedirectResponse Redirect to the forms index page.
     */
    public function destroy( Form $form ): RedirectResponse
    {
        $this->authorize( 'delete', $form );

        $formName = $form->name;
        $this->formService->delete( $form );

        return redirect()
            ->route( 'forms.index' )
            ->with( 'success', "Form \"{$formName}\" has been deleted." );
    }
}
