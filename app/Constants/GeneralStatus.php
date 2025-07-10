<?php 


namespace App\Constants;



enum GeneralStatus: int
{
    public const STATUS_DELETED = 0;
    public const STATUS_NOT_ACTIVE = 10;
    public const STATUS_ACTIVE = 20;
    public const PER_PAGE = 20;
}