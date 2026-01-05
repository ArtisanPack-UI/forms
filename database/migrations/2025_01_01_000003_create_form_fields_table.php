<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create Form Fields Table Migration
 *
 * Creates the form_fields table for storing individual field configurations.
 * Fields can belong directly to a form or to a specific step for multi-step forms.
 *
 * @since   1.0.0
 */
return new class extends Migration
{
    /**
     * Runs the migrations.
     *
     * @since 1.0.0
     */
    public function up(): void
    {
        Schema::create('form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->foreignId('step_id')->nullable()->constrained('form_steps')->nullOnDelete();

            // Field Identity
            $table->uuid('uuid')->unique();
            $table->string('name', 100);

            // Field Type
            $table->string('type', 50);

            // Labels & Display
            $table->string('label')->nullable();
            $table->string('placeholder')->nullable();
            $table->text('help_text')->nullable();

            // Validation
            $table->boolean('is_required')->default(false);
            $table->json('validation_rules')->nullable();

            // Field-Type Specific Configuration
            $table->json('field_config')->nullable();

            // Default Value
            $table->text('default_value')->nullable();

            // Conditional Logic
            $table->json('conditional_logic')->nullable();

            // Layout
            $table->string('width', 20)->default('full');
            $table->string('css_classes')->nullable();

            // Ordering
            $table->unsignedInteger('sort_order')->default(0);

            // Timestamps
            $table->timestamps();

            // Indexes
            $table->index('form_id');
            $table->index('step_id');
            $table->index('uuid');
            $table->index(['form_id', 'step_id', 'sort_order']);
        });
    }

    /**
     * Reverses the migrations.
     *
     * @since 1.0.0
     */
    public function down(): void
    {
        Schema::dropIfExists('form_fields');
    }
};
