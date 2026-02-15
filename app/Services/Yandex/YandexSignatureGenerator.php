<?php

namespace App\Services\Yandex;

class YandexSignatureGenerator
{
    /**
     * Вычисляет s_hash (djb2 XOR) для подписи запроса.
     */
    public function generate(string $queryString): string
    {
        $n = 5381;
        $len = strlen($queryString);
        for ($i = 0; $i < $len; $i++) {
            $n = ((33 * $n) ^ ord($queryString[$i])) & 0xFFFFFFFF;
        }

        $result = $n < 0 ? $n + 0x100000000 : $n;

        return (string) $result;
    }
}
