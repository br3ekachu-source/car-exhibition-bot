<?php

namespace App\Models;

use App\Console\Commands\TelegramPollingCommand;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
            'approved' => "🎉 Поздравляем, Ваша заявка на участие в IDDC, которое пройдет 20-21 сентября на Игора Драйв ОДОБРЕНА! Ссылка на вступление в группу",
            'rejected' => "❌ Благодарим за удельное время за подачу заявки, к сожалению, Ваша заявка отклонена. Будем рады видеть Вас в качестве зрителя",
            default => ""
        };

        if ($this->moderator_comment !== null && $this->moderator_comment !== '') {
            $message .= "\n\nПричина: " . $this->moderator_comment;
        }

        $filename = match($this->status) {
            'approved' => 'approved.png',
            'rejected' => 'denied.png',
            default => ''
        };

        if (!empty($message)) {
            $this->sendImageMessage($this->telegram_id, $message, $filename);
        }
    }

    private function sendImageMessage($chatId, $text, $imageName, $replyMarkup = null)
    {
        $imagePath = storage_path('app/public/' . $imageName);
        
        if (!file_exists($imagePath)) {
            return $this->sendMessage($chatId, "Ошибка: изображение не найдено");
        }

        try {
            $data = [
                'chat_id' => $chatId,
                'caption' => $text,
                'parse_mode' => 'HTML'
            ];

            if ($replyMarkup) {
                $data['reply_markup'] = $replyMarkup;
            }

            $response = Http::attach(
                'photo',
                file_get_contents($imagePath),
                $imageName
            )->post("https://api.telegram.org/bot".env('TELEGRAM_BOT_TOKEN')."/sendPhoto", $data);
            
            return $response;
        } catch (\Exception $e) {
            Log::error('Failed to send photo', ['error' => $e->getMessage()]);
            return $this->sendMessage($chatId, "Не удалось отправить изображение");
        }
    }
}