<?php

namespace app\admin\model;

class VideoSentence extends BaseModel
{
    protected $name = 'video_sentences';

    protected $type = [
        'id' => 'integer',
        'video_id' => 'integer',
        'video_index' => 'integer',
        'begin_time' => 'integer',
        'end_time' => 'integer',
        'words' => 'json',
        'create_time' => 'timestamp',
        'update_time' => 'timestamp',
    ];
}
