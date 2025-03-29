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
$this->registerModule(
    'Maps',
    'Add custom maps to your blog',
    'Philippe aka amalgame and contributors',
    '9.3',
    [
        'date'        => '2025-03-27T00:00:13+0100',
        'requires'    => [['core', '2.33']],
        'permissions' => 'My',
        'type'        => 'plugin',
        'support'     => 'https://github.com/Philippe-dev/myGmaps',
    ]
);
