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

        My::settings()->put('myGmaps_enabled', false, 'boolean', 'Enable myGmaps plugin', false, true);
        My::settings()->put('myGmaps_center', '43.0395797336425, 6.126280043989323', 'string', 'Default maps center', false, true);
        My::settings()->put('myGmaps_zoom', '12', 'integer', 'Default maps zoom level', false, true);
        My::settings()->put('myGmaps_type', 'roadmap', 'string', 'Default maps type', false, true);
        My::settings()->put('myGmaps_API_key', 'AIzaSyCUgB8ZVQD88-T4nSgDlgVtH5fm0XcQAi8', 'string', 'Google Maps browser API key', false, true);

        return true;
    }
}
