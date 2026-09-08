<?php

namespace App\Enums;

enum EvaluationRequestStatus: string
{
    case Draft = 'draft';
    case AwaitingPayment = 'awaiting_payment';
    case Paid = 'paid';
    case Intake = 'intake';
    case AwaitingCreator = 'awaiting_creator';
    case Ready = 'ready';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
}
