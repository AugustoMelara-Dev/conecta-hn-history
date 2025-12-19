<?php

namespace App\Filament\Resources\Tenants\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;

class TenantForm
{
    public static function configure(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                Select::make('city_id')
                    ->relationship('city', 'name'),
                Select::make('category_id')
                    ->relationship('category', 'name'),
                TextInput::make('name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                Textarea::make('about')
                    ->columnSpanFull(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                TextInput::make('phone')
                    ->tel(),
                TextInput::make('seo_title'),
                TextInput::make('seo_description'),
                TextInput::make('seo_keywords'),
                FileUpload::make('og_image')
                    ->image(),
                Textarea::make('address')
                    ->columnSpanFull(),
                TextInput::make('latitude')
                    ->numeric(),
                TextInput::make('longitude')
                    ->numeric(),
                TextInput::make('logo_path'),
                TextInput::make('cover_path'),
                TextInput::make('banner_path'),
                FileUpload::make('cover_image_path')
                    ->image(),
                TextInput::make('blur_hash'),
                TextInput::make('primary_color')
                    ->required()
                    ->default('#000000'),
                TextInput::make('theme_config'),
                TextInput::make('whatsapp_number'),
                Textarea::make('whatsapp_message')
                    ->columnSpanFull(),
                Textarea::make('whatsapp_template')
                    ->columnSpanFull(),
                TextInput::make('social_links'),
                Toggle::make('is_pro')
                    ->required(),
                Toggle::make('is_active')
                    ->required(),
                TextInput::make('status')
                    ->required()
                    ->default('pending'),
                DateTimePicker::make('plan_expires_at'),
                TextInput::make('product_limit')
                    ->required()
                    ->numeric()
                    ->default(10),
                TextInput::make('hours_data'),
                TextInput::make('simple_schedule'),
                TextInput::make('facebook_url')
                    ->url(),
                TextInput::make('instagram_url')
                    ->url(),
                TextInput::make('tiktok_url')
                    ->url(),
                TextInput::make('website_url')
                    ->url(),
                DatePicker::make('next_billing_date'),
                TextInput::make('subscription_status')
                    ->required()
                    ->default('active'),
            ]);
    }
}
