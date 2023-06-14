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
use Dotclear\Module\MyPlugin;

class My extends MyPlugin
{
    /**
     * Current admin page url
     */
    public static function url(): string
    {
        return dcCore::app()->admin->getPageURL();
    }
}
