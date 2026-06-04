<?php

namespace app\controller;

use think\Response;

class ErrorController
{
    public function index()
    {
        return $this->notFound();
    }

    public function __call($name, $arguments)
    {
        return $this->notFound();
    }

    protected function notFound()
    {
        return Response::create([
            'code' => 'NOT_FOUND',
            'message' => '请求的资源不存在',
        ], 'json', 404);
    }
}
