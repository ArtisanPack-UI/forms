<?php

/**
 * Forms list Livewire component.
 *
 * Displays a paginated, searchable, sortable list of forms
 * with actions for edit, duplicate, delete, and toggle publish.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Livewire;

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Services\FormService;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Forms list Livewire component class.
 *
 * Displays a paginated, searchable, sortable list of forms
 * with actions for edit, duplicate, delete, and toggle publish.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.0.0
 */
class FormsList extends Component
{
    use WithPagination;

    /**
     * The search query string.
     */
    #[Url( as: 'q' )]
    public string $search = '';

    /**
     * The column to sort by.
     */
    #[Url( as: 'sort' )]
    public string $sortBy = 'created_at';

    /**
     * The sort direction (asc or desc).
     */
    #[Url( as: 'dir' )]
    public string $sortDirection = 'desc';

    /**
     * Filter by status (all, active, inactive).
     */
    #[Url( as: 'status' )]
    public string $statusFilter = 'all';

    /**
     * Reset pagination when search changes.
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Reset pagination when status filter changes.
     */
    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Sort by a given column.
     */
    public function sort( string $column ): void
    {
        if ( $this->sortBy === $column ) {
            $this->sortDirection = 'asc' === $this->sortDirection ? 'desc' : 'asc';
        } else {
            $this->sortBy        = $column;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    /**
     * Duplicate a form.
     */
    public function duplicate( int $formId ): void
    {
        $form = Form::find( $formId );

        if ( $form ) {
            $formService = app( FormService::class );
            $newForm     = $formService->duplicate( $form );

            session()->flash( 'success', __( 'Form duplicated as ":name".', ['name' => $newForm->name] ) );
        }
    }

    /**
     * Delete a form.
     */
    public function delete( int $formId ): void
    {
        $form = Form::find( $formId );

        if ( $form ) {
            $formName    = $form->name;
            $formService = app( FormService::class );
            $formService->delete( $form );

            session()->flash( 'success', __( 'Form ":name" has been deleted.', ['name' => $formName] ) );
        }
    }

    /**
     * Toggle the published status of a form.
     */
    public function togglePublish( int $formId ): void
    {
        $form = Form::find( $formId );

        if ( $form ) {
            $formService = app( FormService::class );
            $formService->togglePublish( $form );

            $status = $form->fresh()->is_active ? __( 'published' ) : __( 'unpublished' );
            session()->flash( 'success', __( 'Form ":name" has been :status.', ['name' => $form->name, 'status' => $status] ) );
        }
    }

    /**
     * Get the paginated list of forms.
     *
     * @return LengthAwarePaginator<Form>
     */
    #[Computed]
    public function forms(): LengthAwarePaginator
    {
        $query = Form::query()
            ->withCount( ['fields', 'submissions'] )
            ->withCount( ['submissions as unread_count' => function ( $q ): void {
                $q->where( 'is_read', false );
            }] );

        // Apply search filter (escape special LIKE characters to prevent wildcard injection)
        if ( '' !== $this->search ) {
            $search = str_replace( ['%', '_'], ['\%', '\_'], $this->search );
            $query->where( function ( $q ) use ( $search ): void {
                $q->where( 'name', 'like', "%{$search}%" )
                    ->orWhere( 'slug', 'like', "%{$search}%" );
            } );
        }

        // Apply status filter
        if ( 'active' === $this->statusFilter ) {
            $query->where( 'is_active', true );
        } elseif ( 'inactive' === $this->statusFilter ) {
            $query->where( 'is_active', false );
        }

        // Apply sorting (validate sortDirection to prevent injection)
        $sortColumn = match ( $this->sortBy ) {
            'name'        => 'name',
            'submissions' => 'submissions_count',
            default       => 'created_at',
        };

        $sortDirection = strtolower( $this->sortDirection );
        $sortDirection = in_array( $sortDirection, ['asc', 'desc'], true ) ? $sortDirection : 'desc';

        $query->orderBy( $sortColumn, $sortDirection );

        return $query->paginate( config( 'artisanpack.forms.admin.per_page', 15 ) );
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view( 'forms::livewire.forms-list');
    }
}
