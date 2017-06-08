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
    //echo 'INSERT INTO `ticker` (`pair`, `value`) VALUES ' . implode(', ', $values); die();
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
        $messages[] = $row['pair'] . ' growths up to on ' . $row['growth'] . ' at last 2 mins' . PHP_EOL;
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

/**/


/*$ticker = PoloniexAPIPublic::returnTicker();
var_dump($ticker);


define('BOT_TOKEN', 'XXXXXXXXX:YYYYYYY-YYYYYYYYYYYYYYYYY_YY');

define('A_USER_CHAT_ID', 'XXXXXXXXX');

define('A_GROUP_CHAT_ID', 'XXXXXXXXX');

define('A_FILE_ID', 'XXXXXXXXXXXXXXXXXXXXXXXX');

define('A_USER_ID', 'XXXXXXXX');

$tgLog = new TgLog('359802114:AAHfUJfRPqYDAo5xCUQJcFHAUPv4FZuzg9M');
$sendMessage = new SendMessage();
$sendMessage->chat_id = A_GROUP_CHAT_ID;
$sendMessage->text = 'And this is an hello the the group... also from a getMessage file';
try {
    $tgLog->performApiRequest($sendMessage);
    printf('Message "%s" sent!<br/>%s', $sendMessage->text, PHP_EOL);
} catch (ClientException $e) {
    echo 'Error detected trying to send message to group: <pre>';
    print_r((string)$e->getResponse()->getBody());
    echo '</pre>';
    die();
}
]*/