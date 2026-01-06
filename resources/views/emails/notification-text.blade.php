{{ $parsedSubject }}
================================================================================

{{ $submission->form->name }}
New Submission - {{ $submission->created_at->format('F j, Y \a\t g:i A') }}

--------------------------------------------------------------------------------

@if($parsedMessage)
{{ $parsedMessage }}

@endif
@if($notification->include_submission_data)
SUBMISSION DETAILS
------------------
@foreach($submission->values as $value)
{{ $value->field?->label ?? $value->field_name }}: {{ $value->display_value ?? '' }}
@endforeach

@endif
Reference: {{ $submission->submission_number }}

================================================================================

This email was sent from {{ config('app.name') }}
@if($submission->page_url || ($showIpAddress && $submission->ip_address))

@if($submission->page_url)
Page URL: {{ $submission->page_url }}
@endif
@if($showIpAddress && $submission->ip_address)
IP Address: {{ $submission->ip_address }}
@endif
@endif
