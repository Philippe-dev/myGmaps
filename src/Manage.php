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
use Dotclear\Core\Process;
use Dotclear\Core\Backend\UserPref;
use Dotclear\Core\Backend\Page;
use Dotclear\Core\Backend\Notices;
use Exception;
use form;
use Dotclear\Core\Backend\Action\ActionsPosts;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Dotclear\Core\Backend\Filter\FilterPosts;

class Manage extends Process
{
    /**
     * Initializes the page.
     */
    public static function init(): bool
    {
        self::status(My::checkContext(My::MANAGE));

        if (isset($_REQUEST['act']) && $_REQUEST['act'] === 'map') {
            self::status(($_REQUEST['act'] ?? 'list') === 'map' ? ManageMap::init() : true);
        } elseif (isset($_REQUEST['act']) && $_REQUEST['act'] === 'maps') {
            self::status(($_REQUEST['act'] ?? 'list') === 'maps' ? ManageMaps::init() : true);
        }

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

        if (($_REQUEST['act'] ?? 'list') === 'map') {
            ManageMap::process();
        } elseif (($_REQUEST['act'] ?? 'list') === 'maps') {
            ManageMaps::process();
        }

        App::backend()->default_tab = empty($_REQUEST['tab']) ? 'settings' : $_REQUEST['tab'];

        /*
         * Admin page params.
         */

        // Save activation

        $myGmaps_enabled = My::settings()->myGmaps_enabled;
        $myGmaps_API_key = My::settings()->myGmaps_API_key;
        $myGmaps_center  = My::settings()->myGmaps_center;
        $myGmaps_zoom    = My::settings()->myGmaps_zoom;
        $myGmaps_type    = My::settings()->myGmaps_type;

        if (!empty($_POST['saveconfig'])) {
            try {
                My::settings()->put('myGmaps_enabled', !empty($_POST['myGmaps_enabled']));
                My::settings()->put('myGmaps_API_key', $_POST['myGmaps_API_key']);
                My::settings()->put('myGmaps_center', $_POST['myGmaps_center']);
                My::settings()->put('myGmaps_zoom', $_POST['myGmaps_zoom']);
                My::settings()->put('myGmaps_type', $_POST['myGmaps_type']);

                My::redirect(['act' => 'list', 'tab' => 'settings','upd' => 1]);
            } catch (Exception $e) {
                App::error()->add($e->getMessage());
            }
        }

        App::backend()->page        = !empty($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        App::backend()->nb_per_page = UserPref::getUserFilters('pages', 'nb');

        if (!empty($_GET['nb']) && (int) $_GET['nb'] > 0) {
            App::backend()->nb_per_page = (int) $_GET['nb'];
        }

        $params['limit']      = [((App::backend()->page - 1) * App::backend()->nb_per_page), App::backend()->nb_per_page];
        $params['post_type']  = 'map';
        $params['no_content'] = true;
        $params['order']      = 'post_title ASC';

        App::backend()->posts_list = null;

        try {
            $pages   = App::blog()->getPosts($params);
            $counter = App::blog()->getPosts($params, true);

            App::backend()->posts_list = new BackendList($pages, $counter->f(0));
        } catch (Exception $e) {
            App::error()->add($e->getMessage());
        }

        // Actions combo box
        App::backend()->pages_actions_page          = new BackendActions(App::backend()->url()->get('admin.plugin'), ['p' => 'myGmaps','tab' => 'entries-list']);
        App::backend()->pages_actions_page_rendered = null;
        if (App::backend()->pages_actions_page->process()) {
            App::backend()->pages_actions_page_rendered = true;
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

        if (($_REQUEST['act'] ?? 'list') === 'map') {
            ManageMap::render();

            return;
        } elseif (($_REQUEST['act'] ?? 'list') === 'maps') {
            ManageMaps::render();

            return;
        }

        if (App::backend()->pages_actions_page_rendered) {
            App::backend()->pages_actions_page->render();

            return;
        }

        $myGmaps_center = My::settings()->myGmaps_center;
        $myGmaps_zoom   = My::settings()->myGmaps_zoom;
        $myGmaps_type   = My::settings()->myGmaps_type;
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

        // Actions

        App::backend()->posts_actions_page = new ActionsPosts(App::backend()->url()->get('admin.plugin.' . My::id()));
        if (App::backend()->posts_actions_page->process()) {
            return;
        }

        // Filters

        App::backend()->post_filter = new FilterPosts();

        // Get list params

        $params = App::backend()->post_filter->params();

        App::backend()->posts      = null;
        App::backend()->posts_list = null;

        App::backend()->page        = !empty($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        App::backend()->nb_per_page = UserPref::getUserFilters('pages', 'nb');

        /*
        * Config and list of map elements
        */

        if (isset($_GET['page'])) {
            App::backend()->default_tab = 'entries-list';
        }

        // Get map elements

        try {
            $params['no_content']      = true;
            $params['post_type']       = 'map';
            App::backend()->posts      = App::blog()->getPosts($params);
            App::backend()->counter    = App::blog()->getPosts($params, true);
            App::backend()->posts_list = new BackendList(App::backend()->posts, App::backend()->counter->f(0));
        } catch (Exception $e) {
            App::error()->add($e->getMessage());
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
                $map_style_content = file_get_contents($map_styles_dir_path . '/' . $map_style);
                $test_replace = preg_replace('\\', '', $map_style_content);
                $var_styles_name   = pathinfo($map_style, PATHINFO_FILENAME);
                $var_name          = preg_replace('/_styles/s', '', $var_styles_name);
                $nice_name         = ucwords(preg_replace('/_/s', ' ', $var_name));
                $style_script .=  Page::jsJson($var_name, [
                    'style' => json_decode($map_style_content),
                    'name'  => $nice_name,
                ]);
            }
        }

        Page::openModule(
            My::name(),
            $starting_script .
            $style_script .
            Page::jsLoad('js/_posts_list.js') .
            Page::jsMetaEditor() .
            App::backend()->post_filter->js(My::manageUrl() . '#entries-list') .
            Page::jsPageTabs(App::backend()->default_tab) .
            Page::jsConfirmClose('config-form') .
            My::jsLoad('config.map.min.js') .
            My::cssLoad('admin.css')
        );

        echo Page::breadcrumb(
            [
                html::escapeHTML(App::blog()->name) => '',
                My::name()                          => My::manageUrl(),
            ]
        ) .
        Notices::getNotices();

        // Display messages

        if (isset($_GET['upd']) && isset($_GET['act'])) {
            Notices::success(__('Configuration has been saved.'));
        }

        // Config tab

        echo
        '<div class="multi-part" id="parameters" title="' . __('Parameters') . '">' .
        '<form method="post" action="' . My::manageUrl() . '" id="config-form">' .
        '<div class="fieldset"><h3>' . __('Activation') . '</h3>' .
            '<p><label class="classic" for="myGmaps_enabled">' .
            form::checkbox('myGmaps_enabled', '1', My::settings()->myGmaps_enabled) .
            __('Enable extension for this blog') . '</label></p>' .
        '</div>' .
        '<div class="fieldset"><h3>' . __('API key') . '</h3>' .
            '<p><label class="maximal" for="myGmaps_API_key">' . __('Google Maps Javascript browser API key:') .
            '<br />' . form::field('myGmaps_API_key', 80, 255, My::settings()->myGmaps_API_key) .
            '</label></p>';
        if (My::settings()->myGmaps_API_key == 'AIzaSyCUgB8ZVQD88-T4nSgDlgVtH5fm0XcQAi8') {
            echo '<p class="warn">' . __('You are currently using a <em>shared</em> API key. To avoid map display restrictions on your blog, use your own API key.') . '</p>';
        }

        echo '</div>' .
        '<div class="fieldset"><h3>' . __('Default map options') . '</h3>' .
        '<div class="map_toolbar"><span class="search">' . __('Search:') . '</span><span class="map_spacer">&nbsp;</span>' .
            '<input size="50" maxlength="255" type="text" id="address" class="qx" /><input id="geocode" type="submit" value="' . __('OK') . '" />' .
        '</div>' .
        '<p class="area" id="map_canvas"></p>' .
        '<p class="form-note info maximal mapinfo" style="width: 100%">' . __('Choose map center by dragging map or searching for a location. Choose zoom level and map type with map controls.') . '</p>' .
            '<p>' .
            form::hidden('myGmaps_center', My::settings()->myGmaps_center) .
            form::hidden('myGmaps_zoom', My::settings()->myGmaps_zoom) .
            form::hidden('myGmaps_type', My::settings()->myGmaps_type) .
            form::hidden('map_styles_list', $map_styles_list) .
            form::hidden('map_styles_base_url', $map_styles_base_url) .
            App::nonce()->getFormNonce() .
            '</p></div>' .
            '<p><input type="submit" name="saveconfig" value="' . __('Save configuration') . '" /></p>' .

        '</form>' .
        '</div>' .

        // Map elements list tab

        '<div class="multi-part" id="entries-list" title="' . __('Map elements') . '">';

        if (My::settings()->myGmaps_enabled) {
            echo '<p class="top-add"><strong><a class="button add" href="' . My::manageUrl() . '&act=map">' . __('New element') . '</a></strong></p>';
        }

        App::backend()->post_filter->display('admin.plugin.' . My::id(), '<input type="hidden" name="p" value="myGmaps" /><input type="hidden" name="tab" value="entries-list" />');

        // Show posts
        App::backend()->posts_list->display(
            App::backend()->post_filter->page,
            App::backend()->post_filter->nb,
            '<form action="' . My::manageUrl() . '" method="post" id="form-entries">' .

            '%s' .

            '<div class="two-cols">' .
            '<p class="col checkboxes-helpers"></p>' .

            // Actions
            '<p class="col right"><label for="action" class="classic">' . __('Selected entries action:') . '</label> ' .
            form::combo('action', App::backend()->posts_actions_page->getCombo()) .
            '<input id="do-action" type="submit" value="' . __('ok') . '" disabled /></p>' .
            App::backend()->url()->getHiddenFormFields('admin.plugin.' . My::id(), App::backend()->post_filter->values()) .
            App::nonce()->getFormNonce() . '</p>' .
            '</div>' .
            '</form>',
            App::backend()->post_filter->show()
        );

        echo
        '</div>';

        Page::helpBlock('myGmaps');
        Page::closeModule();
    }
}
