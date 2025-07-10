<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\{ReplyKeyboardMarkup, InlineKeyboardMarkup, InlineKeyboardButton, KeyboardButton};
use SergiX44\Nutgram\Telegram\Types\Input\InputFile;
use Illuminate\Support\Facades\Cache;
use App\Models\TelegramUser;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Models\Advertisements;
use App\Models\AdvertisementsDeliveries;

class AdminController extends Controller
{
    public function startAd(Nutgram $bot)
    {
        if (!in_array($bot->userId(), config('nutgram.admins_id'))) {
            $bot->sendMessage('❌ Нет доступа.');
            return;
        }

        Cache::put("ad_fsm_{$bot->userId()}", ['step' => 'lang'], now()->addMinutes(20));

        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make('🇷🇺 Русский', callback_data: 'ad_lang:ru'),
                InlineKeyboardButton::make('🇺🇸 English', callback_data: 'ad_lang:en')
            );

        $bot->sendMessage("🌍 Выберите язык аудитории:", reply_markup: $keyboard);
    }

    public function adFsmLang(Nutgram $bot, $lang)
    {
        $fsm = Cache::get("ad_fsm_{$bot->userId()}");
        if (!$fsm) return;

        $fsm['lang'] = $lang;
        $fsm['step'] = 'title'; // ⬅️ Сначала название рекламы
        Cache::put("ad_fsm_{$bot->userId()}", $fsm);

        $bot->answerCallbackQuery();
        $bot->sendMessage("📢 Введите название рекламы.");
    }

    public function adFsmText(Nutgram $bot)
    {
        $fsm = Cache::get("ad_fsm_{$bot->userId()}");
        if (!$fsm) return;

        switch ($fsm['step']) {
            case 'title':
                $fsm['title'] = $bot->message()->text;
                $fsm['step'] = 'description';
                Cache::put("ad_fsm_{$bot->userId()}", $fsm);
                $bot->sendMessage('📝 Введите текст рекламы.');
                break;

            case 'description':
                $fsm['description'] = $bot->message()->text;
                $fsm['step'] = 'media';
                Cache::put("ad_fsm_{$bot->userId()}", $fsm);
                $bot->sendMessage('📸 Отправьте фото, видео или гифку (или напишите /skip).');
                break;

            case 'url':
                if (!preg_match('~^https?://~', $bot->message()->text)) {
                    $bot->sendMessage('❗️ Пожалуйста, введите корректную ссылку (начинается с http/https).');
                    return;
                }
                $fsm['button_url'] = $bot->message()->text;
                $fsm['step'] = 'button_title';
                Cache::put("ad_fsm_{$bot->userId()}", $fsm);
                $bot->sendMessage('📎 Введите название кнопки (например: Перейти)');
                break;

            case 'button_title':
                $fsm['button_text'] = $bot->message()->text;
                $fsm['step'] = 'preview';
                Cache::put("ad_fsm_{$bot->userId()}", $fsm);

                $keyboard = InlineKeyboardMarkup::make()
                    ->addRow(InlineKeyboardButton::make($fsm['button_text'], url: $fsm['button_url']))
                    ->addRow(
                        InlineKeyboardButton::make('✅ Отправить', callback_data: 'ad_send'),
                        InlineKeyboardButton::make('❌ Отменить', callback_data: 'ad_cancel')
                    );

                $caption = "<b>{$fsm['title']}</b>\n\n{$fsm['description']}";

                if (($fsm['media_type'] ?? null) === 'photo') {
                    $bot->sendPhoto(
                        photo: $fsm['media_file_id'],
                        caption: $caption,
                        parse_mode: 'HTML',
                        reply_markup: $keyboard
                    );
                } elseif (($fsm['media_type'] ?? null) === 'video') {
                    $bot->sendVideo(
                        video: $fsm['media_file_id'],
                        caption: $caption,
                        parse_mode: 'HTML',
                        reply_markup: $keyboard
                    );
                } elseif (($fsm['media_type'] ?? null) === 'animation') {
                    $bot->sendAnimation(
                        animation: $fsm['media_file_id'],
                        caption: $caption,
                        parse_mode: 'HTML',
                        reply_markup: $keyboard
                    );
                } else {
                    $bot->sendMessage(
                        text: $caption,
                        parse_mode: 'HTML',
                        reply_markup: $keyboard
                    );
                }
                break;
        }
    }

    public function adFsmMedia(Nutgram $bot)
    {
        $fsm = Cache::get("ad_fsm_{$bot->userId()}");
        if (!$fsm || $fsm['step'] !== 'media') return;

        if ($bot->message()->photo) {
            $fsm['media_type'] = 'photo';
            $fsm['media_file_id'] = $bot->message()->photo[count($bot->message()->photo)-1]->file_id;
        } elseif ($bot->message()->video) {
            $fsm['media_type'] = 'video';
            $fsm['media_file_id'] = $bot->message()->video->file_id;
        } elseif ($bot->message()->animation) {
            $fsm['media_type'] = 'animation';
            $fsm['media_file_id'] = $bot->message()->animation->file_id;
        } else {
            $bot->sendMessage('❗️Пожалуйста, отправьте медиафайл или напишите /skip.');
            return;
        }
        $fsm['step'] = 'url';
        Cache::put("ad_fsm_{$bot->userId()}", $fsm);
        $bot->sendMessage('🔗 Введите ссылку (начинается с http/https).');
    }

    public function adFsmSkipMedia(Nutgram $bot)
    {
        $fsm = Cache::get("ad_fsm_{$bot->userId()}");
        if (!$fsm || $fsm['step'] !== 'media') return;
        $fsm['media_type'] = 'none';
        $fsm['step'] = 'url';
        Cache::put("ad_fsm_{$bot->userId()}", $fsm);
        $bot->sendMessage('🔗 Введите ссылку (начинается с http/https).');
    }

    public function adFsmSend(Nutgram $bot)
    {
        $fsm = Cache::pull("ad_fsm_{$bot->userId()}");
        if (!$fsm) {
            $bot->answerCallbackQuery('Реклама уже отправлена или устарела.');
            return;
        }

        $callback = $bot->callbackQuery();
        if ($callback?->id) {
            $bot->answerCallbackQuery(
                callback_query_id: $callback->id,
                text: '🚀 Отправка началась...',
                show_alert: false
            );
        }

        $adminIds = config('nutgram.admins_id');
        $users = TelegramUser::where('language', $fsm['lang'])->pluck('user_id');

        // Кнопка
        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(InlineKeyboardButton::make($fsm['button_text'], url: $fsm['button_url']));

        // Заголовок + текст
        $caption = trim(mb_substr("<b>{$fsm['title']}</b>\n\n{$fsm['description']}", 0, 1024));

        $mediaType = $fsm['media_type'] ?? null;
        $mediaId = $fsm['media_file_id'] ?? null;

        // --- СОХРАНЯЕМ РЕКЛАМУ В БД ---
        $ad_uuid = \Illuminate\Support\Str::uuid()->toString();
        $ad = Advertisements::create([
            'ad_uuid' => $ad_uuid,
            'content' => $caption,
            'media_type' => $mediaType,
            'media_file_id' => $mediaId,
            'target_lang' => $fsm['lang'],
            'is_active' => true,
        ]);
        // ---

        $success = 0;
        $failed = 0;

        foreach ($users as $user_id) {
            if (in_array($user_id, $adminIds)) continue;

            try {
                $msg = null;
                switch ($mediaType) {
                    case 'photo':
                        $msg = $bot->sendPhoto(
                            photo: $mediaId,
                            caption: $caption,
                            parse_mode: 'HTML',
                            reply_markup: $keyboard,
                            chat_id: $user_id
                        );
                        break;
                    case 'video':
                        $msg = $bot->sendVideo(
                            video: $mediaId,
                            caption: $caption,
                            parse_mode: 'HTML',
                            reply_markup: $keyboard,
                            chat_id: $user_id
                        );
                        break;
                    case 'animation':
                        $msg = $bot->sendAnimation(
                            animation: $mediaId,
                            caption: $caption,
                            parse_mode: 'HTML',
                            reply_markup: $keyboard,
                            chat_id: $user_id
                        );
                        break;
                    default:
                        $msg = $bot->sendMessage(
                            text: $caption,
                            parse_mode: 'HTML',
                            reply_markup: $keyboard,
                            chat_id: $user_id
                        );
                        break;
                }

                // --- СОХРАНЯЕМ ДОСТАВКУ ---
                AdvertisementsDeliveries::create([
                    'ad_id' => $ad->id,
                    'user_id' => $user_id,
                    'message_id' => $msg->message_id ?? null,
                    'sent_at' => now(),
                ]);
                // ---

                $success++;
            } catch (\Throwable $e) {
                $failed++;
                // Log::error("❌ Ошибка отправки пользователю {$user_id}: {$e->getMessage()}");
            }
        }

        try {
            // Сообщение для админа
            $text = "✅ Реклама отправлена!\n📬 Доставлено: $success\n❌ Ошибок: $failed";

            if ($callback?->message?->text !== null) {
                $bot->editMessageText($text)
                    ->chatId($callback->message->chat->id)
                    ->messageId($callback->message->message_id);
            } else {
                $bot->sendMessage($text)
                    ->chatId($bot->userId());
            }
        } catch (\Throwable $e) {
            $failed++;
            Log::error("❌ Ошибка отправки пользователю {$user_id}: {$e->getMessage()}");
            Log::error("caption: " . $caption);
            Log::error("keyboard: " . json_encode($keyboard->toArray()));
        }
    }

    // Показ рекламы с пагинацией
    public function showAds(Nutgram $bot, $page = 1)
    {
        $perPage = 10;
        $ads = Advertisements::orderByDesc('id')
            ->skip(($page-1)*$perPage)
            ->take($perPage)
            ->get();
        $total = Advertisements::count();

        if ($ads->isEmpty()) {
            $bot->sendMessage('Нет рекламы.');
            return;
        }

        $text = '';
        foreach ($ads as $ad) {
            $text .= "📜 UUID: <code>{$ad->ad_uuid}</code>\n";
            $text .= "🌍 Язык: {$ad->target_lang}\n";
            $text .= "📝 {$ad->content}\n\n";
        }

        $keyboard = InlineKeyboardMarkup::make();
        $navRow = [];
        if ($page > 1) {
            $navRow[] = InlineKeyboardButton::make('⬅️ Предыдущая', callback_data: 'ads_page:'.($page-1));
        }
        if ($page * $perPage < $total) {
            $navRow[] = InlineKeyboardButton::make('Следующая ➡️', callback_data: 'ads_page:'.($page+1));
        }
        if ($navRow) {
            $keyboard->addRow(...$navRow);
        }

        $bot->sendMessage(
            chat_id: $bot->userId(), // или явно id, например: 6597464835
            text: $text,
            parse_mode: 'HTML',
            reply_markup: $keyboard,
            disable_web_page_preview: true
        );
    }

    // Callback для пагинации
    public function showAdsPage(Nutgram $bot, $page)
    {
        $this->showAds($bot, (int)$page);
    }

    public function adFsmCancel(Nutgram $bot)
    {
        $bot->answerCallbackQuery('Отменено');
        $bot->editMessageText('❌ Отменено')->send();
        Cache::forget("ad_fsm_{$bot->userId()}");
    }

    // Удаление рекламы по UUID или ID
    public function adsDelete(Nutgram $bot, $ad_id)
    {
        // Найти рекламу по UUID или ID
        $ad = Advertisements::where('ad_uuid', $ad_id)->first();
        if (is_numeric($ad_id)) {
            $ad = Advertisements::where('id', $ad_id)->first();
        } else {
            $ad = Advertisements::where('ad_uuid', $ad_id)->first();
        }
        if (!$ad) {
            $bot->sendMessage('❌ Реклама с таким ID/UUID не найдена.');
            return;
        }
        // Найти все доставки
        $deliveries = AdvertisementsDeliveries::where('ad_id', $ad->id)->get();
        $deleted = 0;
        foreach ($deliveries as $delivery) {
            try {
                $bot->deleteMessage($delivery->user_id, $delivery->message_id);
                $deleted++;
            } catch (\Throwable $e) {
                // Игнорируем ошибку, если сообщение уже удалено или бот не может удалить
            }
        }
        // Удалить доставки и саму рекламу
        AdvertisementsDeliveries::where('ad_id', $ad->id)->delete();
        $ad->delete();
        $bot->sendMessage("✅ Реклама удалена. Сообщений удалено: $deleted");
    }
}
