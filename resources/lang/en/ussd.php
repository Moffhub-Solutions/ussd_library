<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation Prompts
    |--------------------------------------------------------------------------
    */

    'navigation' => [
        'back' => 'Back',
        'home' => 'Home',
        'next' => 'Next',
        'previous' => 'Previous',
        'search' => 'Search',
        'cancel' => 'Cancel',
        'confirm' => 'Confirm',
        'select_option' => 'Select an option:',
        'page_info' => 'Page :current of :total',
        'enter_search' => 'Enter search term:',
        'no_results' => 'No results found.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Messages
    |--------------------------------------------------------------------------
    */

    'errors' => [
        'invalid_option' => 'Invalid option. Please try again.',
        'session_expired' => 'Your session has expired. Please dial again.',
        'service_unavailable' => 'Service temporarily unavailable. Please try again later.',
        'rate_limited' => 'Too many requests. Please wait and try again.',
        'invalid_input' => 'Invalid input. Please try again.',
        'unauthorized' => 'You are not authorized to access this service.',
        'general_error' => 'An error occurred. Please try again.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Messages
    |--------------------------------------------------------------------------
    */

    'validation' => [
        'required' => 'This field is required.',
        'min_length' => 'Input must be at least :min characters.',
        'max_length' => 'Input must not exceed :max characters.',
        'numeric' => 'Please enter a valid number.',
        'phone' => 'Please enter a valid phone number.',
        'email' => 'Please enter a valid email address.',
        'in_options' => 'Please select a valid option.',
        'regex' => 'Input format is invalid.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Messages
    |--------------------------------------------------------------------------
    */

    'session' => [
        'welcome_back' => 'Welcome back! You can continue from where you left off.',
        'welcome_back_form' => 'Welcome back! You were filling out a form (:percentage% complete). Would you like to continue?',
        'welcome_back_transaction' => 'Welcome back! You had a pending transaction. Would you like to continue?',
        'welcome_back_navigation' => 'Welcome back! You can continue from where you left off in the menu.',
        'context_preserved' => "Welcome back! We've preserved your previous session context.",
        'grace_period' => 'Your session timed out but you can continue where you left off.',
    ],

    /*
    |--------------------------------------------------------------------------
    | General Messages
    |--------------------------------------------------------------------------
    */

    'general' => [
        'processing' => 'Processing...',
        'please_wait' => 'Please wait...',
        'success' => 'Operation completed successfully.',
        'goodbye' => 'Thank you for using our service. Goodbye!',
    ],
];
