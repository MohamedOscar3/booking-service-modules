<?php

namespace Modules\Auth\Enums;

enum Roles: string
{
    case ADMIN = 'admin';
    case USER = 'user';
    case PROVIDER = 'provider';

}
