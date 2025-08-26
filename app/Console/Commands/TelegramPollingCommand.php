<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Participant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TelegramPollingCommand extends Command
{
    protected $signature = 'telegram:polling';
    protected $description = 'Run Telegram bot in long polling mode';

    private function sendMessage($chatId, $text, $replyMarkup = null, $fileName = null)
    {
        if ($fileName) {
            // Отправка сообщения с фото
            $this->sendImageMessage($chatId, $text, $fileName, $replyMarkup);
        } else {
            // Обычное текстовое сообщение
            $url = "https://api.telegram.org/bot".env('TELEGRAM_BOT_TOKEN')."/sendMessage";
            
            $data = [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML'
            ];
        }
        
        if ($replyMarkup) {
            $data['reply_markup'] = $replyMarkup;
        }
        
        return Http::post($url, $data);
    }

    public function handleTestImage()
    {
        $keyboard = [
            'keyboard' => [
                [['text' => '9 августа']],
                [['text' => '10 августа']],
                [['text' => 'Оба дня']]
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => true
        ];
        $this->sendImageMessage(
            1402350810,
            'Тестовое изображение',
            'model.jpg',
            json_encode($keyboard)
        );
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

    public function handle()
    {
        $this->info('Starting Telegram bot in long polling mode...');
        $offset = 0;

        //$this->handleTestImage();
        
        while (true) {
            try {
                sleep(1);
                $response = Http::post(
                    "https://api.telegram.org/bot".env('TELEGRAM_BOT_TOKEN')."/getUpdates", 
                    ['offset' => $offset + 1, 'timeout' => 25]
                );
                
                if ($response->successful()) {
                    $updates = $response->json()['result'];
                    
                    if (!empty($updates)) {
                        foreach ($updates as $update) {
                            $offset = $update['update_id'];
                            $this->processUpdate($update);
                        }
                    }
                } else {
                    Log::error('Telegram API error', ['response' => $response->body()]);
                    sleep(5);
                }
            } catch (\Exception $e) {
                Log::error('Polling error', ['error' => $e->getMessage()]);
                sleep(5);
            }
        }
    }
    
    private function processUpdate($update)
    {
        if (!isset($update['message'])) return;
        
        $message = $update['message'];
        $chatId = $message['chat']['id'];
        $userId = $message['from']['id']; // Используем ID пользователя
        $text = $message['text'] ?? '';
        $username = $message['from']['username'] ?? null;
        
        // Находим участника по telegram_id
        $participant = Participant::where('telegram_id', $userId)->first();
        
        // Начало регистрации
        if (!$participant && strtolower($text) === '/start') {
            $participant = Participant::create([
                'telegram_id' => $userId,
                'telegram_username' => $username,
                'current_step' => 'name'
            ]);
            
            $this->sendMessage($chatId, 'Добро пожаловать на регистрацию участников! Введите ФИО:', fileName: 'register.png');
            return;
        }
        
        if (!$participant) return;

        if ($participant && $participant->status !== 'pending') {
            $this->sendMessage($chatId, "Ваша заявка уже обработана. Текущий статус: " . 
                Participant::STATUSES[$participant->status]);
            return;
        }
                
        // Обработка шагов регистрации
        switch ($participant->current_step) {
            case 'name':
                $participant->update([
                    'name' => $text,
                    'current_step' => 'phone'
                ]);
                $this->sendMessage($chatId, 'Введите ваш номер телефона в формате +79123456789:');
                break;
                
            case 'phone':
                $participant->update([
                    'phone' => $text,
                    'current_step' => 'car_model'
                ]);
                $this->sendMessage($chatId, 'Введите марку и модель вашего автомобиля:', fileName: 'model.jpg');
                break;          
                
            case 'car_model':
                $participant->update([
                    'car_model' => $text,
                    'current_step' => 'license_plate'
                ]);
                $this->sendMessage($chatId, 'Введите гос. номер вашего автомобиля:', fileName: 'plate.jpg');
                break;
                
            case 'license_plate':
                $participant->update([
                    'license_plate' => $text,
                    'current_step' => 'participation_days'
                ]);
                
                $keyboard = [
                    'keyboard' => [
                        [['text' => '20 сентября']],
                        [['text' => '21 сентября']],
                        [['text' => 'Оба дня']]
                    ],
                    'resize_keyboard' => true,
                    'one_time_keyboard' => true
                ];
                
                $this->sendMessage(
                    $chatId, 
                    'На сколько дней планируете? (20 сентября , 21 сентября, оба дня)',
                    json_encode($keyboard),
                    fileName: 'date.png'
                );
                break;
                
            case 'participation_days':
                $daysMap = [
                    '20 сентября' => '20',
                    '21 сентября' => '21',
                    'Оба дня' => 'both'
                ];
                
                $participant->update([
                    'participation_days' => $daysMap[$text] ?? 'both',
                    'current_step' => 'photos'
                ]);
                $this->sendMessage($chatId, 'Пришлите фото автомобиля. Минимум 3 штуки (по одной за раз)', fileName: 'photo.png');
                break;
                
            case 'photos':
                if (isset($message['photo'])) {
                    $photosCount = $participant->carPhotos()->count();
                    
                    if ($photosCount >= 3) {
                        $this->sendMessage($chatId, 'Вы уже загрузили максимальное количество фотографий (3)');
                        break;
                    }
                    
                    $photo = end($message['photo']);
                    $fileId = $photo['file_id'];
                    
                    // Сохраняем фото
                    $this->savePhoto($participant, $fileId);
                    
                    $remaining = 3 - ($photosCount + 1);
                    $messageText = $remaining > 0 
                        ? "Фото сохранено. Осталось загрузить {$remaining} фото."
                        : "🚗 *Ваша заявка принята!* Ожидайте ответа здесь после рассмотрения заявки\n\n";
                        
                    $this->sendMessage($chatId, $messageText);
                    
                    if ($remaining === 0) {
                        $participant->update(['current_step' => 'completed']);
                    }
                }
                break;
        }
    }

    private function savePhoto($participant, $fileId)
    {
        $fileUrl = "https://api.telegram.org/bot".env('TELEGRAM_BOT_TOKEN')."/getFile?file_id=".$fileId;
        $fileInfo = Http::get($fileUrl)->json();
        
        if ($fileInfo['ok']) {
            $filePath = $fileInfo['result']['file_path'];
            $photoUrl = "https://api.telegram.org/file/bot".env('TELEGRAM_BOT_TOKEN')."/".$filePath;
            
            $contents = file_get_contents($photoUrl);
            $filename = 'car_photos/'.md5($fileId).'.jpg';
            
            // Сохраняем в публичную директорию
            Storage::disk('public')->put($filename, $contents);
            
            // Сохраняем относительный путь (без 'public/')
            $participant->carPhotos()->create(['path' => $filename]);
        }
    }
}