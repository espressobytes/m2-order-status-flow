<?php

namespace Espressobytes\OrderStatusFlow\Helper;

class Config
{

    /**
     * @return bool
     */
    public function isStatusReplacementEnabled()
    {
        return true;
    }

    /**
     * @return bool
     */
    public function isStatusChangeCommentEnabled() {
        return true;
    }

}
