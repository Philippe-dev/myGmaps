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

class ManageMaps extends dcNsProcess
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

            static::$init = ($_REQUEST['act'] ?? 'list') === 'maps';
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

        $settings = dcCore::app()->blog->settings->myGmaps;

        /*
         * Admin page params.
         */

        dcCore::app()->admin->page        = !empty($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        dcCore::app()->admin->nb_per_page = adminUserPref::getUserFilters('pages', 'nb');

        if (!empty($_GET['nb']) && (int) $_GET['nb'] > 0) {
            dcCore::app()->admin->nb_per_page = (int) $_GET['nb'];
        }

        // Save added map elements

        if (isset($_POST['entries'])) {
            try {
                $entries   = $_POST['entries'];
                $post_type = $_POST['post_type'];
                $post_id = $_POST['id'];

                $meta = dcCore::app()->meta;

                $entries = implode(',', $entries);
                foreach ($meta->splitMetaValues($entries) as $tag) {
                    $meta->setPostMeta($post_id, 'map', $tag);
                }

                dcCore::app()->blog->triggerBlog();

                if ($post_type == 'page') {
                    http::redirect('plugin.php?p=pages&act=page&id=' . $post_id . '&upd=1');
                } else {
                    http::redirect(DC_ADMIN_URL . 'post.php?id=' . $post_id . '&upd=1');
                }
            } catch (Exception $e) {
                dcCore::app()->error->add($e->getMessage());
            }
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

        $settings = dcCore::app()->blog->settings->myGmaps;

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

        // Filters
        dcCore::app()->admin->post_filter = new adminPostFilter();

        // get list params
        $params = dcCore::app()->admin->post_filter->params();

        dcCore::app()->admin->posts      = null;
        dcCore::app()->admin->posts_list = null;

        dcCore::app()->admin->page        = !empty($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        dcCore::app()->admin->nb_per_page = adminUserPref::getUserFilters('pages', 'nb');

        /*
        * List of map elements
        */

        // Get current post

        try {
            $post_id                 = (int) $_GET['id'];
            $my_params['post_id']    = $post_id;
            $my_params['no_content'] = true;
            $my_params['post_type']  = ['post', 'page'];
            $rs                      = dcCore::app()->blog->getPosts($my_params);
            $post_title              = $rs->post_title;
            $post_type               = $rs->post_type;
        } catch (Exception $e) {
            dcCore::app()->error->add($e->getMessage());
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
            __('Google Maps'),
            $starting_script .
            dcPage::jsLoad('js/_posts_list.js') .
            dcCore::app()->admin->post_filter->js(dcCore::app()->admin->getPageURL() . '&amp;id=' . $post_id . '&amp;act=maps') .
            dcPage::jsPageTabs(dcCore::app()->admin->default_tab) .
            dcPage::jsConfirmClose('config-form') .
            '<link rel="stylesheet" type="text/css" href="index.php?pf=myGmaps/css/admin.css" />'
        );

        dcCore::app()->admin->page_title = __('Add elements');

        echo dcPage::breadcrumb(
            [
                html::escapeHTML(dcCore::app()->blog->name) => '',
                __('Google Maps')                           => dcCore::app()->admin->getPageURL(),
                dcCore::app()->admin->page_title            => '',
            ]
        ) .
        dcPage::notices();

        echo '<h3>' . __('Select map elements for map attached to post:') . ' <a href="' . dcCore::app()->getPostAdminURL($post_type, $post_id) . '">' . $post_title . '</a></h3>';

        dcCore::app()->admin->post_filter->display('admin.plugin.myGmaps', '<input type="hidden" name="p" value="myGmaps" /><input type="hidden" name="id" value="' . $post_id . '" /><input type="hidden" name="act" value="maps" />');

        // Show posts
        dcCore::app()->admin->posts_list->display(
            dcCore::app()->admin->post_filter->page,
            dcCore::app()->admin->post_filter->nb,
            '<form action="' . dcCore::app()->admin->getPageURL() . '" method="post" id="form-entries">' .

            '%s' .

            '<div class="two-cols">' .
            '<p class="col checkboxes-helpers"></p>' .

            '<p class="col right">' .
            '<input type="submit" value="' . __('Add selected map elements') . '" /> <a class="button reset" href="post.php?id=' . $post_id . '">' . __('Cancel') . '</a></p>' .
            '<p>' .
            form::hidden(['post_type'], $post_type) .
            form::hidden(['id'], $post_id) .
            form::hidden(['act'], 'maps') .
            dcCore::app()->adminurl->getHiddenFormFields('admin.plugin.myGmaps', dcCore::app()->admin->post_filter->values()) .
            dcCore::app()->formNonce() . '</p>' .
            '</div>' .
            '</form>',
            dcCore::app()->admin->post_filter->show()
        );

        dcPage::helpBlock('myGmapsadd');
        dcPage::closeModule();
    }
}
