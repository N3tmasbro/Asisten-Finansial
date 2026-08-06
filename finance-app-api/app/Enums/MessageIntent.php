<?php

namespace App\Enums;

enum MessageIntent: string
{
    case AddTransaction = 'add_transaction';
    case QueryReport = 'query_report';
    case Correction = 'correction';
    case GreetingSmallTalk = 'greeting_smalltalk';
    case Unclear = 'unclear';
}
