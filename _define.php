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

if (!defined('DC_RC_PATH')) { return; }

$this->registerModule(
	/* Name */				"Google Maps",
	/* Description*/		"Add custom maps to your blog",
	/* Author */			"Philippe aka amalgame and contributors",
	/* Version */			'5.7.3',
	/* Permissions */		array(
								'permissions' =>	'usage,contentadmin',
								'type' => 'plugin',
								'dc_min' => '2.16',
								'settings'	=>	array(
								'self' => '&do=list#settings')
							)
);
?>
