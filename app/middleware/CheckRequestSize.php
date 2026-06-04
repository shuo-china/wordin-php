<?php

declare(strict_types=1);

namespace app\middleware;

use app\common\Send;

class CheckRequestSize
{
    use Send;

    public function handle($request, \Closure $next)
    {
        $maxPostSize = $this->iniSizeToBytes((string) ini_get('post_max_size'));
        $contentLength = (int) ($request->server('CONTENT_LENGTH') ?: 0);

        if ($maxPostSize > 0 && $contentLength > $maxPostSize) {
            $this->error(413, 'Request body is too large.', 'PAYLOAD_TOO_LARGE', [
                'contentLength' => $contentLength,
                'postMaxSize' => $maxPostSize,
            ]);
        }

        return $next($request);
    }

    private function iniSizeToBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $number = (float) $value;

        switch ($unit) {
            case 'g':
                $number *= 1024;
                // no break
            case 'm':
                $number *= 1024;
                // no break
            case 'k':
                $number *= 1024;
                break;
        }

        return (int) $number;
    }
}
