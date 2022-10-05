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

if (!isset(dcCore::app()->resources['help']['myGmaps'])) {
    dcCore::app()->resources['help']['myGmaps'] = dirname(__FILE__) . '/help/maps.html';
}
if (!isset(dcCore::app()->resources['help']['myGmap'])) {
    dcCore::app()->resources['help']['myGmap'] = dirname(__FILE__) . '/help/map.html';
}
if (!isset(dcCore::app()->resources['help']['myGmapsadd'])) {
    dcCore::app()->resources['help']['myGmapsadd'] = dirname(__FILE__) . '/help/addmap.html';
}
if (!isset(dcCore::app()->resources['help']['myGmaps_post'])) {
    dcCore::app()->resources['help']['myGmaps_post'] = dirname(__FILE__) . '/help/myGmaps_post.html';
}
