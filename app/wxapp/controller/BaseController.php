<?php

namespace app\wxapp\controller;

use app\wxapp\WxappSend;
use app\common\BaseController as CommonBaseController;

class BaseController extends CommonBaseController
{
    use WxappSend;

    protected $middleware = ['wxapp_api_auth:bound'];

    protected function initialize()
    {
        parent::initialize();
    }
}