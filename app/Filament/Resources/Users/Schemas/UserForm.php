<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->validationMessages([
                        'unique' => 'Este e-mail já foi cadastrado para outro usuário.',
                    ]),
                DatePicker::make('data_ingresso')
                    ->label('Data de ingresso na carreira')
                    ->native(false)
                    ->closeOnDateSelection(),
                DatePicker::make('data_ingresso_servico_publico')
                    ->label('Ingresso no serviço público')
                    ->native(false)
                    ->closeOnDateSelection(),
                DatePicker::make('data_ingresso_unidade')
                    ->label('Ingresso na unidade')
                    ->native(false)
                    ->closeOnDateSelection(),
               // DateTimePicker::make('email_verified_at'),
                //TextInput::make('password')
                //    ->password()
                //    ->required(),
                TextInput::make('password')
                    ->label('Senha')
                    ->password()
                    ->revealable()
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? $state : null)
                    ->dehydrated(fn ($state) => filled($state)) // só manda pro save se tiver valor
                    ->required(fn (string $operation) => $operation === 'create')
                    ->minLength(8)
                    ->autocomplete('new-password')
                    ->helperText('Deixe em branco para manter a senha atual.'),
                Select::make('roles')
                    ->relationship(
                        'roles',
                        'name',
                        modifyQueryUsing: fn (Builder $query) => auth()->user()?->hasRole('super_admin')
                            ? $query
                            : $query->where('name', '!=', 'super_admin'),
                    )
                    ->multiple()
                    ->preload()
                    ->searchable(),
            ]);
    }
}
