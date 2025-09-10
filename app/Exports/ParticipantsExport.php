<?php

namespace App\Exports;

use App\Models\Participant;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ParticipantsExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        return Participant::all();
    }

    public function headings(): array
    {
        return [
            'ФИО',
            'Telegram',
            'Телефон',
            'Марка авто',
            'Модель авто',
            'Гос. номер',
            'Дни участия',
            'Статус',
            'Комментарий модератора',
            'Дата создания',
        ];
    }

    public function map($participant): array
    {
        return [
            $participant->name,
            $participant->telegram_username,
            $participant->phone,
            $participant->car_brand,
            $participant->car_model,
            $participant->license_plate,
            match ($participant->participation_days) {
                '20' => '20 сентября',
                '21' => '21 сентября',
                'both' => 'Оба дня',
                default => $participant->participation_days,
            },
            Participant::STATUSES[$participant->status] ?? $participant->status,
            $participant->moderator_comment,
            $participant->created_at->format('d.m.Y H:i'),
        ];
    }
}