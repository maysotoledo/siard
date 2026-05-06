<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('telefone')
                    ->label('Telefone')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('data_ingresso')
                    ->label('Data de ingresso na carreira')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('roles_badge')
                    ->label('Tipo')
                    ->state(fn ($record) => $record->getRoleNames()
                        ->when(! auth()->user()?->hasRole('super_admin'), fn ($roles) => $roles->reject(fn (string $role): bool => $role === 'super_admin'))
                        ->implode(',')) // "admin,editor"
                    ->badge()
                    ->separator(','), // vira múltiplos badges
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->requiresConfirmation(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
