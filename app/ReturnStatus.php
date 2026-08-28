<?php

namespace App;

enum ReturnStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case RefundPending = 'refund_pending';
    case Refunded = 'refunded';
    case RefundFailed = 'refund_failed';
}
