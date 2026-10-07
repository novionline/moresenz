<?php

namespace CookieConfirm;

use CookieConfirm\Controllers\SettingsController;
use CookieConfirm\Modules\AdminAssets;
use CookieConfirm\Modules\ConsentManager;
use CookieConfirm\Modules\FrontendAssets;
use CookieConfirm\Modules\OutputModifier;
use CookieConfirm\Modules\ScriptModifier;

class CookieConfirm
{
    public function __construct()
    {
        new AdminAssets();
        new SettingsController();

        new ConsentManager();
        new FrontendAssets();
        new ScriptModifier();
        new OutputModifier();
    }
}