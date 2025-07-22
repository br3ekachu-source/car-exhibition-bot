<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;

class Participant extends Model
{
    use HasFactory;

// app/Models/Participant.php

    protected $fillable = [
        'telegram_id',
        'name',
        'telegram_username',
        'phone',
        'car_model',
        'license_plate',
        'participation_days',
        'current_step',
        'status', // Новое поле
        'moderator_comment' // Комментарий модератора
    ];

    protected $casts = [
        'participation_days' => 'string',
        'status' => 'string',
    ];

    // Статусы заявки
    public const STATUSES = [
        'pending' => 'На рассмотрении',
        'approved' => 'Одобрено',
        'rejected' => 'Отклонено'
    ];

    public function carPhotos()
    {
        return $this->hasMany(CarPhoto::class);
    }

    // app/Models/Participant.php

    public function notifyStatusUpdate()
    {
        $message = match($this->status) {
            'approved' => "🎉 Ваша заявка на участие в автовыставке одобрена!",
            'rejected' => "❌ Ваша заявка отклонена. Причина: " . $this->moderator_comment,
            default => ""
        };

        if (!empty($message)) {
            Http::post("https://api.telegram.org/bot".env('TELEGRAM_BOT_TOKEN')."/sendMessage", [
                'chat_id' => $this->telegram_id,
                'text' => $message
            ]);
        }
    }
}