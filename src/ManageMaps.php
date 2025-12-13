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
use Dotclear\Core\Backend\Filter\FilterPosts;
use Dotclear\Core\Backend\UserPref;
use Dotclear\Helper\Process\TraitProcess;
use Dotclear\Helper\Html\Form\Div;
use Dotclear\Helper\Html\Form\Form;
use Dotclear\Helper\Html\Form\Hidden;
use Dotclear\Helper\Html\Form\Link;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Submit;
use Dotclear\Helper\Html\Form\Text;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Exception;

class ManageMaps
{
    use TraitProcess;

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

        App::backend()->posts     = null;
        App::backend()->post_list = null;

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
            App::backend()->post_list  = new BackendList(App::backend()->posts, App::backend()->counter->f(0));
        } catch (Exception $e) {
            App::error()->add($e->getMessage());
        }

        App::backend()->page()->openModule(
            My::name(),
            App::backend()->page()->jsLoad('js/_posts_list.js') .
            App::backend()->post_filter->js(App::backend()->url()->get('admin.plugin', ['p' => My::id(),'id' => $post_id, 'act' => 'maps'], '&')) . My::cssLoad('admin.css')
        );

        App::backend()->page_title = __('Add elements');

        echo App::backend()->page()->breadcrumb(
            [
                html::escapeHTML(App::blog()->name) => '',
                My::name()                          => My::manageUrl(),
                App::backend()->page_title          => '',
            ]
        ) .
        App::backend()->notices()->getNotices();

        echo
        (new Text('h3', ($post_type === 'post' ? __('Select map elements for map attached to post:') : __('Select map elements for map attached to page:')) . '&nbsp;'))
        ->items([
            (new Link())
                ->href(App::postTypes()->get($post_type)->adminUrl($post_id))
                ->text($post_title),
        ])
        ->render();

        $hidden = (new Para())
            ->items([(new Hidden('act', 'maps')),
                (new Hidden('id', (string) $post_id)),
                (new Hidden('p', (string) My::id())),
            ])
        ->render();

        App::backend()->post_filter->display('admin.plugin.' . My::id(), $hidden);

        // Show posts
        App::backend()->post_list->display(
            App::backend()->post_filter->page,
            App::backend()->post_filter->nb,
            (new Form('form-entries'))
                ->method('post')
                ->action(App::backend()->getPageURL())
                ->fields([
                    (new Text(null, '%s')), // List of pages
                    (new Div())
                        ->class('two-cols')
                        ->items([
                            (new Para())->class(['col', 'checkboxes-helpers']),
                            (new Para())
                                ->class(['col', 'right', 'form-buttons'])
                                ->items([
                                    (new Submit(['do-action']))
                                        ->id('do-action')
                                        ->value(__('Add selected map elements')),
                                    (new Link())
                                        ->class(['button','reset'])
                                        ->href(App::postTypes()->get($post_type)->adminUrl($post_id))
                                        ->text(__('Cancel')),
                                ]),
                        ]),

                    (new Para())
                        ->class('form-buttons')
                        ->items([
                            ...My::hiddenFields(),
                            (new Hidden(['post_type'], $post_type)),
                            (new Hidden(['id'], (string) $post_id)),
                            (new Hidden(['act'], 'maps')),
                        ]),
                ])
            ->render()
        );

        App::backend()->page()->helpBlock('myGmapsadd');
        App::backend()->page()->closeModule();
    }
}
