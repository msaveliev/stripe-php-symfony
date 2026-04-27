<?php

declare(strict_types=1);

namespace App\Enum;

enum PaymentStatus: string
{
    case REQUIRES_PAYMENT_METHOD = 'requires_payment_method';
    case REQUIRES_CONFIRMATION = 'requires_confirmation';
    case REQUIRES_ACTION = 'requires_action';
    case PROCESSING = 'processing';
    case REQUIRES_CAPTURE = 'requires_capture';
    case FAILED = 'failed';
    case CANCELED = 'canceled';
    case SUCCEEDED = 'succeeded';
}
