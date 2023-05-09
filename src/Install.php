<?php
/**
 * @brief myGmaps, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @author Philippe aka amalgame and contributors
 *
 * @copyright GPL-2.0 [https://www.gnu.org/licenses/gpl-2.0.html]
 */
declare(strict_types=1);

namespace Dotclear\Plugin\myGmaps;

use dcCore;
use dcNsProcess;

class Install extends dcNsProcess
{
    public static function init(): bool
    {
        $check = dcCore::app()->newVersion(My::id(), dcCore::app()->plugins->moduleInfo(My::id(), 'version'));

        self::$init = My::checkContext(My::INSTALL);

        return self::$init;
    }

    public static function process(): bool
    {
        if (!self::$init) {
            return false;
        }

        $settings = dcCore::app()->blog->settings->get(My::id());

        $settings->put('myGmaps_enabled', false, 'boolean', 'Enable myGmaps plugin', false, true);
        $settings->put('myGmaps_center', '43.0395797336425, 6.126280043989323', 'string', 'Default maps center', false, true);
        $settings->put('myGmaps_zoom', '12', 'integer', 'Default maps zoom level', false, true);
        $settings->put('myGmaps_type', 'roadmap', 'string', 'Default maps type', false, true);
        $settings->put('myGmaps_API_key', 'AIzaSyCUgB8ZVQD88-T4nSgDlgVtH5fm0XcQAi8', 'string', 'Google Maps browser API key', false, true);

        return true;
    }
}
