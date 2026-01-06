<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{{ $parsedSubject }}</title>
    <style>
        /* Reset */
        body, table, td, p, a, li, blockquote {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        img {
            -ms-interpolation-mode: bicubic;
        }

        /* Base styles */
        body {
            margin: 0;
            padding: 0;
            width: 100% !important;
            height: 100% !important;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #1f2937;
            background-color: #f3f4f6;
        }

        /* Container */
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }

        /* Header */
        .email-header {
            background-color: #3b82f6;
            padding: 24px;
            text-align: center;
        }
        .email-header h1 {
            margin: 0;
            color: #ffffff;
            font-size: 24px;
            font-weight: 600;
        }
        .email-header p {
            margin: 8px 0 0;
            color: rgba(255, 255, 255, 0.9);
            font-size: 14px;
        }

        /* Body */
        .email-body {
            padding: 32px 24px;
        }
        .email-body p {
            margin: 0 0 16px;
        }
        .email-body h2 {
            margin: 24px 0 16px;
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
        }

        /* Message content */
        .message-content {
            white-space: pre-wrap;
            margin-bottom: 24px;
        }

        /* Submission data table */
        .submission-data {
            margin: 24px 0;
        }
        .submission-data h2 {
            margin: 0 0 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e5e7eb;
        }
        .submission-data table {
            width: 100%;
            border-collapse: collapse;
        }
        .submission-data td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }
        .submission-data td:first-child {
            font-weight: 500;
            color: #374151;
            width: 35%;
        }
        .submission-data td:last-child {
            color: #1f2937;
        }

        /* Footer */
        .email-footer {
            background-color: #f9fafb;
            padding: 24px;
            border-top: 1px solid #e5e7eb;
        }
        .email-footer p {
            margin: 0;
            font-size: 12px;
            color: #6b7280;
            text-align: center;
        }
        .email-footer .metadata {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
        }
        .email-footer .metadata p {
            margin: 4px 0;
        }

        /* Utility */
        .text-muted {
            color: #6b7280;
        }
        .text-small {
            font-size: 12px;
        }

        /* Responsive */
        @media only screen and (max-width: 620px) {
            .email-container {
                width: 100% !important;
            }
            .email-body {
                padding: 24px 16px !important;
            }
            .submission-data td {
                display: block;
                width: 100%;
            }
            .submission-data td:first-child {
                padding-bottom: 4px;
                border-bottom: none;
            }
        }
    </style>
</head>
<body>
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f3f4f6;">
        <tr>
            <td align="center" style="padding: 24px;">
                <!-- Email Container -->
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" class="email-container" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td class="email-header" style="background-color: #3b82f6; padding: 24px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 600;">
                                {{ $submission->form->name }}
                            </h1>
                            <p style="margin: 8px 0 0; color: rgba(255, 255, 255, 0.9); font-size: 14px;">
                                New Submission &middot; {{ $submission->created_at->format('F j, Y \a\t g:i A') }}
                            </p>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td class="email-body" style="padding: 32px 24px;">
                            <!-- Parsed Message Content -->
                            @if($parsedMessage)
                                <div class="message-content" style="white-space: pre-wrap; margin-bottom: 24px;">
                                    {!! nl2br(e($parsedMessage)) !!}
                                </div>
                            @endif

                            <!-- Submission Data Table -->
                            @if($notification->include_submission_data)
                                <div class="submission-data" style="margin: 24px 0;">
                                    <h2 style="margin: 0 0 16px; padding-bottom: 8px; border-bottom: 2px solid #e5e7eb; font-size: 18px; font-weight: 600; color: #1f2937;">
                                        Submission Details
                                    </h2>
                                    {!! $submissionDataTable !!}
                                </div>
                            @endif

                            <!-- Submission Reference -->
                            <p style="margin: 24px 0 0; font-size: 14px; color: #6b7280;">
                                <strong>Reference:</strong> {{ $submission->submission_number }}
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td class="email-footer" style="background-color: #f9fafb; padding: 24px; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0; font-size: 12px; color: #6b7280; text-align: center;">
                                This email was sent from {{ config('app.name') }}
                            </p>

                            @if($submission->page_url || ($showIpAddress && $submission->ip_address))
                                <div class="metadata" style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #e5e7eb;">
                                    @if($submission->page_url)
                                        @php
                                            // Validate URL is safe (http/https only, no javascript: URIs)
                                            $pageUrl = $submission->page_url;
                                            $isSafeUrl = preg_match('/^https?:\/\//i', $pageUrl);
                                        @endphp
                                        <p style="margin: 4px 0; font-size: 12px; color: #6b7280; text-align: center;">
                                            <strong>Page URL:</strong>
                                            @if($isSafeUrl)
                                                <a href="{{ e($pageUrl) }}" style="color: #3b82f6; text-decoration: underline;">{{ e($pageUrl) }}</a>
                                            @else
                                                {{ e($pageUrl) }}
                                            @endif
                                        </p>
                                    @endif
                                    @if($showIpAddress && $submission->ip_address)
                                        <p style="margin: 4px 0; font-size: 12px; color: #6b7280; text-align: center;">
                                            <strong>IP Address:</strong> {{ $submission->ip_address }}
                                        </p>
                                    @endif
                                </div>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
