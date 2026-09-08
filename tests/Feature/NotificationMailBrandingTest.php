<?php

use App\Models\User;
use App\Notifications\BuyerOrderStatusNotification;
use App\Notifications\NewReturnRequestNotification;
use App\Notifications\OrderAcknowledgmentNotification;
use App\Notifications\PaymentConfirmedNotification;
use App\Notifications\QueuedResetPasswordNotification;
use App\Notifications\QueuedVerifyEmailNotification;
use App\Notifications\RefundOutcomeNotification;
use App\Notifications\RefundReadyNotification;
use App\Notifications\ReturnDecisionNotification;
use App\Notifications\SellerOrderReadyNotification;
use App\Notifications\SeoMonitoringNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Markdown;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

test('mail uses the ProDeals theme and support reply address', function () {
    expect(config('mail.markdown.theme'))->toBe('prodeals')
        ->and(config('mail.reply_to.address'))->toBe('support@prodeals.lk')
        ->and(config('mail.reply_to.name'))->toBe('ProDeals.lk Support');
});

test('every transactional notification has consistent branded content', function (
    Closure $notificationFactory,
    string $expectedSubject,
    string $expectedAction,
    string $expectedActionUrlFragment,
    string $expectedContent,
) {
    config()->set('app.url', 'https://prodeals.test');
    URL::forceRootUrl('https://prodeals.test');
    URL::forceScheme('https');

    $user = User::factory()->unverified()->create(['name' => 'Priya Perera']);
    $notification = $notificationFactory();

    expect($notification)->toBeInstanceOf(Notification::class);

    expect($notification)
        ->toBeInstanceOf(ShouldQueue::class)
        ->and(class_uses_recursive($notification))->toContain(Queueable::class)
        ->and($notification->afterCommit)->toBeTrue();

    $mailMessage = $notification->toMail($user);

    expect($mailMessage)->toBeInstanceOf(MailMessage::class)
        ->and($mailMessage->subject)->toBe($expectedSubject)
        ->and($mailMessage->greeting)->toBe('Hello Priya Perera,')
        ->and($mailMessage->actionText)->toBe($expectedAction)
        ->and($mailMessage->actionUrl)->toContain($expectedActionUrlFragment)
        ->and([...$mailMessage->introLines, ...$mailMessage->outroLines])->toContain($expectedContent);

    $html = (string) $mailMessage->render();
    $markdown = app(Markdown::class)->theme(config('mail.markdown.theme'));
    $text = (string) $markdown->renderText($mailMessage->markdown, $mailMessage->data());

    expect($html)
        ->toContain('prodeals-email-logo.png?v=')
        ->toContain('alt="ProDeals.lk"')
        ->toContain('background-color: #ff6d00')
        ->toContain('Better deals. Closer to home.')
        ->toContain('support@prodeals.lk')
        ->toContain('The ProDeals.lk team')
        ->not->toContain('laravel.com/img/notification-logo')
        ->not->toContain('#0f766e')
        ->not->toContain('#102a5c')
        ->and($text)
        ->toContain('ProDeals.lk - Better deals. Closer to home.')
        ->toContain('support@prodeals.lk')
        ->toContain('The ProDeals.lk team')
        ->toContain($mailMessage->actionUrl);
})->with([
    'password reset' => [
        fn (): QueuedResetPasswordNotification => new QueuedResetPasswordNotification('brand-test-token'),
        'Reset your ProDeals.lk password',
        'Reset password',
        '/reset-password/brand-test-token',
        'We received a request to reset the password for your ProDeals.lk account.',
    ],
    'email verification' => [
        fn (): QueuedVerifyEmailNotification => new QueuedVerifyEmailNotification,
        'Confirm your ProDeals.lk email address',
        'Confirm email address',
        '/email/verify/',
        'Confirm your email address to finish setting up your ProDeals.lk account.',
    ],
    'new return request' => [
        fn (): NewReturnRequestNotification => new NewReturnRequestNotification(42, 'Travel Backpack', 2, 'Amal Silva'),
        'New return request: Travel Backpack',
        'Review return request',
        '/seller/returns',
        'Amal Silva requested to return 2 × Travel Backpack.',
    ],
    'order acknowledgment' => [
        fn (): OrderAcknowledgmentNotification => new OrderAcknowledgmentNotification('PRO000234', '22500.00', 'cod', 2),
        'Order received: PRO000234',
        'View order confirmation',
        '/checkout/thank-you/PRO000234',
        "Thank you for your order. We've received 2 items under order PRO000234.",
    ],
    'payment confirmation' => [
        fn (): PaymentConfirmedNotification => new PaymentConfirmedNotification('PRO000234', '22500.00'),
        'Payment confirmed: PRO000234',
        'View order',
        '/checkout/thank-you/PRO000234',
        'We received your payment of LKR 22500.00. Your order is confirmed.',
    ],
    'seller order ready' => [
        fn (): SellerOrderReadyNotification => new SellerOrderReadyNotification('SO-260906-ABC12345', 'PRO000234', 2, '22000.00', 'stripe'),
        'New order ready: SO-260906-ABC12345',
        'Open seller orders',
        '/seller/orders',
        'Payment status: Card payment confirmed.',
    ],
    'buyer order shipped' => [
        fn (): BuyerOrderStatusNotification => new BuyerOrderStatusNotification('PRO000234', 'SO-260906-ABC12345', 'shipped'),
        'Order shipped: SO-260906-ABC12345',
        'View order',
        '/buyer/orders/PRO000234',
        'Seller order SO-260906-ABC12345 is on its way.',
    ],
    'return decision' => [
        fn (): ReturnDecisionNotification => new ReturnDecisionNotification(42, 'Travel Backpack', 'rejected', 'The item is outside the return window.'),
        'Return request rejected: Travel Backpack',
        'View return status',
        '/buyer/returns',
        'Reply to this email if you have questions about the decision.',
    ],
    'refund ready' => [
        fn (): RefundReadyNotification => new RefundReadyNotification(42, 'Travel Backpack', '5,000.00'),
        'Return ready for coordination: Travel Backpack',
        'Open returns queue',
        '/admin/returns',
        'The calculated refund is LKR 5,000.00. Please coordinate the offline return before marking it ready for refund.',
    ],
    'refund outcome' => [
        fn (): RefundOutcomeNotification => new RefundOutcomeNotification(42, 'Travel Backpack', '5,000.00', 'failed', 'Provider unavailable'),
        'Refund Failed: Travel Backpack',
        'View return status',
        '/buyer/returns',
        'Reply to this email if you need help with your refund.',
    ],
]);

test('the branded email inventory covers every application notification', function (): void {
    $notificationClasses = collect(glob(app_path('Notifications/*.php')))
        ->map(fn (string $path): string => 'App\\Notifications\\'.pathinfo($path, PATHINFO_FILENAME))
        ->sort()
        ->values()
        ->all();

    $coveredClasses = collect([
        BuyerOrderStatusNotification::class,
        NewReturnRequestNotification::class,
        OrderAcknowledgmentNotification::class,
        PaymentConfirmedNotification::class,
        QueuedResetPasswordNotification::class,
        QueuedVerifyEmailNotification::class,
        RefundOutcomeNotification::class,
        RefundReadyNotification::class,
        ReturnDecisionNotification::class,
        SellerOrderReadyNotification::class,
        SeoMonitoringNotification::class,
    ])->sort()->values()->all();

    expect($notificationClasses)->toBe($coveredClasses);
});

test('SEO monitoring notifications use the branded operational email layout', function () {
    foreach ([
        new SeoMonitoringNotification('merchant', ['Import failed.']),
        new SeoMonitoringNotification('merchant', [], recovered: true),
        new SeoMonitoringNotification('catalog', [], test: true),
    ] as $notification) {
        expect($notification)->toBeInstanceOf(ShouldQueue::class);
        $message = $notification->toMail(new stdClass);
        expect((string) $message->render())->toContain('prodeals-email-logo.png?v=', 'support@prodeals.lk', 'The ProDeals.lk team');
    }
});
