<?php

namespace app\customer\logic;

use RuntimeException;

class YoudaoLogic
{
    private const API_URL = 'https://openapi.youdao.com/api';

    public function translate($word, $from = 'en', $to = 'zh-CHS')
    {
        $word = trim((string) $word);
        if ($word === '') {
            throw new RuntimeException('word is required.');
        }

        $appKey = (string) env('youdao.app_key', '');
        $appSecret = (string) env('youdao.app_secret', '');

        if ($appKey === '' || $appSecret === '') {
            throw new RuntimeException('Youdao app key or secret is not configured.');
        }

        $salt = $this->uuid();
        $curtime = (string) time();
        $sign = hash('sha256', $appKey . $this->truncate($word) . $salt . $curtime . $appSecret);

        $result = $this->post(self::API_URL, [
            'q' => $word,
            'from' => $from,
            'to' => $to,
            'appKey' => $appKey,
            'salt' => $salt,
            'sign' => $sign,
            'signType' => 'v3',
            'curtime' => $curtime,
        ]);

        if (!isset($result['errorCode']) || (string) $result['errorCode'] !== '0') {
            throw new RuntimeException($result['errorCode'] ?? 'Youdao API returned an error.');
        }

        return [
            'word' => $word,
            'translation' => $result['translation'] ?? [],
            'explains' => $result['basic']['explains'] ?? [],
            'phonetic' => $result['basic']['phonetic'] ?? '',
            'ukPhonetic' => $result['basic']['uk-phonetic'] ?? '',
            'usPhonetic' => $result['basic']['us-phonetic'] ?? '',
        ];
    }

    private function post($url, array $params)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params, '', '&', PHP_QUERY_RFC1738),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Failed to request Youdao API: ' . $error);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new RuntimeException('Youdao API returned non-JSON response: HTTP ' . $httpCode);
        }

        return $data;
    }

    private function truncate($text)
    {
        $length = mb_strlen($text, 'UTF-8');
        if ($length <= 20) {
            return $text;
        }

        return mb_substr($text, 0, 10, 'UTF-8') . $length . mb_substr($text, $length - 10, 10, 'UTF-8');
    }

    private function uuid()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff)
        );
    }
}
