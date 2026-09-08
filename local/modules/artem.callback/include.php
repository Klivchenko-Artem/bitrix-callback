<?php

use Bitrix\Main\Loader;

// Классы модуля лежат по PSR-4, а не по правилам автозагрузки Битрикса:
// так их видит и composer в тестах, и ядро на сайте.
Loader::registerAutoLoadClasses('artem.callback', [
    'Artem\Callback\CallbackRequest' => 'lib/CallbackRequest.php',
    'Artem\Callback\Config' => 'lib/Config.php',
    'Artem\Callback\Contract\RateStorageInterface' => 'lib/Contract/RateStorageInterface.php',
    'Artem\Callback\Model\RequestTable' => 'lib/Model/RequestTable.php',
    'Artem\Callback\Service\PhoneNormalizer' => 'lib/Service/PhoneNormalizer.php',
    'Artem\Callback\Service\RateLimiter' => 'lib/Service/RateLimiter.php',
    'Artem\Callback\Service\RequestValidator' => 'lib/Service/RequestValidator.php',
    'Artem\Callback\Bitrix\CacheRateStorage' => 'lib/Bitrix/CacheRateStorage.php',
    'Artem\Callback\Bitrix\Notifier' => 'lib/Bitrix/Notifier.php',
    'Artem\Callback\Bitrix\RequestRepository' => 'lib/Bitrix/RequestRepository.php',
]);
