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

declare(strict_types=1);

namespace Dotclear\Plugin\myGmaps;

use Dotclear\App;

App::backend()->resources()->set('help', 'myGmaps', __DIR__ . '/help/maps.html');
App::backend()->resources()->set('help', 'myGmap', __DIR__ . '/help/map.html');
App::backend()->resources()->set('help', 'myGmapsadd', __DIR__ . '/help/addmap.html');
App::backend()->resources()->set('help', 'myGmaps_post', __DIR__ . '/help/myGmaps_post.html');


