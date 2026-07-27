<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Billing\Models\Subscription;
use App\Filament\Admin\Resources\SubscriptionResource\Pages;
use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $navigationLabel = 'Langganan';

    protected static ?string $modelLabel = 'langganan';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('plan_id')
                ->label('Plan')
                ->relationship('plan', 'name')
                ->required(),

            Select::make('status')
                ->label('Status')
                ->options([
                    'trialing' => 'Masa coba',
                    'active' => 'Aktif',
                    'past_due' => 'Tertunggak',
                    'grace' => 'Masa tenggang',
                    'canceled' => 'Dibatalkan',
                ])
                ->required(),

            DateTimePicker::make('current_period_end')
                ->label('Berakhir')
                ->helperText('Perpanjangan masa tenggang dilakukan di sini. Buku pengguna tidak pernah dikunci karena status pembayaran.')
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('workspace.name')->label('Workspace')->searchable(),
                TextColumn::make('plan.name')->label('Plan')->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (Subscription $record): string => $record->statusLabel())
                    ->color(fn (Subscription $record): string => $record->aktif() ? 'success' : 'warning'),
                TextColumn::make('current_period_end')->label('Berakhir')->dateTime('j M Y')->sortable(),
            ])
            ->defaultSort('current_period_end');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptions::route('/'),
            'edit' => Pages\EditSubscription::route('/{record}/edit'),
        ];
    }
}
