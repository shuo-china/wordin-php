<?php

namespace app\customer\controller;

use Throwable;
use app\customer\logic\YoudaoLogic;

class WordController extends BaseController
{
    public function translate()
    {
        $word = trim((string) $this->request->param('word', ''));

        if ($word === '') {
            $this->error(400, '单词不能为空', 'WORD_REQUIRED');
        }

        try {
            $result = (new YoudaoLogic())->translate($word);
        } catch (Throwable $e) {
            $this->error(500, $e->getMessage(), 'YOUDAO_TRANSLATE_FAILED');
        }

        $this->success(200, $result);
    }
}
