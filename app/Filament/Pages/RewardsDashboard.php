<?php

namespace App\Filament\Pages;

use App\Enums\CustomerTier;
use App\Models\Shop\Customer;
use App\Services\Shop\CustomerLoyaltyService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class RewardsDashboard extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.rewards-dashboard';

    protected static string $routePath = 'rewards';

    protected static ?string $title = 'Rewards Dashboard';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedGift;

    protected static UnitEnum | string | null $navigationGroup = 'Shop';

    protected static ?int $navigationSort = 5;

    public function getTitle(): string | Htmlable
    {
        return 'Rewards Dashboard';
    }

    public function table(Table $table): Table
    {
        $service = app(CustomerLoyaltyService::class);
        $vipIds = $service->getTop5GoldCustomerIds();

        return $table
            ->query(
                Customer::query()->withCount('orders')->withCount('discountCodes')
            )
            ->defaultSort('orders_count', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium),

                TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('orders_count')
                    ->label('Total Orders')
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('loyalty_tier')
                    ->label('Tier')
                    ->badge()
                    ->state(fn (Customer $record): string => CustomerTier::fromOrderCount($record->orders_count ?? 0)->getLabel())
                    ->color(fn (Customer $record): string => CustomerTier::fromOrderCount($record->orders_count ?? 0)->getColor())
                    ->icon(fn (Customer $record): Heroicon => CustomerTier::fromOrderCount($record->orders_count ?? 0)->getIcon()),

                TextColumn::make('discount_codes_count')
                    ->label('Codes Generated')
                    ->sortable()
                    ->badge()
                    ->color('gray'),
            ])
            ->filters([
                SelectFilter::make('loyalty_tier')
                    ->label('Tier')
                    ->options([
                        'new' => 'New (1 order)',
                        'silver' => 'Silver (2–5 orders)',
                        'gold' => 'Gold (6+ orders)',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'new' => $query->has('orders', '=', 1),
                            'silver' => $query->has('orders', '>=', 2)->has('orders', '<=', 5),
                            'gold' => $query->has('orders', '>=', 6),
                            default => $query,
                        };
                    }),
            ])
            ->recordActions([
                Action::make('reward_vip')
                    ->label('Reward VIP')
                    ->icon(Heroicon::Trophy)
                    ->color('warning')
                    ->modalWidth(Width::Medium)
                    ->modalSubmitActionLabel('Generate Code')
                    ->visible(fn (Customer $record): bool => $vipIds->contains($record->id))
                    ->schema([
                        Select::make('discount_percentage')
                            ->label('Discount percentage')
                            ->options([
                                10 => '10% — Standard VIP',
                                15 => '15% — Premium VIP',
                                20 => '20% — Elite VIP',
                            ])
                            ->required(),
                        Placeholder::make('note')
                            ->label('')
                            ->content('A unique coupon code (e.g. VIP20-XXXX) will be generated and tied to this customer.'),
                    ])
                    ->action(function (Customer $record, array $data) use ($service): void {
                        $code = $service->generateVipCode($record, $data['discount_percentage']);

                        Notification::make()
                            ->title("VIP code generated: {$code->code}")
                            ->body("{$data['discount_percentage']}% discount for {$record->name}")
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
