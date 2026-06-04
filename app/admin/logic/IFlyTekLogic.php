<?php

namespace app\admin\logic;

use RuntimeException;

class IFlyTekLogic
{
    private const XFYUN_APP_ID = 'd4cc905d';
    private const XFYUN_API_SECRET = 'OGNlZDNkMDNiMmVlNjFmMjBlNDM0MmNj';
    private const XFYUN_API_KEY = 'e1d0574934c4b6b5597472ac4ef2c6fe';
    private const ASR_API_HOST = 'https://office-api-ist-dx.iflyaisol.com';
    private const TRANSLATE_API_HOST = 'https://ntrans.xfyun.cn';
    private const TRANSLATE_API_PATH = '/v2/ots';
    private const DEFAULT_REQUEST_TIMEOUT = 60;

    public function createUrlLinkTask($audioUrl, array $options = [])
    {
        $audioUrl = trim((string) $audioUrl);
        if ($audioUrl === '') {
            throw new RuntimeException('audioUrl is required.');
        }

        $fileName = trim((string) ($options['fileName'] ?? ''));
        if ($fileName === '') {
            $fileName = basename(parse_url($audioUrl, PHP_URL_PATH) ?: 'audio');
        }

        $params = array_merge($this->buildAuthParams(), [
            'fileName' => $fileName,
            'fileSize' => (string) max(1, (int) ($options['fileSize'] ?? 1)),
            'durationCheckDisable' => (string) ($options['durationCheckDisable'] ?? 'true'),
            'language' => (string) ($options['language'] ?? 'autodialect'),
            'audioMode' => 'urlLink',
            'audioUrl' => $audioUrl,
            'eng_smoothproc' => (string) ($options['eng_smoothproc'] ?? 'true'),
            'eng_colloqproc' => (string) ($options['eng_colloqproc'] ?? 'false'),
        ]);

        if (isset($options['duration'])) {
            $params['duration'] = (string) $options['duration'];
            $params['durationCheckDisable'] = 'false';
        }

        if (!empty($options['callbackUrl'])) {
            $params['callbackUrl'] = (string) $options['callbackUrl'];
        }

        $requestTimeout = max(1, (int) ($options['requestTimeout'] ?? self::DEFAULT_REQUEST_TIMEOUT));
        $result = $this->requestUpload($params, $requestTimeout);
        $this->assertSuccess($result, 'Xfyun upload API returned an error.');

        $content = isset($result['content']) && is_array($result['content']) ? $result['content'] : [];

        return [
            'orderId' => $content['orderId'] ?? '',
            'taskEstimateTime' => $content['taskEstimateTime'] ?? null,
            'language' => $params['language'],
            'signatureRandom' => $params['signatureRandom'],
            'audioMode' => 'urlLink',
            'audioUrl' => $audioUrl,
            'fileName' => $fileName,
            'fileSize' => (int) $params['fileSize'],
            'raw' => $result,
        ];
    }

    public function getResult($orderId, $signatureRandom = null, $requestTimeout = self::DEFAULT_REQUEST_TIMEOUT)
    {
        $orderId = trim((string) $orderId);
        if ($orderId === '') {
            throw new RuntimeException('orderId is required.');
        }

        $transferResult = $this->requestResult($orderId, 'transfer', $signatureRandom, $requestTimeout);
        $this->assertSuccess($transferResult, 'Xfyun transfer result API returned an error.');

        $transferContent = isset($transferResult['content']) && is_array($transferResult['content'])
            ? $transferResult['content']
            : [];
        $orderInfo = isset($transferContent['orderInfo']) && is_array($transferContent['orderInfo'])
            ? $transferContent['orderInfo']
            : [];

        $transferSegments = $this->parseOrderResult($transferContent['orderResult'] ?? '');

        if (!empty($transferSegments) && $this->isCompletedOrderInfo($orderInfo)) {
            $transferSegments = $this->translateSegments($transferSegments, $requestTimeout);
        }

        return [
            'orderId' => $orderId,
            'status' => $orderInfo['status'] ?? null,
            'failType' => $orderInfo['failType'] ?? null,
            'originalDuration' => $orderInfo['originalDuration'] ?? null,
            'realDuration' => $orderInfo['realDuration'] ?? null,
            'resultType' => 'transfer',
            'segments' => $transferSegments,
            'raw' => [
                'transfer' => $transferResult,
            ],
        ];
    }

    private function requestUpload(array $params, $requestTimeout)
    {
        $url = self::ASR_API_HOST . '/v2/upload?' . $this->buildQuery($params);

        return $this->post($url, [
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'signature: ' . $this->buildAsrSignature($params),
            ],
            CURLOPT_POSTFIELDS => '{}',
            CURLOPT_TIMEOUT => $requestTimeout,
        ]);
    }

    private function requestResult($orderId, $resultType, $signatureRandom = null, $requestTimeout = self::DEFAULT_REQUEST_TIMEOUT)
    {
        $params = array_merge($this->buildAuthParams(), [
            'orderId' => $orderId,
            'resultType' => $resultType,
        ]);

        if ($signatureRandom !== null && trim((string) $signatureRandom) !== '') {
            $params['signatureRandom'] = (string) $signatureRandom;
        }

        $url = self::ASR_API_HOST . '/v2/getResult?' . $this->buildQuery($params);

        return $this->post($url, [
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'signature: ' . $this->buildAsrSignature($params),
            ],
            CURLOPT_POSTFIELDS => '{}',
            CURLOPT_TIMEOUT => $requestTimeout,
        ]);
    }

    private function assertSuccess(array $result, $fallbackMessage)
    {
        if (isset($result['code']) && (string) $result['code'] === '000000') {
            return;
        }

        throw new RuntimeException($result['descInfo'] ?? $fallbackMessage);
    }

    private function isCompletedOrderInfo(array $orderInfo)
    {
        $status = isset($orderInfo['status']) ? (int) $orderInfo['status'] : 0;
        $failType = isset($orderInfo['failType']) ? (int) $orderInfo['failType'] : 0;

        return $status === 4 && $failType === 0;
    }

    private function buildAuthParams()
    {
        return [
            'appId' => self::XFYUN_APP_ID,
            'accessKeyId' => self::XFYUN_API_KEY,
            'dateTime' => date('Y-m-d\TH:i:sO'),
            'signatureRandom' => $this->randomString(16),
        ];
    }

    private function buildAsrSignature(array $params)
    {
        unset($params['signature']);
        ksort($params, SORT_STRING);

        $pairs = [];
        foreach ($params as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $pairs[] = urlencode((string) $key) . '=' . urlencode((string) $value);
        }

        return base64_encode(hash_hmac('sha1', implode('&', $pairs), self::XFYUN_API_SECRET, true));
    }

    private function buildQuery(array $params)
    {
        return http_build_query($params, '', '&', PHP_QUERY_RFC1738);
    }

    private function post($url, array $options)
    {
        $ch = curl_init($url);
        $defaults = [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_CONNECTTIMEOUT => 10,
        ];

        if (!empty($options[CURLOPT_UPLOAD])) {
            unset($defaults[CURLOPT_POST]);
        }

        curl_setopt_array($ch, $options + $defaults);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Failed to request Xfyun API: ' . $error);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new RuntimeException('Xfyun API returned non-JSON response: HTTP ' . $httpCode . ', ' . $response);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $data['httpCode'] = $httpCode;
        }

        return $data;
    }

    private function parseOrderResult($orderResult)
    {
        $data = json_decode((string) $orderResult, true);
        if (!isset($data['lattice']) || !is_array($data['lattice'])) {
            return [];
        }

        $segments = [];

        foreach ($data['lattice'] as $index => $item) {
            if (!isset($item['json_1best'])) {
                continue;
            }

            $oneBest = json_decode($item['json_1best'], true);
            if (!isset($oneBest['st']) || !is_array($oneBest['st'])) {
                continue;
            }

            $st = $oneBest['st'];
            $segmentWords = $this->extractWordItems($st);
            $segmentText = $this->joinWords(array_column($segmentWords, 'text'));
            if ($segmentText === '') {
                continue;
            }

            $beginTime = isset($st['bg']) ? (int) $st['bg'] : 0;
            $segments[] = [
                'index' => $index + 1,
                'begin' => $beginTime,
                'end' => isset($st['ed']) ? (int) $st['ed'] : null,
                'text' => $segmentText,
                'words' => $this->normalizeWordItems($segmentWords, $beginTime),
            ];
        }

        return $segments;
    }

    private function translateSegments(array $segments, $requestTimeout = self::DEFAULT_REQUEST_TIMEOUT)
    {
        foreach ($segments as $index => $segment) {
            $text = trim((string) ($segment['text'] ?? ''));
            if ($text === '') {
                $segments[$index]['translateText'] = '';
                continue;
            }

            $segments[$index]['translateText'] = $this->translateText($text, $requestTimeout);
        }

        return $segments;
    }

    private function translateText($text, $requestTimeout = self::DEFAULT_REQUEST_TIMEOUT)
    {
        $body = json_encode([
            'common' => [
                'app_id' => self::XFYUN_APP_ID,
            ],
            'business' => [
                'from' => 'en',
                'to' => 'cn',
            ],
            'data' => [
                'text' => base64_encode($text),
            ],
        ], JSON_UNESCAPED_UNICODE);

        if ($body === false) {
            throw new RuntimeException('Failed to encode Xfyun translate request.');
        }

        $result = $this->post(self::TRANSLATE_API_HOST . self::TRANSLATE_API_PATH, [
            CURLOPT_HTTPHEADER => $this->buildTranslateHeaders($body),
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => $requestTimeout,
        ]);

        if (!isset($result['code']) || (int) $result['code'] !== 0) {
            throw new RuntimeException($result['message'] ?? 'Xfyun translate API returned an error.');
        }

        return (string) ($result['data']['result']['trans_result']['dst'] ?? '');
    }

    private function buildTranslateHeaders($body)
    {
        $host = parse_url(self::TRANSLATE_API_HOST, PHP_URL_HOST);
        $date = gmdate('D, d M Y H:i:s') . ' GMT';
        $digest = 'SHA-256=' . base64_encode(hash('sha256', $body, true));
        $signatureString = "host: {$host}\n";
        $signatureString .= "date: {$date}\n";
        $signatureString .= 'POST ' . self::TRANSLATE_API_PATH . " HTTP/1.1\n";
        $signatureString .= "digest: {$digest}";
        $signature = base64_encode(hash_hmac('sha256', $signatureString, self::XFYUN_API_SECRET, true));
        $authorization = sprintf(
            'api_key="%s", algorithm="hmac-sha256", headers="host date request-line digest", signature="%s"',
            self::XFYUN_API_KEY,
            $signature
        );

        return [
            'Content-Type: application/json',
            'Accept: application/json',
            'Host: ' . $host,
            'Date: ' . $date,
            'Digest: ' . $digest,
            'Authorization: ' . $authorization,
        ];
    }

    private function extractWordItems(array $st)
    {
        if (!isset($st['rt']) || !is_array($st['rt'])) {
            return [];
        }

        $words = [];
        foreach ($st['rt'] as $rt) {
            if (!isset($rt['ws']) || !is_array($rt['ws'])) {
                continue;
            }

            foreach ($rt['ws'] as $ws) {
                if (!isset($ws['cw']) || !is_array($ws['cw'])) {
                    continue;
                }

                foreach ($ws['cw'] as $cw) {
                    if (!isset($cw['w']) || !is_string($cw['w']) || $cw['w'] === '') {
                        continue;
                    }

                    $text = trim($cw['w']);
                    if ($text === '') {
                        continue;
                    }

                    $words[] = [
                        'text' => $text,
                        'is_word' => !$this->isPunctuation($text),
                        'wb' => isset($ws['wb']) ? (int) $ws['wb'] : null,
                        'we' => isset($ws['we']) ? (int) $ws['we'] : null,
                    ];
                }
            }
        }

        return $words;
    }

    private function normalizeWordItems(array $words, $segmentBeginTime)
    {
        $items = [];

        foreach ($words as $index => $word) {
            $beginTime = isset($word['wb']) ? ((int) $segmentBeginTime + ((int) $word['wb'] * 10)) : null;
            $endTime = isset($word['we']) ? ((int) $segmentBeginTime + ((int) $word['we'] * 10)) : $beginTime;

            $items[] = [
                'index' => $index + 1,
                'text' => $word['text'],
                'is_word' => (bool) ($word['is_word'] ?? false),
                'begin_time' => $beginTime,
                'end_time' => $endTime,
            ];
        }

        return $items;
    }

    private function joinWords(array $words)
    {
        $text = '';

        foreach ($words as $word) {
            $word = trim((string) $word);
            if ($word === '') {
                continue;
            }

            if ($text === '' || $this->isPunctuation($word) || !$this->shouldInsertSpace($text, $word)) {
                $text .= $word;
            } else {
                $text .= ' ' . $word;
            }
        }

        return $text;
    }

    private function shouldInsertSpace($text, $word)
    {
        $last = substr($text, -1);

        return preg_match('/[A-Za-z0-9]$/', $last) === 1
            && preg_match('/^[A-Za-z0-9]/', $word) === 1;
    }

    private function isPunctuation($word)
    {
        return preg_match('/^\p{P}+$/u', $word) === 1;
    }

    private function randomString($length)
    {
        $pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $max = strlen($pool) - 1;
        $value = '';

        for ($i = 0; $i < $length; $i++) {
            $value .= $pool[random_int(0, $max)];
        }

        return $value;
    }
}
