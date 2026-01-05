<?php

declare(strict_types=1);

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormStep;

describe('FormStep Model', function (): void {
    describe('factory', function (): void {
        it('creates a valid form step', function (): void {
            $step = FormStep::factory()->create();

            expect($step)->toBeInstanceOf(FormStep::class)
                ->and($step->title)->not->toBeEmpty()
                ->and($step->form)->toBeInstanceOf(Form::class);
        });
    });

    describe('relationships', function (): void {
        it('belongs to a form', function (): void {
            $form = Form::factory()->multiStep()->create();
            $step = FormStep::factory()->for($form)->create();

            expect($step->form->id)->toBe($form->id);
        });

        it('has many fields', function (): void {
            $step = FormStep::factory()->create();
            FormField::factory()->count(2)->for($step->form)->state(['step_id' => $step->id])->create();

            expect($step->fields)->toHaveCount(2);
        });
    });

    describe('accessors', function (): void {
        it('calculates step number', function (): void {
            $form = Form::factory()->multiStep()->create();
            $step1 = FormStep::factory()->for($form)->create(['sort_order' => 0]);
            $step2 = FormStep::factory()->for($form)->create(['sort_order' => 1]);
            $step3 = FormStep::factory()->for($form)->create(['sort_order' => 2]);

            expect($step1->step_number)->toBe(1)
                ->and($step2->step_number)->toBe(2)
                ->and($step3->step_number)->toBe(3);
        });

        it('identifies first step', function (): void {
            $form = Form::factory()->multiStep()->create();
            $step1 = FormStep::factory()->for($form)->create(['sort_order' => 0]);
            $step2 = FormStep::factory()->for($form)->create(['sort_order' => 1]);

            expect($step1->is_first_step)->toBeTrue()
                ->and($step2->is_first_step)->toBeFalse();
        });

        it('identifies last step', function (): void {
            $form = Form::factory()->multiStep()->create();
            $step1 = FormStep::factory()->for($form)->create(['sort_order' => 0]);
            $step2 = FormStep::factory()->for($form)->create(['sort_order' => 1]);

            expect($step1->is_last_step)->toBeFalse()
                ->and($step2->is_last_step)->toBeTrue();
        });
    });

    describe('methods', function (): void {
        it('gets previous step', function (): void {
            $form = Form::factory()->multiStep()->create();
            $step1 = FormStep::factory()->for($form)->create(['sort_order' => 0]);
            $step2 = FormStep::factory()->for($form)->create(['sort_order' => 1]);

            expect($step1->getPreviousStep())->toBeNull()
                ->and($step2->getPreviousStep()->id)->toBe($step1->id);
        });

        it('gets next step', function (): void {
            $form = Form::factory()->multiStep()->create();
            $step1 = FormStep::factory()->for($form)->create(['sort_order' => 0]);
            $step2 = FormStep::factory()->for($form)->create(['sort_order' => 1]);

            expect($step1->getNextStep()->id)->toBe($step2->id)
                ->and($step2->getNextStep())->toBeNull();
        });
    });
});
