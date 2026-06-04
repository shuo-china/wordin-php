<?php

namespace app\admin\controller;

use app\admin\AdminSend;
use app\common\BaseController as CommonBaseController;

class BaseController extends CommonBaseController
{
    use AdminSend;

    protected $middleware = ['api_auth'];

    protected function initialize()
    {
        parent::initialize();
    }
}