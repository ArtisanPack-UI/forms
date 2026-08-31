<div class="forms-ai-summary" data-feature="forms.submission_summary">
	@if ( ! $this->isEnabled )
		<p class="forms-ai-summary__disabled">
			{{ __( 'AI submission summaries are currently disabled.' ) }}
		</p>
	@else
		<button
			type="button"
			wire:click="summarize"
			wire:loading.attr="disabled"
			wire:target="summarize"
			@disabled( $isLoading || '' === trim( $formName ) )
			class="forms-ai-summary__button cursor-pointer hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed"
		>
			<span wire:loading.remove wire:target="summarize">
				{{ __( 'Summarize submissions' ) }}
			</span>
			<span wire:loading wire:target="summarize">
				{{ __( 'Summarizing…' ) }}
			</span>
		</button>

		@if ( null !== $error )
			<p class="forms-ai-summary__error" role="alert">
				{{ $error }}
			</p>
		@endif

		@if ( null !== $headline )
			<div class="forms-ai-summary__result">
				<p class="forms-ai-summary__headline">{{ $headline }}</p>
				<p class="forms-ai-summary__total">
					{{ __( ':count submissions', ['count' => $totalCount] ) }}
					@if ( $sampleCount > 0 && $sampleCount < $totalCount )
						<span class="forms-ai-summary__sample-note">
							{{ __( '(themes reflect the first :sample of :total submissions)', ['sample' => $sampleCount, 'total' => $totalCount] ) }}
						</span>
					@endif
				</p>

				@if ( ! empty( $themes ) )
					<section class="forms-ai-summary__themes">
						<h4>{{ __( 'Themes' ) }}</h4>
						<ul>
							@foreach ( $themes as $index => $theme )
								<li wire:key="theme-{{ $index }}">
									<strong>{{ $theme['title'] }}</strong>
									<span>({{ $theme['count'] }})</span>
									@if ( ! empty( $theme['examples'] ) )
										<ul class="forms-ai-summary__examples">
											@foreach ( $theme['examples'] as $exampleIndex => $example )
												<li wire:key="theme-{{ $index }}-example-{{ $exampleIndex }}">{{ $example }}</li>
											@endforeach
										</ul>
									@endif
								</li>
							@endforeach
						</ul>
					</section>
				@endif

				@if ( ! empty( $notable ) )
					<section class="forms-ai-summary__notable">
						<h4>{{ __( 'Notable' ) }}</h4>
						<ul>
							@foreach ( $notable as $index => $item )
								<li wire:key="notable-{{ $index }}">{{ $item }}</li>
							@endforeach
						</ul>
					</section>
				@endif

				@if ( ! empty( $suggestions ) )
					<section class="forms-ai-summary__suggestions">
						<h4>{{ __( 'Suggestions' ) }}</h4>
						<ul>
							@foreach ( $suggestions as $index => $item )
								<li wire:key="suggestion-{{ $index }}">{{ $item }}</li>
							@endforeach
						</ul>
					</section>
				@endif
			</div>
		@endif
	@endif
</div>
