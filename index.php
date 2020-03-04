<?php
# -- BEGIN LICENSE BLOCK ----------------------------------
#
# This file is part of myGmaps, a plugin for Dotclear 2.
#
# Copyright (c) 2014 - 2018 Philippe aka amalgame and contributors
# Licensed under the GPL version 2.0 license.
# See LICENSE file or
# http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
#
# -- END LICENSE BLOCK ------------------------------------

if (!defined('DC_CONTEXT_ADMIN')) return;

if ($_SERVER['HTTP_REFERER'] == DC_ADMIN_URL.'plugins.php') {
	require_once dirname(__FILE__).'/config.maps.php';
}

$edit = '';

if (isset($_REQUEST['do']) &&  $_REQUEST['do'] == 'edit') {
	$edit = 'map';

} elseif (isset($_GET['add_map_filters']) || (isset($_REQUEST['do']) && $_REQUEST['do']!= 'list') || (isset($_GET['post_id']) || isset($_POST['post_id']))) {
	$edit = 'addmap';

} elseif (isset($_POST['action']) || (isset($_GET['do']) && $_GET['do'] == 'list' )|| isset($_POST['saveconfig']) || isset($_GET['maps_filters'])) {
	$edit = 'maps';
}

if ($edit == 'map') {
	require_once dirname(__FILE__).'/element.map.php';

} elseif ($edit == 'maps') {
	require_once dirname(__FILE__).'/config.maps.php';

} elseif ($edit == 'addmap') {
	require_once dirname(__FILE__).'/add.map.php';
}
?>
