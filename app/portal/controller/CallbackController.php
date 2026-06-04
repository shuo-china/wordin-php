<?php

namespace app\portal\controller;

use Throwable;
use app\admin\logic\IFlyTekLogic;
use app\admin\logic\VideoSentenceLogic;
use app\admin\model\Video;

class CallbackController extends BaseController
{
    public function iFlyTekNotify()
    {
        $orderId = $this->request->param('orderId', $this->request->param('OrderId', ''));
        $orderId = trim((string) $orderId);
        if ($orderId === '') {
            $this->error(400, 'orderId is required.', 'XFYUN_ORDER_ID_REQUIRED');
        }

        $video = Video::where('order_id', $orderId)->find();
        if (!$video) {
            $this->error(404, 'Video not found by orderId.', 'VIDEO_NOT_FOUND');
        }

        $status = (string) $this->request->param('status', '');
        if ($status !== '1') {
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
}
