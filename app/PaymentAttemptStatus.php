<?php

namespace App;

enum PaymentAttemptStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Succeeded => 'Successful',
            self::Failed => 'Failed',
            self::Expired => 'Expired',
        };
    }
}
