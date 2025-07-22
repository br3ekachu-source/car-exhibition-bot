<?php

namespace App\Filament\Resources\Participants\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ParticipantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('telegram_id')
                    ->tel()
                    ->required()
                    ->numeric(),
                TextInput::make('name'),
                TextInput::make('telegram_username')
                    ->tel(),
                TextInput::make('phone')
                    ->tel(),
                TextInput::make('car_model'),
                TextInput::make('license_plate'),
                TextInput::make('participation_days'),
                TextInput::make('current_step'),
                TextInput::make('status')
                    ->required()
                    ->default('pending'),
                Textarea::make('moderator_comment')
                    ->columnSpanFull(),
            ]);
    }
}
