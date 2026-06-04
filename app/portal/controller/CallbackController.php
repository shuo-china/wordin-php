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
        Log::info('Xfyun notify received: ' . json_encode([
            'method' => $this->request->method(),
            'ip' => $this->request->ip(),
            'param' => $this->request->param(),
            'input' => $this->request->getInput(),
        ], JSON_UNESCAPED_UNICODE));

        $orderId = $this->getNotifyParam('orderId', $this->getNotifyParam('OrderId', ''));
        $orderId = trim((string) $orderId);
        if ($orderId === '') {
            $this->error(400, 'orderId is required.', 'XFYUN_ORDER_ID_REQUIRED');
        }

        $video = Video::where('order_id', $orderId)->find();
        if (!$video) {
            $this->error(404, 'Video not found by orderId.', 'VIDEO_NOT_FOUND');
        }

        $status = (string) $this->getNotifyParam('status', '');
        if ($status !== '1') {
            Log::info('Xfyun notify ignored: ' . json_encode([
                'orderId' => $orderId,
                'status' => $status,
            ], JSON_UNESCAPED_UNICODE));

            $this->success(200, [
                'message' => 'ignored',
                'orderId' => $orderId,
                'status' => $status,
            ]);
        }

        try {
            $result = (new IFlyTekLogic())->getResult($orderId);
            $sentenceCount = (new VideoSentenceLogic())->replace($video->id, $result['segments'] ?? []);
        } catch (Throwable $e) {
            $this->error(500, $e->getMessage(), 'XFYUN_NOTIFY_FAILED');
        }

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
}
