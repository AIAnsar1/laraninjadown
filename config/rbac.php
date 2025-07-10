<?php




return [


    /*
    |--------------------------------------------------------------------------
    |  SUPER_ADMIN
    |--------------------------------------------------------------------------
    |
    | permission roles for SUPER_ADMIN
    | just add array controllers methods
    |
    |
    */
    'super_admin' => [
        'users' => [
            'index',
            'store',
            'show',
            'update',
            'destroy',
            'roles',
            'checkUserToken',
            'updateYourself',
        ],
    ],





    /*
    |--------------------------------------------------------------------------
    | MODERATOR
    |--------------------------------------------------------------------------
    |
    | permission roles for MODERATOR
    | just add array controllers methods
    |
    |
    */
    'moderator' => [
        'users' => [
            'checkUserToken',
            'updateYourself',
        ],
        'countries' => [
            'index',
            'store',
            'show',
            'update',
            'destroy',
        ],
        'check-email-verification' => [
            'checkEmailVerification',
        ],
    ],






    /*
    |--------------------------------------------------------------------------
    | EDITOR
    |--------------------------------------------------------------------------
    |
    | permission roles for EDITOR
    | just add array controllers methods
    |
    |
    */
    'editor' => [
        'users' => [
            'checkUserToken',
            'updateYourself',
        ],
        'countries' => [
            'index',
            'store',
            'show',
            'update',
            'destroy',
        ],
        'check-email-verification' => [
            'checkEmailVerification',
        ],
    ],





    /*
    |--------------------------------------------------------------------------
    | USER
    |--------------------------------------------------------------------------
    |
    | permission roles for SUPER USER
    | just add array controllers methods
    |
    |
    */
    'user' => [
        'users' => [
            'checkUserToken',
            'updateYourself',
        ],
    ],





    /*
    |--------------------------------------------------------------------------
    | SUPER_USER
    |--------------------------------------------------------------------------
    |
    | permission roles for SUPER_USER
    | just add array controllers methods
    |
    |
    */
    'super_user' => [
        'users' => [
            'index',
            'store',
            'show',
            'update',
            'destroy',
            'roles',
            'checkUserToken',
            'updateYourself',
        ],
    ],




    /*
    |--------------------------------------------------------------------------
    | NEW_USER
    |--------------------------------------------------------------------------
    |
    | permission roles for NEW_USER
    | just add array controllers methods
    |
    |
    */
    'new_user' => [
        'users' => [
            'checkUserToken',
            'updateYourself',
        ],
    ],
];
