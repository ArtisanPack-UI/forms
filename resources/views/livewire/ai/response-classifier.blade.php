<div class="forms-ai-classifier" data-feature="forms.response_classification">
	@if ( ! $this->isEnabled )
		<p class="forms-ai-classifier__disabled">
			{{ __( 'AI response classification is currently disabled.' ) }}
		</p>
	@else
		<button
			type="button"
			wire:click="classify"
			wire:loading.attr="disabled"
			wire:target="classify"
			@disabled( $isLoading || [] === $fields || [] === $availableCategories )
			class="forms-ai-classifier__button cursor-pointer hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed"
		>
			<span wire:loading.remove wire:target="classify">
				{{ __( 'Classify submission' ) }}
			</span>
			<span wire:loading wire:target="classify">
				{{ __( 'Classifying…' ) }}
			</span>
		</button>

		@if ( null !== $error )
			<p class="forms-ai-classifier__error" role="alert">
				{{ $error }}
			</p>
		@endif

		@if ( null !== $category )
			<div class="forms-ai-classifier__result">
				<p class="forms-ai-classifier__category">
					<strong>{{ __( 'Suggested category:' ) }}</strong> {{ $category }}
					@if ( null !== $confidence )
						<span class="forms-ai-classifier__confidence">
							({{ __( ':percent% confidence', ['percent' => (int) round( $confidence * 100 )] ) }})
						</span>
					@endif
				</p>

				@if ( null !== $suggestedNew )
					<p class="forms-ai-classifier__suggested-new">
						{{ __( 'Consider adding a new category:' ) }}
						<code>{{ $suggestedNew }}</code>
					</p>
				@endif

				<button
					type="button"
					wire:click="accept"
					class="forms-ai-classifier__accept cursor-pointer hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2"
				>
					{{ __( 'Apply category' ) }}
				</button>
			</div>
		@endif
	@endif
</div>
