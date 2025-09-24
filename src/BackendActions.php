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
use Dotclear\Core\Backend\Action\ActionsPosts;
use Dotclear\Helper\Html\Form\Link;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Html;
use Exception;

class BackendActions extends ActionsPosts
{
    /**
     * Constructs a new instance.
     *
     * @param      null|string  $uri            The uri
     * @param      array        $redirect_args  The redirect arguments
     */
    public function __construct(?string $uri, array $redirect_args = [])
    {
        parent::__construct($uri, $redirect_args);

        $this->redirect_fields = [
            'user_id', 'cat_id', 'status', 'selected', 'attachment', 'month', 'lang', 'sortby', 'order', 'page', 'nb',
        ];

        $this->caller_title = My::name();

        $this->loadDefaults();
    }

    /**
     * Display error page
     *
     * @param      Exception  $e      { parameter_description }
     */
    public function error(Exception $e): void
    {
        App::error()->add($e->getMessage());
        $this->beginPage(
            App::backend()->page()->breadcrumb(
                [
                    Html::escapeHTML(App::blog()->name) => '',
                    My::name()                          => $this->getRedirection(true),
                    __('Maps actions')                  => '',
                ]
            )
        );
        $this->endPage();
    }

    /**
     * Begins a page.
     *
     * @param      string  $breadcrumb  The breadcrumb
     * @param      string  $head        The head
     */
    public function beginPage(string $breadcrumb = '', string $head = ''): void
    {
        if ($this->in_plugin) {
            App::backend()->page()->openModule(
                __('Maps'),
                App::backend()->page()->jsLoad('js/_posts_actions.js') .
                $head
            );
            echo $breadcrumb;
        } else {
            App::backend()->page()->open(
                __('Maps'),
                App::backend()->page()->jsLoad('js/_posts_actions.js') .
                $head,
                $breadcrumb
            );
        }

        echo (new Para())
            ->items([
                (new Link())
                    ->class('back')
                    ->href($this->getRedirection(true))
                    ->text(__('Back to elements list')),
            ])
            ->render();
    }

    /**
     * Ends a page.
     */
    public function endPage(): void
    {
        App::backend()->page()->helpBlock(My::id());
        App::backend()->page()->closeModule();
    }

    /**
     * Set pages actions
     */
    public function loadDefaults(): void
    {
        // We could have added a behavior here, but we want default action to be setup first
        BackendDefaultActions::adminPostsActionsPage($this);
        # --BEHAVIOR-- adminPagesActions -- dcActions
        App::behavior()->callBehavior('adminPostsActions', $this);
    }

    /**
     * Proceeds action handling, if any
     *
     * This method may issue an exit() if an action is being processed.
     *  If it returns, no action has been performed
     *
     *  @return mixed
     */
    public function process()
    {
        // fake action for pages reordering
        if (!empty($this->from['reorder'])) {
            $this->from['action'] = 'reorder';
        }
        $this->from['post_type'] = 'map';

        return parent::process();
    }
}
