<?php

require_once('vendor/autoload.php');
require_once('constants.php');

use \poloniex\api\PoloniexAPIPublic;
use \unreal4u\TelegramAPI\TgLog;
use \unreal4u\TelegramAPI\Telegram\Methods\SendMessage;
use \unreal4u\TelegramAPI\Telegram\Methods\GetUpdates;

try {
    $pdo = new PDO(
        PDO_DSN,
        PDO_USERNAME,
        PDO_PASSWORD,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
    
    $ticker = PoloniexAPIPublic::returnTicker();
    
    $values = [];
    foreach ($ticker as $pair => $market) {
        $values[] = "('" . $pair . "', " . $market['last'] . ")";
        
    }
    
    $pdo->exec('INSERT INTO `ticker` (`pair`, `value`) VALUES ' . implode(', ', $values));
        
    $messages = [];
    
    $rows = $pdo->query(
        'SELECT `pair`, (MAX(`value`) - MIN(`value`)) / MIN(`value`) * 100 AS `growth`
        FROM `ticker`
        WHERE `ts` > UNIX_TIMESTAMP() - 120
        GROUP BY `pair`
        HAVING `growth` > 10'
    );
    
    while ($row = $rows->fetch()) {
        $messages[] = $row['pair'] . ' growths up to on ' . $row['growth'] . '% at last 2 mins' . PHP_EOL;
    }
    $messages[] = 'test';
    if (count($messages)) {
        $tgLog = new TgLog(TELEGRAM_BOT_TOKEN);
        $getUpdates = new GetUpdates();
        
        $updates = $tgLog->performApiRequest($getUpdates);
        foreach ($updates->traverseObject() as $update) {
            $sendMessage = new SendMessage();
            $sendMessage->chat_id = $update->message->chat->id;
            $sendMessage->text = implode(PHP_EOL, $messages);
            try {
                $tgLog->performApiRequest($sendMessage);
            } catch (\Exception $e) {
                echo $e->getMessage() . PHP_EOL;
            }
        }
    }
    
    echo 111;
    
} catch (\Exception $e) {
    echo $e->getMessage() . PHP_EOL;
}