<?php

/**
 * Парсит СМС-подтверждение от Яндекса. Возвращает код подтверждения, сумму и
 * кошелек в одном массиве.
 *
 * @param string $message
 * @return array
 */
function parseYandexResponse(string $message): array
{
    // Исходя из описания тестовго задания, на "слова" вообще не смотрим,
    // пытаемся разобраться в цифрах. Предполагаем, что в сообщениях всегда
    // ровно по 3 числа (код, сумма, кошелек).

    // У кошельков Яндекса есть определенная длина (https://kassa.yandex.ru/tech/payout/wallet.html)
    // и все они начинаются с определенных префиксов (https://roem.ru/03-04-2014/111682/zachem-koshelkam-yandeksdeneg-nujen-prefiks-41001-kommentariy-yandeksdeneg/),
    // но на это расчитывать нельзя, т.к. сумма оплаты может совпасть с номером
    // кошелька (хотя, теоретически, такие большие суммы не должны фигурировать
    // в типичном приложении).

    // Предположим, что сумма = число, сразу после которого идет название валюты.
    // Можно было еще рассмотреть вариант, когда число начинается с валюты,
    // например "$1000". Если число будет совсем без валюты (просто число),
    // тогда вся моя логика сломается - лучше не придумал.
    if (($amount = findRegex('/(\d{1,}[,]?[\d]{0,})[а-яА-Яa-zA-Z]{1,}/', $message)) === null) {
        throw new \Exception('Не удалось определить номер счёта. Неверный формат сообщения.');
    }

    // Удаляем найденное число из сообщения
    $message = removeFromMessage($amount[1], $message);

    // Номер счёта кошельков яндекса - число от 11 до 20 цифр
    // Предположим, что проверочный код всегда короче 11 символов (обычно эти
    // коды 4-6 символов).
    if (($account = findRegex('/\d{11,20}/', $message)) === null) {
        throw new \Exception('Не удалось определить сумму. Неверный формат сообщения.');
    }

    // Удаляем найденное число из сообщения
    $message = removeFromMessage($account[0], $message);

    // Предположим, что единственное оставшееся число - проверочный код
    if (($code = findRegex('/\d{1,}/', $message)) === null) {
        throw new \Exception('Не удалось определить код подтверждения. Неверный формат сообщения.');
    }

    return [$code[0][0], $amount[1][0], $account[0][0]];
}

/**
 * Найти первое совпадение регулярного выражения в строке.
 *
 * @param string $regex
 * @param string $subject
 * @return string|null
 */
function findRegex(string $regex, string $subject)
{
    preg_match($regex, $subject, $matches, PREG_OFFSET_CAPTURE);

    return count($matches) && isset($matches[0][0]) ? $matches : null;
}

/**
 * Удалить найденную строку из сообщения
 *
 * @param array $match
 * @param string $message
 * @return string
 */
function removeFromMessage(array $match, string $message): string
{
    return substr($message, 0, $match[1])
        . substr($message, $match[1] + strlen($match[0]));
}