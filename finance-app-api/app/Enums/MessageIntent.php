<?php

namespace App\Enums;

enum MessageIntent: string
{
    case AddTransaction = 'add_transaction';
    case QueryReport = 'query_report';
    case Correction = 'correction';
    case DeleteTransaction = 'delete_transaction';
    case InspectRecords = 'inspect_records';
    case ManageRecords = 'manage_records';
    case SavingsAdvice = 'savings_advice';
    case GreetingSmallTalk = 'greeting_smalltalk';
    case Unclear = 'unclear';
}
