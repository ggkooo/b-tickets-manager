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

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ticket_printer' => [
        'enabled' => env('TICKET_PRINTER_ENABLED', false),
        'connection' => env('TICKET_PRINTER_CONNECTION', 'network'),
        'host' => env('TICKET_PRINTER_HOST'),
        'port' => env('TICKET_PRINTER_PORT', 9100),
        'smb_uri' => env('TICKET_PRINTER_SMB_URI'),
        'cups_queue' => env('TICKET_PRINTER_CUPS_QUEUE'),
        'profile' => env('TICKET_PRINTER_PROFILE', 'simple'),
        'header' => env('TICKET_PRINTER_HEADER', 'SENHA DE ATENDIMENTO'),
    ],

];
