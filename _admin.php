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

if (!defined('DC_CONTEXT_ADMIN')) {
    return;
}

dcCore::app()->menu[dcAdmin::MENU_BLOG]->addItem(
    __('Google Maps'),
    dcCore::app()->adminurl->get('admin.plugin.myGmaps').'&amp;do=list',
    [dcPage::getPF('myGmaps/icon.svg'), dcPage::getPF('myGmaps/icon-dark.svg')],
    preg_match('/' . preg_quote(dcCore::app()->adminurl->get('admin.plugin.myGmaps')) . '(&.*)?$/', $_SERVER['REQUEST_URI']),
    dcCore::app()->auth->check(dcCore::app()->auth->makePermissions([dcAuth::PERMISSION_CONTENT_ADMIN]), dcCore::app()->blog->id)
);

dcCore::app()->addBehavior('adminDashboardFavs', ['myGmapsBehaviors', 'dashboardFavs']);
dcCore::app()->addBehavior('adminDashboardFavsIcon', ['myGmapsBehaviors', 'dashboardFavsIcon']);
dcCore::app()->addBehavior('adminPageHelpBlock', ['myGmapsBehaviors', 'adminPageHelpBlock']);
dcCore::app()->addBehavior('adminPageHTTPHeaderCSP', ['myGmapsBehaviors', 'adminPageHTTPHeaderCSP']);

Clearbricks::lib()->autoload([
    'adminMapsMiniList' => __DIR__ . '/inc/lib.pager.php',
    'mygmapsPublic' => __DIR__ . '/inc/class.mygmaps.public.php',
]);

class myGmapsBehaviors
{
    public static function adminPageHTTPHeaderCSP($csp)
    {
        if (isset($csp['default-src'])) {
            $csp['default-src'] .= ' fonts.gstatic.com maps.googleapis.com';
        } else {
            $csp['default-src'] = 'fonts.gstatic.com maps.googleapis.com';
        }

        if (isset($csp['script-src'])) {
            $csp['script-src'] .= ' maps.googleapis.com';
        } else {
            $csp['script-src'] = 'maps.googleapis.com';
        }

        if (isset($csp['img-src'])) {
            $csp['img-src'] .= ' *.google.com *.googleusercontent.com *.gstatic.com *.googleapis.com *.ggpht.com tile.openstreetmap.org';
        } else {
            $csp['img-src'] = '*.google.com *.googleusercontent.com *.gstatic.com *.googleapis.com *.ggpht.com tile.openstreetmap.org';
        }

        if (isset($csp['style-src'])) {
            $csp['style-src'] .= ' fonts.googleapis.com';
        } else {
            $csp['style-src'] = 'fonts.googleapis.com';
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

    public static function dashboardFavs($core, $favs)
    {
        $favs['myGmaps'] = new ArrayObject([
            'myGmaps',
            __('Google Maps'),
            'plugin.php?p=myGmaps&amp;do=list',
            [dcPage::getPF('myGmaps/icon.svg'), dcPage::getPF('myGmaps/icon-dark.svg')],
            [dcPage::getPF('myGmaps/icon.svg'), dcPage::getPF('myGmaps/icon-dark.svg')],
            dcCore::app()->auth->makePermissions([dcAuth::PERMISSION_CONTENT_ADMIN]),
            null,
            null]);
    }

    public static function dashboardFavsIcon($core, $name, $icon)
    {
        if ($name == 'myGmaps') {
            $params = new ArrayObject();
            $params['post_type'] = 'map';
            $page_count = dcCore::app()->blog->getPosts($params, true)->f(0);
            if ($page_count > 0) {
                $str_pages = ($page_count > 1) ? __('%d map elements') : __('%d map element');
                $icon[0] = __('Google Maps') . '<br />' . sprintf($str_pages, $page_count);
            } else {
                $icon[0] = __('Google Maps');
            }
        }
    }
}



(isset($_GET['p']) && $_GET['p'] == 'pages') ? $type = 'page' : $type = 'post';

if (isset($_GET['remove']) && $_GET['remove'] == 'map') {
    try {
        $post_id = $_GET['id'];
        $meta = dcCore::app()->meta;
        $meta->delPostMeta($post_id, 'map');
        $meta->delPostMeta($post_id, 'map_options');

        dcCore::app()->blog->triggerBlog();

        if ($type == 'page') {
            http::redirect(DC_ADMIN_URL . 'plugin.php?p=pages&act=page&id=' . $post_id . '&upd=1#gmap-area');
        } elseif ($type == 'post') {
            http::redirect(DC_ADMIN_URL . 'post.php?id=' . $post_id . '&upd=1#gmap-area');
        }
    } catch (Exception $e) {
        dcCore::app()->error->add($e->getMessage());
    }
} elseif (!empty($_GET['remove']) && is_numeric($_GET['remove'])) {
    try {
        $post_id = $_GET['id'];

        $meta = dcCore::app()->meta;
        $meta->delPostMeta($post_id, 'map', (int) $_GET['remove']);

        dcCore::app()->blog->triggerBlog();

        if ($type == 'page') {
            http::redirect(DC_ADMIN_URL . 'plugin.php?p=pages&act=page&id=' . $post_id . '&upd=1#gmap-area');
        } elseif ($type == 'post') {
            http::redirect(DC_ADMIN_URL . 'post.php?id=' . $post_id . '&upd=1#gmap-area');
        }
    } catch (Exception $e) {
        dcCore::app()->error->add($e->getMessage());
    }
} elseif (!empty($_GET['add']) && $_GET['add'] == 'map') {
    try {
        $post_id = $_GET['id'];
        $myGmaps_center = $_GET['center'];
        $myGmaps_zoom = $_GET['zoom'];
        $myGmaps_type = $_GET['type'];

        $meta = dcCore::app()->meta;
        $meta->delPostMeta($post_id, 'map_options');

        $map_options = $myGmaps_center . ',' . $myGmaps_zoom . ',' . $myGmaps_type;
        $meta->setPostMeta($post_id, 'map_options', $map_options);

        dcCore::app()->blog->triggerBlog();

        if ($type == 'page') {
            http::redirect(DC_ADMIN_URL . 'plugin.php?p=pages&act=page&id=' . $post_id . '&upd=1#gmap-area');
        } elseif ($type == 'post') {
            http::redirect(DC_ADMIN_URL . 'post.php?id=' . $post_id . '&upd=1#gmap-area');
        }
    } catch (Exception $e) {
        dcCore::app()->error->add($e->getMessage());
    }
}

dcCore::app()->addBehavior('adminPostHeaders', ['myGmapsPostBehaviors', 'postHeaders']);
dcCore::app()->addBehavior('adminPageHeaders', ['myGmapsPostBehaviors', 'postHeaders']);
dcCore::app()->addBehavior('adminPostFormItems', ['myGmapsPostBehaviors', 'adminPostFormItems']);
dcCore::app()->addBehavior('adminPageFormItems', ['myGmapsPostBehaviors', 'adminPostFormItems']);
dcCore::app()->addBehavior('adminBeforePostUpdate', ['myGmapsPostBehaviors', 'adminBeforePostUpdate']);
dcCore::app()->addBehavior('adminBeforePageUpdate', ['myGmapsPostBehaviors', 'adminBeforePostUpdate']);

class myGmapsPostBehaviors
{
    public static function postHeaders()
    {
        $s = dcCore::app()->blog->settings->myGmaps;

        if (!$s->myGmaps_enabled) {
            return;
        }

        return
        '<script src="https://maps.googleapis.com/maps/api/js?key=' . $s->myGmaps_API_key . '&amp;libraries=places"></script>' . "\n" .
        '<script>' . "\n" .
        '$(document).ready(function() {' . "\n" .
            '$(\'#gmap-area label\').toggleWithLegend($(\'#post-gmap\'), {' . "\n" .
                'legend_click: true,' . "\n" .
                'cookie: \'dcx_gmap_detail\'' . "\n" .
            '})' . "\n" .
            '$(\'a.map-remove\').on(\'click\', function() {' . "\n" .
            'msg = \'' . __('Are you sure you want to remove this map?') . '\';' . "\n" .
            'if (!window.confirm(msg)) {' . "\n" .
                'return false;' . "\n" .
            '}' . "\n" .
            '});' . "\n" .
            '$(\'a.element-remove\').on(\'click\', function() {' . "\n" .
            'msg = \'' . __('Are you sure you want to remove this element?') . '\';' . "\n" .
            'if (!window.confirm(msg)) {' . "\n" .
                'return false;' . "\n" .
            '}' . "\n" .
            '});' . "\n" .
        '});' . "\n" .
        '</script>' . "\n" .
        '<link rel="stylesheet" type="text/css" href="index.php?pf=myGmaps/css/admin.css" />' . "\n";
    }

    public static function adminBeforePostUpdate($cur, $post_id)
    {
        $s = dcCore::app()->blog->settings->myGmaps;

        $my_params['post_id'] = $post_id;
        $my_params['no_content'] = true;
        $my_params['post_type'] = ['post', 'page'];

        $rs = dcCore::app()->blog->getPosts($my_params);

        if (!$s->myGmaps_enabled) {
            return;
        }

        if (isset($_POST['myGmaps_center']) && $_POST['myGmaps_center'] != '') {
            $myGmaps_center = $_POST['myGmaps_center'];
            $myGmaps_zoom = $_POST['myGmaps_zoom'];
            $myGmaps_type = $_POST['myGmaps_type'];
            $meta = dcCore::app()->meta;

            $meta->delPostMeta($post_id, 'map_options');

            $map_options = $myGmaps_center . ',' . $myGmaps_zoom . ',' . $myGmaps_type;
            $meta->setPostMeta($post_id, 'map_options', $map_options);
        }
    }

    public static function adminPostFormItems($main_items, $sidebar_items, $post)
    {
        $s = dcCore::app()->blog->settings->myGmaps;
        $postTypes = ['post', 'page'];

        if (!$s->myGmaps_enabled) {
            return;
        }
        if (is_null($post) || !in_array($post->post_type, $postTypes)) {
            return;
        }
        $id = $post->post_id;
        $type = $post->post_type;

        $meta = dcCore::app()->meta;
        $elements_list = $meta->getMetaStr($post->post_meta, 'map');
        $map_options = $meta->getMetaStr($post->post_meta, 'map_options');

        // Custom map styles

        $public_path = dcCore::app()->blog->public_path;
        $public_url = dcCore::app()->blog->settings->system->public_url;
        $blog_url = dcCore::app()->blog->url;

        $map_styles_dir_path = $public_path . '/myGmaps/styles/';
        $map_styles_dir_url = http::concatURL(dcCore::app()->blog->url, $public_url . '/myGmaps/styles/');

        if (is_dir($map_styles_dir_path)) {
            $map_styles = glob($map_styles_dir_path . '*.js');
            $map_styles_list = [];
            foreach ($map_styles as $map_style) {
                $map_style = basename($map_style);
                array_push($map_styles_list, $map_style);
            }
            $map_styles_list = implode(',', $map_styles_list);
            $map_styles_base_url = $map_styles_dir_url;
        } else {
            $map_styles_list = '';
            $map_styles_base_url = '';
        }

        if ($map_options != '') {
            $map_options = explode(',', $map_options);
            $myGmaps_center = $map_options[0] . ',' . $map_options[1];
            $myGmaps_zoom = $map_options[2];
            $myGmaps_type = $map_options[3];
        } else {
            $myGmaps_center = $s->myGmaps_center;
            $myGmaps_zoom = $s->myGmaps_zoom;
            $myGmaps_type = $s->myGmaps_type;
        }

        $map_js =
        '<script src="' . DC_ADMIN_URL . '?pf=myGmaps/js/add.map.js"></script>' .
        '<script>' . "\n" .
        '//<![CDATA[' . "\n" .
        'var neutral_blue_styles = [{"featureType":"water","elementType":"geometry","stylers":[{"color":"#193341"}]},{"featureType":"landscape","elementType":"geometry","stylers":[{"color":"#2c5a71"}]},{"featureType":"road","elementType":"geometry","stylers":[{"color":"#29768a"},{"lightness":-37}]},{"featureType":"poi","elementType":"geometry","stylers":[{"color":"#406d80"}]},{"featureType":"transit","elementType":"geometry","stylers":[{"color":"#406d80"}]},{"elementType":"labels.text.stroke","stylers":[{"visibility":"on"},{"color":"#3e606f"},{"weight":2},{"gamma":0.84}]},{"elementType":"labels.text.fill","stylers":[{"color":"#ffffff"}]},{"featureType":"administrative","elementType":"geometry","stylers":[{"weight":0.6},{"color":"#1a3541"}]},{"elementType":"labels.icon","stylers":[{"visibility":"off"}]},{"featureType":"poi.park","elementType":"geometry","stylers":[{"color":"#2c5a71"}]}];' . "\n" .
        'var neutral_blue = new google.maps.StyledMapType(neutral_blue_styles,{name: "Neutral Blue"});' . "\n";

        if (is_dir($map_styles_dir_path)) {
            $list = explode(',', $map_styles_list);
            foreach ($list as $map_style) {
                $map_style_content = file_get_contents($map_styles_dir_path . '/' . $map_style);
                $var_styles_name = pathinfo($map_style, PATHINFO_FILENAME);
                $var_name = preg_replace('/_styles/s', '', $var_styles_name);
                $nice_name = ucwords(preg_replace('/_/s', ' ', $var_name));
                $map_js .=
                'var ' . $var_styles_name . ' = ' . $map_style_content . ';' . "\n" .
                'var ' . $var_name . ' = new google.maps.StyledMapType(' . $var_styles_name . ',{name: "' . $nice_name . '"});' . "\n";
            }
        }

        $map_js .=
        '//]]>' . "\n" .
        '</script>';

        // redirection URLs
        if ($type == 'page') {
            $addmapurl = DC_ADMIN_URL . 'plugin.php?p=pages&amp;act=page&amp;id=' . $id . '&amp;add=map&amp;center=' . $myGmaps_center . '&amp;zoom=' . $myGmaps_zoom . '&amp;type=' . $myGmaps_type . '&amp;upd=1';
            $removemapurl = DC_ADMIN_URL . 'plugin.php?p=pages&amp;act=page&amp;id=' . $id . '&amp;remove=map&amp;upd=1';
        } elseif ($type == 'post') {
            $addmapurl = DC_ADMIN_URL . 'post.php?id=' . $id . '&amp;add=map&amp;center=' . $myGmaps_center . '&amp;zoom=' . $myGmaps_zoom . '&amp;type=' . $myGmaps_type . '&amp;upd=1';
            $removemapurl = DC_ADMIN_URL . 'post.php?id=' . $id . '&amp;remove=map&amp;upd=1';
        }

        if ($elements_list == '' && $map_options == '') {
            $item =
            '<div class="area" id="gmap-area">' .
            '<p class="smart-title">' . __('Google Map:') . '</p>' .
            '<div id="post-gmap" >' .
            '<p>' . __('No map') . '</p>' .
            '<p><a href="' . $addmapurl . '">' . __('Add a map to entry') . '</a></p>' .
            '</div>' .
            '</div>';
        } elseif ($elements_list == '' && $map_options != '') {
            $item =
            '<div class="area" id="gmap-area">' .
            '<p class="smart-title">' . __('Google Map:') . '</p>' .
            '<div id="post-gmap" >' .
            '<div class="map_toolbar">' . __('Search:') . '<span class="map_spacer">&nbsp;</span>' .
                '<input size="50" maxlength="255" type="text" id="address" class="qx" /><input id="geocode" type="submit" value="' . __('OK') . '" />' .
            '</div>' .
            '<p class="area" id="map_canvas"></p>' .
            $map_js .
            '<p class="form-note info maximal mapinfo" style="width: 100%">' . __('Choose map center by dragging map or searching for a location. Choose zoom level and map type with map controls.') . '</p>' .
            '<p><input type="hidden" name="myGmaps_center" value="' . $myGmaps_center . '" />' .
            '<input type="hidden" name="myGmaps_zoom" value="' . $myGmaps_zoom . '" />' .
            '<input type="hidden" name="myGmaps_type" value="' . $myGmaps_type . '" />' .
            '<input type="hidden" name="map_styles_list" id="map_styles_list" value="' . $map_styles_list . '" />' .
            '<input type="hidden" name="map_styles_base_url" id="map_styles_base_url" value="' . $map_styles_base_url . '" /></p>' .
            '<p>' . __('Empty map') . '</p>' .
            '<p class="two-boxes"><a href="plugin.php?p=myGmaps&amp;post_id=' . $id . '"><strong>' . __('Add elements') . '</strong></a></p>' .
            '<p class="two-boxes right"><a class="map-remove delete" href="' . $removemapurl . '"><strong>' . __('Remove map') . '</strong></a></p>' .
            '</div>' .
            '</div>';
        } else {
            $item =
            '<div class="area" id="gmap-area">' .
            '<p class="smart-title">' . __('Google Map:') . '</p>' .
            '<div id="post-gmap" >' .
            '<div class="map_toolbar">' . __('Search:') . '<span class="map_spacer">&nbsp;</span>' .
                '<input size="50" maxlength="255" type="text" id="address" class="qx" /><input id="geocode" type="submit" value="' . __('OK') . '" />' .
            '</div>' .
            '<p class="area" id="map_canvas"></p>' .
            $map_js .
            '<p class="form-note info maximal mapinfo" style="width: 100%">' . __('Choose map center by dragging map or searching for a location. Choose zoom level and map type with map controls.') . '</p>' .
            '<p><input type="hidden" name="myGmaps_center" value="' . $myGmaps_center . '" />' .
            '<input type="hidden" name="myGmaps_zoom" value="' . $myGmaps_zoom . '" />' .
            '<input type="hidden" name="myGmaps_type" value="' . $myGmaps_type . '" />' .
            '<input type="hidden" name="map_styles_list" id="map_styles_list" value="' . $map_styles_list . '" />' .
            '<input type="hidden" name="map_styles_base_url" id="map_styles_base_url" value="' . $map_styles_base_url . '" /></p>';

            // Get map elements
            try {
                $params['post_id'] = $meta->splitMetaValues($elements_list);
                $params['post_type'] = 'map';
                $posts = dcCore::app()->blog->getPosts($params);
                $counter = dcCore::app()->blog->getPosts($params, true);
                $post_list = new adminMapsMiniList(dcCore::app(), $posts, $counter->f(0));
            } catch (Exception $e) {
                dcCore::app()->error->add($e->getMessage());
            }
            $page = '1';
            $nb_per_page = '30';

            $item .=
            '<div id="form-entries">' .
            '<p>' . __('Included elements list') . '</p>' .
            $post_list->display($page, $nb_per_page, $enclose_block = '', $id, $type) .
            '</div>' .
            '<p class="two-boxes"><a href="' . DC_ADMIN_URL . 'plugin.php?p=myGmaps&amp;post_id=' . $id . '"><strong>' . __('Add elements') . '</strong></a></p>' .
            '<p class="two-boxes right"><a class="map-remove delete" href="' . DC_ADMIN_URL . 'post.php?id=' . $id . '&amp;remove=map"><strong>' . __('Remove map') . '</strong></a></p>' .
            '</div>' .
            '</div>';

            // Display map elements on post map
            $item .=
            '<script>' . "\n" .
            '//<![CDATA[' . "\n" .
            '$(document).ready(function() {' . "\n";

            try {
                $params['post_id'] = $meta->splitMetaValues($elements_list);
                $params['post_type'] = 'map';
                $params['post_status'] = '1';
                $elements = dcCore::app()->blog->getPosts($params);
            } catch (Exception $e) {
                dcCore::app()->error->add($e->getMessage());
            }

            while ($elements->fetch()) {
                $list = explode("\n", html::clean($elements->post_excerpt_xhtml));

                $content = str_replace('\\', '\\\\', $elements->post_content_xhtml);
                $content = str_replace(["\r\n", "\n", "\r"], '\\n', $content);
                $content = str_replace(["'"], "\'", $content);

                $meta = dcCore::app()->meta;
                $description = $meta->getMetaStr($elements->post_meta, 'description');
                $type = $meta->getMetaStr($elements->post_meta, 'map');

                if ($description == 'none') {
                    $content = '';
                }
                $aElementOptions = [
                    'map_id' => 'add',
                    'element_id' => $elements->post_id,
                    'title' => html::escapeHTML($elements->post_title),
                    'description' => $content,
                    'type' => $type
                ];

                $sElementsTemplate = '';

                if ($type == 'point of interest') {
                    $has_marker = true;
                    $marker = explode('|', $list[0]);

                    $aElementOptions['position'] = $marker[0] . ',' . $marker[1];
                    $aElementOptions['icon'] = $marker[2];

                    $sElementsTemplate .= mygmapsPublic::getMapElementOptions($aElementOptions);
                } elseif ($type == 'polyline') {
                    $has_poly = true;
                    $parts = explode('|', array_pop($list));
                    $coordinates = [];
                    $points = $list;
                    foreach ($points as $point) {
                        $coord = explode('|', $point);
                        $coordinates[] = $coord[0] . ',' . $coord[1];
                    }

                    $aElementOptions['coordinates'] = $coordinates;
                    $aElementOptions['stroke_color'] = $parts[2];
                    $aElementOptions['stroke_opacity'] = $parts[1];
                    $aElementOptions['stroke_weight'] = $parts[0];

                    $sElementsTemplate .= mygmapsPublic::getMapElementOptions($aElementOptions);
                } elseif ($type == 'polygon') {
                    $has_poly = true;
                    $parts = explode('|', array_pop($list));
                    $coordinates = [];
                    $points = $list;
                    foreach ($points as $point) {
                        $coord = explode('|', $point);
                        $coordinates[] = $coord[0] . ',' . $coord[1];
                    }

                    $aElementOptions['coordinates'] = $coordinates;
                    $aElementOptions['stroke_color'] = $parts[2];
                    $aElementOptions['stroke_opacity'] = $parts[1];
                    $aElementOptions['stroke_weight'] = $parts[0];
                    $aElementOptions['fill_color'] = $parts[3];
                    $aElementOptions['fill_opacity'] = $parts[4];

                    $sElementsTemplate .= mygmapsPublic::getMapElementOptions($aElementOptions);
                } elseif ($type == 'rectangle') {
                    $has_poly = true;
                    $parts = explode('|', array_pop($list));
                    $coordinates = explode('|', $list[0]);

                    $aElementOptions['bound1'] = $coordinates[0] . ',' . $coordinates[1];
                    $aElementOptions['bound2'] = $coordinates[2] . ',' . $coordinates[3];
                    $aElementOptions['stroke_color'] = $parts[2];
                    $aElementOptions['stroke_opacity'] = $parts[1];
                    $aElementOptions['stroke_weight'] = $parts[0];
                    $aElementOptions['fill_color'] = $parts[3];
                    $aElementOptions['fill_opacity'] = $parts[4];

                    $sElementsTemplate .= mygmapsPublic::getMapElementOptions($aElementOptions);
                } elseif ($type == 'circle') {
                    $has_poly = true;
                    $parts = explode('|', array_pop($list));
                    $coordinates = explode('|', $list[0]);

                    $aElementOptions['center'] = $coordinates[0] . ',' . $coordinates[1];
                    $aElementOptions['radius'] = $coordinates[2];
                    $aElementOptions['stroke_color'] = $parts[2];
                    $aElementOptions['stroke_opacity'] = $parts[1];
                    $aElementOptions['stroke_weight'] = $parts[0];
                    $aElementOptions['fill_color'] = $parts[3];
                    $aElementOptions['fill_opacity'] = $parts[4];

                    $sElementsTemplate .= mygmapsPublic::getMapElementOptions($aElementOptions);
                } elseif ($type == 'included kml file' || $type == 'GeoRSS feed') {
                    $layer = html::clean($elements->post_excerpt_xhtml);

                    $aElementOptions['layer'] = $layer;

                    $sElementsTemplate .= mygmapsPublic::getMapElementOptions($aElementOptions);
                } elseif ($type == 'directions') {
                    $has_poly = true;
                    $parts = explode('|', $list[0]);

                    $aElementOptions['origin'] = $parts[0];
                    $aElementOptions['destination'] = $parts[1];
                    $aElementOptions['stroke_color'] = $parts[4];
                    $aElementOptions['stroke_opacity'] = $parts[3];
                    $aElementOptions['stroke_weight'] = $parts[2];
                    $aElementOptions['display_direction'] = (isset($parts[5]) && $parts[5] == 'true' ? 'true' : 'false');

                    $sElementsTemplate .= mygmapsPublic::getMapElementOptions($aElementOptions);
                }

                $item .= $sElementsTemplate;
            }

            if ($has_poly = true || $has_marker = true) {
                $sOutput = <<<EOT
var infowindow_add = new google.maps.InfoWindow({});
google.maps.event.addListener(map_add, "click", function (event) {
    infowindow_add.close();
});\n
EOT;
                $item .= $sOutput;
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
    $("#post-infowindow_add").parent("div", "div#map_canvas_add").css("overflow","hidden");
}\n
EOT;
                $item .= $sOutput;
            }

            if ($has_marker = true) {
                $sOutput = <<<EOT
function openmarkerinfowindow(marker,title,content) {
    infowindow_add.setContent(
        "<h3>"+title+"</h3>"+
        "<div class=\"post-infowindow\" id=\"post-infowindow_add\">"+content+"</div>"
    );
    infowindow_add.open(map_add, marker);
    $("#post-infowindow_add").parent("div", "div#map_canvas_add").css("overflow","hidden");
}\n
EOT;
                $item .= $sOutput;
            }

            $item .=
            '});' . "\n" .
            '//]]>' . "\n" .
            '</script>';
        }

        $main_items['gmap-area'] = $item;
    }
}
