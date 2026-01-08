<?php

/**
 * Submission deleted event.
 *
 * Dispatched after a form submission is successfully deleted.
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
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Submission deleted event class.
 *
 * Dispatched after a form submission is successfully deleted.
 * This event can be used for native Laravel event listeners.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.0.0
 */
class SubmissionDeleted
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * The form submission instance (before deletion).
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
     * @param FormSubmission $submission The deleted submission.
     */
    public function __construct( FormSubmission $submission )
    {
        $this->submission = $submission;
    }
}
