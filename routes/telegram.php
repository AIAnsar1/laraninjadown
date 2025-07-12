<?php
/** @var SergiX44\Nutgram\Nutgram $bot */

use App\Http\Controllers\{TelegramController, AdminController};
use SergiX44\Nutgram\Nutgram;
use Illuminate\Support\Facades\Cache;
/*
|--------------------------------------------------------------------------
| Nutgram Handlers
|--------------------------------------------------------------------------
|
| Here is where you can register telegram handlers for Nutgram. These
| handlers are loaded by the NutgramServiceProvider. Enjoy!
|
*/

// $bot->onCommand('start', function (Nutgram $bot) {
//     $bot->sendMessage('Hello, world!');
// })->description('The start command!');

$controller = app(TelegramController::class);

// --- FSM для рекламы ---
$bot->onCommand('ads_create', [AdminController::class, 'startAd']);
$bot->onText('/skip', [AdminController::class, 'adFsmSkipMedia']);
$bot->onPhoto([AdminController::class, 'adFsmMedia']);
$bot->onVideo([AdminController::class, 'adFsmMedia']);
$bot->onAnimation([AdminController::class, 'adFsmMedia']);
$bot->onCallbackQueryData('ad_lang:{lang}', [AdminController::class, 'adFsmLang']);
$bot->onCallbackQueryData('ad_send', [AdminController::class, 'adFsmSend']);
$bot->onCallbackQueryData('ad_cancel', [AdminController::class, 'adFsmCancel']);

$bot->onText('.*', function (Nutgram $bot) {
    $fsm = Cache::get("ad_fsm_{$bot->userId()}");

    if ($fsm) {
        app(abstract: AdminController::class)->adFsmText($bot);
    } else {
        app(TelegramController::class)->handleLink($bot);
    }
});
// --- Остальные команды и callback'и ---
$bot->onCallbackQueryData('lang:(uz|ru|eng)', [$controller, 'setLanguage']);
$bot->onCommand('start', [$controller, 'start']);
$bot->onCommand('ads_list', [AdminController::class, 'showAds']);
$bot->onCallbackQueryData('ads_page:{page}', [AdminController::class, 'showAdsPage']);
$bot->onCommand('ads_delete {ad_id}', [AdminController::class, 'adsDelete']);

$bot->onCallbackQuery(function (Nutgram $bot) use ($controller) {
    $data = $bot->callbackQuery()?->data;
    if (preg_match('~^yt:video:[a-f0-9]+$~', $data)) {
        $controller->downloadYoutubeVideo($bot);
    } elseif (preg_match('~^yt:audio:[a-f0-9]+$~', $data)) {
        $controller->downloadYoutubeAudio($bot);
    } elseif (preg_match('~^yt:format:[^:]+:[a-f0-9]+$~', $data)) {
        $controller->downloadYoutubeFormat($bot);
    }
});

// --- Общий обработчик текста ---

