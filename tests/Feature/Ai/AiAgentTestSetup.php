<?php

/**
 * Shared setup helpers for Forms AI agent tests.
 *
 *
 * @since      1.2.0
 */

declare( strict_types=1 );

namespace Tests\Feature\Ai;

use ArtisanPackUI\Ai\Contracts\AgentPrompter;
use ArtisanPackUI\Ai\Contracts\CredentialResolver;
use ArtisanPackUI\Ai\Credentials\ChainedCredentialResolver;
use ArtisanPackUI\Ai\Credentials\Credentials;
use Illuminate\Foundation\Application;
use Tests\Support\FakeAgentPrompter;

/**
 * Registers a fake prompter and stub credentials for the four Forms AI
 * features so agents can run without hitting the network.
 *
 *
 * @since      1.2.0
 */
class AiAgentTestSetup
{
    /**
     * Prepare the container so agents can run against a fake prompter.
     *
     * @since 1.2.0
     *
     * @param  Application  $app  Application instance.
     *
     * @return FakeAgentPrompter The bound fake prompter.
     */
    public static function bootstrap( $app ): FakeAgentPrompter
    {
        /** @var ChainedCredentialResolver $resolver */
        $resolver = $app->make( CredentialResolver::class );
        $resolver->setOverride(
            new Credentials( provider: 'anthropic', apiKey: 'sk-test', defaultModel: 'claude-haiku-4-5' ),
        );
        $resolver->useStore( fn () => null );

        $prompter = new FakeAgentPrompter;
        $app->instance( AgentPrompter::class, $prompter );

        foreach (
            [
                'forms.spam_detection',
                'forms.submission_summary',
                'forms.response_classification',
                'forms.smart_validation',
            ] as $key
        ) {
            $app['config']->set( "artisanpack.ai.features.{$key}.enabled", true );
        }

        return $prompter;
    }
}
