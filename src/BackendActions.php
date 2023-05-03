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
use dcPage;
use dcPostsActions;
use Dotclear\Helper\Html\Html;
use Exception;

class BackendActions extends dcPostsActions
{
    protected $use_render = true;

    /**
     * Constructs a new instance.
     *
     * @param      null|string  $uri            The uri
     * @param      array        $redirect_args  The redirect arguments
     */
    public function __construct(?string $uri, array $redirect_args = [])
    {
        parent::__construct($uri, $redirect_args);

        $this->redirect_fields = [];
        $this->caller_title    = __('Google Maps');
    }

    /**
     * Display error page
     *
     * @param      Exception  $e      { parameter_description }
     */
    public function error(Exception $e)
    {
        dcCore::app()->error->add($e->getMessage());
        $this->beginPage(
            dcPage::breadcrumb(
                [
                    Html::escapeHTML(dcCore::app()->blog->name) => '',
                    __('Google Maps')                           => $this->getRedirection(true),
                    __('Maps actions')                          => '',
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
        echo
        '<html><head><title>' . __('Google Maps') . '</title>' .
        dcPage::jsLoad('js/_posts_actions.js') .
        $head .
        '</script></head><body>' .
        $breadcrumb .
        '<p><a class="back" href="' . $this->getRedirection(true) . '">' . __('Back to elements list') . '</a></p>';
    }

    /**
     * Ends a page.
     */
    public function endPage(): void
    {
        echo
        '</body></html>';
    }

    /**
     * Set pages actions
     */
    public function loadDefaults(): void
    {
        // We could have added a behavior here, but we want default action to be setup first
        BackendDefaultActions::adminPostsActionsPage($this);
        # --BEHAVIOR-- adminPagesActions -- dcActions
        dcCore::app()->callBehavior('adminPagesActions', $this);
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
