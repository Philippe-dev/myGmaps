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

if (!defined('DC_RC_PATH')) {
    return;
}

$this->registerModule(
    'Google Maps',           					// Name
    'Add custom maps to your blog', 			// Description
    'Philippe aka amalgame and contributors',   // Author
    '5.9.2',                   					// Version
    [
        'requires' => [['core', '2.23']],   	// Dependencies
        'permissions' => 'usage,contentadmin', 	// Permissions
        'type' => 'plugin',             	    // Type
        'priority' => 2000,                 	// Priority
        'settings' => ['self' => '&do=list#settings']
    ]
);
