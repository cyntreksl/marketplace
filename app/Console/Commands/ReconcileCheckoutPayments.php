<?php

namespace App\Console\Commands;

use App\Contracts\Repositories\CheckoutRepository;
use App\Services\CheckoutPaymentService;
use Illuminate\Console\Command;
use Throwable;

class ReconcileCheckoutPayments extends Command
{
    protected $signature = 'checkout:reconcile-payments';

    protected $description = 'Reconcile overdue card payments and release expired stock reservations';

    public function handle(CheckoutRepository $orders, CheckoutPaymentService $payments): int
    {
        $failed = false;
        foreach ($orders->duePayments() as $payment) {
            try {
                $payments->reconcile($payment);
            } catch (Throwable $exception) {
                report($exception);
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
