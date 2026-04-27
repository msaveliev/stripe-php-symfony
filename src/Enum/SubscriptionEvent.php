<?php

declare(strict_types=1);

namespace App\Enum;

enum SubscriptionEvent: string
{
    case TRIAL_ENDED = 'trial_ended';
    case TRIAL_CANCELED = 'trial_canceled';
    case FIRST_PAYMENT_SUCCEEDED = 'first_payment_succeeded';
    case RENEWAL_PAYMENT_FAILED = 'renewal_payment_failed';
    case RENEWAL_PAYMENT_RECOVERED = 'renewal_payment_recovered';
    case RENEWAL_PAYMENT_FAILED_PERMANENTLY = 'renewal_payment_failed_permanently';
    case PLAN_CHANGED = 'plan_changed';
    case IMMEDIATE_CANCELLATION = 'immediate_cancel';
    case CANCEL_AT_PERIOD_END_ENABLED = 'cancel_at_period_end_enabled';
    case CANCEL_AT_PERIOD_END_DISABLED = 'cancel_at_period_end_disabled';
    case CANCEL_AT_CUSTOM_DATE_ENABLED = 'cancel_at_custom_date_enabled';
    case CANCEL_AT_CUSTOM_DATE_DISABLED = 'cancel_at_custom_date_disabled';
}
