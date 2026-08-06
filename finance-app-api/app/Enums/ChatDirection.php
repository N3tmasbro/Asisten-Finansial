<?php

namespace App\Enums;

enum ChatDirection: string
{
    case Incoming = 'incoming';
    case Outgoing = 'outgoing';
}
