<?php
/**
 * Form step model factory.
 *
 * Provides factory methods for creating FormStep model instances
 * during testing, including states for steps with fields.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 * @author     Jacob Martella <support@artisanpackui.dev>
 * @since      1.0.0
 */

declare(strict_types=1);

namespace ArtisanPackUI\Forms\Database\Factories;

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Form step model factory class.
 *
 * @extends Factory<FormStep>
 *
 * @since 1.0.0
 */
class FormStepFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<FormStep>
     */
    protected $model = FormStep::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'form_id' => Form::factory()->multiStep(),
            'title' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'sort_order' => 0,
            'next_button_text' => 'Next',
            'prev_button_text' => 'Previous',
        ];
    }

    /**
     * Create a step with fields.
     */
    public function withFields(int $count = 3): static
    {
        return $this->afterCreating(function (FormStep $step) use ($count): void {
            \ArtisanPackUI\Forms\Models\FormField::factory()
                ->count($count)
                ->for($step->form)
                ->state(['step_id' => $step->id])
                ->create();
        });
    }
}
