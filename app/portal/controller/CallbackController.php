<?php

namespace app\portal\controller;

use Throwable;
use app\admin\logic\IFlyTekLogic;
use app\admin\logic\VideoSentenceLogic;
use app\admin\model\Video;
use think\facade\Log;

class CallbackController extends BaseController
{
    public function iFlyTekNotify()
    {
        $this->writeNotifyLog('received', [
            'method' => $this->request->method(),
            'ip' => $this->request->ip(),
            'param' => $this->request->param(),
            'input' => $this->request->getInput(),
        ]);

        $orderId = $this->getNotifyParam('orderId', $this->getNotifyParam('OrderId', ''));
        $orderId = trim((string) $orderId);
        if ($orderId === '') {
            $this->writeNotifyLog('missing orderId', [
                'param' => $this->request->param(),
                'input' => $this->request->getInput(),
            ]);

            $this->error(400, 'orderId is required.', 'XFYUN_ORDER_ID_REQUIRED');
        }

        $video = Video::where('order_id', $orderId)->find();
        if (!$video) {
            $this->writeNotifyLog('video not found', [
                'orderId' => $orderId,
            ]);

            $this->error(404, 'Video not found by orderId.', 'VIDEO_NOT_FOUND');
        }

        $status = (string) $this->getNotifyParam('status', '');
        if ($status === '-1') {
            $this->writeNotifyLog('transfer failed', [
                'videoId' => $video->id,
                'orderId' => $orderId,
                'status' => $status,
            ]);

            $this->success(200, [
                'message' => 'transfer failed',
                'orderId' => $orderId,
                'status' => $status,
            ]);
        }

        if ($status !== '1') {
            $this->writeNotifyLog('ignored', [
                'videoId' => $video->id,
                'orderId' => $orderId,
                'status' => $status,
            ]);

            $this->success(200, [
                'message' => 'ignored',
                'orderId' => $orderId,
                'status' => $status,
            ]);
        }

        try {
            $result = (new IFlyTekLogic())->getResult($orderId);
            $this->writeNotifyLog('result fetched', [
                'videoId' => $video->id,
                'orderId' => $orderId,
                'status' => $result['status'] ?? null,
                'failType' => $result['failType'] ?? null,
                'segmentCount' => count($result['segments'] ?? []),
            ]);

            $sentenceCount = (new VideoSentenceLogic())->replace($video->id, $result['segments'] ?? []);
        } catch (Throwable $e) {
            $this->writeNotifyLog('failed', [
                'videoId' => $video->id,
                'orderId' => $orderId,
                'message' => $e->getMessage(),
            ]);

            $this->error(500, $e->getMessage(), 'XFYUN_NOTIFY_FAILED');
        }

        $this->writeNotifyLog('success', [
            'videoId' => $video->id,
            'orderId' => $orderId,
            'sentenceCount' => $sentenceCount,
        ]);

        $this->success(200, [
            'message' => 'success',
            'orderId' => $orderId,
            'sentenceCount' => $sentenceCount,
        ]);
    }

    protected function getNotifyParam($name, $default = null)
    {
        $value = $this->request->param($name);
        if ($value !== null && $value !== '') {
            return $value;
        }

        $input = $this->request->getInput();
        $json = json_decode((string) $input, true);
        if (is_array($json) && array_key_exists($name, $json)) {
            return $json[$name];
        }

        return $default;
    }

    protected function writeNotifyLog($event, array $data)
    {
        $message = 'Xfyun notify ' . $event . ': ' . json_encode($data, JSON_UNESCAPED_UNICODE);
        Log::write($message, 'info');

        try {
            $file = $this->app->getRuntimePath() . 'iflytek_notify.log';
            $dir = dirname($file);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            if (is_dir($dir) && is_writable($dir)) {
                file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
            }
        } catch (Throwable $e) {
            Log::write('Xfyun notify file log failed: ' . $e->getMessage(), 'error');
        }
    }
}
