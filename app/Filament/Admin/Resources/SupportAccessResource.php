<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Tenancy\Models\SupportAccessGrant;
use App\Filament\Admin\Resources\SupportAccessResource\Pages;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Izin akses dukungan yang berlaku dan riwayatnya.
 *
 * Halaman ini hanya menampilkan. Tidak ada tombol "minta akses" dan tidak ada
 * tombol "pakai izin ini untuk membuka data" — izin diterbitkan pengguna dari
 * halaman Keamanan miliknya, dan yang tercatat di sini adalah kenyataan, bukan
 * permintaan.
 */
class SupportAccessResource extends Resource
{
    protected static ?string $model = SupportAccessGrant::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static ?string $navigationLabel = 'Akses dukungan';

    protected static ?string $modelLabel = 'izin akses';

    protected static ?int $navigationSort = 60;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('workspace.name')->label('Workspace')->searchable(),
                TextColumn::make('pemberi.email')->label('Diberikan oleh')->searchable(),
                TextColumn::make('scope')->label('Lingkup')->badge(),
                TextColumn::make('reason')->label('Alasan')->placeholder('—')->wrap(),
                TextColumn::make('expires_at')->label('Berlaku sampai')->dateTime('j M Y H:i')->sortable(),
                TextColumn::make('used_at')->label('Dipakai')->dateTime('j M Y H:i')->placeholder('Belum'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (SupportAccessGrant $record): string => $record->statusLabel())
                    ->color(fn (SupportAccessGrant $record): string => $record->masihBerlaku() ? 'success' : 'gray'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListSupportAccess::route('/')];
    }
}
