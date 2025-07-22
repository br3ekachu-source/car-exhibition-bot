<?php

namespace App\Filament\Resources\Participants;

use App\Filament\Resources\Participants\Pages\CreateParticipant;
use App\Filament\Resources\Participants\Pages\EditParticipant;
use App\Filament\Resources\Participants\Pages\ListParticipants;
use App\Filament\Resources\Participants\Schemas\ParticipantForm;
use App\Filament\Resources\Participants\Tables\ParticipantsTable;
use App\Models\Participant;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class ParticipantResource extends Resource
{
    protected static ?string $model = Participant::class;
    
    protected static ?string $navigationLabel = 'Объявления';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ParticipantForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('ФИО')
                    ->sortable()
                    ->searchable(),
                    
                TextColumn::make('telegram_username')
                    ->label('Telegram')
                    ->searchable(),
                    
                TextColumn::make('phone')
                    ->label('Телефон')
                    ->searchable(),
                    
                TextColumn::make('car_brand')
                    ->label('Марка авто')
                    ->sortable()
                    ->searchable(),
                    
                TextColumn::make('car_model')
                    ->label('Модель авто')
                    ->sortable()
                    ->searchable(),
                    
                TextColumn::make('license_plate')
                    ->label('Гос. номер')
                    ->searchable(),
                    
                TextColumn::make('participation_days')
                    ->label('Дни участия')
                    ->formatStateUsing(function (string $state) {
                        return match ($state) {
                            '9' => '9 августа',
                            '10' => '10 августа',
                            'both' => 'Оба дня',
                            default => $state,
                        };
                    })
                    ->sortable(),
                    
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Participant::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'primary',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'pending' => 'heroicon-o-clock',
                        'approved' => 'heroicon-o-check-circle',
                        'rejected' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-question-mark-circle',
                    }),
            ImageColumn::make('preview_photo')
                ->label('Фото')
                ->size(60)
                ->getStateUsing(fn ($record) => $record->carPhotos->first()?->path)
                ->disk('public')
                ->action(
                    Action::make('viewGallery')
                        ->modalHeading('Галерея фото')
                        ->modalContent(function ($record) {
                            return view('filament.gallery-modal', [
                                'photos' => $record->carPhotos
                            ]);
                        })
                        ->modalWidth('7xl')
                )
                ])
                ->filters([
                    SelectFilter::make('status')
                        ->label('Статус')
                        ->options(Participant::STATUSES)
                        ->multiple() // позволяет выбирать несколько статусов
                        ->placeholder('Все статусы')
                ])
            ->actions([
                ViewAction::make(),
                DeleteAction::make(),
                Action::make('approve')
                ->label('Принять')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->action(function (Participant $record) {
                    $record->update([
                        'status' => 'approved',
                        'moderator_comment' => null
                    ]);
                    
                    Notification::make()
                        ->title('Заявка одобрена')
                        ->success()
                        ->send();
                        
                    $record->notifyStatusUpdate();
                })
                ->visible(fn (Participant $record) => $record->status !== 'approved'),
                
            // Кнопка "Отклонить" с формой для комментария
            Action::make('reject')
                ->label('Отклонить')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->form([
                    Textarea::make('comment')
                        ->label('Причина отклонения')
                        ->required()
                ])
                ->action(function (Participant $record, array $data) {
                    $record->update([
                        'status' => 'rejected',
                        'moderator_comment' => $data['comment']
                    ]);
                    
                    Notification::make()
                        ->title('Заявка отклонена')
                        ->danger()
                        ->send();
                        
                    $record->notifyStatusUpdate();
                })
                ->visible(fn (Participant $record) => $record->status !== 'rejected'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListParticipants::route('/'),
            'create' => CreateParticipant::route('/create'),
            'edit' => EditParticipant::route('/{record}/edit'),
        ];
    }
}
