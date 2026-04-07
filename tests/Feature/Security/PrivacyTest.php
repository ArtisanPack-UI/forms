<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Services\SubmissionService;
use Illuminate\Http\Request;

describe( 'Privacy Settings', function (): void {
    beforeEach( function (): void {
        $this->service = app( SubmissionService::class );
    } );

    describe( 'IP anonymization', function (): void {
        it( 'anonymizes IPv4 addresses by masking last octet', function (): void {
            config( ['artisanpack.forms.privacy.submission.include_ip' => true] );
            config( ['artisanpack.forms.privacy.submission.anonymize_ip' => true] );

            $request = Request::create( '/', 'POST', [], [], [], [
                'REMOTE_ADDR' => '192.168.1.123',
            ] );

            $metadata = $this->service->getRequestMetadata( $request );

            expect( $metadata['ip_address'] )->toBe( '192.168.1.0' );
        } );

        it( 'anonymizes IPv6 addresses by masking last 5 groups', function (): void {
            config( ['artisanpack.forms.privacy.submission.include_ip' => true] );
            config( ['artisanpack.forms.privacy.submission.anonymize_ip' => true] );

            $request = Request::create( '/', 'POST', [], [], [], [
                'REMOTE_ADDR' => '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
            ] );

            $metadata = $this->service->getRequestMetadata( $request );

            // inet_ntop returns compressed format (:: for consecutive zeros)
            expect( $metadata['ip_address'] )->toBe( '2001:db8:85a3::' );
        } );

        it( 'anonymizes compressed IPv6 addresses correctly', function (): void {
            config( ['artisanpack.forms.privacy.submission.include_ip' => true] );
            config( ['artisanpack.forms.privacy.submission.anonymize_ip' => true] );

            $request = Request::create( '/', 'POST', [], [], [], [
                'REMOTE_ADDR' => '2001:db8::1',
            ] );

            $metadata = $this->service->getRequestMetadata( $request );

            // Should properly handle compressed format and mask last 80 bits
            expect( $metadata['ip_address'] )->toBe( '2001:db8::' );
        } );

        it( 'does not anonymize when config is disabled', function (): void {
            config( ['artisanpack.forms.privacy.submission.include_ip' => true] );
            config( ['artisanpack.forms.privacy.submission.anonymize_ip' => false] );

            $request = Request::create( '/', 'POST', [], [], [], [
                'REMOTE_ADDR' => '192.168.1.123',
            ] );

            $metadata = $this->service->getRequestMetadata( $request );

            expect( $metadata['ip_address'] )->toBe( '192.168.1.123' );
        } );
    } );

    describe( 'IP collection settings', function (): void {
        it( 'includes IP when enabled', function (): void {
            config( ['artisanpack.forms.privacy.submission.include_ip' => true] );

            $request = Request::create( '/', 'POST', [], [], [], [
                'REMOTE_ADDR' => '192.168.1.1',
            ] );

            $metadata = $this->service->getRequestMetadata( $request );

            expect( $metadata['ip_address'] )->toBe( '192.168.1.1' );
        } );

        it( 'excludes IP when disabled', function (): void {
            config( ['artisanpack.forms.privacy.submission.include_ip' => false] );

            $request = Request::create( '/', 'POST', [], [], [], [
                'REMOTE_ADDR' => '192.168.1.1',
            ] );

            $metadata = $this->service->getRequestMetadata( $request );

            expect( $metadata['ip_address'] )->toBeNull();
        } );
    } );

    describe( 'user agent collection settings', function (): void {
        it( 'includes user agent when enabled', function (): void {
            config( ['artisanpack.forms.privacy.submission.include_user_agent' => true] );

            $request = Request::create( '/', 'POST', [], [], [], [
                'HTTP_USER_AGENT' => 'Mozilla/5.0 Test Browser',
            ] );

            $metadata = $this->service->getRequestMetadata( $request );

            expect( $metadata['user_agent'] )->toBe( 'Mozilla/5.0 Test Browser' );
        } );

        it( 'excludes user agent when disabled', function (): void {
            config( ['artisanpack.forms.privacy.submission.include_user_agent' => false] );

            $request = Request::create( '/', 'POST', [], [], [], [
                'HTTP_USER_AGENT' => 'Mozilla/5.0 Test Browser',
            ] );

            $metadata = $this->service->getRequestMetadata( $request );

            expect( $metadata['user_agent'] )->toBeNull();
        } );
    } );

    describe( 'metadata collection', function (): void {
        it( 'always includes page URL and referrer', function (): void {
            $request = Request::create( '/contact', 'POST', [], [], [], [
                'HTTP_REFERER' => 'https://example.com/previous-page',
            ] );

            $metadata = $this->service->getRequestMetadata( $request );

            expect( $metadata['page_url'] )->toContain( '/contact' );
            expect( $metadata['referrer_url'] )->toBe( 'https://example.com/previous-page' );
        } );
    } );
});
