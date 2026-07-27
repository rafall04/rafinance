<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Billing\Models\Payment;
use App\Filament\Admin\Resources\PaymentResource\Pages;
use App\Support\Money;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Riwayat pembayaran langganan platform.
 *
 * Nominal di sini adalah uang yang dibayarkan KEPADA Rafin. Ia tidak ada
 * hubungannya dengan isi buku kas pengguna, dan tidak boleh dijadikan pintu
 * ke sana.
 */
class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Pembayaran';

    protected static ?string $modelLabel = 'pembayaran';

    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subscription.workspace.name')->label('Workspace')->searchable(),
                TextColumn::make('amount_minor')
                    ->label('Jumlah')
                    ->formatStateUsing(fn (mixed $state): string => $state instanceof Money
                        ? $state->format()
                        : (string) $state),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('provider')->label('Penyedia')->placeholder('—'),
                TextColumn::make('paid_at')->label('Dibayar')->dateTime('j M Y H:i')->placeholder('Belum'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPayments::route('/')];
    }
}
