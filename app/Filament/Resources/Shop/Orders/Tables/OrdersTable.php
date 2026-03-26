<?php

namespace App\Filament\Resources\Shop\Orders\Tables;

use App\Enums\CustomerTier;
use App\Enums\OrderStatus;
use App\Models\Shop\Order;
use App\Services\Shop\CustomerLoyaltyService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'customer' => fn ($q) => $q->withCount('orders'),
            ]))
            ->columns([
                TextColumn::make('number')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium),

                TextColumn::make('customer.name')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('customer_tier')
                    ->label('Loyalty Tier')
                    ->badge()
                    ->state(function (Order $record): ?string {
                        if ($record->customer === null) {
                            return null;
                        }

                        return CustomerTier::fromOrderCount($record->customer->orders_count ?? 0)->getLabel();
                    })
                    ->color(function (Order $record): string {
                        if ($record->customer === null) {
                            return 'gray';
                        }

                        return CustomerTier::fromOrderCount($record->customer->orders_count ?? 0)->getColor();
                    })
                    ->icon(function (Order $record): ?Heroicon {
                        if ($record->customer === null) {
                            return null;
                        }

                        return CustomerTier::fromOrderCount($record->customer->orders_count ?? 0)->getIcon();
                    }),

                TextColumn::make('status')
                    ->badge(),

                TextColumn::make('currency')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('total_price')
                    ->searchable()
                    ->sortable()
                    ->summarize([
                        Sum::make()->money(),
                    ]),

                TextColumn::make('shipping_price')
                    ->label('Shipping cost')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->summarize([
                        Sum::make()->money(),
                    ]),

                TextColumn::make('created_at')
                    ->label('Order date')
                    ->date()
                    ->toggleable(),
            ])
            ->filters([
                TrashedFilter::make(),

                Filter::make('created_at')
                    ->label('Order date')
                    ->schema([
                        DatePicker::make('created_from')
                            ->placeholder(fn ($state): string => 'Dec 18, ' . now()->subYear()->format('Y')),
                        DatePicker::make('created_until')
                            ->placeholder(fn ($state): string => now()->format('M d, Y')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['created_from'] ?? null) {
                            $indicators['created_from'] = 'Order from ' . Carbon::parse($data['created_from'])->toFormattedDateString();
                        }
                        if ($data['created_until'] ?? null) {
                            $indicators['created_until'] = 'Order until ' . Carbon::parse($data['created_until'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
            ])
            ->recordActions([
                Action::make('apply_welcome_discount')
                    ->label('Apply 10% Welcome')
                    ->icon(Heroicon::Gift)
                    ->button()
                    ->color('success')
                    ->modalWidth(Width::Medium)
                    ->modalSubmitActionLabel('Apply Discount')
                    ->visible(function (Order $record): bool {
                        if ($record->customer === null || $record->discount_applied) {
                            return false;
                        }

                        return CustomerTier::fromOrderCount($record->customer->orders_count ?? 0) === CustomerTier::New;
                    })
                    ->fillForm(fn (Order $record): array => [
                        'current_price' => number_format((float) $record->total_price, 2),
                        'discounted_price' => number_format(
                            app(CustomerLoyaltyService::class)->previewWelcomeDiscount($record),
                            2
                        ),
                    ])
                    ->schema([
                        TextInput::make('current_price')
                            ->label('Current price')
                            ->prefix('$')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('discounted_price')
                            ->label('Price after 10% discount')
                            ->prefix('$')
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->action(function (Order $record): void {
                        app(CustomerLoyaltyService::class)->applyWelcomeDiscount($record);

                        Notification::make()
                            ->title('10% welcome discount applied')
                            ->success()
                            ->send();
                    }),

                Action::make('reward_new')
                    ->label('Give Welcome Code')
                    ->icon(Heroicon::Sparkles)
                    ->button()
                    ->color('info')
                    ->modalWidth(Width::Medium)
                    ->modalSubmitActionLabel('Generate Code')
                    ->visible(function (Order $record): bool {
                        if ($record->customer === null) {
                            return false;
                        }

                        return CustomerTier::fromOrderCount($record->customer->orders_count ?? 0) === CustomerTier::New;
                    })
                    ->schema([
                        Placeholder::make('note')
                            ->label('')
                            ->content('A unique 10% welcome coupon code (e.g. WELCOME-XXXX) will be generated and tied to this customer.'),
                    ])
                    ->action(function (Order $record): void {
                        $code = app(CustomerLoyaltyService::class)
                            ->generateWelcomeCode($record->customer);

                        Notification::make()
                            ->title("Welcome code generated: {$code->code}")
                            ->body("10% discount for {$record->customer->name}")
                            ->success()
                            ->send();
                    }),

                Action::make('reward_vip')
                    ->label('Reward VIP')
                    ->icon(Heroicon::Trophy)
                    ->button()
                    ->color('warning')
                    ->modalWidth(Width::Medium)
                    ->modalSubmitActionLabel('Generate Code')
                    ->visible(function (Order $record): bool {
                        if ($record->customer === null) {
                            return false;
                        }

                        return app(CustomerLoyaltyService::class)->isVipCustomer($record->customer);
                    })
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
                    ->action(function (Order $record, array $data): void {
                        $code = app(CustomerLoyaltyService::class)
                            ->generateVipCode($record->customer, $data['discount_percentage']);

                        Notification::make()
                            ->title("VIP code generated: {$code->code}")
                            ->body("{$data['discount_percentage']}% discount for {$record->customer->name}")
                            ->success()
                            ->send();
                    }),

                ActionGroup::make([

                    Action::make('process')
                        ->icon(Heroicon::ArrowPath)
                        ->color('warning')
                        ->visible(fn (Order $record): bool => $record->status === OrderStatus::New)
                        ->action(function (Order $record): void {
                            $record->update(['status' => OrderStatus::Processing]);

                            Notification::make()
                                ->title('Order is now processing')
                                ->success()
                                ->send();
                        }),

                    Action::make('ship')
                        ->icon(Heroicon::Truck)
                        ->color('success')
                        ->visible(fn (Order $record): bool => $record->status === OrderStatus::Processing)
                        ->slideOver()
                        ->modalSubmitActionLabel('Ship')
                        ->schema([
                            Textarea::make('notes')
                                ->label('Shipping notes')
                                ->rows(3),
                        ])
                        ->extraModalFooterActions([
                            Action::make('ship_and_notify')
                                ->label('Ship & notify customer')
                                ->color('info')
                                ->action(function (Order $record, array $data): void {
                                    $record->update([
                                        'status' => OrderStatus::Shipped,
                                        'notes' => $data['notes'] ?? null,
                                    ]);

                                    Notification::make()
                                        ->title('Order shipped & customer notified')
                                        ->success()
                                        ->send();
                                }),
                        ])
                        ->action(function (Order $record, array $data): void {
                            $record->update([
                                'status' => OrderStatus::Shipped,
                                'notes' => $data['notes'] ?? null,
                            ]);

                            Notification::make()
                                ->title('Order shipped')
                                ->success()
                                ->send();
                        }),

                    Action::make('deliver')
                        ->icon(Heroicon::CheckBadge)
                        ->color('success')
                        ->visible(fn (Order $record): bool => $record->status === OrderStatus::Shipped)
                        ->requiresConfirmation()
                        ->action(function (Order $record): void {
                            $record->update(['status' => OrderStatus::Delivered]);

                            Notification::make()
                                ->title('Order marked as delivered')
                                ->success()
                                ->send();
                        }),

                    EditAction::make(),

                    Action::make('cancel')
                        ->icon(Heroicon::XCircle)
                        ->color('danger')
                        ->visible(fn (Order $record): bool => ! in_array($record->status, [OrderStatus::Delivered, OrderStatus::Cancelled]))
                        ->disabled(fn (Order $record): bool => $record->status === OrderStatus::Shipped)
                        ->requiresConfirmation()
                        ->action(function (Order $record): void {
                            $record->update(['status' => OrderStatus::Cancelled]);

                            Notification::make()
                                ->title('Order cancelled')
                                ->danger()
                                ->send();
                        }),

                    DeleteAction::make()
                        ->action(function (): void {
                            Notification::make()
                                ->title('Now, now, don\'t be cheeky, leave some records for others to play with!')
                                ->warning()
                                ->send();
                        }),
                ]),
            ])
            ->groupedBulkActions([
                DeleteBulkAction::make()
                    ->action(function (): void {
                        Notification::make()
                            ->title('Now, now, don\'t be cheeky, leave some records for others to play with!')
                            ->warning()
                            ->send();
                    }),
            ])
            ->groups([
                Group::make('created_at')
                    ->label('Order date')
                    ->date()
                    ->collapsible(),
            ]);
    }
}
