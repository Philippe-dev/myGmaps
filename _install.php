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

if (!defined('DC_CONTEXT_ADMIN')) { exit; }

$m_version = $core->plugins->moduleInfo('myGmaps','version');

$i_version = $core->getVersion('myGmaps');

if (version_compare($i_version,$m_version,'>=')) {
	return;
}

/* Settings
-------------------------------------------------------- */
$core->blog->settings->addNamespace('myGmaps');
$s =& $core->blog->settings->myGmaps;

$s->put('myGmaps_enabled',false,'boolean','Enable myGmaps plugin',false,true);
$s->put('myGmaps_center','43.0395797336425, 6.126280043989323','string','Default maps center',false,true);
$s->put('myGmaps_zoom','12','integer','Default maps zoom level',false,true);
$s->put('myGmaps_type','roadmap','string','Default maps type',false,true);
$s->put('myGmaps_API_key','AIzaSyCUgB8ZVQD88-T4nSgDlgVtH5fm0XcQAi8','string','Google Maps browser API key',false,true);

$core->setVersion('myGmaps',$m_version);

return true;

?>
