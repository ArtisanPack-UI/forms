<div class="forms-ai-spam-check" data-feature="forms.spam_detection">
	@if ( ! $this->isEnabled )
		<p class="forms-ai-spam-check__disabled">
			{{ __( 'AI spam detection is currently disabled.' ) }}
		</p>
	@else
		<button
			type="button"
			wire:click="check"
			wire:loading.attr="disabled"
			wire:target="check"
			@disabled( $isLoading || [] === $fields )
			class="forms-ai-spam-check__button cursor-pointer hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed"
		>
			<span wire:loading.remove wire:target="check">
				{{ __( 'Score for spam' ) }}
			</span>
			<span wire:loading wire:target="check">
				{{ __( 'Scoring…' ) }}
			</span>
		</button>

		@if ( null !== $error )
			<p class="forms-ai-spam-check__error" role="alert">
				{{ $error }}
			</p>
		@endif

		@if ( null !== $verdict && null !== $spamScore )
			<div
				class="forms-ai-spam-check__result"
				data-verdict="{{ $verdict }}"
			>
				<p class="forms-ai-spam-check__verdict">
					<strong>{{ __( 'Verdict:' ) }}</strong> {{ $verdict }}
					<span class="forms-ai-spam-check__score">({{ $spamScore }}/100)</span>
				</p>

				@if ( ! empty( $reasons ) )
					<ul class="forms-ai-spam-check__reasons">
						@foreach ( $reasons as $index => $reason )
							<li wire:key="spam-reason-{{ $index }}">{{ $reason }}</li>
						@endforeach
					</ul>
				@endif
			</div>
		@endif
	@endif
</div>
