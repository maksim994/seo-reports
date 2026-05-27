<?php

namespace App\Enums;

enum IntegrationStatus: string
{
    case Active = 'active';
    case TokenExpired = 'token_expired';
    case Error = 'error';
}
