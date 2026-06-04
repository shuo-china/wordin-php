<?php

namespace app\admin\controller;

use Throwable;
use app\admin\logic\IFlyTekLogic;
use app\admin\model\File;
use app\admin\model\Video;
use app\admin\model\VideoSentence;

class VideoController extends BaseController
{
    public function pagination()
    {
        $param = $this->request->param();
        $map = [];

        if (!empty($param['title'])) {
            $map[] = ['title', 'like', '%' . $param['title'] . '%'];
        }

        $videos = Video::with(['file'])
            ->where($map)
            ->order('id desc')
            ->paginate();

        $this->success(200, $videos);
    }

    public function detail()
    {
        $id = $this->getRequestId();
        if ($id <= 0) {
            $this->error(400, 'Video ID is required.', 'VIDEO_ID_REQUIRED');
        }

        $video = Video::with(['file'])->where('id', $id)->find();

        $this->success(200, $video);
    }

    public function create()
    {
        $post = $this->request->post();
        $this->validate($post, 'Video');

        Video::create($post);
        $this->success(201);
    }

    public function update()
    {
        $post = $this->request->post();
        $this->validate($post, 'Video');

        Video::update($post);
        $this->success(201);
    }

    public function delete()
    {
        $id = (int) $this->request->param('id');
        if ($id <= 0) {
            $this->error(400, 'Video ID is required.', 'VIDEO_ID_REQUIRED');
        }

        $video = Video::with(['file'])->where('id', $id)->find();
        if (!$video) {
            $this->success(204);
        }

        $file = $video->file;

        VideoSentence::where('video_id', $video->id)->delete();
        $video->delete();

        if ($file) {
            $this->deletePhysicalFile($file->getData('path'));
            File::where('key', $file->key)->delete();
        }

        $this->success(204);
    }

    protected function deletePhysicalFile($path)
    {
        $path = trim((string) $path);
        if ($path === '' || preg_match('/^https?:\/\//', $path) === 1) {
            return;
        }

        $filename = public_path() . $path;
        if (is_file($filename)) {
            @unlink($filename);
        }
    }

    public function analyze()
    {
        $id = $this->getRequestId();
        if ($id <= 0) {
            $this->error(400, 'Video ID is required.', 'VIDEO_ID_REQUIRED');
        }

        $video = Video::with(['file'])->where('id', $id)->find();

        if (!$video) {
            $this->error(404, 'Video not found.', 'VIDEO_NOT_FOUND');
        }

        if (!$video->file) {
            $this->error(400, 'Video file not found.', 'VIDEO_FILE_NOT_FOUND');
        }

        $audioUrl = $this->buildPublicUrl($video->file->getData('path'));
        $callbackUrl = $this->buildIFlyTekCallbackUrl();

        try {
            $result = (new IFlyTekLogic())->createUrlLinkTask($audioUrl, [
                'fileName' => $video->file->name,
                'requestTimeout' => $this->getAnalyzeRequestTimeout(),
                'callbackUrl' => $callbackUrl,
            ]);
        } catch (Throwable $e) {
            $this->error(500, $e->getMessage(), 'XFYUN_ANALYZE_FAILED');
        }

        if (empty($result['orderId'])) {
            $this->error(500, 'Xfyun upload API did not return orderId.', 'XFYUN_ORDER_ID_MISSING');
        }

        $video->save([
            'order_id' => $result['orderId'],
        ]);

        $this->success(202, [
            'message' => 'success',
            'orderId' => $result['orderId'],
            'taskEstimateTime' => $result['taskEstimateTime'] ?? null,
            'audioUrl' => $audioUrl,
            'callbackUrl' => $callbackUrl,
        ]);
    }

    protected function getAnalyzeRequestTimeout()
    {
        $timeout = (int) $this->request->param('requestTimeout', 60);
        if ($timeout <= 0) {
            return 60;
        }

        return min($timeout, 180);
    }

    protected function buildIFlyTekCallbackUrl()
    {
        return $this->buildPublicUrl('/portal/callback/iFlyTekNotify');
    }

    protected function buildPublicUrl($path)
    {
        $path = trim((string) $path);
        if (preg_match('/^https?:\/\//', $path) === 1) {
            return $path;
        }

        $host = trim((string) config('app.app_host'));
        if ($host === '') {
            $host = $this->request->domain();
        }

        return rtrim($host, '/') . '/' . ltrim($path, '/');
    }

    protected function getRequestId()
    {
        $id = $this->request->param('id');
        if ($id !== null && $id !== '') {
            return (int) $id;
        }

        $payload = $this->request->param('payload');
        $payloadId = $this->getPayloadId($payload);
        if ($payloadId > 0) {
            return $payloadId;
        }

        $input = $this->request->getInput();
        $json = json_decode((string) $input, true);
        if (is_array($json)) {
            if (isset($json['id'])) {
                return (int) $json['id'];
            }

            $payloadId = $this->getPayloadId($json['payload'] ?? null);
            if ($payloadId > 0) {
                return $payloadId;
            }
        }

        return 0;
    }

    protected function getPayloadId($payload)
    {
        if (is_array($payload) && isset($payload['id'])) {
            return (int) $payload['id'];
        }

        if (is_string($payload) && $payload !== '') {
            $json = json_decode($payload, true);
            if (is_array($json) && isset($json['id'])) {
                return (int) $json['id'];
            }
        }

        return 0;
    }

    public function sentences()
    {
        $videoId = $this->request->param('id');

        $sentences = VideoSentence::where('video_id', $videoId)
            ->order('video_index asc')
            ->select();

        $this->success(200, $sentences);
    }
}
