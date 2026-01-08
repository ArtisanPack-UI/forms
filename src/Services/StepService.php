<?php

/**
 * Step service.
 *
 * Business logic layer for step CRUD operations. Handles creating,
 * updating, deleting, and reordering form steps for multi-step forms.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Services;

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormStep;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Step service class.
 *
 * Business logic layer for step CRUD operations. Handles creating,
 * updating, deleting, and reordering form steps for multi-step forms.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.0.0
 */
class StepService
{
    /**
     * Creates a new step for a form.
     *
     * @since 1.0.0
     *
     * @param Form                 $form The form to add the step to.
     * @param array<string, mixed> $data Additional data to override defaults.
     *
     * @return FormStep The created step.
     */
    public function create( Form $form, array $data = [] ): FormStep
    {
        $maxSortOrder = $this->getMaxSortOrder( $form );
        $stepNumber   = $form->steps()->count() + 1;

        $stepData = array_merge( [
            'form_id'          => $form->id,
            'title'            => "Step {$stepNumber}",
            'description'      => null,
            'sort_order'       => $maxSortOrder + 1,
            'next_button_text' => 'Next',
            'prev_button_text' => 'Previous',
        ], $data );

        return FormStep::create( $stepData );
    }

    /**
     * Updates an existing step.
     *
     * @since 1.0.0
     *
     * @param FormStep             $step The step to update.
     * @param array<string, mixed> $data The updated step data.
     *
     * @throws RuntimeException If the step cannot be refreshed after update.
     *
     * @return FormStep The updated step.
     *
     */
    public function update( FormStep $step, array $data ): FormStep
    {
        $step->update( $data );

        $refreshed = $step->fresh();

        if ( null === $refreshed ) {
            throw new RuntimeException( "Failed to refresh step after update. Step ID: {$step->id}" );
        }

        return $refreshed;
    }

    /**
     * Deletes a step and reassigns its fields.
     *
     * Fields from the deleted step are moved to the previous step,
     * or to no step (null) if this was the first step.
     *
     * @since 1.0.0
     *
     * @param FormStep $step The step to delete.
     *
     * @return bool True on success.
     */
    public function delete( FormStep $step ): bool
    {
        return DB::transaction( function () use ( $step ): bool {
            $form = $step->form;

            // Find the step to reassign fields to
            $previousStep = $step->getPreviousStep();
            $nextStep     = $step->getNextStep();
            $targetStepId = $previousStep?->id ?? $nextStep?->id;

            // Reassign all fields from this step
            $step->fields()->update( ['step_id' => $targetStepId] );

            // Delete the step
            $result = (bool) $step->delete();

            // If this form now has no steps but is marked as multi-step,
            // we should probably create a default step or let the form builder handle it
            if ( $result && $form->is_multi_step && 0 === $form->steps()->count() ) {
                $this->create( $form, ['title' => 'Step 1'] );
            }

            return $result;
        } );
    }

    /**
     * Reorders steps based on an array of step IDs.
     *
     * @since 1.0.0
     *
     * @param Form           $form       The form containing the steps.
     * @param array<int, int> $orderedIds Array of step IDs in desired order.
     *
     * @return void
     */
    public function reorder( Form $form, array $orderedIds ): void
    {
        DB::transaction( function () use ( $form, $orderedIds ): void {
            $steps = $form->steps()->get()->keyBy( 'id' );

            foreach ( $orderedIds as $index => $id ) {
                if ( isset( $steps[ $id ] ) ) {
                    $steps[ $id ]->update( ['sort_order' => $index + 1] );
                }
            }
        } );
    }

    /**
     * Gets a step by its ID within a form.
     *
     * @since 1.0.0
     *
     * @param Form $form   The form to search in.
     * @param int  $stepId The step ID.
     *
     * @return FormStep|null The step or null if not found.
     */
    public function getById( Form $form, int $stepId ): ?FormStep
    {
        return $form->steps()->where( 'id', $stepId )->first();
    }

    /**
     * Gets all steps for a form with their fields.
     *
     * @since 1.0.0
     *
     * @param Form $form The form to get steps from.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, FormStep> The steps collection.
     */
    public function getSteps( Form $form ): \Illuminate\Database\Eloquent\Collection
    {
        return $form->steps()
            ->with( ['fields' => fn ( $q ) => $q->orderBy( 'sort_order' )] )
            ->orderBy( 'sort_order' )
            ->get();
    }

    /**
     * Initializes a multi-step form with a default first step.
     *
     * Creates a step only if the form has no existing steps.
     *
     * @since 1.0.0
     *
     * @param Form $form The form to initialize.
     *
     * @return FormStep|null The created step or null if steps exist.
     */
    public function initializeMultiStep( Form $form ): ?FormStep
    {
        if ( $form->steps()->count() > 0 ) {
            return null;
        }

        return $this->create( $form, ['title' => 'Step 1'] );
    }

    /**
     * Converts a single-step form to multi-step.
     *
     * Creates a default step and assigns all existing fields to it.
     *
     * @since 1.0.0
     *
     * @param Form $form The form to convert.
     *
     * @return FormStep The created first step.
     */
    public function convertToMultiStep( Form $form ): FormStep
    {
        return DB::transaction( function () use ( $form ): FormStep {
            // Create the first step
            $step = $this->create( $form, ['title' => 'Step 1'] );

            // Move all existing fields without a step to this new step
            $form->fields()
                ->whereNull( 'step_id' )
                ->update( ['step_id' => $step->id] );

            return $step;
        } );
    }

    /**
     * Converts a multi-step form to single-step.
     *
     * Removes all steps and sets all fields to have no step.
     *
     * @since 1.0.0
     *
     * @param Form $form The form to convert.
     *
     * @return void
     */
    public function convertToSingleStep( Form $form ): void
    {
        DB::transaction( function () use ( $form ): void {
            // Remove step assignments from all fields
            $form->fields()->update( ['step_id' => null] );

            // Delete all steps
            $form->steps()->delete();
        } );
    }

    /**
     * Gets the first step of a form.
     *
     * @since 1.0.0
     *
     * @param Form $form The form to get the first step from.
     *
     * @return FormStep|null The first step or null if none exist.
     */
    public function getFirstStep( Form $form ): ?FormStep
    {
        // The steps() relationship already orders by sort_order ASC
        return $form->steps()->first();
    }

    /**
     * Gets the last step of a form.
     *
     * @since 1.0.0
     *
     * @param Form $form The form to get the last step from.
     *
     * @return FormStep|null The last step or null if none exist.
     */
    public function getLastStep( Form $form ): ?FormStep
    {
        // Use reorder() to clear the default ascending order from the relationship
        return $form->steps()->reorder( 'sort_order', 'desc' )->first();
    }

    /**
     * Gets the maximum sort order for steps in a form.
     *
     * @since 1.0.0
     *
     * @param Form $form The form to check.
     *
     * @return int The maximum sort order value.
     */
    protected function getMaxSortOrder( Form $form ): int
    {
        return (int) $form->steps()->max( 'sort_order');
    }
}
