<?php

declare(strict_types=1);

use ArtisanPackUI\Forms\Events\FormCreated;
use ArtisanPackUI\Forms\Events\FormDeleted;
use ArtisanPackUI\Forms\Events\FormUpdated;
use ArtisanPackUI\Forms\Models\Form;
use Illuminate\Support\Facades\Event;

describe('Form Events', function (): void {
    describe('FormCreated event', function (): void {
        it('dispatches FormCreated event when form is created', function (): void {
            Event::fake([FormCreated::class]);

            $form = Form::factory()->create();

            Event::assertDispatched(FormCreated::class, function (FormCreated $event) use ($form) {
                return $event->form->id === $form->id;
            });
        });

        it('fires ap.forms.form.created action hook when form is created', function (): void {
            $hookFired = false;
            $receivedForm = null;

            addAction('ap.forms.form.created', function (Form $form) use (&$hookFired, &$receivedForm): void {
                $hookFired = true;
                $receivedForm = $form;
            });

            $form = Form::factory()->create();

            expect($hookFired)->toBeTrue()
                ->and($receivedForm)->not->toBeNull()
                ->and($receivedForm->id)->toBe($form->id);
        });
    });

    describe('FormUpdated event', function (): void {
        it('dispatches FormUpdated event when form is updated', function (): void {
            Event::fake([FormUpdated::class]);

            $form = Form::factory()->create();
            $form->update(['name' => 'Updated Form Name']);

            Event::assertDispatched(FormUpdated::class, function (FormUpdated $event) use ($form) {
                return $event->form->id === $form->id;
            });
        });

        it('fires ap.forms.form.updated action hook when form is updated', function (): void {
            $hookFired = false;
            $receivedForm = null;

            addAction('ap.forms.form.updated', function (Form $form) use (&$hookFired, &$receivedForm): void {
                $hookFired = true;
                $receivedForm = $form;
            });

            $form = Form::factory()->create();
            $form->update(['name' => 'Updated Form Name']);

            expect($hookFired)->toBeTrue()
                ->and($receivedForm)->not->toBeNull()
                ->and($receivedForm->id)->toBe($form->id);
        });
    });

    describe('FormDeleted event', function (): void {
        it('dispatches FormDeleted event when form is deleted', function (): void {
            Event::fake([FormDeleted::class]);

            $form = Form::factory()->create();
            $formId = $form->id;
            $form->delete();

            Event::assertDispatched(FormDeleted::class, function (FormDeleted $event) use ($formId) {
                return $event->form->id === $formId;
            });
        });

        it('fires ap.forms.form.deleted action hook when form is deleted', function (): void {
            $hookFired = false;
            $receivedFormId = null;

            addAction('ap.forms.form.deleted', function (Form $form) use (&$hookFired, &$receivedFormId): void {
                $hookFired = true;
                $receivedFormId = $form->id;
            });

            $form = Form::factory()->create();
            $formId = $form->id;
            $form->delete();

            expect($hookFired)->toBeTrue()
                ->and($receivedFormId)->toBe($formId);
        });
    });
});
