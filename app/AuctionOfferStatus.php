<?php

namespace App;

enum AuctionOfferStatus: string
{
    case Waiting = 'waiting';
    case Offered = 'offered';
    case Accepted = 'accepted';
    case Paid = 'paid';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
