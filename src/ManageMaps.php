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
use Dotclear\Core\Backend\UserPref;
use Exception;
use form;
use Dotclear\Core\Backend\Filter\FilterPosts;
use Dotclear\Core\Process;
use Dotclear\Core\Backend\Page;
use Dotclear\Core\Backend\Notices;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;

class ManageMaps extends Process
{
    /**
     * Initializes the page.
     */
    public static function init(): bool
    {
        if (My::checkContext(My::MANAGE)) {
            self::status(($_REQUEST['act'] ?? 'list') === 'maps');
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

        /*
         * Admin page params.
         */

        App::backend()->page        = !empty($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        App::backend()->nb_per_page = UserPref::getUserFilters('pages', 'nb');

        if (!empty($_GET['nb']) && (int) $_GET['nb'] > 0) {
            App::backend()->nb_per_page = (int) $_GET['nb'];
        }

        // Save added map elements

        if (isset($_POST['entries'])) {
            try {
                $entries   = $_POST['entries'];
                $post_type = $_POST['post_type'];
                $post_id   = $_POST['id'];

                $meta = App::meta();

                $entries = implode(',', $entries);
                foreach ($meta->splitMetaValues($entries) as $tag) {
                    $meta->setPostMeta($post_id, 'map', $tag);
                }

                App::blog()->triggerBlog();

                Http::redirect(App::postTypes()->get($post_type)->adminUrl($post_id, false, ['upd' => 1]));
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

        // Filters
        App::backend()->post_filter = new FilterPosts();

        // get list params
        $params = App::backend()->post_filter->params();

        App::backend()->posts      = null;
        App::backend()->posts_list = null;

        App::backend()->page        = !empty($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        App::backend()->nb_per_page = UserPref::getUserFilters('pages', 'nb');

        /*
        * List of map elements
        */

        // Get current post

        try {
            $post_id                 = (int) $_GET['id'];
            $my_params['post_id']    = $post_id;
            $my_params['no_content'] = true;
            $my_params['post_type']  = ['post', 'page'];
            $rs                      = App::blog()->getPosts($my_params);
            $post_title              = $rs->post_title;
            $post_type               = $rs->post_type;
            $map_ids                 = $rs->post_meta;
        } catch (Exception $e) {
            App::error()->add($e->getMessage());
        }

        // Get map ids to exclude from list

        $meta          = App::meta();
        $elements_list = $meta->getMetaStr($map_ids, 'map');
        $excluded      = !empty($elements_list) ? $meta->splitMetaValues($elements_list) : '';

        // Get map elements

        try {
            $params['no_content']      = true;
            $params['post_type']       = 'map';
            $params['exclude_post_id'] = $excluded;
            App::backend()->posts      = App::blog()->getPosts($params);
            App::backend()->counter    = App::blog()->getPosts($params, true);
            App::backend()->posts_list = new BackendList(App::backend()->posts, App::backend()->counter->f(0));
        } catch (Exception $e) {
            App::error()->add($e->getMessage());
        }

        Page::openModule(
            My::name(),
            Page::jsLoad('js/_posts_list.js') .
            App::backend()->post_filter->js(App::backend()->url()->get('admin.plugin'). '&p=' . My::id() . '&id=' . $post_id . '&act=maps') .
            Page::jsPageTabs(App::backend()->default_tab) .
            Page::jsConfirmClose('config-form') .
            My::cssLoad('admin.css')
        );

        App::backend()->page_title = __('Add elements');

        echo Page::breadcrumb(
            [
                html::escapeHTML(App::blog()->name) => '',
                My::name()                          => My::manageUrl(),
                App::backend()->page_title          => '',
            ]
        ) .
        Notices::getNotices();

        if ($post_type === 'page') {
            echo '<h3>' . __('Select map elements for map attached to page:') . ' <a href="' . App::postTypes()->get($post_type)->adminUrl($post_id) . '">' . $post_title . '</a></h3>';
        } elseif ($post_type === 'post') {
            echo '<h3>' . __('Select map elements for map attached to post:') . ' <a href="' . App::postTypes()->get($post_type)->adminUrl($post_id) . '">' . $post_title . '</a></h3>';
        }

        App::backend()->post_filter->display('admin.plugin.' . My::id(), '<input type="hidden" name="p" value="myGmaps" /><input type="hidden" name="id" value="' . $post_id . '" /><input type="hidden" name="act" value="maps" />');

        // Show posts
        App::backend()->posts_list->display(
            App::backend()->post_filter->page,
            App::backend()->post_filter->nb,
            '<form action="' . My::manageUrl() . '" method="post" id="form-entries">' .

            '%s' .

            '<div class="two-cols">' .
            '<p class="col checkboxes-helpers"></p>' .

            '<p class="col right">' .
            '<input type="submit" value="' . __('Add selected map elements') . '" /> <a class="button reset" href="post.php?id=' . $post_id . '">' . __('Cancel') . '</a></p>' .
            '<p>' .
            form::hidden(['post_type'], $post_type) .
            form::hidden(['id'], $post_id) .
            form::hidden(['act'], 'maps') .
            App::backend()->url()->getHiddenFormFields('admin.plugin.' . My::id(), App::backend()->post_filter->values()) .
            App::nonce()->getFormNonce() . '</p>' .
            '</div>' .
            '</form>',
            App::backend()->post_filter->show()
        );

        Page::helpBlock('myGmapsadd');
        Page::closeModule();
    }
}
