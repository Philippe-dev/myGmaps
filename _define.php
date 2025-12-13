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
    '10.8.1',
    [
        'date'        => '2025-10-01T00:00:08+0100',
        'requires'    => [['core', '2.36']],
        'permissions' => 'My',
        'type'        => 'plugin',
        'support'     => 'https://github.com/Philippe-dev/myGmaps',
    ]
);
