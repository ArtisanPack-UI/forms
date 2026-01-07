<?php

declare(strict_types=1);

namespace ArtisanPackUI\Forms\Events;

use ArtisanPackUI\Forms\Models\Form;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * FormDeleted Event
 *
 * Dispatched after a form is successfully deleted.
 * This event can be used for native Laravel event listeners.
 *
 * @since 1.0.0
 */
class FormDeleted
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * The form instance (before deletion).
     */
    public Form $form;

    /**
     * Create a new event instance.
     */
    public function __construct(Form $form)
    {
        $this->form = $form;
    }
}
