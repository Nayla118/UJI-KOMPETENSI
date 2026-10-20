<?php

return [
    'otp' => [
        'subject' => ':app — your login verification code',
        'title' => 'Login verification code',
        'preheader' => 'Use this one-time code to sign in to Travelo.',
        'subtitle' => 'Enter the code below to finish signing in.',
        'greeting' => 'Hi, you tried to sign in to Travelo. Use this code (valid for :minutes minutes):',
        'security' => 'Never share this code with anyone. Travelo never asks for your verification code by phone or message.',
        'ignore' => 'Not signing in? Ignore this email — nobody can sign in without this code.',
        'footer' => '© :year Travelo · Automated email, please do not reply',
        'text' => [
            'subject' => ':app — your login verification code',
            'intro' => 'Your verification code:',
            'expiry' => 'This code is valid for :minutes minutes and can only be used once.',
            'security' => 'Never share this code with anyone. Travelo never asks for your verification code by phone or message.',
            'ignore' => 'Not signing in? Ignore this email — nobody can sign in without this code.',
            'footer' => 'Automated email from Travelo, please do not reply.',
        ],
        'api' => [
            'missing_parameters' => 'Missing required parameters.',
            'id_token_required' => 'The Firebase ID token is required.',
            'code_format' => 'The code must be 6 digits.',
            'no_email' => 'Your Firebase account has no email address.',
            'invalid_session' => 'Your session is invalid. Please sign in again.',
            'verification_unavailable' => 'Verification is temporarily unavailable. Please try again.',
            'code_invalid' => 'The code is invalid or has expired. Request a new code.',
            'too_many_attempts' => 'Too many incorrect attempts. The code was cancelled; request a new one.',
            'wrong_code' => 'Incorrect code. Attempts remaining: :attempts.',
            'verified' => 'Verification successful.',
            'sent' => 'A verification code has been sent to your email.',
            'resend_wait' => 'Wait :seconds seconds before requesting a new code.',
            'mail_failed' => 'Could not send the verification email. Check the backend mail configuration and try again.',
        ],
    ],

    'callback' => [
        'success' => [
            'title' => 'Payment successful',
            'body' => 'Thank you. Your payment has been received and your booking will be confirmed automatically.',
        ],
        'pending' => [
            'title' => 'Waiting for payment',
            'body' => 'The transaction is not finished yet. Complete the payment using the instructions for your chosen method, then return to the Travelo app to refresh the status.',
        ],
        'error' => [
            'title' => 'Payment not completed',
            'body' => 'The transaction could not be processed. Return to the Travelo app to try again.',
        ],
        'booking_id' => 'Booking ID',
        'open_app' => 'Open the Travelo app again to see the latest status.',
    ],
];
