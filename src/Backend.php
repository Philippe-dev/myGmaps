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

use Dotclear\Core\Backend\UserPref;
use Dotclear\App;
use Dotclear\Core\Backend\Page;
use Dotclear\Core\Backend\Favorites;
use Dotclear\Core\Process;
use ArrayObject;
use Dotclear\Helper\Stack\Filter;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use form;

class Backend extends Process
{
    public static function init(): bool
    {
        return self::status(My::checkContext(My::BACKEND));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        App::behavior()->addBehaviors([
            'adminDashboardFavoritesV2' => function (Favorites $favs) {
                $favs->register(My::id(), [
                    'title'       => My::name(),
                    'url'         => My::manageUrl(),
                    'small-icon'  => My::icons(),
                    'large-icon'  => My::icons(),
                    'permissions' => App::auth()->makePermissions([
                        App::auth()::PERMISSION_ADMIN,
                    ]),
                ]);
            },
        ]);

        My::addBackendMenuItem(App::backend()->menus()::MENU_BLOG);

        if (My::settings()->myGmaps_enabled) {
            App::behavior()->addBehavior('adminPostListValueV2', [self::class, 'adminEntryListValue']);
            App::behavior()->addBehavior('adminPagesListValueV2', [self::class, 'adminEntryListValue']);
        }

        App::behavior()->addBehavior('adminDashboardFavsIconV2', [self::class, 'dashboardFavsIcon']);

        App::behavior()->addBehavior('adminPageHelpBlock', [self::class, 'adminPageHelpBlock']);
        App::behavior()->addBehavior('adminPageHTTPHeaderCSP', [self::class, 'adminPageHTTPHeaderCSP']);

        App::behavior()->addBehavior('adminPostForm', [self::class,  'adminPostForm']);
        App::behavior()->addBehavior('adminPageForm', [self::class, 'adminPostForm']);
        App::behavior()->addBehavior('adminBeforePostUpdate', [self::class, 'adminBeforePostUpdate']);
        App::behavior()->addBehavior('adminBeforePageUpdate', [self::class, 'adminBeforePostUpdate']);

        App::behavior()->addBehavior('adminPostFilterV2', [self::class,  'adminPostFilter']);
        App::behavior()->addBehavior('adminPostHeaders', [self::class,  'postHeaders']);
        App::behavior()->addBehavior('adminPageHeaders', [self::class,  'postHeaders']);

        (isset($_GET['p']) && $_GET['p'] == 'pages') ? $type = 'page' : $type = 'post';

        if (isset($_GET['remove']) && $_GET['remove'] == 'map') {
            try {
                $post_id = $_GET['id'];
                $meta    = App::meta();
                $meta->delPostMeta($post_id, 'map');
                $meta->delPostMeta($post_id, 'map_options');

                App::blog()->triggerBlog();

                Http::redirect(App::postTypes()->get($type)->adminUrl($post_id, false, ['upd' => 1]));
            } catch (Exception $e) {
                App::error()->add($e->getMessage());
            }
        } elseif (!empty($_GET['remove']) && is_numeric($_GET['remove'])) {
            try {
                $post_id = $_GET['id'];

                $meta = App::meta();
                $meta->delPostMeta($post_id, 'map', $_GET['remove']);

                App::blog()->triggerBlog();

                Http::redirect(App::postTypes()->get($type)->adminUrl($post_id, false, ['upd' => 1]));
            } catch (Exception $e) {
                App::error()->add($e->getMessage());
            }
        } elseif (!empty($_GET['add']) && $_GET['add'] == 'map') {
            try {
                $post_id        = $_GET['id'];
                $myGmaps_center = $_GET['center'];
                $myGmaps_zoom   = $_GET['zoom'];
                $myGmaps_type   = $_GET['type'];

                $meta = App::meta();
                $meta->delPostMeta($post_id, 'map_options');

                $map_options = $myGmaps_center . ',' . $myGmaps_zoom . ',' . $myGmaps_type;
                $meta->setPostMeta($post_id, 'map_options', $map_options);

                App::blog()->triggerBlog();

                Http::redirect(App::postTypes()->get($type)->adminUrl($post_id, false, ['upd' => 1]));
            } catch (Exception $e) {
                App::error()->add($e->getMessage());
            }
        }

        return true;
    }

    public static function dashboardFavsIcon($name, $icon)
    {
        if ($name == My::id()) {
            $params              = new ArrayObject();
            $params['post_type'] = 'map';
            $page_count          = App::blog()->getPosts($params, true)->f(0);
            if ($page_count > 0) {
                $str_pages = ($page_count > 1) ? __('%d map elements') : __('%d map element');
                $icon[0]   = My::name() . '<br>' . sprintf($str_pages, $page_count);
            } else {
                $icon[0] = My::name();
            }
        }
    }

    public static function adminPageHelpBlock($blocks)
    {
        $found = false;
        foreach ($blocks as $block) {
            if ($block == 'core_post') {
                $found = true;

                break;
            }
        }
        if (!$found) {
            return null;
        }
        $blocks[] = 'myGmaps_post';
    }

    public static function adminPageHTTPHeaderCSP($csp)
    {
        if (isset($csp['default-src'])) {
            $csp['default-src'] .= ' fonts.gstatic.com maps.googleapis.com';
        } else {
            $csp['default-src'] = 'fonts.gstatic.com maps.googleapis.com';
        }

        if (isset($csp['script-src'])) {
            $csp['script-src'] .= ' https://*.googleapis.com https://*.gstatic.com *.google.com https://*.ggpht.com *.googleusercontent.com blob:';
        } else {
            $csp['script-src'] = 'https://*.googleapis.com https://*.gstatic.com *.google.com https://*.ggpht.com *.googleusercontent.com blob:';
        }

        if (isset($csp['img-src'])) {
            $csp['img-src'] .= ' https://*.googleapis.com https://*.gstatic.com *.google.com  *.googleusercontent.com data: tile.openstreetmap.org';
        } else {
            $csp['img-src'] = 'https://*.googleapis.com https://*.gstatic.com *.google.com  *.googleusercontent.com data: tile.openstreetmap.org';
        }

        if (isset($csp['frame-src'])) {
            $csp['frame-src'] .= ' *.google.com tile.openstreetmap.org';
        } else {
            $csp['frame-src'] = '*.google.com tile.openstreetmap.org';
        }

        if (isset($csp['style-src'])) {
            $csp['style-src'] .= ' https://fonts.googleapis.com';
        } else {
            $csp['style-src'] = 'https://fonts.googleapis.com';
        }

        if (isset($csp['worker-src'])) {
            $csp['worker-src'] .= ' blob:';
        } else {
            $csp['worker-src'] = 'blob:';
        }
    }

    public static function adminPostForm($post)
    {
        $postTypes = ['post', 'page'];

        if (!My::settings()->myGmaps_enabled) {
            return;
        }
        if (is_null($post) || !in_array($post->post_type, $postTypes)) {
            return;
        }
        $id   = $post->post_id;
        $type = $post->post_type;

        $meta          = App::meta();
        $elements_list = $meta->getMetaStr($post->post_meta, 'map');
        $map_options   = $meta->getMetaStr($post->post_meta, 'map_options');

        // Custom map styles

        $public_path = App::blog()->public_path;
        $public_url  = App::blog()->settings->system->public_url;
        $blog_url    = App::blog()->url;

        $map_styles_dir_path = $public_path . '/myGmaps/styles/';
        $map_styles_dir_url  = http::concatURL(App::blog()->url, $public_url . '/myGmaps/styles/');

        if (is_dir($map_styles_dir_path)) {
            $map_styles      = glob($map_styles_dir_path . '*.js');
            $map_styles_list = [];
            foreach ($map_styles as $map_style) {
                $map_style = basename($map_style);
                array_push($map_styles_list, $map_style);
            }
            $map_styles_list     = implode(',', $map_styles_list);
            $map_styles_base_url = $map_styles_dir_url;
        } else {
            $map_styles_list     = '';
            $map_styles_base_url = '';
        }

        if ($map_options != '') {
            $map_options    = explode(',', $map_options);
            $myGmaps_center = $map_options[0] . ',' . $map_options[1];
            $myGmaps_zoom   = $map_options[2];
            $myGmaps_type   = $map_options[3];
        } else {
            $myGmaps_center = My::settings()->myGmaps_center;
            $myGmaps_zoom   = My::settings()->myGmaps_zoom;
            $myGmaps_type   = My::settings()->myGmaps_type;
        }

        $style_script = '';

        if (is_dir($map_styles_dir_path)) {
            $list = explode(',', $map_styles_list);
            foreach ($list as $map_style) {
                $map_style_content = json_decode(file_get_contents($map_styles_dir_path . '/' . $map_style));
                $var_styles_name   = pathinfo($map_style, PATHINFO_FILENAME);
                $var_name          = preg_replace('/_styles/s', '', $var_styles_name);
                $nice_name         = ucwords(preg_replace('/_/s', ' ', $var_name));
                $style_script .= Page::jsJson($var_name, [
                    'style' => $map_style_content,
                    'name'  => $nice_name,
                ]);
            }
        }

        // redirection URLs

        $addmapurl    = App::postTypes()->get($post->post_type)->adminUrl($post->post_id) . '&add=map&center=' . $myGmaps_center . '&zoom=' . $myGmaps_zoom . '&type=' . $myGmaps_type . '&upd=1';
        $removemapurl = App::postTypes()->get($post->post_type)->adminUrl($post->post_id) . '&remove=map&upd=1';

        if ($post->post_type === 'page') {
            $form_note      = '<span class="form-note">' . __('Map attached to this page.') . '</span>';
            $addmap_message = '<p class="add"><a href="' . $addmapurl . '">' . __('Add a map to page') . '</a></p>';
        } elseif ($post->post_type === 'post') {
            $form_note      = '<span class="form-note">' . __('Map attached to this post.') . '</span>';
            $addmap_message = '<p class="add"><a href="' . $addmapurl . '">' . __('Add a map to post') . '</a></p>';
        }

        if ($elements_list == '' && $map_options == '') {
            echo '<div class="area" id="gmap-area">' .
            '<label class="bold" for="post-gmap">' . __('Google Map:') . '</label>' .
            $form_note .
            '<div id="post-gmap" >' .
            '<p class="elements-list">' . __('No map') . '</p>' .
            $addmap_message .
            '</div>' .
            '</div>';
        } elseif ($elements_list == '' && $map_options != '') {
            echo '<div class="area" id="gmap-area">' .
            '<label class="bold" for="post-gmap">' . __('Google Map:') . '</label>' .
            $form_note .
            '<div id="post-gmap" >' .
            '<div class="map_toolbar"><span class="search">' . __('Search:') . '</span><span class="map_spacer">&nbsp;</span>' .
                '<input size="50" maxlength="255" type="text" id="address" class="qx"><input id="geocode" type="submit" value="' . __('OK') . '">' .
            '</div>' .
            '<p class="area" id="map_canvas"></p>' .
            $style_script .
            '<p class="form-note info maximal mapinfo" style="width: 100%">' . __('Choose map center by dragging map or searching for a location. Choose zoom level and map type with map controls.') . '</p>' .
            '<p>' .
            form::hidden('myGmaps_center', $myGmaps_center) .
            form::hidden('myGmaps_zoom', $myGmaps_zoom) .
            form::hidden('myGmaps_type', $myGmaps_type) .
            form::hidden('map_styles_list', $map_styles_list) .
            form::hidden('map_styles_base_url', $map_styles_base_url) .
            '</p>' .
            '<p class="elements-list">' . __('Empty map') . '</p>' .
            '<ul>' .
            '<li class="add"><a href="' . App::backend()->url()->get('admin.plugin.' . My::id()) . '&act=maps&id=' . $id . '"><strong>' . __('Add elements') . '</strong></a></li>' .
            '<li class="right"><a class="map-remove delete" href="' . $removemapurl . '"><strong>' . __('Remove map') . '</strong></a></li>' .
            '</ul>' .
            '</div>' .
            '</div>';
        } else {
            echo '<div class="area" id="gmap-area">' .
            '<label class="bold" for="post-gmap">' . __('Google Map:') . '</label>' .
            $form_note .
            '<div id="post-gmap" >' .
            '<div class="map_toolbar"><span class="search">' . __('Search:') . '</span><span class="map_spacer">&nbsp;</span>' .
                '<input size="50" maxlength="255" type="text" id="address" class="qx"><input id="geocode" type="submit" value="' . __('OK') . '">' .
            '</div>' .
            '<p class="area" id="map_canvas"></p>' .
            $style_script .
            '<p class="form-note info maximal mapinfo" style="width: 100%">' . __('Choose map center by dragging map or searching for a location. Choose zoom level and map type with map controls.') . '</p>' .
            '<p>' .
            form::hidden('myGmaps_center', $myGmaps_center) .
            form::hidden('myGmaps_zoom', $myGmaps_zoom) .
            form::hidden('myGmaps_type', $myGmaps_type) .
            form::hidden('map_styles_list', $map_styles_list) .
            form::hidden('map_styles_base_url', $map_styles_base_url) .
            '</p>';

            // Get map elements
            try {
                $params['post_id']   = $meta->splitMetaValues($elements_list);
                $params['post_type'] = 'map';
                $posts               = App::blog()->getPosts($params);
                $counter             = App::blog()->getPosts($params, true);
                $post_list           = new BackendMiniList($posts, $counter->f(0));
            } catch (Exception $e) {
                App::error()->add($e->getMessage());
            }

            App::backend()->page        = !empty($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
            App::backend()->nb_per_page = UserPref::getUserFilters('pages', 'nb');

            echo '<div id="form-entries">' .
            '<p class="elements-list">' . __('Included elements list') . '</p>' ;

            $post_list->display(App::backend()->page, App::backend()->nb_per_page, $enclose_block = '', $post->post_id, $post->post_type);

            echo '</div>' .
            '<ul>' .
            '<li class="add"><a href="' . App::backend()->url()->get('admin.plugin.' . My::id()) . '&act=maps&id=' . $id . '"><strong>' . __('Add elements') . '</strong></a></li>' .
            '<li class="right"><a class="map-remove delete" href="' . $removemapurl . '"><strong>' . __('Remove map') . '</strong></a></li>' .
            '</ul>' .
            '</div>' .
            '</div>' . "\n" ;

            // Display map elements on post map
            $script = '<script>' . "\n" .

            'async function initElements(map_add) {' . "\n";

            try {
                $params['post_id']     = $meta->splitMetaValues($elements_list);
                $params['post_type']   = 'map';
                $params['post_status'] = '1';
                $elements              = App::blog()->getPosts($params);
            } catch (Exception $e) {
                App::error()->add($e->getMessage());
            }

            while ($elements->fetch()) {
                $list = explode("\n", html::clean($elements->post_excerpt_xhtml));

                $content = str_replace('\\', '\\\\', $elements->post_content_xhtml);
                $content = str_replace(["\r\n", "\n", "\r"], '\\n', $content);
                $content = str_replace(["'"], "\'", $content);

                $meta        = App::meta();
                $description = $meta->getMetaStr($elements->post_meta, 'description');
                $type        = $meta->getMetaStr($elements->post_meta, 'map');

                if ($description == 'none') {
                    $content = '';
                }
                $aElementOptions = [
                    'map_id'      => 'add',
                    'element_id'  => $elements->post_id,
                    'title'       => html::escapeHTML($elements->post_title),
                    'description' => $content,
                    'type'        => $type,
                ];

                $sElementsTemplate = '';

                if ($type == 'point of interest') {
                    $has_marker = true;
                    $marker     = explode('|', $list[0]);

                    $aElementOptions['position'] = $marker[0] . ',' . $marker[1];
                    $aElementOptions['icon']     = $marker[2];

                    $sElementsTemplate .= FrontendTemplate::getMapElementOptions($aElementOptions);
                } elseif ($type == 'polyline') {
                    $has_poly    = true;
                    $parts       = explode('|', array_pop($list));
                    $coordinates = [];
                    $points      = $list;
                    foreach ($points as $point) {
                        $coord         = explode('|', $point);
                        $coordinates[] = $coord[0] . ',' . $coord[1];
                    }

                    $aElementOptions['coordinates']    = $coordinates;
                    $aElementOptions['stroke_color']   = $parts[2];
                    $aElementOptions['stroke_opacity'] = $parts[1];
                    $aElementOptions['stroke_weight']  = $parts[0];

                    $sElementsTemplate .= FrontendTemplate::getMapElementOptions($aElementOptions);
                } elseif ($type == 'polygon') {
                    $has_poly    = true;
                    $parts       = explode('|', array_pop($list));
                    $coordinates = [];
                    $points      = $list;
                    foreach ($points as $point) {
                        $coord         = explode('|', $point);
                        $coordinates[] = $coord[0] . ',' . $coord[1];
                    }

                    $aElementOptions['coordinates']    = $coordinates;
                    $aElementOptions['stroke_color']   = $parts[2];
                    $aElementOptions['stroke_opacity'] = $parts[1];
                    $aElementOptions['stroke_weight']  = $parts[0];
                    $aElementOptions['fill_color']     = $parts[3];
                    $aElementOptions['fill_opacity']   = $parts[4];

                    $sElementsTemplate .= FrontendTemplate::getMapElementOptions($aElementOptions);
                } elseif ($type == 'rectangle') {
                    $has_poly    = true;
                    $parts       = explode('|', array_pop($list));
                    $coordinates = explode('|', $list[0]);

                    $aElementOptions['bound1']         = $coordinates[0] . ',' . $coordinates[1];
                    $aElementOptions['bound2']         = $coordinates[2] . ',' . $coordinates[3];
                    $aElementOptions['stroke_color']   = $parts[2];
                    $aElementOptions['stroke_opacity'] = $parts[1];
                    $aElementOptions['stroke_weight']  = $parts[0];
                    $aElementOptions['fill_color']     = $parts[3];
                    $aElementOptions['fill_opacity']   = $parts[4];

                    $sElementsTemplate .= FrontendTemplate::getMapElementOptions($aElementOptions);
                } elseif ($type == 'circle') {
                    $has_poly    = true;
                    $parts       = explode('|', array_pop($list));
                    $coordinates = explode('|', $list[0]);

                    $aElementOptions['center']         = $coordinates[0] . ',' . $coordinates[1];
                    $aElementOptions['radius']         = $coordinates[2];
                    $aElementOptions['stroke_color']   = $parts[2];
                    $aElementOptions['stroke_opacity'] = $parts[1];
                    $aElementOptions['stroke_weight']  = $parts[0];
                    $aElementOptions['fill_color']     = $parts[3];
                    $aElementOptions['fill_opacity']   = $parts[4];

                    $sElementsTemplate .= FrontendTemplate::getMapElementOptions($aElementOptions);
                } elseif ($type == 'included kml file' || $type == 'GeoRSS feed') {
                    $layer = html::clean($elements->post_excerpt_xhtml);

                    $aElementOptions['layer'] = $layer;

                    $sElementsTemplate .= FrontendTemplate::getMapElementOptions($aElementOptions);
                } elseif ($type == 'directions') {
                    $has_poly = true;
                    $parts    = explode('|', $list[0]);

                    $aElementOptions['origin']            = $parts[0];
                    $aElementOptions['destination']       = $parts[1];
                    $aElementOptions['stroke_color']      = $parts[4];
                    $aElementOptions['stroke_opacity']    = $parts[3];
                    $aElementOptions['stroke_weight']     = $parts[2];
                    $aElementOptions['display_direction'] = (isset($parts[5]) && $parts[5] == 'true' ? 'true' : 'false');

                    $sElementsTemplate .= FrontendTemplate::getMapElementOptions($aElementOptions);
                }

                $script .= $sElementsTemplate;
            }

            if ($has_poly = true || $has_marker = true) {
                $sOutput = <<<EOT
                    var infowindow_add = new google.maps.InfoWindow({});
                    google.maps.event.addListener(map_add, "click", function (event) {
                        infowindow_add.close();
                    });\n
                    EOT;
                $script .= $sOutput;
            }

            if ($has_poly = true) {
                $sOutput = <<<EOT
                    function openpolyinfowindow(title,content, pos) {
                        infowindow_add.setPosition(pos);
                        infowindow_add.setContent(
                            "<h3>"+title+"</h3>"+
                            "<div class=\"post-infowindow\" id=\"post-infowindow_add\">"+content+"</div>"
                        );
                        infowindow_add.open(map_add);
                        
                    }\n
                    EOT;
                $script .= $sOutput;
            }

            if ($has_marker = true) {
                $sOutput = <<<EOT
                    function openmarkerinfowindow(marker,title,content) {
                        infowindow_add.setContent(
                            "<h3>"+title+"</h3>"+
                            "<div class=\"post-infowindow\" id=\"post-infowindow_add\">"+content+"</div>"
                        );
                        infowindow_add.open(map_add, marker);
                        
                    }\n
                    EOT;
                $script .= $sOutput;
            }

            $script .= '}' .

            '</script>';

            echo $script;
        }
    }

    public static function adminPostFilter(ArrayObject $filters)
    {
        if (App::backend()->getPageURL() === App::backend()->url()->get('admin.plugin.' . My::id())) {
            // Replace default category filter

            $categories = null;

            try {
                $categories = App::blog()->getCategories(['post_type' => 'map', 'without_empty' => true]);
                if ($categories->isEmpty()) {
                    return null;
                }
            } catch (Exception $e) {
                App::error()->add($e->getMessage());

                return null;
            }

            $combo = [
                '-'            => '',
                __('(No cat)') => 'NULL',
            ];
            while ($categories->fetch()) {
                try {
                    $params['no_content'] = true;
                    $params['cat_id']     = $categories->cat_id;
                    $params['post_type']  = 'map';
                    App::blog()->withoutPassword(false);
                    App::backend()->counter = App::blog()->getPosts($params, true);
                } catch (Exception $e) {
                    App::error()->add($e->getMessage());
                }
                $combo[
                    str_repeat('&nbsp;', ($categories->level - 1) * 4) .
                    Html::escapeHTML($categories->cat_title) . ' (' . App::backend()->counter->f(0) . ')'
                ] = $categories->cat_id;
            }

            $filters->append((new Filter('cat_id'))
                ->param()
                ->title(__('Category:'))
                ->options($combo));

            // - Add map type filter

            $element_type = !empty($_GET['element_type']) ? $_GET['element_type'] : '';

            $element_type_combo = [
                '-'                     => '',
                __('none')              => 'notype',
                __('point of interest') => 'point of interest',
                __('polyline')          => 'polyline',
                __('polygon')           => 'polygon',
                __('rectangle')         => 'rectangle',
                __('circle')            => 'circle',
                __('included kml file') => 'included kml file',
                __('GeoRSS feed')       => 'GeoRSS feed',
                __('directions')        => 'directions',
            ];

            $filters->append((new Filter('element_type'))
            ->param('sql', "AND post_meta LIKE '%" . $element_type . "%' ")
            ->title(__('Type:'))
            ->options($element_type_combo));

            // Remove unused filters

            $filters->append((new Filter('comment'))
                ->param());

            $filters->append((new Filter('trackback'))
                ->param());

            $filters->append((new Filter('attachment'))
                ->param());

            $filters->append((new Filter('featuredmedia'))
            ->param());

            $filters->append((new Filter('password'))
            ->param());

            $filters->append((new Filter('lang'))
            ->param());

            $filters->append((new Filter('month'))
            ->param());
        } else {
            // Add map filter on posts list

            $map = !empty($_GET['map']) ? $_GET['map'] : '';

            $map_combo = [
                '-'                        => '',
                __('With attached map')    => 'map_options',
                __('Without attached map') => 'none',
            ];

            $filters->append((new Filter('map'))
            ->param('sql', ($map === 'map_options') ? "AND post_meta LIKE '%" . 'map_options' . "%' " : "AND post_meta NOT LIKE '%" . 'map_options' . "%' ")
            ->title(__('Google Map:'))
            ->options($map_combo)
            ->prime(true));
        }
    }

    public static function adminBeforePostUpdate($cur, $post_id)
    {
        $my_params['post_id']    = $post_id;
        $my_params['no_content'] = true;
        $my_params['post_type']  = ['post', 'page'];

        $rs = App::blog()->getPosts($my_params);

        if (!My::settings()->myGmaps_enabled) {
            return;
        }

        if (isset($_POST['myGmaps_center']) && $_POST['myGmaps_center'] != '') {
            $myGmaps_center = $_POST['myGmaps_center'];
            $myGmaps_zoom   = $_POST['myGmaps_zoom'];
            $myGmaps_type   = $_POST['myGmaps_type'];
            $meta           = App::meta();

            $meta->delPostMeta($post_id, 'map_options');

            $map_options = $myGmaps_center . ',' . $myGmaps_zoom . ',' . $myGmaps_type;
            $meta->setPostMeta($post_id, 'map_options', $map_options);
        }
    }

    public static function postHeaders()
    {
        if (!My::settings()->myGmaps_enabled) {
            return;
        }

        if (isset($_GET['p']) && $_GET['p'] == My::id()) {
            return;
        }

        return
        '<script>' . "\n" .
            '(g=>{var h,a,k,p="The Google Maps JavaScript API",c="google",l="importLibrary",q="__ib__",m=document,b=window;b=b[c]||(b[c]={});var d=b.maps||(b.maps={}),r=new Set,e=new URLSearchParams,u=()=>h||(h=new Promise(async(f,n)=>{await (a=m.createElement("script"));e.set("libraries",[...r]+"");for(k in g)e.set(k.replace(/[A-Z]/g,t=>"_"+t[0].toLowerCase()),g[k]);e.set("callback",c+".maps."+q);a.src=`https://maps.${c}apis.com/maps/api/js?`+e;d[q]=f;a.onerror=()=>h=n(Error(p+" could not load."));a.nonce=m.querySelector("script[nonce]")?.nonce||"";m.head.append(a)}));d[l]?console.warn(p+" only loads once. Ignoring:",g):d[l]=(f,...n)=>r.add(f)&&u().then(()=>d[l](f,...n))})({' . "\n" .
                'key: "' . My::settings()->myGmaps_API_key . '",' . "\n" .
                'v: "weekly",' . "\n" .
            '});' . "\n" .
        '</script>' . "\n" .

        My::jsLoad('add.map.min.js') .

        '<script>' . "\n" .
        'document.addEventListener("DOMContentLoaded", function() {' . "\n" .
            'document.querySelector("#gmap-area label").addEventListener("click", function() {' . "\n" .
            'var postGmap = document.querySelector("#post-gmap");' . "\n" .
            'postGmap.style.display = postGmap.style.display === "none" ? "block" : "none";' . "\n" .
            '});' . "\n" .
            'document.querySelectorAll("a.map-remove").forEach(function(element) {' . "\n" .
            'element.addEventListener("click", function(event) {' . "\n" .
                'var msg = "' . __('Are you sure you want to remove this map?') . '";' . "\n" .
                'if (!window.confirm(msg)) {' . "\n" .
                'event.preventDefault();' . "\n" .
                '}' . "\n" .
            '});' . "\n" .
            '});' . "\n" .
            'document.querySelectorAll("a.element-remove").forEach(function(element) {' . "\n" .
            'element.addEventListener("click", function(event) {' . "\n" .
                'var msg = "' . __('Are you sure you want to remove this element?') . '";' . "\n" .
                'if (!window.confirm(msg)) {' . "\n" .
                'event.preventDefault();' . "\n" .
                '}' . "\n" .
            '});' . "\n" .
            '});' . "\n" .
        '});' . "\n" .
        '</script>' . "\n" .
        My::cssLoad('admin-post.css') . "\n";
    }

    public static function adminEntryListValue($rs, $cols)
    {
        $postTypes = ['post', 'page'];
        $meta      = App::meta();

        if (!empty($meta->getMetaStr($rs->post_meta, 'map_options')) && in_array($rs->post_type, $postTypes)) {
            $img     = '<img alt="%1$s" title="%1$s" src="images/%2$s" class="mark mark-%3$s">';
            $map_img = '<img alt="%1$s" title="%1$s" src="%2$s" class="mark mark-%3$s">';

            $img_status = '';
            $sts_class  = '';
            switch ($rs->post_status) {
                case App::blog()::POST_PUBLISHED:
                    $img_status = sprintf($img, __('Published'), 'check-on.svg', 'published');
                    $sts_class  = 'sts-online';

                    break;
                case App::blog()::POST_UNPUBLISHED:
                    $img_status = sprintf($img, __('Unpublished'), 'check-off.svg', 'unpublished');
                    $sts_class  = 'sts-offline';

                    break;
                case App::blog()::POST_SCHEDULED:
                    $img_status = sprintf($img, __('Scheduled'), 'scheduled.svg', 'scheduled');
                    $sts_class  = 'sts-scheduled';

                    break;
                case App::blog()::POST_PENDING:
                    $img_status = sprintf($img, __('Pending'), 'check-wrn.svg', 'pending');
                    $sts_class  = 'sts-pending';

                    break;
            }

            $protected = '';
            if ($rs->post_password) {
                $protected = sprintf($img, __('Protected'), 'locker.svg', 'locked');
            }

            $selected = '';
            if ($rs->post_selected) {
                $selected = sprintf($img, __('Selected'), 'selected.svg', 'selected');
            }

            $attach   = '';
            $nb_media = $rs->countMedia();
            if ($nb_media > 0) {
                $attach_str = $nb_media == 1 ? __('%d attachment') : __('%d attachments');
                $attach     = sprintf($img, sprintf($attach_str, $nb_media), 'attach.svg', 'attach');
            }

            $map = '';
            $map = sprintf($map_img, __('Attached Map'), Page::getPF(My::id()) . '/icon.svg', 'map');

            $cols['status'] = '<td class="nowrap status">' . $img_status . ' ' . $selected . ' ' . $protected . ' ' . $attach . ' ' . $map . '</td>';
        }
    }
}
