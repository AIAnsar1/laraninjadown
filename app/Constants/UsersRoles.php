<?php

namespace App\Constants;

use JetBrains\PhpStorm\ArrayShape;

class UsersRoles
{
    /**
     * @var string
     */
    public const SUPER_ADMIN = 'super_admin';
    /**
     * @var string
     */
    public const MODERATOR = 'moderator';
    /**
     * @var string
     */
    public const EDITOR = 'editor';
    /**
     * @var string
     */
    public const USER = 'user';
    /**
     * @var string
     */
    public const SUPER_USER = 'super_user';
    /**
     * @var string
     */
    public const NEW_USER = 'new_user';



    /**
     * @return string[]
     */
    #[ArrayShape([
        self::SUPER_ADMIN => "string",
        self::MODERATOR => "string",
        self::EDITOR => "string",
        self::USER => "string",
        self::SUPER_USER => "string",
        self::NEW_USER => "string",
    ])]
    public static function getRoleList(): array
    {
        return [
            self::SUPER_ADMIN => 'super_admin',
            self::MODERATOR => 'moderator',
            self::EDITOR => 'editor',
            self::USER => 'user',
            self::SUPER_USER => 'super_user',
            self::NEW_USER => 'new_user'
        ];
    }



    /**
     * @param string $role_code
     * @return string
     */
    #[ArrayShape([
        self::SUPER_ADMIN => "string",
        self::MODERATOR => "string",
        self::EDITOR => "string",
        self::USER => "string",
        self::SUPER_USER => "string",
        self::NEW_USER => "string",
    ])]
    public static function getRoleName(string $role_code): string
    {
        return match ($role_code) {
            self::SUPER_ADMIN => 'super_admin',
            self::MODERATOR => 'moderator',
            self::EDITOR => 'editor',
            self::USER => 'user',
            self::SUPER_USER => 'super_user',
            self::NEW_USER => 'new_user',
            default => '',
        };
    }
}