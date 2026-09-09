<?php

use App\Contracts\Repositories\CheckoutRepository;
use App\Models\CustomerOrder;
use App\Models\OrderNumberSequence;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

function sequenceOrderData(): array
{
    return CustomerOrder::factory()->raw(['buyer_id' => User::factory()->create()->id]);
}

test('customer order numbers start at 1125 without changing historical numbers or ids', function () {
    $historical = CustomerOrder::factory()->create(['number' => 'PRO000008']);
    $historical->delete();
    $repository = app(CheckoutRepository::class);
    $first = $repository->createOrder(sequenceOrderData());
    $second = $repository->createOrder(sequenceOrderData());

    expect($first->number)->toBe('PRO001125')
        ->and($second->number)->toBe('PRO001126')
        ->and($first->id)->toBe($historical->id + 1)
        ->and($historical->refresh()->number)->toBe('PRO000008')
        ->and(OrderNumberSequence::findOrFail('customer')->last_number)->toBe(1126);
});

test('failed orders roll back sequence allocation', function () {
    $data = sequenceOrderData();
    expect(fn () => DB::transaction(function () use ($data): void {
        app(CheckoutRepository::class)->createOrder($data);
        throw new RuntimeException('Rollback order');
    }))->toThrow(RuntimeException::class, 'Rollback order');

    expect(CustomerOrder::count())->toBe(0)
        ->and(OrderNumberSequence::findOrFail('customer')->last_number)->toBe(1124)
        ->and(app(CheckoutRepository::class)->createOrder($data)->number)->toBe('PRO001125');
});

test('sequence initialization refuses conflicting historical numbers including deleted orders', function (bool $deleted) {
    $order = CustomerOrder::factory()->create(['number' => 'PRO001125']);
    if ($deleted) {
        $order->delete();
    }
    OrderNumberSequence::query()->delete();
    $migration = require database_path('migrations/2026_09_09_143027_initialize_customer_order_sequence.php');

    expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'Cannot initialize order numbering')
        ->and(OrderNumberSequence::count())->toBe(0);
})->with([true, false]);

test('concurrent connections allocate unique successive customer order numbers', function () {
    $database = tempnam(sys_get_temp_dir(), 'order-sequence-');
    config(['database.connections.sequence_concurrency' => [
        'driver' => 'sqlite', 'database' => $database, 'foreign_key_constraints' => true,
    ]]);
    $schema = Schema::connection('sequence_concurrency');
    $processes = [];

    try {
        $schema->create('order_number_sequences', function (Blueprint $table): void {
            $table->string('name')->primary();
            $table->unsignedBigInteger('last_number');
        });
        $schema->create('customer_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->timestamps();
            $table->softDeletes();
        });
        DB::connection('sequence_concurrency')->table('order_number_sequences')->insert(['name' => 'customer', 'last_number' => 1124]);
        $code = <<<'WORKER'
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $argv[1], 'database.connections.sqlite.busy_timeout' => 5000]);
        Illuminate\Support\Facades\DB::purge('sqlite');
        $repository = app(App\Contracts\Repositories\CheckoutRepository::class);
        for ($i = 0; $i < 4; $i++) {
            $repository->createOrder([]);
        }
        WORKER;
        for ($index = 0; $index < 2; $index++) {
            $process = new Process([PHP_BINARY, '-r', $code, $database], base_path(), ['APP_ENV' => 'testing']);
            $process->start();
            $processes[] = $process;
        }
        foreach ($processes as $process) {
            $process->wait();
            expect($process->isSuccessful())->toBeTrue($process->getErrorOutput().$process->getOutput());
        }
        expect(DB::connection('sequence_concurrency')->table('customer_orders')->orderBy('number')->pluck('number')->all())
            ->toBe(array_map(fn (int $number): string => 'PRO'.str_pad((string) $number, 6, '0', STR_PAD_LEFT), range(1125, 1132)));
    } finally {
        foreach ($processes as $process) {
            $process->stop();
        }
        DB::purge('sequence_concurrency');
        unlink($database);
    }
});
