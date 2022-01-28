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

require_once(dirname(__FILE__).'/inc/class.mygmaps.public.php');

$core->addBehavior('publicEntryAfterContent', array('dcMyGmapsPublic','publicMapContent'));
$core->addBehavior('publicPageAfterContent', array('dcMyGmapsPublic','publicMapContent'));
$core->addBehavior('publicHeadContent', array('dcMyGmapsPublic','publicHeadContent'));

$core->tpl->addValue('myGmaps', array('dcMyGmapsPublic','publicTagMapContent'));

class dcMyGmapsPublic
{
    public static function hasMap($post_id)
    {
        global $core;
        $meta =& $core->meta;
        $my_params['post_id'] = $post_id;
        $my_params['no_content'] = true;
        $my_params['post_type'] = ['post','page'];

        $rs = $core->blog->getPosts($my_params);
        return $meta->getMetaStr($rs->post_meta, 'map_options');
    }
    public static function thisPostMap($post_id)
    {
        global $core;
        $meta =& $core->meta;
        $my_params['post_id'] = $post_id;
        $my_params['no_content'] = true;
        $my_params['post_type'] = ['post','page'];

        $rs = $core->blog->getPosts($my_params);
        return $meta->getMetaStr($rs->post_meta, 'map');
    }
    /**
     *
     * @param array $aParams ['ids' => array(), 'categories' => array()]
     */
    public static function getMapElements($aParams = array())
    {
        global $core;

        $rs = array();

        $my_params = array();
        $my_params['post_type'] = ['map'];
        $my_params['post_status'] = '1';

        // Récupérer tous les éléments de cartes selon leurs ids
        if (array_key_exists('ids', $aParams) && ! empty($aParams['ids'])) {
            $my_params['post_id'] = $aParams['ids'];
            $rs1 = $core->blog->getPosts($my_params);
            while ($rs1->fetch()) { // Evite les doublons
                $rs[$rs1->post_id] = $rs1->row();
            }
        }

        // Récupérer tous les éléments de cartes selon des catégories
        if (array_key_exists('categories', $aParams) && ! empty($aParams['categories'])) {
            $my_params['post_id'] = '';
            $my_params['cat_id'] = $aParams['categories'];
            $rs2 = $core->blog->getPosts($my_params);
            while ($rs2->fetch()) { // Evite les doublons
                $rs[$rs2->post_id] = $rs2->row();
            }
        }

        // Récupérer tous les éléments de cartes
        if (! isset($rs1) && ! isset($rs2)) {
            $rs3 = $core->blog->getPosts($my_params);
            while ($rs3->fetch()) {
                $rs[$rs3->post_id] = $rs3->row();
            }
        }

        return $rs;
    }
    public static function thisPostMapType($post_id)
    {
        global $core;
        $meta =& $core->meta;
        $my_params['post_id'] = $post_id;
        $my_params['no_content'] = true;
        $my_params['post_type'] = 'map';

        $rs = $core->blog->getPosts($my_params);
        return $meta->getMetaStr($rs->post_meta, 'map');
    }
    public static function publicHeadContent($core, $_ctx)
    {
        # Settings
        global $core;
        $s =& $core->blog->settings->myGmaps;
        $sPublicPath = $core->blog->getQmarkURL().'pf='.basename(dirname(__FILE__));
        if ($s->myGmaps_enabled) {
            echo mygmapsPublic::publicJsContent(array());
            echo mygmapsPublic::publicCssContent(array('public_path' => $sPublicPath));
        }
    }
    public static function publicMapContent($core, $_ctx, $aElements = array())
    {
        # Settings
        global $core;
        $s =& $core->blog->settings->myGmaps;
        $url = $core->blog->getQmarkURL().'pf='.basename(dirname(__FILE__));
        $postTypes = ['post','page'];

        if ($s->myGmaps_enabled) {
            // Appel depuis un billet, ou depuis une balise de template
            $sTemplate = '';
            $isTemplateTag = (! empty($aElements)) ? true : false ;
            $sPostId = ($isTemplateTag) ? $aElements['id'] : $_ctx->posts->post_id ;

            if ($isTemplateTag || (in_array($_ctx->posts->post_type, $postTypes) && self::hasMap($sPostId) != '')) {

            // Map styles. Get more styles from http://snazzymaps.com/

                $public_path = $core->blog->public_path;
                $public_url = $core->blog->settings->system->public_url;
                $blog_url = $core->blog->url;

                $map_styles_dir_path = $public_path.'/myGmaps/styles/';
                $map_styles_dir_url = http::concatURL($core->blog->url, $public_url.'/myGmaps/styles/');

                if (is_dir($map_styles_dir_path)) {
                    $map_styles = glob($map_styles_dir_path."*.js");
                    $map_styles_list = array();
                    foreach ($map_styles as $map_style) {
                        $map_style = basename($map_style);
                        array_push($map_styles_list, $map_style);
                    }
                    $map_styles_list = implode(",", $map_styles_list);
                    $map_styles_base_url = $map_styles_dir_url;
                } else {
                    $map_styles_list = '';
                    $map_styles_base_url = '';
                }

                // Map type
                $custom_style = false;

                // Appel depuis un billet, ou depuis une balise de template
                $aOptions = array();
                if ($isTemplateTag) {
                    $aOptions = $aElements;
                } else {
                    $meta =& $GLOBALS['core']->meta;
                    $post_map_options = explode(",", $meta->getMetaStr($_ctx->posts->post_meta, 'map_options'));
                    $aOptions = array(
                    'center' => $post_map_options[0].','.$post_map_options[1],
                    'zoom' => $post_map_options[2],
                    'style' => $post_map_options[3]
                );
                }

                // Get map elements
                $map_elements = array();
                if ($isTemplateTag) {
                    $map_elements = self::getMapElements(array(
                    'ids' => $aOptions['map_elements'],
                    'categories' => $aOptions['map_element_category']
                ));
                } else {
                    $maps_array = explode(",", self::thisPostMap($sPostId));

                    $params['post_type'] = 'map';
                    $params['post_status'] = '1';
                    $rs = $core->blog->getPosts($params);

                    while ($rs->fetch()) {
                        if (in_array($rs->post_id, $maps_array)) {
                            $map_elements[$rs->post_id] = $rs->row();
                        }
                    }
                }

                $has_marker = false;
                $has_poly = false;
                $sElementsTemplate = '';

                foreach ($map_elements as $map_element_id => $map_element) {

                // Common element vars
                    $list = explode("\n", html::clean($map_element['post_excerpt_xhtml']));

                    $content = str_replace("\\", "\\\\", $map_element['post_content_xhtml']);
                    $content = str_replace(array("\r\n", "\n", "\r"), "\\n", $content);
                    $content = str_replace(array("'"), "\'", $content);

                    $meta =& $core->meta;
                    $description = $meta->getMetaStr($map_element['post_meta'], 'description');

                    $type = self::thisPostMapType($map_element_id);

                    if ($description == 'none') {
                        $content = '';
                    }

                    $aElementOptions = array(
                    'map_id' => $sPostId,
                    'element_id' => $map_element_id,
                    'title' => html::escapeHTML($map_element['post_title']),
                    'description' => $content,
                    'type' => $type
                );

                    // Place element
                    if ($type == 'point of interest') {
                        $has_marker = true;
                        $marker = explode("|", $list[0]);

                        $aElementOptions['position'] = $marker[0].','.$marker[1];
                        $aElementOptions['icon'] = $marker[2];

                        $sElementsTemplate .= mygmapsPublic::getMapElementOptions($aElementOptions);
                    } elseif ($type == 'polyline') {
                        $has_poly = true;
                        $parts = explode("|", array_pop($list));
                        $coordinates = array();
                        $points = $list;
                        foreach ($points as $point) {
                            $coord = explode("|", $point);
                            $coordinates[] = $coord[0].','.$coord[1];
                        }

                        $aElementOptions['coordinates'] = $coordinates;
                        $aElementOptions['stroke_color'] = $parts[2];
                        $aElementOptions['stroke_opacity'] = $parts[1];
                        $aElementOptions['stroke_weight'] = $parts[0];

                        $sElementsTemplate .= mygmapsPublic::getMapElementOptions($aElementOptions);
                    } elseif ($type == 'polygon') {
                        $has_poly = true;
                        $parts = explode("|", array_pop($list));
                        $coordinates = array();
                        $points = $list;
                        foreach ($points as $point) {
                            $coord = explode("|", $point);
                            $coordinates[] = $coord[0].','.$coord[1];
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
                        $parts = explode("|", array_pop($list));
                        $coordinates = explode("|", $list[0]);

                        $aElementOptions['bound1'] = $coordinates[0].','.$coordinates[1];
                        $aElementOptions['bound2'] = $coordinates[2].','.$coordinates[3];
                        $aElementOptions['stroke_color'] = $parts[2];
                        $aElementOptions['stroke_opacity'] = $parts[1];
                        $aElementOptions['stroke_weight'] = $parts[0];
                        $aElementOptions['fill_color'] = $parts[3];
                        $aElementOptions['fill_opacity'] = $parts[4];

                        $sElementsTemplate .= mygmapsPublic::getMapElementOptions($aElementOptions);
                    } elseif ($type == 'circle') {
                        $has_poly = true;
                        $parts = explode("|", array_pop($list));
                        $coordinates = explode("|", $list[0]);

                        $aElementOptions['center'] = $coordinates[0].','.$coordinates[1];
                        $aElementOptions['radius'] = $coordinates[2];
                        $aElementOptions['stroke_color'] = $parts[2];
                        $aElementOptions['stroke_opacity'] = $parts[1];
                        $aElementOptions['stroke_weight'] = $parts[0];
                        $aElementOptions['fill_color'] = $parts[3];
                        $aElementOptions['fill_opacity'] = $parts[4];

                        $sElementsTemplate .= mygmapsPublic::getMapElementOptions($aElementOptions);
                    } elseif ($type == 'included kml file' || $type == 'GeoRSS feed') {
                        $layer = html::clean($map_element['post_excerpt_xhtml']);

                        $aElementOptions['layer'] = $layer;

                        $sElementsTemplate .= mygmapsPublic::getMapElementOptions($aElementOptions);
                    } elseif ($type == 'directions') {
                        $has_poly = true;
                        $parts = explode("|", $list[0]);

                        $aElementOptions['origin'] = $parts[0];
                        $aElementOptions['destination'] = $parts[1];
                        $aElementOptions['stroke_color'] = $parts[4];
                        $aElementOptions['stroke_opacity'] = $parts[3];
                        $aElementOptions['stroke_weight'] = $parts[2];
                        $aElementOptions['display_direction'] = (isset($parts[5]) && $parts[5] == 'true' ? 'true' : 'false');

                        $sElementsTemplate .= mygmapsPublic::getMapElementOptions($aElementOptions);
                    }
                }

                $sMapContainerStyles = '';
                $sMapCanvasStyles = '';
                if ($isTemplateTag) {
                    $sMapContainerStyles .= ($aOptions['width'] != '' ? 'width:'.$aOptions['width'].';' : '');
                    $sMapContainerStyles .= ($aOptions['height'] != '' ? 'height:'.$aOptions['height'].';' : '');
                    $sMapCanvasStyles .= ($aOptions['height'] != '' ? 'min-height:'.$aOptions['height'].';' : '');
                }


                $sTemplate = mygmapsPublic::getMapOptions(array(
                'elements' => $sElementsTemplate,
                'style' => $aOptions['style'],
                'styles_path' => $map_styles_dir_path,
                'zoom' => $aOptions['zoom'],
                'center' => $aOptions['center'],
                'map_id' => $sPostId,
                'has_marker' => $has_marker,
                'has_poly' => $has_poly
            ));

                $sTemplate .= mygmapsPublic::publicHtmlContent(array(
                'id' => $sPostId,
                'mapContainerStyles' => $sMapContainerStyles,
                'mapCanvasStyles' => $sMapCanvasStyles
            ));
            }

            if ($isTemplateTag) {
                return $sTemplate;
            } else {
                echo $sTemplate;
            }
        }
    }

    public static function publicTagMapContent($attr, $content)
    {
        global $core;

        // Récupérer tous les filtres
        // id="home" center="latlng" zoom="x" style="style_name" elements="id,id,id,id" category="id,id,id"
        $sId = isset($attr['id']) ? $attr['id'] : substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6);
        $sCenter = isset($attr['center']) ? addslashes($attr['center']) : '';
        $iZoom = isset($attr['zoom']) ? (integer) $attr['zoom'] : '12';
        $sStyle = isset($attr['style']) ? $attr['style'] : '';
        $sWidth = isset($attr['width']) ? $attr['width'] : '';
        $sHeight = isset($attr['height']) ? $attr['height'] : '';
        $aElements = (isset($attr['elements']) && !empty($attr['elements'])) ? explode(',', $attr['elements']) : array();
        $aCategories = (isset($attr['category']) && !empty($attr['category'])) ? explode(',', $attr['category']) : array();

        return self::publicMapContent($core, null, array(
            'id' => $sId,
            'center' => $sCenter,
            'zoom' => $iZoom,
            'style' => $sStyle,
            'width' => $sWidth,
            'height' => $sHeight,
            'map_elements' => $aElements,
            'map_element_category' => $aCategories
        ));
    }
}
