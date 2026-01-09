{{ $parsedSubject }}
================================================================================

{{ $submission->form->name }}
{{ __( 'New Submission' ) }} - {{ $submission->created_at->format('F j, Y \a\t g:i A') }}

--------------------------------------------------------------------------------

@if($parsedMessage)
{{ $parsedMessage }}

@endif
@if($notification->include_submission_data)
{{ __( 'SUBMISSION DETAILS' ) }}
------------------
@foreach($submission->values as $value)
{{ $value->field?->label ?? $value->field_name }}: {{ $value->display_value ?? '' }}
@endforeach

@endif
{{ __( 'Reference' ) }}: {{ $submission->submission_number }}

================================================================================

{{ __( 'This email was sent from :name', ['name' => config('app.name')] ) }}
@if($submission->page_url || ($showIpAddress && $submission->ip_address))

@if($submission->page_url)
{{ __( 'Page URL' ) }}: {{ $submission->page_url }}
@endif
@if($showIpAddress && $submission->ip_address)
{{ __( 'IP Address' ) }}: {{ $submission->ip_address }}
@endif
@endif
