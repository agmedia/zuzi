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

    'gls' => [
        'client_number' => env('GLS_CLIENT_NUMBER', 380006507),
        'username' => env('GLS_USERNAME', 'info@zuzi.hr'),
        'password' => env('GLS_PASSWORD', 'Mimizizi0510'),
        'wsdl' => env('GLS_WSDL', 'https://api.mygls.hr/ParcelService.svc?singleWsdl'),
        'language' => env('GLS_LANGUAGE', 'HR'),
        'printer_type' => env('GLS_PRINTER_TYPE', 'A4_2x2'),
        'tracking_url' => env('GLS_TRACKING_URL', 'https://gls-group.com/HR/hr/pracenje-posiljke/'),
    ],

    'boxnow' => [
        'base_url' => env('BOXNOW_API_URL', 'https://api-production.boxnow.hr/api/v1'),
        'client_id' => env('BOXNOW_CLIENT_ID', '813f3a0c-bbea-4f83-bfb1-a605e79a11cd'),
        'client_secret' => env('BOXNOW_CLIENT_SECRET', '8dc55b1ef55369e08c1b8eada89e2ba1bdf9256fdeb43c81cc1efc5f4755953b'),
        'tracking_url' => env('BOXNOW_TRACKING_URL', 'https://track.boxnow.hr/en?track={parcel}'),
        'webhook_secret' => env('BOXNOW_WEBHOOK_SECRET'),
    ],

    'pelion' => [
        'base_url' => env('PELION_BASE_URL', 'https://zuzishop.pelionpro.com/api/v1'),
        'api_key' => env('PELION_API_KEY'),
    ],

    /*******************************************************************************
     *                              END Copyright : AGmedia                         *
     *******************************************************************************/

];
