<?php
return [
    /*
    |--------------------------------------------------------------------------
    | LM_SALT
    |--------------------------------------------------------------------------
    |
    | Random salt used for encryption. It should contain random symbols (16 or more recommended) 
    | and be different for each application you want to protect. Cannot be modified after installing script.
    |
    */
    'LM_PRODUCT_KEY' => env('PRODUCT_KEY', 'A4YFMNWCA2DD7PS6Z383MVHYKZXZG6X5UU7RMYQ692DFJJU3MST3WA3CUAE5'),
    'LM_API_KEY' => env('LM_API_KEY', 'LDF55GHJ6NTLDZ2R6CAGYEEUP67DP9X9CDT6GNCH'),
    /*
    |--------------------------------------------------------------------------
    | LM_ROOT_URL
    |--------------------------------------------------------------------------
    |
    | URL of the License Manager App
    |
    */

    'LM_ROOT_URL' => env('LM_ROOT_URL', 'http://licensemanager:8888'),

    /*
    |--------------------------------------------------------------------------
    | LM_PRODUCT_ID
    |--------------------------------------------------------------------------
    |
    | Product ID from License Manager App.
    |
    */
    'LM_PRODUCT_ID' => env('PRODUCT_ID', 1),


    /*
    |--------------------------------------------------------------------------
    | LM_DAYS
    |--------------------------------------------------------------------------
    |
    | Time period (in days) between automatic license verifications. The lower the number,
    | the more often license will be verified, but if many end users use your script, it can cause extra load on your server.
    | Available values are between 1 and 365. Usually 7 or 14 days are the best choice.
    |
    */
    'LM_DAYS' => env('LICENSE_CHECK_TIME', 2),



    /*
    |--------------------------------------------------------------------------
    | LM_DELETE_CRACKED
    |--------------------------------------------------------------------------
    |
    | When option set to "YES", script files and MySQL data will be deleted when cracking attempt is detected.
    | This is very useful against users who may try cracking software; if some unauthorized changes in
    | core functions are detected, Auto PHP Licenser will try to delete all script files and any data in MySQL database.
    | Use at your own risk!
    |
    */
    'LM_DELETE_CRACKED' => false,

    'LM_DELETE_CANCELLED' => false,

    /*
    |--------------------------------------------------------------------------
    | LM_MESSAGES
    |--------------------------------------------------------------------------
    |
    | License Manager App Messages.
    |
    */
    'LM_NOTIFICATION_NO_CONNECTION' => "Can't connect to licensing server.",
    'LM_NOTIFICATION_INVALID_SIGNATURE' => "Installation on localhost is not allowed.",
    'LM_NOTIFICATION_INVALID_RESPONSE' => 'Invalid server response.',
    'LM_NOTIFICATION_DATABASE_WRITE_ERROR' => "Can't write to database.",
    'LM_CORE_NOTIFICATION_API_WHITELIST_ISSUE' => "License Error: License server doesn't identify you as whitelisted accessor.",
    'LM_NOTIFICATION_LICENSE_FILE_WRITE_ERROR' => "Can't write to license file.",
    'LM_CORE_NOTIFICATION_LICENSE_EXPIRED_PERIOD' => 'License is already expired',
    'LM_CORE_NOTIFICATION_LICENSE_UPDATE_EXPIRED' => 'Updates to this product is already expired',
    'LM_CORE_NOTIFICATION_LICENSE_NO_UPDATE' => 'This product doesnt support updates.',
    'LM_CORE_NOTIFICATION_LICENSE_NO_SUPPORT' => 'This product doesnt have support.',
    'LM_CORE_NOTIFICATION_LICENSE_SUPPORT_EXPIRED' => 'Support is already expired',
    'LM_CORE_NOTIFICATION_LICENSE_SUSPENDED' => 'License is suspended due to illegal usage of application.',
    'LM_NOTIFICATION_LICENSE_CORRUPTED' => 'License is not installed yet or corrupted.',
    'LM_NOTIFICATION_BYPASS_VERIFICATION' => 'No need to verify.',
    'LM_NOTIFICATION_LICENSE_OCCUPIED' => 'All purchase licenses are already occupied.',
    'LM_NOTIFICATION_SCRIPT_ALREADY_INSTALLED' => 'Script is already installed (or database not empty).',
    "LM_CORE_NOTIFICATION_INVALID_PRODUCT_KEY" => "Configuration error: invalid or default product key missing.",
    "LM_CORE_NOTIFICATION_INVALID_ROOT_URL" => "Configuration error: invalid root URL of installation",
    "LM_CORE_NOTIFICATION_INVALID_PRODUCT_ID" => "Configuration error: invalid product ID",
    "LM_CORE_NOTIFICATION_CLIENT_NOT_FOUND" => "Configuration error: client not found.",
    "LM_CORE_NOTIFICATION_INVALID_VERIFICATION_PERIOD" => "Configuration error: invalid license verification period",
    "LM_CORE_NOTIFICATION_INVALID_STORAGE" => "Configuration error: invalid license storage option",
    "LM_CORE_NOTIFICATION_INVALID_TABLE" => "Configuration error: invalid MySQL table name to store license signature",
    "LM_CORE_NOTIFICATION_INVALID_LICENSE_KEY" => "Configuration error: invalid license key.",
    "LM_CORE_NOTIFICATION_INVALID_LICENSE_FILE" => "Configuration error: invalid license file location (or file not writable)",
    "LM_CORE_NOTIFICATION_INVALID_ROOT_IP" => "Configuration error: invalid IP address of your Auto PHP Licenser installation",
    "LM_CORE_NOTIFICATION_INVALID_ROOT_NAMESERVERS" => "Configuration error: invalid nameservers of your Auto PHP Licenser installation",
    "LM_CORE_NOTIFICATION_INVALID_DNS" => "License error: actual IP address and/or nameservers of your Auto PHP Licenser installation don't match specified IP address and/or nameservers",
    "LM_CORE_NOTIFICATION_MISSING_INSTALL_API_KEY" => "License is missing Installer API Key. Please contact your vendor for further details.",
    "LM_CORE_NOTIFICATION_LICENSE_KEYFILE_MISSING" => "License error: This app hasn't completed all the steps of Setup.",
    "LM_CORE_NOTIFICATION_LICENSE_FILE_MISSING" => "License error: Actual setup is missing license file",


];
