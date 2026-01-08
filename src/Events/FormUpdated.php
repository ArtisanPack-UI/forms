<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Events;

use ArtisanPackUI\Forms\Models\Form;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * FormUpdated Event
 *
 * Dispatched after a form is successfully updated.
 * This event can be used for native Laravel event listeners.
 *
 * @since 1.0.0
 */
class FormUpdated
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * The form instance.
     */
    public Form $form;

    /**
     * Create a new event instance.
     */
    public function __construct( Form $form )
    {
        $this->form = $form;
    }
}
