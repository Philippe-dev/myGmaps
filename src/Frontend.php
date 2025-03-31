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

declare(strict_types=1);

namespace Dotclear\Plugin\myGmaps;

use Dotclear\App;
use Dotclear\Core\Process;
use Dotclear\Helper\L10n;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;

class Frontend extends Process
{
    public static function init(): bool
    {
        return self::status(My::checkContext(My::FRONTEND));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        App::behavior()->addBehavior('publicEntryAfterContent', [self::class, 'publicMapContent']);
        App::behavior()->addBehavior('publicPageAfterContent', [self::class, 'publicMapContent']);
        App::behavior()->addBehavior('publicHeadContent', [self::class, 'publicHeadContent']);

        App::frontend()->template()->addValue(My::id(), [self::class, 'publicTagMapContent']);

        L10n::set(dirname(__FILE__) . '/locales/' . App::lang()->getLang() . '/main');

        return true;
    }

    public static function hasMap($post_id)
    {
        $meta                    = App::meta();
        $my_params['post_id']    = $post_id;
        $my_params['no_content'] = true;
        $my_params['post_type']  = ['post', 'page'];
        App::blog()->withoutPassword(false);
        $rs = App::blog()->getPosts($my_params);

        return $meta->getMetaStr($rs->post_meta, 'map_options');
    }

    public static function thisPostMap($post_id)
    {
        $meta                    = App::meta();
        $my_params['post_id']    = $post_id;
        $my_params['no_content'] = true;
        $my_params['post_type']  = ['post', 'page'];
        App::blog()->withoutPassword(false);
        $rs = App::blog()->getPosts($my_params);

        return $meta->getMetaStr($rs->post_meta, 'map');
    }

    /**
     * @param array $aParams ['ids' => array(), 'categories' => array()]
     */
    public static function getMapElements($aParams = [])
    {
        $rs = [];

        $my_params                = [];
        $my_params['post_type']   = ['map'];
        $my_params['post_status'] = '1';

        // Récupérer tous les éléments de cartes selon leurs ids

        if (array_key_exists('ids', $aParams) && !empty($aParams['ids'])) {
            $my_params['post_id'] = $aParams['ids'];
            $rs1                  = App::blog()->getPosts($my_params);
            while ($rs1->fetch()) { // Evite les doublons
                $rs[$rs1->post_id] = $rs1->row();
            }
        }

        // Récupérer tous les éléments de cartes selon des catégories

        if (array_key_exists('categories', $aParams) && !empty($aParams['categories'])) {
            $my_params['post_id'] = '';
            $my_params['cat_id']  = $aParams['categories'];
            $rs2                  = App::blog()->getPosts($my_params);
            while ($rs2->fetch()) { // Evite les doublons
                $rs[$rs2->post_id] = $rs2->row();
            }
        }

        // Récupérer tous les éléments de cartes

        if (!isset($rs1) && !isset($rs2)) {
            $rs3 = App::blog()->getPosts($my_params);
            while ($rs3->fetch()) {
                $rs[$rs3->post_id] = $rs3->row();
            }
        }

        return $rs;
    }

    public static function thisPostMapType($post_id)
    {
        $meta                    = App::meta();
        $my_params['post_id']    = $post_id;
        $my_params['no_content'] = true;
        $my_params['post_type']  = 'map';

        $rs = App::blog()->getPosts($my_params);

        return $meta->getMetaStr($rs->post_meta, 'map');
    }

    public static function publicHeadContent()
    {
        // Settings

        if (!My::settings()->myGmaps_enabled) {
            return;
        }

        echo My::cssLoad('public.css') .
        '<script>' . "\n" .
            '(g=>{var h,a,k,p="The Google Maps JavaScript API",c="google",l="importLibrary",q="__ib__",m=document,b=window;b=b[c]||(b[c]={});var d=b.maps||(b.maps={}),r=new Set,e=new URLSearchParams,u=()=>h||(h=new Promise(async(f,n)=>{await (a=m.createElement("script"));e.set("libraries",[...r]+"");for(k in g)e.set(k.replace(/[A-Z]/g,t=>"_"+t[0].toLowerCase()),g[k]);e.set("callback",c+".maps."+q);a.src=`https://maps.${c}apis.com/maps/api/js?`+e;d[q]=f;a.onerror=()=>h=n(Error(p+" could not load."));a.nonce=m.querySelector("script[nonce]")?.nonce||"";m.head.append(a)}));d[l]?console.warn(p+" only loads once. Ignoring:",g):d[l]=(f,...n)=>r.add(f)&&u().then(()=>d[l](f,...n))})({' . "\n" .
                'key: "' . My::settings()->myGmaps_API_key . '",' . "\n" .
                'v: "weekly",' . "\n" .
            '});' . "\n" .
        '</script>' . "\n" ;
    }

    public static function publicMapContent($attr, $content, $aElements = [])
    {
        // Settings

        $postTypes = ['post', 'page'];

        if (My::settings()->myGmaps_enabled) {
            // Appel depuis un billet, ou depuis une balise de template

            $sTemplate     = '';
            $isTemplateTag = (!empty($aElements)) ? true : false ;
            $sPostId       = ($isTemplateTag) ? $aElements['id'] : App::frontend()->context()->posts->post_id ;

            if ($isTemplateTag || (in_array(App::frontend()->context()->posts->post_type, $postTypes) && self::hasMap($sPostId) != '')) {
                // Map styles. Get more styles from http://snazzymaps.com/

                $public_path = App::blog()->public_path;
                $public_url  = App::blog()->settings->system->public_url;
                $blog_url    = App::blog()->url;

                $map_styles_dir_path = $public_path . '/myGmaps/styles/';
                $map_styles_dir_url  = Http::concatURL(App::blog()->url, $public_url . '/myGmaps/styles/');

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

                // Map type

                $custom_style = false;

                // Appel depuis un billet, ou depuis une balise de template

                $aOptions = [];
                if ($isTemplateTag) {
                    $aOptions = $aElements;
                } else {
                    $meta             = App::meta();
                    $post_map_options = explode(',', $meta->getMetaStr(App::frontend()->context()->posts->post_meta, 'map_options'));
                    $aOptions         = [
                        'center' => $post_map_options[0] . ',' . $post_map_options[1],
                        'zoom'   => $post_map_options[2],
                        'style'  => $post_map_options[3],
                    ];
                }

                // Get map elements

                $map_elements = [];
                if ($isTemplateTag) {
                    $map_elements = self::getMapElements([
                        'ids'        => $aOptions['map_elements'],
                        'categories' => $aOptions['map_element_category'],
                    ]);
                } else {
                    $maps_array = explode(',', self::thisPostMap($sPostId));

                    $params['post_type']   = 'map';
                    $params['post_status'] = '1';
                    $rs                    = App::blog()->getPosts($params);

                    while ($rs->fetch()) {
                        if (in_array($rs->post_id, $maps_array)) {
                            $map_elements[$rs->post_id] = $rs->row();
                        }
                    }
                }

                $has_marker        = false;
                $has_poly          = false;
                $sElementsTemplate = '';

                foreach ($map_elements as $map_element_id => $map_element) {
                    // Common element vars
                    $list = explode("\n", Html::clean($map_element['post_excerpt_xhtml']));

                    $content = str_replace('\\', '\\\\', $map_element['post_content_xhtml']);
                    $content = str_replace(["\r\n", "\n", "\r"], '\\n', $content);
                    $content = str_replace(["'"], "\'", $content);

                    $meta        = App::meta();
                    $description = $meta->getMetaStr($map_element['post_meta'], 'description');

                    $type = self::thisPostMapType($map_element_id);

                    if ($description == 'none') {
                        $content = '';
                    }

                    $aElementOptions = [
                        'map_id'      => $sPostId,
                        'element_id'  => $map_element_id,
                        'title'       => Html::escapeHTML($map_element['post_title']),
                        'description' => $content,
                        'type'        => $type,
                    ];

                    // Place element

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
                        $layer = Html::clean($map_element['post_excerpt_xhtml']);

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
                }

                $sMapContainerStyles = '';
                $sMapCanvasStyles    = '';
                if ($isTemplateTag) {
                    $sMapContainerStyles .= ($aOptions['width'] != '' ? 'width:' . $aOptions['width'] . ';' : '');
                    $sMapContainerStyles .= ($aOptions['height'] != '' ? 'height:' . $aOptions['height'] . ';' : '');
                    $sMapCanvasStyles    .= ($aOptions['height'] != '' ? 'min-height:' . $aOptions['height'] . ';' : '');
                }

                $sTemplate = FrontendTemplate::getMapOptions([
                    'elements'    => $sElementsTemplate,
                    'style'       => $aOptions['style'],
                    'styles_path' => $map_styles_dir_path,
                    'zoom'        => $aOptions['zoom'],
                    'center'      => $aOptions['center'],
                    'map_id'      => $sPostId,
                    'has_marker'  => $has_marker,
                    'has_poly'    => $has_poly,
                ]);

                $sTemplate .= FrontendTemplate::publicHtmlContent([
                    'id'                 => $sPostId,
                    'mapContainerStyles' => $sMapContainerStyles,
                    'mapCanvasStyles'    => $sMapCanvasStyles,
                ]);
            }

            if ($isTemplateTag) {
                return $sTemplate;
            }
            echo $sTemplate;
        }
    }

    public static function publicTagMapContent($attr, $content)
    {
        // Récupérer tous les filtres
        // id="home" center="latlng" zoom="x" style="style_name" elements="id,id,id,id" category="id,id,id"

        $sId         = $attr['id'] ?? substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 6);
        $sCenter     = isset($attr['center']) ? addslashes($attr['center']) : '';
        $iZoom       = isset($attr['zoom']) ? (int) $attr['zoom'] : '12';
        $sStyle      = $attr['style']  ?? '';
        $sWidth      = $attr['width']  ?? '';
        $sHeight     = $attr['height'] ?? '';
        $aElements   = (isset($attr['elements']) && !empty($attr['elements'])) ? explode(',', $attr['elements']) : [];
        $aCategories = (isset($attr['category']) && !empty($attr['category'])) ? explode(',', $attr['category']) : [];

        return self::publicMapContent(null, null, [
            'id'                   => $sId,
            'center'               => $sCenter,
            'zoom'                 => $iZoom,
            'style'                => $sStyle,
            'width'                => $sWidth,
            'height'               => $sHeight,
            'map_elements'         => $aElements,
            'map_element_category' => $aCategories,
        ]);
    }
}
