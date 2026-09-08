<?php

namespace App;

enum AuctionStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Live = 'live';
    case OfferPending = 'offer_pending';
    case Sold = 'sold';
    case EndedNoBids = 'ended_no_bids';
    case BidderExhausted = 'bidder_exhausted';
    case Cancelled = 'cancelled';

    /** @return list<string> */
    public static function nonTerminalValues(): array
    {
        return [self::Draft->value, self::Scheduled->value, self::Live->value, self::OfferPending->value];
    }
}
