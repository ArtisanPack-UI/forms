<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Events;

use ArtisanPackUI\Forms\Models\FormSubmission;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * SubmissionDeleted Event
 *
 * Dispatched after a form submission is successfully deleted.
 * This event can be used for native Laravel event listeners.
 *
 * @since 1.0.0
 */
class SubmissionDeleted
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * The form submission instance (before deletion).
     */
    public FormSubmission $submission;

    /**
     * Create a new event instance.
     */
    public function __construct( FormSubmission $submission )
    {
        $this->submission = $submission;
    }
}
