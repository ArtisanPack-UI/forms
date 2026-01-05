<?php

declare(strict_types=1);

namespace ArtisanPackUI\Forms\Database\Factories;

use ArtisanPackUI\Forms\Models\Form;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory for the Form model.
 *
 * @extends Factory<Form>
 *
 * @since 1.0.0
 */
class FormFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Form>
     */
    protected $model = Form::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->optional()->paragraph(),
            'submit_button_text' => 'Submit',
            'success_message' => fake()->optional()->sentence(),
            'redirect_url' => fake()->optional()->url(),
            'settings' => null,
            'is_multi_step' => false,
            'show_progress_bar' => true,
            'allow_step_navigation' => false,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the form is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the form is multi-step.
     */
    public function multiStep(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_multi_step' => true,
            'show_progress_bar' => true,
            'allow_step_navigation' => false,
        ]);
    }

    /**
     * Create a form with fields.
     */
    public function withFields(int $count = 3): static
    {
        return $this->afterCreating(function (Form $form) use ($count): void {
            \ArtisanPackUI\Forms\Models\FormField::factory()
                ->count($count)
                ->for($form)
                ->create();
        });
    }

    /**
     * Create a form with notifications.
     */
    public function withNotifications(int $count = 1): static
    {
        return $this->afterCreating(function (Form $form) use ($count): void {
            \ArtisanPackUI\Forms\Models\FormNotification::factory()
                ->count($count)
                ->for($form)
                ->create();
        });
    }

    /**
     * Create a form with steps.
     */
    public function withSteps(int $count = 2): static
    {
        return $this->multiStep()->afterCreating(function (Form $form) use ($count): void {
            \ArtisanPackUI\Forms\Models\FormStep::factory()
                ->count($count)
                ->for($form)
                ->sequence(fn ($sequence) => ['sort_order' => $sequence->index])
                ->create();
        });
    }
}
