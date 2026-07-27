<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UserResource\Pages;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Password;

/**
 * Pengguna platform.
 *
 * Yang bisa dilakukan di sini sengaja sempit: memperbaiki data identitas,
 * menyetel ulang verifikasi dua langkah untuk orang yang kehilangan ponselnya,
 * dan mengirim tautan pemulihan. Tidak ada impersonate, dan itu bukan fitur
 * yang belum sempat dibuat — ia memang tidak akan dibuat.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Pengguna';

    protected static ?string $modelLabel = 'pengguna';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama')->required()->maxLength(255),
            TextInput::make('email')->label('Surel')->email()->required()->unique(ignoreRecord: true),
            Toggle::make('is_platform_admin')
                ->label('Admin platform')
                ->helperText('Memberi akses ke panel ini. Tetap tidak memberi akses ke data transaksi siapa pun.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('email')->label('Surel')->searchable(),
                IconColumn::make('email_verified_at')->label('Terverifikasi')->boolean(),
                IconColumn::make('two_factor_confirmed_at')->label('2FA')->boolean(),
                IconColumn::make('is_platform_admin')->label('Admin')->boolean(),
                TextColumn::make('created_at')->label('Bergabung')->dateTime('j M Y')->sortable(),
            ])
            ->recordActions([
                Action::make('setelUlangDuaLangkah')
                    ->label('Setel ulang 2FA')
                    ->requiresConfirmation()
                    ->modalDescription('Dipakai kalau pengguna kehilangan ponselnya. Ia harus memasang ulang dari awal.')
                    ->visible(fn (User $record): bool => $record->duaLangkahAktif())
                    ->action(function (User $record): void {
                        $record->forceFill([
                            'two_factor_secret' => null,
                            'two_factor_recovery_codes' => null,
                            'two_factor_confirmed_at' => null,
                        ])->save();

                        Notification::make()->title('Verifikasi dua langkah disetel ulang.')->success()->send();
                    }),

                Action::make('kirimPemulihan')
                    ->label('Kirim tautan pemulihan')
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        Password::sendResetLink(['email' => $record->email]);

                        Notification::make()->title('Tautan pemulihan dikirim.')->success()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
