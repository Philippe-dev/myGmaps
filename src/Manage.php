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

use dcCore;
use dcNsProcess;
use adminUserPref;
use dcPage;
use Exception;
use form;
use dcPostsActions;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use adminPostFilter;

class Manage extends dcNsProcess
{
    /**
     * Initializes the page.
     */
    public static function init(): bool
    {
        if (defined('DC_CONTEXT_ADMIN')) {
            dcPage::check(dcCore::app()->auth->makePermissions([
                dcCore::app()->auth::PERMISSION_CONTENT_ADMIN,
            ]));

            if (isset($_REQUEST['act']) && $_REQUEST['act'] === 'map') {
                static::$init = ($_REQUEST['act'] ?? 'list') === 'map' ? ManageMap::init() : true;
            } elseif (isset($_REQUEST['act']) && $_REQUEST['act'] === 'maps') {
                static::$init = ($_REQUEST['act'] ?? 'list') === 'maps' ? ManageMaps::init() : true;
            }
        }

        return static::$init;
    }

    /**
     * Processes the request(s).
     */
    public static function process(): bool
    {
        if (!self::$init) {
            return false;
        }

        if (($_REQUEST['act'] ?? 'list') === 'map') {
            ManageMap::process();
        } elseif (($_REQUEST['act'] ?? 'list') === 'maps') {
            ManageMaps::process();
        }

        $settings = dcCore::app()->blog->settings->get(My::id());

        dcCore::app()->admin->default_tab = empty($_REQUEST['tab']) ? 'settings' : $_REQUEST['tab'];

        /*
         * Admin page params.
         */

        // Save activation

        $myGmaps_enabled = $settings->myGmaps_enabled;
        $myGmaps_API_key = $settings->myGmaps_API_key;
        $myGmaps_center  = $settings->myGmaps_center;
        $myGmaps_zoom    = $settings->myGmaps_zoom;
        $myGmaps_type    = $settings->myGmaps_type;

        if (!empty($_POST['saveconfig'])) {
            try {
                $settings->put('myGmaps_enabled', !empty($_POST['myGmaps_enabled']));
                $settings->put('myGmaps_API_key', $_POST['myGmaps_API_key']);
                $settings->put('myGmaps_center', $_POST['myGmaps_center']);
                $settings->put('myGmaps_zoom', $_POST['myGmaps_zoom']);
                $settings->put('myGmaps_type', $_POST['myGmaps_type']);

                http::redirect(My::url() . '&act=list&tab=settings&upd=1');
            } catch (Exception $e) {
                dcCore::app()->error->add($e->getMessage());
            }
        }

        dcCore::app()->admin->page        = !empty($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        dcCore::app()->admin->nb_per_page = adminUserPref::getUserFilters('pages', 'nb');

        if (!empty($_GET['nb']) && (int) $_GET['nb'] > 0) {
            dcCore::app()->admin->nb_per_page = (int) $_GET['nb'];
        }

        $params['limit']      = [((dcCore::app()->admin->page - 1) * dcCore::app()->admin->nb_per_page), dcCore::app()->admin->nb_per_page];
        $params['post_type']  = 'map';
        $params['no_content'] = true;
        $params['order']      = 'post_title ASC';

        dcCore::app()->admin->posts_list = null;

        try {
            $pages   = dcCore::app()->blog->getPosts($params);
            $counter = dcCore::app()->blog->getPosts($params, true);

            dcCore::app()->admin->posts_list = new BackendList($pages, $counter->f(0));
        } catch (Exception $e) {
            dcCore::app()->error->add($e->getMessage());
        }

        // Actions combo box
        dcCore::app()->admin->pages_actions_page          = new BackendActions('plugin.php', ['p' => 'myGmaps','tab' => 'entries-list']);
        dcCore::app()->admin->pages_actions_page_rendered = null;
        if (dcCore::app()->admin->pages_actions_page->process()) {
            dcCore::app()->admin->pages_actions_page_rendered = true;
        }

        return true;
    }

    /**
     * Renders the page.
     */
    public static function render(): void
    {
        if (!self::$init) {
            return;
        }

        if (($_REQUEST['act'] ?? 'list') === 'map') {
            ManageMap::render();

            return;
        } elseif (($_REQUEST['act'] ?? 'list') === 'maps') {
            ManageMaps::render();

            return;
        }

        if (dcCore::app()->admin->pages_actions_page_rendered) {
            dcCore::app()->admin->pages_actions_page->render();

            return;
        }

        $settings = dcCore::app()->blog->settings->get(My::id());

        $myGmaps_center = $settings->myGmaps_center;
        $myGmaps_zoom   = $settings->myGmaps_zoom;
        $myGmaps_type   = $settings->myGmaps_type;
        $myGmaps_type   = $settings->myGmaps_type;

        // Custom map styles

        $public_path = dcCore::app()->blog->public_path;
        $public_url  = dcCore::app()->blog->settings->system->public_url;
        $blog_url    = dcCore::app()->blog->url;

        $map_styles_dir_path = $public_path . '/myGmaps/styles/';
        $map_styles_dir_url  = http::concatURL(dcCore::app()->blog->url, $public_url . '/myGmaps/styles/');

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
        // -------

        dcCore::app()->admin->posts_actions_page = new dcPostsActions(dcCore::app()->adminurl->get('admin.plugin.myGmaps'));
        if (dcCore::app()->admin->posts_actions_page->process()) {
            return;
        }

        // Filters

        dcCore::app()->admin->post_filter = new adminPostFilter();

        // Get list params

        $params = dcCore::app()->admin->post_filter->params();

        dcCore::app()->admin->posts      = null;
        dcCore::app()->admin->posts_list = null;

        dcCore::app()->admin->page        = !empty($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        dcCore::app()->admin->nb_per_page = adminUserPref::getUserFilters('pages', 'nb');

        /*
        * Config and list of map elements
        */

        if (isset($_GET['page'])) {
            dcCore::app()->admin->default_tab = 'entries-list';
        }

        // Get map elements

        try {
            $params['no_content']            = true;
            $params['post_type']             = 'map';
            dcCore::app()->admin->posts      = dcCore::app()->blog->getPosts($params);
            dcCore::app()->admin->counter    = dcCore::app()->blog->getPosts($params, true);
            dcCore::app()->admin->posts_list = new BackendList(dcCore::app()->admin->posts, dcCore::app()->admin->counter->f(0));
        } catch (Exception $e) {
            dcCore::app()->error->add($e->getMessage());
        }

        $starting_script = '<script src="https://maps.googleapis.com/maps/api/js?key=' . $settings->myGmaps_API_key . '&amp;libraries=places&amp;callback=Function.prototype"></script>';

        $starting_script .= '<script>' . "\n" .
        '//<![CDATA[' . "\n" .
            'var neutral_blue_styles = [{"featureType":"water","elementType":"geometry","stylers":[{"color":"#193341"}]},{"featureType":"landscape","elementType":"geometry","stylers":[{"color":"#2c5a71"}]},{"featureType":"road","elementType":"geometry","stylers":[{"color":"#29768a"},{"lightness":-37}]},{"featureType":"poi","elementType":"geometry","stylers":[{"color":"#406d80"}]},{"featureType":"transit","elementType":"geometry","stylers":[{"color":"#406d80"}]},{"elementType":"labels.text.stroke","stylers":[{"visibility":"on"},{"color":"#3e606f"},{"weight":2},{"gamma":0.84}]},{"elementType":"labels.text.fill","stylers":[{"color":"#ffffff"}]},{"featureType":"administrative","elementType":"geometry","stylers":[{"weight":0.6},{"color":"#1a3541"}]},{"elementType":"labels.icon","stylers":[{"visibility":"off"}]},{"featureType":"poi.park","elementType":"geometry","stylers":[{"color":"#2c5a71"}]}];' . "\n" .
            'var neutral_blue = new google.maps.StyledMapType(neutral_blue_styles,{name: "Neutral Blue"});' . "\n";

        if (is_dir($map_styles_dir_path)) {
            $list = explode(',', $map_styles_list);
            foreach ($list as $map_style) {
                $map_style_content = file_get_contents($map_styles_dir_path . '/' . $map_style);
                $var_styles_name   = pathinfo($map_style, PATHINFO_FILENAME);
                $var_name          = preg_replace('/_styles/s', '', $var_styles_name);
                $nice_name         = ucwords(preg_replace('/_/s', ' ', $var_name));

                $starting_script .= 'var ' . $var_styles_name . ' = ' . $map_style_content . ';' . "\n" .
                'var ' . $var_name . ' = new google.maps.StyledMapType(' . $var_styles_name . ',{name: "' . $nice_name . '"});' . "\n";
            }
        }

        $starting_script .= '//]]>' . "\n" .
        '</script>';

        dcPage::openModule(
            My::name(),
            $starting_script .
            dcPage::jsLoad('js/_posts_list.js') .
            dcPage::jsMetaEditor() .
            dcPage::jsLoad(DC_ADMIN_URL . '?pf=myGmaps/js/config.map.min.js') .
            dcCore::app()->admin->post_filter->js(My::url() . '#entries-list') .
            dcPage::jsPageTabs(dcCore::app()->admin->default_tab) .
            dcPage::jsConfirmClose('config-form') .
            '<link rel="stylesheet" type="text/css" href="index.php?pf=myGmaps/css/admin.css" />'
        );

        echo dcPage::breadcrumb(
            [
                html::escapeHTML(dcCore::app()->blog->name) => '',
                My::name()                                  => My::url(),
            ]
        ) .
        dcPage::notices();

        // Display messages

        if (isset($_GET['upd']) && isset($_GET['act'])) {
            dcPage::success(__('Configuration has been saved.'));
        }

        // Config tab

        echo
        '<div class="multi-part" id="parameters" title="' . __('Parameters') . '">' .
        '<form method="post" action="' . My::url() . '" id="config-form">' .
        '<div class="fieldset"><h3>' . __('Activation') . '</h3>' .
            '<p><label class="classic" for="myGmaps_enabled">' .
            form::checkbox('myGmaps_enabled', '1', $settings->myGmaps_enabled) .
            __('Enable extension for this blog') . '</label></p>' .
        '</div>' .
        '<div class="fieldset"><h3>' . __('API key') . '</h3>' .
            '<p><label class="maximal" for="myGmaps_API_key">' . __('Google Maps Javascript browser API key:') .
            '<br />' . form::field('myGmaps_API_key', 80, 255, $settings->myGmaps_API_key) .
            '</label></p>';
        if ($settings->myGmaps_API_key == 'AIzaSyCUgB8ZVQD88-T4nSgDlgVtH5fm0XcQAi8') {
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
            form::hidden('myGmaps_center', $settings->myGmaps_center) .
            form::hidden('myGmaps_zoom', $settings->myGmaps_zoom) .
            form::hidden('myGmaps_type', $settings->myGmaps_type) .
            form::hidden('map_styles_list', $map_styles_list) .
            form::hidden('map_styles_base_url', $map_styles_base_url) .
            dcCore::app()->formNonce() .
            '</p></div>' .
            '<p><input type="submit" name="saveconfig" value="' . __('Save configuration') . '" /></p>' .

        '</form>' .
        '</div>' .

        // Map elements list tab

        '<div class="multi-part" id="entries-list" title="' . __('Map elements') . '">';

        if ($settings->myGmaps_enabled) {
            echo '<p class="top-add"><strong><a class="button add" href="' . My::url() . '&amp;act=map">' . __('New element') . '</a></strong></p>';
        }

        dcCore::app()->admin->post_filter->display('admin.plugin.myGmaps', '<input type="hidden" name="p" value="myGmaps" /><input type="hidden" name="tab" value="entries-list" />');

        // Show posts
        dcCore::app()->admin->posts_list->display(
            dcCore::app()->admin->post_filter->page,
            dcCore::app()->admin->post_filter->nb,
            '<form action="' . My::url() . '" method="post" id="form-entries">' .

            '%s' .

            '<div class="two-cols">' .
            '<p class="col checkboxes-helpers"></p>' .

            // Actions
            '<p class="col right"><label for="action" class="classic">' . __('Selected entries action:') . '</label> ' .
            form::combo('action', dcCore::app()->admin->posts_actions_page->getCombo()) .
            '<input id="do-action" type="submit" value="' . __('ok') . '" disabled /></p>' .
            dcCore::app()->adminurl->getHiddenFormFields('admin.plugin.myGmaps', dcCore::app()->admin->post_filter->values()) .
            dcCore::app()->formNonce() . '</p>' .
            '</div>' .
            '</form>',
            dcCore::app()->admin->post_filter->show()
        );

        echo
        '</div>';

        dcPage::helpBlock('myGmaps');
        dcPage::closeModule();
    }
}
