<?php

namespace app\customer\controller;

use app\customer\CustomerSend;
use app\common\BaseController as CommonBaseController;

class BaseController extends CommonBaseController
{
    use CustomerSend;

    protected function initialize()
    {
        parent::initialize();
    }
}
