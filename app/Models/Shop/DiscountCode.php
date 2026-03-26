<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscountCode extends Model
{
    /**
     * @var string
     */
    protected $table = 'discount_codes';

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'discount_percentage' => 'integer',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
