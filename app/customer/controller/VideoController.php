<?php

namespace app\customer\controller;

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
        $id = $this->request->param('id');
        $video = Video::with(['file'])->where('id', $id)->find();

        if (!$video) {
            $this->error(404, '视频不存在', 'VIDEO_NOT_FOUND');
        }

        $this->success(200, $video);
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
