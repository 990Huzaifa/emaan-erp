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
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'whatsapp' => [
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID','732336233285690'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN','EAAR1Rq25RVcBOxT3HAkUXcDsafKg61AxiAgsoGT2U2PYmRdvxlD7813L3lPOSgzhbWyrTZBk56wzDRlLmZC8kVTnpNUUowGiypRG8ZCpA6TVPrzow0H569MwmV9VinKocz3DspFktLIM4FXKmdJBdtnaOkNwdA8XMOHoJBWgzMbndlh6LnZCirCcxs8yLC8PGgZDZD'),
    ],

];
