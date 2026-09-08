<?php

namespace App;

enum AuctionType: string
{
    case Normal = 'normal';
    case Blind = 'blind';
    case TimeExtended = 'time_extended';

    public function settingKey(): string
    {
        return "auction.types.{$this->value}.enabled";
    }
}
