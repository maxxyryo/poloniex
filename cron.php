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
    
    $time = mktime(date('H'), date('i'), 0);
    $ticker = PoloniexAPIPublic::returnTicker();
    
    $values = [];
    foreach ($ticker as $pair => $market) {
        $values[] = "('" . $pair . "', " . $market['last'] . ", " . $time . ")";
        
    }
    
    $pdo->exec('INSERT INTO `ticker` (`pair`, `value`, `ts`) VALUES ' . implode(', ', $values));
        
    $messages = [];
    
    $rows = $pdo->query(
        'SELECT
            curr.`pair`,
            prev.`value` AS `prev_value`, 
            curr.`value` AS `curr_value`, 
            (curr.`value` - prev.`value`) / prev.`value` * 100 AS `growth`
        FROM 
            (SELECT * FROM `ticker` WHERE `ts` = ' . ($time - 60) . ') AS prev
            INNER JOIN (SELECT * FROM `ticker` WHERE `ts` = ' . $time . ') AS curr ON prev.`pair` = curr.`pair` 
        WHERE 
            `growth` > 10'
    );
    
    while ($row = $rows->fetch()) {
        $messages[] = $row['pair'] . ' growths up to on ' . $row['growth'] . '%  from ' . $row['prev_value'] . ' to ' . $row['curr_value'] . ' at last 2 mins' . PHP_EOL;
    }
    
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
    
} catch (\Exception $e) {
    echo $e->getMessage() . PHP_EOL;
}