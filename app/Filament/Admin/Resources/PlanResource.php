<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Billing\Models\Plan;
use App\Filament\Admin\Resources\PlanResource\Pages;
use App\Support\Money;
use BackedEnum;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Definisi plan dan batasnya.
 *
 * price_minor di sini adalah harga langganan Rafin, bukan uang di dalam buku
 * pengguna. Semua bernilai nol selama beta.
 */
class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Plan';

    protected static ?string $modelLabel = 'plan';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')
                ->label('Kode')
                ->required()
                ->maxLength(32)
                ->alphaDash()
                ->unique(ignoreRecord: true),

            TextInput::make('name')->label('Nama')->required()->maxLength(64),

            TextInput::make('price_minor')
                ->label('Harga (minor unit)')
                ->helperText('Dalam sen. Rp 50.000 ditulis 5000000. Semua plan bernilai 0 selama beta.')
                ->numeric()
                ->integer()
                ->default(0)
                ->required(),

            Select::make('interval')
                ->label('Interval')
                ->options(['monthly' => 'Bulanan', 'yearly' => 'Tahunan'])
                ->default('monthly')
                ->required(),

            Toggle::make('is_public')->label('Tampil di halaman langganan')->default(true),

            TextInput::make('sort_order')->label('Urutan')->numeric()->integer()->default(0),

            KeyValue::make('limits')
                ->label('Batas')
                ->keyLabel('Kunci')
                ->valueLabel('Nilai')
                ->helperText('-1 berarti tanpa batas. Kunci: workspaces, members, transactions_per_month, attachments_mb, retention_months, ocr, llm_parser.')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Kode')->searchable()->sortable(),
                TextColumn::make('name')->label('Nama')->searchable(),
                TextColumn::make('price_minor')
                    ->label('Harga')
                    ->formatStateUsing(fn (mixed $state): string => $state instanceof Money
                        ? $state->format()
                        : (string) $state),
                TextColumn::make('interval')->label('Interval'),
                IconColumn::make('is_public')->label('Publik')->boolean(),
                TextColumn::make('subscriptions_count')
                    ->label('Langganan')
                    ->counts('subscriptions')
                    ->sortable(),
            ])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlans::route('/'),
            'create' => Pages\CreatePlan::route('/create'),
            'edit' => Pages\EditPlan::route('/{record}/edit'),
        ];
    }
}
