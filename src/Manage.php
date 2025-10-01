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
use Dotclear\Helper\Html\Form\Label;
use Dotclear\Helper\Html\Form\Link;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Select;
use Dotclear\Helper\Html\Form\Submit;
use Dotclear\Helper\Html\Form\Text;
use Dotclear\Helper\Html\Html;
use Exception;

class Manage
{
    use TraitProcess;
    
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

        // Actions
        App::backend()->posts_actions_page = new BackendActions(App::backend()->url()->get('admin.plugin'), ['p' => My::id()]);

        if (App::backend()->posts_actions_page->process()) {
            return;
        }

        if (App::backend()->posts_actions_page_rendered) {
            App::backend()->posts_actions_page->render();

            return;
        }

        // Filters
        App::backend()->post_filter = new FilterPosts();

        $params = App::backend()->post_filter->params();

        // lexical sort
        $sortby_lex = [
            // key in sorty_combo (see above) => field in SQL request
            'post_title' => 'post_title',
            'cat_title'  => 'cat_title',
            'user_id'    => 'P.user_id', ];

        # --BEHAVIOR-- adminPostsSortbyLexCombo -- array<int,array<string,string>>
        App::behavior()->callBehavior('adminPostsSortbyLexCombo', [&$sortby_lex]);

        $params['order'] = (array_key_exists(App::backend()->post_filter->sortby, $sortby_lex) ?
            App::db()->con()->lexFields($sortby_lex[App::backend()->post_filter->sortby]) :
            App::backend()->post_filter->sortby) . ' ' . App::backend()->post_filter->order;

        App::backend()->page        = !empty($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        App::backend()->nb_per_page = UserPref::getUserFilters('pages', 'nb');

        // Get map elements

        try {
            $params['no_content'] = true;
            $params['post_type']  = 'map';

            App::backend()->posts      = App::blog()->getPosts($params);
            App::backend()->counter    = App::blog()->getPosts($params, true);
            App::backend()->posts_list = new BackendList(App::backend()->posts, App::backend()->counter->f(0));
        } catch (Exception $e) {
            App::error()->add($e->getMessage());
        }

        App::backend()->page()->openModule(
            My::name(),
            App::backend()->page()->jsLoad('js/_posts_list.js') .
            App::backend()->page()->jsMetaEditor() .
            App::backend()->post_filter->js(App::backend()->url()->get('admin.plugin', ['p' => My::id()], '&')) .
            My::jsLoad('config.map.min.js') .
            My::cssLoad('admin.css')
        );

        echo App::backend()->page()->breadcrumb(
            [
                html::escapeHTML(App::blog()->name) => '',
                My::name()                          => '',
            ]
        ) .
        App::backend()->notices()->getNotices();

        echo
        (new Para())
        ->class('top-add')
        ->items([
            (new Text(
                null,
                (new Link())
                ->class('button add')
                ->href(My::manageUrl() . '&act=map')
                ->text(__('New element'))->render()
            )),
        ])
        ->render();

        App::backend()->post_filter->display('admin.plugin.' . My::id());

        # Show posts

        $combo = App::backend()->posts_actions_page->getCombo();
        if (is_array($combo)) {
            $block = (new Form('form-entries'))
                ->method('post')
                ->action(My::manageUrl())
                ->fields([
                    (new Text(null, '%s')), // Here will go the posts list
                    (new Div())
                        ->class('two-cols')
                        ->items([
                            (new Para())->class(['col', 'checkboxes-helpers']),
                            (new Para())
                                ->class(['col', 'right', 'form-buttons'])
                                ->items([
                                    (new Select('action'))
                                        ->items($combo)
                                        ->label(new Label(__('Selected elements action:'), Label::IL_TF)),
                                    (new Submit('do-action', __('ok')))
                                        ->disabled(true),
                                    App::nonce()->formNonce(),
                                    ... App::backend()->url()->hiddenFormFields('admin.plugin.' . My::id(), App::backend()->post_filter->values()),
                                ]),
                        ]),
                ])
            ->render();
        } else {
            $block = (new Text(null, '%s'))
            ->render();
        }

        App::backend()->posts_list->display(
            App::backend()->post_filter->page,
            App::backend()->post_filter->nb,
            $block,
            App::backend()->post_filter->show()
        );

        App::backend()->page()->helpBlock(My::id());
        App::backend()->page()->closeModule();
    }
}
