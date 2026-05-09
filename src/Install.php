<?php
/**
 * @brief myGmaps, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @author Philippe aka amalgame and contributors
 *
 * @copyright AGPL-3.0
 */

declare(strict_types=1);

namespace Dotclear\Plugin\myGmaps;

use Dotclear\App;
use Dotclear\Helper\Process\TraitProcess;

class Install
{
    use TraitProcess;

    public static function init(): bool
    {
        return self::status(My::checkContext(My::INSTALL));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        $settings = My::settings();

        $settings->put('myGmaps_enabled', false, App::blogWorkspace()::NS_BOOL, 'Enable myGmaps plugin', false, true);
        $settings->put('myGmaps_center', '43.0395797336425, 6.126280043989323', App::blogWorkspace()::NS_STRING, 'Default maps center', false, true);
        $settings->put('myGmaps_zoom', 12, App::blogWorkspace()::NS_INT, 'Default maps zoom level', false, true);
        $settings->put('myGmaps_type', 'roadmap', App::blogWorkspace()::NS_STRING, 'Default maps type', false, true);
        $settings->put('myGmaps_API_key', 'AIzaSyAfIFXVaGwrCrm0Oj2-LGhbqnMoEGtbWC8', App::blogWorkspace()::NS_STRING, 'Google Maps demo API key', false, true);

        return true;
    }
}
