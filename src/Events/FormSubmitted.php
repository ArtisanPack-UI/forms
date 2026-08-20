<?php

/**
 * Form submitted event.
 *
 * Dispatched after a form submission is successfully created.
 * This event can be used for native Laravel event listeners.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Events;

use ArtisanPackUI\Forms\Models\FormSubmission;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Form submitted event class.
 *
 * Dispatched after a form submission is successfully created.
 * This event can be used for native Laravel event listeners.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.0.0
 */
class FormSubmitted implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * The form submission instance.
     *
     * @since 1.0.0
     *
     * @var FormSubmission
     */
    public FormSubmission $submission;

    /**
     * Creates a new event instance.
     *
     * @since 1.0.0
     *
     * @param FormSubmission $submission The form submission.
     */
    public function __construct( FormSubmission $submission )
    {
        $this->submission = $submission;
    }
}
