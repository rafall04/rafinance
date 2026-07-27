<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Tenancy\Models\Workspace;
use App\Filament\Admin\Resources\WorkspaceResource\Pages;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Metadata workspace. TANPA akses ke transaksi (aturan A5).
 *
 * Yang ditampilkan di sini sudah dipilih satu per satu: nama, pemilik, jumlah
 * anggota, plan, dan kapan terakhir dipakai. Tidak ada satu pun kolom yang
 * bersumber dari transactions, entries, atau accounts — dan arch test memindai
 * seluruh direktori ini untuk memastikan tidak ada yang menambahkannya.
 *
 * Jumlah transaksi ditampilkan sebagai HITUNGAN saja. Berapa banyak orang
 * mencatat adalah metrik produk; berapa nominalnya bukan urusan siapa pun di
 * sini.
 */
class WorkspaceResource extends Resource
{
    protected static ?string $model = Workspace::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $navigationLabel = 'Workspace';

    protected static ?string $modelLabel = 'workspace';

    protected static ?int $navigationSort = 20;

    /**
     * Panel admin tidak punya konteks tenant, jadi global scope workspace akan
     * menyaring semuanya jadi kosong. Di sini scope-nya memang dilepas — dan
     * boleh, karena tabel ini hanya memuat metadata.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes()->withCount('members');
    }

    public static function form(Schema $schema): Schema
    {
        // Tidak ada formulir. Admin platform tidak mengubah workspace orang.
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('type')
                    ->label('Jenis')
                    ->formatStateUsing(fn (mixed $state): string => $state?->label() ?? '—'),
                TextColumn::make('owner.email')->label('Pemilik')->searchable(),
                TextColumn::make('members_count')->label('Anggota')->sortable(),
                TextColumn::make('currency')->label('Mata uang'),
                TextColumn::make('created_at')->label('Dibuat')->dateTime('j M Y')->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListWorkspaces::route('/')];
    }
}
