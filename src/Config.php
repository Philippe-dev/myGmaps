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
use Dotclear\Core\Backend\Notices;
use Dotclear\Core\Backend\Page;
use Dotclear\Core\Process;
use Dotclear\Helper\Html\Form\Checkbox;
use Dotclear\Helper\Html\Form\Div;
use Dotclear\Helper\Html\Form\Fieldset;
use Dotclear\Helper\Html\Form\Input;
use Dotclear\Helper\Html\Form\Label;
use Dotclear\Helper\Html\Form\Legend;
use Dotclear\Helper\Html\Form\None;
use Dotclear\Helper\Html\Form\Note;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Text;
use Dotclear\Helper\Network\Http;
use Exception;

class Config extends Process
{
    /**
     * Initializes the page.
     */
    public static function init(): bool
    {
        self::status(My::checkContext(My::CONFIG));

        return self::status();
    }

    /**
     * Processes the request(s).
     */
    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }
        // Save activation

        $myGmaps_enabled = My::settings()->myGmaps_enabled;
        $myGmaps_API_key = My::settings()->myGmaps_API_key;
        $myGmaps_center  = My::settings()->myGmaps_center;
        $myGmaps_zoom    = My::settings()->myGmaps_zoom;
        $myGmaps_type    = My::settings()->myGmaps_type;

        if (!empty($_POST['save'])) {
            try {
                My::settings()->put('myGmaps_enabled', !empty($_POST['myGmaps_enabled']));
                My::settings()->put('myGmaps_API_key', $_POST['myGmaps_API_key']);
                My::settings()->put('myGmaps_center', $_POST['myGmaps_center']);
                My::settings()->put('myGmaps_zoom', $_POST['myGmaps_zoom']);
                My::settings()->put('myGmaps_type', $_POST['myGmaps_type']);

                Notices::addSuccessNotice(__('Configuration has been updated.'));

                App::backend()->url()->redirect('admin.plugins', ['module' => My::id(), 'conf' => '1', 'redir' => $_REQUEST['redir']]);
            } catch (Exception $e) {
                App::error()->add($e->getMessage());
            }
        }

        return true;
    }

    /**
     * Renders the page.
     */
    public static function render(): void
    {
        if (!self::status()) {
            return;
        }

        $myGmaps_center = My::settings()->myGmaps_center;
        $myGmaps_zoom   = My::settings()->myGmaps_zoom;
        $myGmaps_type   = My::settings()->myGmaps_type;

        // Custom map styles

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

        $starting_script = '<script>' . "\n" .
            '(g=>{var h,a,k,p="The Google Maps JavaScript API",c="google",l="importLibrary",q="__ib__",m=document,b=window;b=b[c]||(b[c]={});var d=b.maps||(b.maps={}),r=new Set,e=new URLSearchParams,u=()=>h||(h=new Promise(async(f,n)=>{await (a=m.createElement("script"));e.set("libraries",[...r]+"");for(k in g)e.set(k.replace(/[A-Z]/g,t=>"_"+t[0].toLowerCase()),g[k]);e.set("callback",c+".maps."+q);a.src=`https://maps.${c}apis.com/maps/api/js?`+e;d[q]=f;a.onerror=()=>h=n(Error(p+" could not load."));a.nonce=m.querySelector("script[nonce]")?.nonce||"";m.head.append(a)}));d[l]?console.warn(p+" only loads once. Ignoring:",g):d[l]=(f,...n)=>r.add(f)&&u().then(()=>d[l](f,...n))})({' . "\n" .
                'key: "' . My::settings()->myGmaps_API_key . '",' . "\n" .
                'v: "weekly",' . "\n" .
            '});' . "\n" .
        '</script>' . "\n" ;

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

        echo
            $starting_script .
            $style_script .
            Page::jsConfirmClose('module_config') .
            My::jsLoad('config.map.min.js') .
            My::cssLoad('admin.css')
        ;

        echo
        (new Div())->items([
            (new Fieldset())->class('fieldset')->legend((new Legend(__('Activation'))))->fields([
                (new Para())->items([
                    (new Checkbox('myGmaps_enabled', (bool) My::settings()->myGmaps_enabled)),
                    (new Label(__('Enable extension for this blog'), Label::OUTSIDE_LABEL_AFTER))->for('myGmaps_enabled')->class('classic'),
                ]),
            ]),
            (new Fieldset())->class('fieldset')->legend((new Legend(__('API key'))))->fields([
                (new Para())->items([
                    (new Input('myGmaps_API_key'))
                        ->class('classic')
                        ->size(50)
                        ->maxlength(255)
                        ->value(My::settings()->myGmaps_API_key)
                        ->required(true)
                        ->placeholder(__('API key'))
                        ->label((new Label(
                            (new Text('abbr', '*'))->title(__('Required field'))->render() . __('Google Maps Javascript browser API key:'),
                            Label::OUTSIDE_TEXT_BEFORE
                        ))
                        ->id('myGmaps_API_key')->class('required')->title(__('Required field'))),
                    (My::settings()->myGmaps_API_key == 'AIzaSyCUgB8ZVQD88-T4nSgDlgVtH5fm0XcQAi8' ?
                        (new Text('span', __('You are currently using a <em>shared</em> API key. To avoid map display restrictions on your blog, use your own API key.')))
                            ->class('warn') :
                        (new None())),
                ]),
            ]),
            (new Fieldset())->class('fieldset')->legend((new Legend(__('Default map options'))))->fields([
                (new Div())
                    ->class('map_toolbar')
                    ->id('map_toolbar')
                    ->items([
                        (new Text('span', __('Search:')))->class('search'),
                        (new Text('span', '&nbsp;'))->class('map_spacer'),
                        (new Input('address'))
                            ->size(50)
                            ->maxlength(255)
                            ->class('qx'),
                        (new Input('geocode'))
                            ->type('submit')
                            ->value(__('OK')),
                    ]),
                (new Para())
                    ->class('area')
                    ->id('map_canvas'),
                (new Note())
                    ->class(['form-note', 'info', 'maximal', 'mapinfo'])
                    ->text(__('Choose map center by dragging map or searching for a location. Choose zoom level and map type with map controls.')),
                (new Para())->items([
                    (new Input('myGmaps_center'))
                        ->type('hidden')
                        ->value(My::settings()->myGmaps_center),
                    (new Input('myGmaps_zoom'))
                        ->type('hidden')
                        ->value(My::settings()->myGmaps_zoom),
                    (new Input('myGmaps_type'))
                        ->type('hidden')
                        ->value(My::settings()->myGmaps_type),
                    (new Input('map_styles_list'))
                        ->type('hidden')
                        ->value($map_styles_list),
                    (new Input('map_styles_base_url'))
                        ->type('hidden')
                        ->value($map_styles_base_url),
                ]),
            ]),

        ])
        ->render();

        Page::helpBlock(My::id());
    }
}
