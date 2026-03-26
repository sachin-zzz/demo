<?php

use App\Enums\CustomerTier;
use App\Models\Shop\Customer;
use App\Models\Shop\DiscountCode;
use App\Models\Shop\Order;
use App\Services\Shop\CustomerLoyaltyService;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    $this->service = app(CustomerLoyaltyService::class);
});

// --- Tier logic ---

it('assigns New tier to a customer with 1 order', function () {
    expect($this->service->getTierForOrderCount(1))->toBe(CustomerTier::New);
});

it('assigns Silver tier to customers with 2 to 5 orders', function (int $count) {
    expect($this->service->getTierForOrderCount($count))->toBe(CustomerTier::Silver);
})->with([2, 3, 4, 5]);

it('assigns Gold tier to customers with 6 or more orders', function (int $count) {
    expect($this->service->getTierForOrderCount($count))->toBe(CustomerTier::Gold);
})->with([6, 10, 25]);

it('assigns New tier to a customer with 0 orders', function () {
    expect($this->service->getTierForOrderCount(0))->toBe(CustomerTier::New);
});

it('getTierForCustomer uses orders_count relation', function () {
    $customer = Customer::factory()->create();
    Order::factory()->count(6)->for($customer, 'customer')->create();
    $customer->loadCount('orders');

    expect($this->service->getTierForCustomer($customer))->toBe(CustomerTier::Gold);
});

// --- Top 5 VIP ---

it('identifies top 5 Gold customers correctly', function () {
    $goldCustomers = Customer::factory()->count(5)->create();
    foreach ($goldCustomers as $c) {
        Order::factory()->count(8)->for($c, 'customer')->create();
    }

    $silver = Customer::factory()->create();
    Order::factory()->count(3)->for($silver, 'customer')->create();

    Cache::forget('top_5_gold_customer_ids');
    $ids = $this->service->getTop5GoldCustomerIds();

    expect($ids)->toHaveCount(5);
    expect($ids->contains($silver->id))->toBeFalse();
});

it('excludes customers with fewer than 6 orders from VIP', function () {
    $customer = Customer::factory()->create();
    Order::factory()->count(5)->for($customer, 'customer')->create();

    Cache::forget('top_5_gold_customer_ids');

    expect($this->service->isVipCustomer($customer))->toBeFalse();
});

it('caps VIP list at 5 even when more Gold customers exist', function () {
    $customers = Customer::factory()->count(8)->create();
    foreach ($customers as $c) {
        Order::factory()->count(7)->for($c, 'customer')->create();
    }

    Cache::forget('top_5_gold_customer_ids');
    $ids = $this->service->getTop5GoldCustomerIds();

    expect($ids)->toHaveCount(5);
});

// --- Welcome discount ---

it('applies 10% discount to order total_price', function () {
    $customer = Customer::factory()->create();
    $order = Order::factory()->for($customer, 'customer')->create(['total_price' => 200.00]);

    $this->service->applyWelcomeDiscount($order);

    $order->refresh();
    expect((float) $order->total_price)->toBe(180.00);
    expect($order->discount_applied)->toBeTrue();
});

it('previews discounted price without saving', function () {
    $customer = Customer::factory()->create();
    $order = Order::factory()->for($customer, 'customer')->create(['total_price' => 100.00]);

    $preview = $this->service->previewWelcomeDiscount($order);

    expect($preview)->toBe(90.00);
    $order->refresh();
    expect((float) $order->total_price)->toBe(100.00);
    expect($order->discount_applied)->toBeFalse();
});

// --- VIP code generation ---

it('generates a VIP coupon code in the correct format', function () {
    $customer = Customer::factory()->create();

    $code = $this->service->generateVipCode($customer, 20);

    expect($code)->toBeInstanceOf(DiscountCode::class);
    expect($code->discount_percentage)->toBe(20);
    expect($code->customer_id)->toBe($customer->id);
    expect($code->code)->toMatch('/^VIP20-[A-Z0-9]{4}$/');
});

it('generates unique codes for each call', function () {
    $customer = Customer::factory()->create();

    $code1 = $this->service->generateVipCode($customer, 10);
    $code2 = $this->service->generateVipCode($customer, 10);

    expect($code1->code)->not->toBe($code2->code);
});

it('stores the discount code tied to the customer', function () {
    $customer = Customer::factory()->create();

    $this->service->generateVipCode($customer, 15);

    expect($customer->discountCodes()->count())->toBe(1);
    expect($customer->discountCodes()->first()->discount_percentage)->toBe(15);
});
