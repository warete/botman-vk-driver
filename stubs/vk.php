<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Vk Token
    |--------------------------------------------------------------------------
    |
    | Your Vk bot token.
    |
    */
    'access_token' => env('VK_ACCESS_TOKEN'),
    /*
    |--------------------------------------------------------------------------
    | Vk API version
    |--------------------------------------------------------------------------
    |
    | Version to use while performing queries.
    |
    */
    'api_version' => env('VK_API_VERSION'),
    /*
    |--------------------------------------------------------------------------
    | Language
    |--------------------------------------------------------------------------
    |
    | Used to perform queries to vk api.
    |
    */
    'lang' => env('VK_LANG'),
    /*
    |--------------------------------------------------------------------------
    | Secret key
    |--------------------------------------------------------------------------
    |
    | Used to authenticate incoming requests.
    |
    */
    'secret_key' => env('VK_SECRET_KEY'),
	/*
    |--------------------------------------------------------------------------
    | Confirmation code
    |--------------------------------------------------------------------------
    |
    | Used to accept testing request from VK.
    |
    */
    'confirmation_code' => env('VK_CONFIRMATION_CODE'),
];
