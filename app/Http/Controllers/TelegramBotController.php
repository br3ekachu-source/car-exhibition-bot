<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Participant;
use Illuminate\Support\Facades\Log;

class TelegramBotController extends Controller
{
    private function sendTelegramMessage($chatId, $text, $replyMarkup = null)
    {
        $url = "https://api.telegram.org/bot" . env('TELEGRAM_BOT_TOKEN') . "/sendMessage";
        
        $data = [
            'chat_id' => $chatId,
            'text' => $text,
        ];
        
        if ($replyMarkup) {
            $data['reply_markup'] = $replyMarkup;
        }
        
        return Http::post($url, $data);
    }

    public function handleWebhook(Request $request)
    {
        $update = $request->all();
        Log::info('Telegram update:', $update);
        
        $chatId = $update['message']['chat']['id'];
        $message = $update['message'];
        $text = $message['text'] ?? '';
        $username = $message['from']['username'] ?? '';
        
        // Поиск или создание участника
        $participant = Participant::where('telegram_username', $username)->first();
        
        if (!$participant) {
            $participant = new Participant();
            $participant->telegram_username = $username;
            $participant->current_step = 'name';
            $participant->save();
            
            $this->sendTelegramMessage($chatId, 'Добро пожаловать на регистрацию автовыставки! Введите ваше имя:');
            return response()->json(['status' => 'success']);
        }
        
        // Обработка шагов регистрации
        switch ($participant->current_step) {
            case 'name':
                $participant->name = $text;
                $participant->current_step = 'phone';
                $participant->save();
                $this->sendTelegramMessage($chatId, 'Введите ваш номер телефона:');
                break;
                
            case 'phone':
                $participant->phone = $text;
                $participant->current_step = 'car_brand';
                $participant->save();
                $this->sendTelegramMessage($chatId, 'Введите марку вашего автомобиля:');
                break;
                
            case 'car_brand':
                $participant->car_brand = $text;
                $participant->current_step = 'car_model';
                $participant->save();
                $this->sendTelegramMessage($chatId, 'Введите модель вашего автомобиля:');
                break;
                
            case 'car_model':
                $participant->car_model = $text;
                $participant->current_step = 'license_plate';
                $participant->save();
                $this->sendTelegramMessage($chatId, 'Введите гос. номер вашего автомобиля:');
                break;
                
            case 'license_plate':
                $participant->license_plate = $text;
                $participant->current_step = 'participation_days';
                $participant->save();
                
                $keyboard = [
                    'keyboard' => [
                        [['text' => '20 сентября']],
                        [['text' => '21 сентября']],
                        [['text' => 'Оба дня']]
                    ],
                    'resize_keyboard' => true,
                    'one_time_keyboard' => true
                ];
                
                $this->sendTelegramMessage(
                    $chatId, 
                    'На сколько дней планируете? (20 сентября , 21 сентября, оба дня)',
                    json_encode($keyboard)
                );
                break;
                
            case 'participation_days':
                $daysMap = [
                    '20 сентября' => '20',
                    '21 сентября' => '21',
                    'Оба дня' => 'both'
                ];
                
                $participant->participation_days = $daysMap[$text] ?? 'both';
                $participant->current_step = 'photos';
                $participant->save();
                $this->sendTelegramMessage($chatId, 'Пожалуйста, отправьте 3 горизонтальные фотографии вашего автомобиля (по одной за раз)');
                break;
                
            case 'photos':
                if (isset($message['photo'])) {
                    $photos = $participant->getMedia('car_photos');
                    
                    if ($photos->count() >= 3) {
                        $this->sendTelegramMessage($chatId, 'Вы уже загрузили максимальное количество фотографий (3)');
                        break;
                    }
                    
                    $photo = end($message['photo']);
                    $fileId = $photo['file_id'];
                    
                    // Получаем информацию о файле
                    $fileUrl = "https://api.telegram.org/bot" . env('TELEGRAM_BOT_TOKEN') . "/getFile?file_id=" . $fileId;
                    $fileInfo = Http::get($fileUrl)->json();
                    
                    if ($fileInfo['ok']) {
                        $filePath = $fileInfo['result']['file_path'];
                        $photoUrl = "https://api.telegram.org/file/bot" . env('TELEGRAM_BOT_TOKEN') . "/" . $filePath;
                        
                        // Сохранение фото
                        $participant->addMediaFromUrl($photoUrl)->toMediaCollection('car_photos');
                        
                        $remaining = 3 - ($photos->count() + 1);
                        $messageText = $remaining > 0 
                            ? "Фото сохранено. Осталось загрузить {$remaining} фото."
                            : "Регистрация завершена! Спасибо за участие.";
                            
                        $this->sendTelegramMessage($chatId, $messageText);
                        
                        if ($remaining === 0) {
                            $participant->current_step = 'completed';
                            $participant->save();
                        }
                    }
                }
                break;
                
            default:
                $this->sendTelegramMessage($chatId, 'Неизвестная команда');
        }
        
        return response()->json(['status' => 'success']);
    }
    
    public function setWebhook()
    {
        $url = "https://api.telegram.org/bot" . env('TELEGRAM_BOT_TOKEN') . "/setWebhook";
        $response = Http::post($url, [
            'url' => env('TELEGRAM_WEBHOOK_URL')
        ]);
        
        return $response->json();
    }
}