<?php

namespace app\admin\validate;

use think\Validate;

class Video extends Validate
{
    protected $rule = [
        'title|视频标题' => 'require',
        'file_key|视频文件' => 'require',
    ];
}
