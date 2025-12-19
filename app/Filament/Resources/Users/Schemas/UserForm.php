<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;

class UserForm
{
    public static function configure(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                DateTimePicker::make('email_verified_at'),
                TextInput::make('password')
                    ->password()
                    ->required(),
                TextInput::make('google_id'),
                TextInput::make('facebook_id'),
                TextInput::make('avatar'),
                TextInput::make('role')
                    ->required()
                    ->default('user'),
                TextInput::make('phone')
                    ->tel(),
                DateTimePicker::make('banned_at'),
            ]);
    }
}
