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
$this->registerModule(
    'Maps',
    'Add custom maps to your blog',
    'Philippe aka amalgame and contributors',
    '9.6',
    [
        'date'        => '2025-06-09T00:00:13+0100',
        'requires'    => [['core', '2.33']],
        'permissions' => 'My',
        'type'        => 'plugin',
        'support'     => 'https://github.com/Philippe-dev/myGmaps',
    ]
);
