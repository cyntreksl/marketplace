<?php

namespace App;

enum BuyerOrderStage: string
{
    case All = 'all';
    case ToPay = 'to_pay';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Completed = 'completed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::All => 'All',
            self::ToPay => 'To pay',
            self::Processing => 'Processing',
            self::Shipped => 'Shipped',
            self::Completed => 'Completed',
            self::Archived => 'Archived',
        };
    }
}
