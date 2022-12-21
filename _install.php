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
if (!defined('DC_CONTEXT_ADMIN')) {
    exit;
}

if (!dcCore::app()->newVersion(basename(__DIR__), dcCore::app()->plugins->moduleInfo(basename(__DIR__), 'version'))) {
    return;
}

try {
    dcCore::app()->blog->settings->addNamespace('myGmaps');
    $s = dcCore::app()->blog->settings->myGmaps;

    $s->put('myGmaps_enabled', false, 'boolean', 'Enable myGmaps plugin', false, true);
    $s->put('myGmaps_center', '43.0395797336425, 6.126280043989323', 'string', 'Default maps center', false, true);
    $s->put('myGmaps_zoom', '12', 'integer', 'Default maps zoom level', false, true);
    $s->put('myGmaps_type', 'roadmap', 'string', 'Default maps type', false, true);
    $s->put('myGmaps_API_key', 'AIzaSyCUgB8ZVQD88-T4nSgDlgVtH5fm0XcQAi8', 'string', 'Google Maps browser API key', false, true);

    return true;
} catch (Exception $e) {
    dcCore::app()->error->add($e->getMessage());
}

return false;
