<?php
/**
 * @brief myGmaps, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @author Philippe aka amalgame and contributors
 *
 * @copyright Philippe Hénaff philippe@dissitou.org
 * @copyright GPL-2.0 [https://www.gnu.org/licenses/gpl-2.0.html]
 */

if (!defined('DC_CONTEXT_ADMIN')) {
    return;
}

$edit = '';

if (isset($_REQUEST['do']) &&  $_REQUEST['do'] == 'edit') {
    $edit = 'map';
} elseif (isset($_GET['add_map_filters']) || (isset($_REQUEST['do']) && $_REQUEST['do']!= 'list') || (isset($_GET['post_id']) || isset($_POST['post_id']))) {
    $edit = 'addmap';
} elseif (isset($_POST['action']) || (isset($_GET['do']) && $_GET['do'] == 'list')|| isset($_POST['saveconfig']) || isset($_GET['maps_filters'])) {
    $edit = 'maps';
}

if ($edit == 'map') {
    require_once dirname(__FILE__).'/element.map.php';
} elseif ($edit == 'maps') {
    require_once dirname(__FILE__).'/config.maps.php';
} elseif ($edit == 'addmap') {
    require_once dirname(__FILE__).'/add.map.php';
}
