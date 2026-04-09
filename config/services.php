<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*******************************************************************************
     *                                Copyright : AGmedia                           *
     *                              email: filip@agmedia.hr                         *
     *******************************************************************************/

    'recaptcha' => [
        'sitekey'    => env('GOOGLE_RECAPTCHA_SITE_KEY'),
        'secret'     => env('GOOGLE_RECAPTCHA_SECRET_KEY'),
        'verify_url' => 'https://www.google.com/recaptcha/api/siteverify',
    ],

    'mailchimp' => [
        'api_key'       => env('MAILCHIMP_API_KEY'),
        'audience_id'   => env('MAILCHIMP_AUDIENCE_ID'),
        'server_prefix' => env('MAILCHIMP_SERVER_PREFIX'),
    ],

    'google_analytics' => [
        'measurement_id'         => env('GOOGLE_ANALYTICS_MEASUREMENT_ID', 'G-WWPNJL6JD5'),
        'measurement_api_secret' => env('GOOGLE_ANALYTICS_MEASUREMENT_API_SECRET'),
    ],

    'wolt' => [
        'url'         => env('WOLT_API_URL', 'https://daas-public-api.wolt.com'),
        'api_key'     => env('WOLT_API_KEY'),
        'merchant_id' => env('WOLT_MERCHANT_ID'), // nije potreban u venueful flowu, ali može ostati
        'venue_id'    => env('WOLT_VENUE_ID'),
    ],

    'pelion' => [
        'base_url' => env('PELION_BASE_URL', 'https://api.pelionpro.com/api/v1'),
        'api_key' => env('PELION_API_KEY'),
    ],

    /*******************************************************************************
     *                              END Copyright : AGmedia                         *
     *******************************************************************************/

];
