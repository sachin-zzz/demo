<?php

use App\Filament\Pages\RewardsDashboard;
use App\Models\Shop\Customer;
use App\Models\Shop\Order;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

it('can render the rewards dashboard page', function () {
    $customers = Customer::factory()->count(3)->create();

    Livewire::test(RewardsDashboard::class)
        ->assertOk()
        ->assertCanSeeTableRecords($customers);
});

it('shows customers sorted by order count descending by default', function () {
    $lowCustomer = Customer::factory()->create();
    Order::factory()->count(2)->for($lowCustomer, 'customer')->create();

    $highCustomer = Customer::factory()->create();
    Order::factory()->count(10)->for($highCustomer, 'customer')->create();

    $records = Livewire::test(RewardsDashboard::class)
        ->instance()
        ->getTableRecords();

    expect($records->first()->id)->toBe($highCustomer->id);
});

it('can filter customers by Gold tier', function () {
    $goldCustomer = Customer::factory()->create();
    Order::factory()->count(8)->for($goldCustomer, 'customer')->create();

    $silverCustomer = Customer::factory()->create();
    Order::factory()->count(3)->for($silverCustomer, 'customer')->create();

    Livewire::test(RewardsDashboard::class)
        ->filterTable('loyalty_tier', 'gold')
        ->assertCanSeeTableRecords([$goldCustomer])
        ->assertCanNotSeeTableRecords([$silverCustomer]);
});

it('can filter customers by Silver tier', function () {
    $silverCustomer = Customer::factory()->create();
    Order::factory()->count(4)->for($silverCustomer, 'customer')->create();

    $newCustomer = Customer::factory()->create();
    Order::factory()->count(1)->for($newCustomer, 'customer')->create();

    Livewire::test(RewardsDashboard::class)
        ->filterTable('loyalty_tier', 'silver')
        ->assertCanSeeTableRecords([$silverCustomer])
        ->assertCanNotSeeTableRecords([$newCustomer]);
});

it('can filter customers by New tier', function () {
    $newCustomer = Customer::factory()->create();
    Order::factory()->count(1)->for($newCustomer, 'customer')->create();

    $goldCustomer = Customer::factory()->create();
    Order::factory()->count(9)->for($goldCustomer, 'customer')->create();

    Livewire::test(RewardsDashboard::class)
        ->filterTable('loyalty_tier', 'new')
        ->assertCanSeeTableRecords([$newCustomer])
        ->assertCanNotSeeTableRecords([$goldCustomer]);
});

it('reward_vip action generates a code and notifies', function () {
    $customers = Customer::factory()->count(5)->create();
    foreach ($customers as $c) {
        Order::factory()->count(7)->for($c, 'customer')->create();
    }

    Cache::forget('top_5_gold_customer_ids');
    $vipCustomer = $customers->first();

    Livewire::test(RewardsDashboard::class)
        ->callAction(TestAction::make('reward_vip')->table($vipCustomer), [
            'discount_percentage' => 20,
        ])
        ->assertNotified();

    expect($vipCustomer->discountCodes()->count())->toBe(1);
    expect($vipCustomer->discountCodes()->first()->discount_percentage)->toBe(20);
});

it('reward_vip action is hidden for non-VIP customers', function () {
    $silverCustomer = Customer::factory()->create();
    Order::factory()->count(3)->for($silverCustomer, 'customer')->create();

    Cache::forget('top_5_gold_customer_ids');

    Livewire::test(RewardsDashboard::class)
        ->assertActionHidden(TestAction::make('reward_vip')->table($silverCustomer));
});
