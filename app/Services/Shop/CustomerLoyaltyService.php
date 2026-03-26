<?php

namespace App\Services\Shop;

use App\Enums\CustomerTier;
use App\Models\Shop\Customer;
use App\Models\Shop\DiscountCode;
use App\Models\Shop\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CustomerLoyaltyService
{
    public function getTierForOrderCount(int $count): CustomerTier
    {
        return CustomerTier::fromOrderCount($count);
    }

    public function getTierForCustomer(Customer $customer): CustomerTier
    {
        $count = $customer->orders_count ?? $customer->orders()->count();

        return CustomerTier::fromOrderCount($count);
    }

    /**
     * @return Collection<int, int>
     */
    public function getTop5GoldCustomerIds(): Collection
    {
        return Cache::remember('top_5_gold_customer_ids', now()->addMinutes(10), function (): Collection {
            return Customer::query()
                ->join('orders', 'customers.id', '=', 'orders.customer_id')
                ->whereNull('orders.deleted_at')
                ->whereNull('customers.deleted_at')
                ->selectRaw('customers.id, count(orders.id) as orders_count')
                ->groupBy('customers.id')
                ->havingRaw('count(orders.id) >= 6')
                ->orderByDesc('orders_count')
                ->limit(5)
                ->pluck('orders_count', 'customers.id')
                ->keys();
        });
    }

    public function isVipCustomer(Customer $customer): bool
    {
        return $this->getTop5GoldCustomerIds()->contains($customer->id);
    }

    public function applyWelcomeDiscount(Order $order): void
    {
        $discounted = round((float) $order->total_price * 0.9, 2);

        $order->update([
            'total_price' => $discounted,
            'discount_applied' => true,
        ]);
    }

    public function previewWelcomeDiscount(Order $order): float
    {
        return round((float) $order->total_price * 0.9, 2);
    }

    public function generateVipCode(Customer $customer, int $percentage): DiscountCode
    {
        $code = $this->buildVipCodeString($percentage);

        return $customer->discountCodes()->create([
            'code' => $code,
            'discount_percentage' => $percentage,
        ]);
    }

    public function generateWelcomeCode(Customer $customer): DiscountCode
    {
        $code = $this->buildWelcomeCodeString();

        return $customer->discountCodes()->create([
            'code' => $code,
            'discount_percentage' => 10,
        ]);
    }

    private function buildVipCodeString(int $percentage): string
    {
        do {
            $suffix = strtoupper(Str::random(4));
            $code = "VIP{$percentage}-{$suffix}";
        } while (DiscountCode::where('code', $code)->exists());

        return $code;
    }

    private function buildWelcomeCodeString(): string
    {
        do {
            $suffix = strtoupper(Str::random(4));
            $code = "WELCOME-{$suffix}";
        } while (DiscountCode::where('code', $code)->exists());

        return $code;
    }
}
