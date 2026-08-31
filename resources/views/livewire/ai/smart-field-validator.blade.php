<div class="forms-ai-smart-validator" data-feature="forms.smart_validation">
	@if ( ! $this->isEnabled )
		<p class="forms-ai-smart-validator__disabled">
			{{ __( 'AI smart validation is currently disabled.' ) }}
		</p>
	@else
		<button
			type="button"
			wire:click="validateField"
			wire:loading.attr="disabled"
			wire:target="validateField"
			@disabled( $isLoading || '' === trim( $value ) || '' === trim( $fieldKind ) )
			class="forms-ai-smart-validator__button cursor-pointer hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed"
		>
			<span wire:loading.remove wire:target="validateField">
				{{ __( 'Check this field' ) }}
			</span>
			<span wire:loading wire:target="validateField">
				{{ __( 'Checking…' ) }}
			</span>
		</button>

		@if ( null !== $error )
			<p class="forms-ai-smart-validator__error" role="alert">
				{{ $error }}
			</p>
		@endif

		@if ( null !== $plausible )
			<div
				class="forms-ai-smart-validator__result"
				data-plausible="{{ $plausible ? 'true' : 'false' }}"
			>
				<p class="forms-ai-smart-validator__verdict">
					<strong>
						{{ $plausible ? __( 'Looks plausible' ) : __( 'Doesn\'t look right' ) }}
					</strong>
					@if ( null !== $confidence )
						<span class="forms-ai-smart-validator__confidence">
							({{ __( ':percent% confidence', ['percent' => (int) round( $confidence * 100 )] ) }})
						</span>
					@endif
				</p>

				@if ( null !== $reason && '' !== $reason )
					<p class="forms-ai-smart-validator__reason">{{ $reason }}</p>
				@endif

				@if ( null !== $suggestion && '' !== $suggestion )
					<p class="forms-ai-smart-validator__suggestion">
						{{ __( 'Suggestion:' ) }} {{ $suggestion }}
					</p>
				@endif
			</div>
		@endif
	@endif
</div>
