<?php

namespace app\admin\logic;

use app\admin\model\VideoSentence;

class VideoSentenceLogic
{
    public function replace($videoId, array $segments)
    {
        $rows = [];
        $now = time();

        foreach ($segments as $index => $segment) {
            $rows[] = [
                'video_id' => $videoId,
                'video_index' => $segment['index'] ?? ($index + 1),
                'english_text' => $segment['text'] ?? '',
                'chinese_text' => $segment['translateText'] ?? '',
                'words' => json_encode($segment['words'] ?? [], JSON_UNESCAPED_UNICODE),
                'begin_time' => $segment['begin'] ?? 0,
                'end_time' => $segment['end'] ?? 0,
                'create_time' => $now,
                'update_time' => $now,
            ];
        }

        VideoSentence::where('video_id', $videoId)->delete();

        if (!empty($rows)) {
            VideoSentence::insertAll($rows);
        }

        return count($rows);
    }
}
