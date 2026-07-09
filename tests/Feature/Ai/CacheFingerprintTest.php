<?php

declare(strict_types=1);

use ArtisanPackUI\Forms\Ai\Agents\ResponseClassificationAgent;
use ArtisanPackUI\Forms\Ai\Agents\SmartFieldValidationAgent;
use ArtisanPackUI\Forms\Ai\Agents\SpamDetectionAgent;
use ArtisanPackUI\Forms\Ai\Agents\SubmissionSummaryAgent;
use Illuminate\Support\Carbon;
use Tests\Feature\Ai\AiAgentTestSetup;

/**
 * The base ArtisanPackAgent::cacheFingerprint() default throws
 * InvalidArgumentException for any non-scalar array entry — a hard crash
 * on realistic submissions containing Carbon timestamps, nested arrays,
 * or objects when the AI cache is enabled. Each agent overrides
 * cacheFingerprint() to fingerprint the normalized input as JSON so
 * cached runs survive real form-submission data.
 */
beforeEach(function (): void {
    $this->prompter = AiAgentTestSetup::bootstrap($this->app);

    // Enable the AI cache to exercise the cache-key derivation path.
    $this->app['config']->set('artisanpack.ai.cache.enabled', true);
    $this->app['config']->set('cache.default', 'array');
});

it('runs SpamDetectionAgent with a Carbon-nested submission without cacheFingerprint throwing', function (): void {
    $this->prompter->queue([
        'spam_score' => 5,
        'verdict' => 'ham',
        'reasons' => [],
    ]);

    $result = SpamDetectionAgent::for([
        'fields' => [
            'message' => 'legitimate customer question',
            'submitted_at' => Carbon::parse('2026-07-01T10:00:00Z'),
            'attachments' => [['name' => 'a.pdf', 'size' => 100]],
        ],
    ])->run();

    expect($result['verdict'])->toBe('ham');
});

it('runs SubmissionSummaryAgent with nested submission arrays without cacheFingerprint throwing', function (): void {
    $this->prompter->queue([
        'headline' => 'summary headline',
        'total_count' => 1,
        'themes' => [],
        'notable' => [],
        'suggestions' => [],
    ]);

    $result = SubmissionSummaryAgent::for([
        'form_name' => 'Contact us',
        'submissions' => [
            ['message' => 'hi', 'created_at' => Carbon::parse('2026-07-01T10:00:00Z')->toIso8601String()],
        ],
    ])->run();

    expect($result['headline'])->toBe('summary headline');
});

it('runs ResponseClassificationAgent with nested submission arrays without cacheFingerprint throwing', function (): void {
    $this->prompter->queue([
        'category' => 'support-request',
        'confidence' => 0.9,
    ]);

    $result = ResponseClassificationAgent::for([
        'fields' => [
            'message' => 'my login is broken',
            'attachments' => [['name' => 'screenshot.png']],
        ],
        'available_categories' => ['support-request', 'sales-inquiry'],
    ])->run();

    expect($result['category'])->toBe('support-request');
});

it('runs SmartFieldValidationAgent with a nested-context submission without cacheFingerprint throwing', function (): void {
    $this->prompter->queue([
        'plausible' => true,
        'confidence' => 0.9,
        'reason' => 'looks fine',
    ]);

    $result = SmartFieldValidationAgent::for([
        'field_label' => 'Address',
        'field_kind' => 'address',
        'value' => '1 Infinite Loop',
        'context' => [
            'city' => 'Cupertino',
            'state' => 'CA',
            'geocoded' => ['lat' => 37.331, 'lng' => -122.030],
        ],
    ])->run();

    expect($result['plausible'])->toBeTrue();
});
