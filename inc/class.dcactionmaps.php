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

if (!defined('DC_RC_PATH')) {
    return;
}

class dcMapsActionsPage extends dcActionsPage
{
    public function __construct(dcCore $core, $uri, $redirect_args = [])
    {
        parent::__construct(dcCore::app(), $uri, $redirect_args);
        $this->redirect_fields = ['user_id', 'cat_id', 'status',
            'selected', 'attachment', 'month', 'lang', 'sortby', 'order', 'page', 'nb'];
        $this->caller_title = __('Google Maps');
        $this->enable_redir_selection = true;
        $this->loadDefaults();
    }

    protected function loadDefaults()
    {
        // We could have added a behavior here, but we want default action
        // to be setup first
        dcDefaultMapsActions::adminMapsActionsPage(dcCore::app(), $this);
        //$this->core->callBehavior('adminPostsActionsPage',$this->core,$this);
    }

    public function beginPage($breadcrumb = '', $head = '')
    {
        if ($this->in_plugin) {
            echo '<html><head><title>' . __('Google Maps') . '</title>' .
                dcPage::jsLoad('js/_posts_actions.js') .
                $head .
                '</script></head><body>' .
                $breadcrumb;
        } else {
            dcPage::open(
                __('Google Maps'),
                dcPage::jsLoad('js/_posts_actions.js') .
                $head,
                $breadcrumb
            );
        }
        echo '<p><a class="back" href="' . $this->getRedirection(true) . '">' . __('Back to map elements list') . '</a></p>';
    }

    public function endPage()
    {
        if ($this->in_plugin) {
            echo '</body></html>';
        } else {
            dcPage::close();
        }
    }

    public function error(Exception $e)
    {
        dcCore::app()->error->add($e->getMessage());
        $this->beginPage(
            dcPage::breadcrumb(
                [
                    html::escapeHTML(dcCore::app()->blog->name) => '',
                    $this->getCallerTitle() => $this->getRedirection(true),
                    __('Map elements actions') => ''
                ]
            )
        );
        $this->endPage();
    }

    protected function fetchEntries($from)
    {
        $params = [];

        if (!empty($from['entries'])) {
            $entries = $from['entries'];

            foreach ($entries as $k => $v) {
                $entries[$k] = (int) $v;
            }

            $params['sql'] = 'AND P.post_id IN(' . implode(',', $entries) . ') ';
        } else {
            $params['sql'] = 'AND 1=0 ';
        }

        if (!isset($from['full_content']) || empty($from['full_content'])) {
            $params['no_content'] = true;
        }

        if (isset($from['post_type'])) {
            $params['post_type'] = $from['post_type'];
        }

        $posts = dcCore::app()->blog->getPosts($params);
        while ($posts->fetch()) {
            $this->entries[$posts->post_id] = $posts->post_title;
        }
        $this->rs = $posts;
    }
}

class dcDefaultMapsActions
{
    public static function adminMapsActionsPage($core, $ap)
    {
        if (dcCore::app()->auth->check('publish,contentadmin', dcCore::app()->blog->id)) {
            $ap->addAction(
                [__('Status') => [
                    __('Publish') => 'publish',
                    __('Unpublish') => 'unpublish',
                    __('Schedule') => 'schedule',
                    __('Mark as pending') => 'pending'
                ]],
                ['dcDefaultMapsActions', 'doChangeMapStatus']
            );
        }
        $ap->addAction(
            [__('Mark') => [
                __('Mark as selected') => 'selected',
                __('Mark as unselected') => 'unselected'
            ]],
            ['dcDefaultMapsActions', 'doUpdateSelectedMap']
        );
        $ap->addAction(
            [__('Change') => [
                __('Change category') => 'category',
            ]],
            ['dcDefaultMapsActions', 'doChangeMapCategory']
        );
        $ap->addAction(
            [__('Change') => [
                __('Change language') => 'lang',
            ]],
            ['dcDefaultMapsActions', 'doChangeMapLang']
        );
        if (dcCore::app()->auth->check('admin', dcCore::app()->blog->id)) {
            $ap->addAction(
                [__('Change') => [
                    __('Change author') => 'author']],
                ['dcDefaultMapsActions', 'doChangeMapAuthor']
            );
        }
        if (dcCore::app()->auth->check('delete,contentadmin', dcCore::app()->blog->id)) {
            $ap->addAction(
                [__('Delete') => [
                    __('Delete') => 'delete']],
                ['dcDefaultMapsActions', 'doDeleteMap']
            );
        }
    }

    public static function doChangeMapStatus(dcMapsActionsPage $ap, $post)
    {
        switch ($ap->getAction()) {
            case 'unpublish': $status = 0;
                break;
            case 'schedule': $status = -1;
                break;
            case 'pending': $status = -2;
                break;
            default: $status = 1;
                break;
        }
        $posts_ids = $ap->getIDs();
        if (empty($posts_ids)) {
            throw new Exception(__('No element selected'));
        }
        dcCore::app()->blog->updPostsStatus($posts_ids, $status);

        $ap->redirect(true, ['upd' => 2]);
    }

    public static function doUpdateSelectedMap(dcMapsActionsPage $ap, $post)
    {
        $posts_ids = $ap->getIDs();
        if (empty($posts_ids)) {
            throw new Exception(__('No element selected'));
        }
        $action = $ap->getAction();
        dcCore::app()->blog->updPostsSelected($posts_ids, $action == 'selected');
        if ($action == 'selected') {
            $ap->redirect(true, ['upd' => 3]);
        } else {
            $ap->redirect(true, ['upd' => 4]);
        }
    }

    public static function doDeleteMap(dcMapsActionsPage $ap, $post)
    {
        $posts_ids = $ap->getIDs();
        if (empty($posts_ids)) {
            throw new Exception(__('No element selected'));
        }
        // Backward compatibility
        foreach ($posts_ids as $post_id) {
            // --BEHAVIOR-- adminBeforePostDelete
            dcCore::app()->callBehavior('adminBeforePostDelete', (int) $post_id);
        }

        // --BEHAVIOR-- adminBeforePostsDelete
        dcCore::app()->callBehavior('adminBeforePostsDelete', $posts_ids);

        dcCore::app()->blog->delPosts($posts_ids);

        $ap->redirect(true, ['upd' => 5]);
    }

    public static function doChangeMapCategory(dcMapsActionsPage $ap, $post)
    {
        if (isset($post['new_cat_id'])) {
            $posts_ids = $_POST['entries'];
            if (empty($posts_ids)) {
                throw new Exception(__('No element selected'));
            }
            $new_cat_id = $post['new_cat_id'];
            if (!empty($post['new_cat_title']) && dcCore::app()->auth->check('categories', dcCore::app()->blog->id)) {
                $cur_cat = dcCore::app()->con->openCursor(dcCore::app()->prefix . 'category');
                $cur_cat->cat_title = $post['new_cat_title'];
                $cur_cat->cat_url = '';
                $title = $cur_cat->cat_title;

                $parent_cat = !empty($post['new_cat_parent']) ? $post['new_cat_parent'] : '';

                // --BEHAVIOR-- adminBeforeCategoryCreate
                dcCore::app()->callBehavior('adminBeforeCategoryCreate', $cur_cat);

                $new_cat_id = dcCore::app()->blog->addCategory($cur_cat, (int) $parent_cat);

                // --BEHAVIOR-- adminAfterCategoryCreate
                dcCore::app()->callBehavior('adminAfterCategoryCreate', $cur_cat, $new_cat_id);
            }

            dcCore::app()->blog->updPostsCategory($posts_ids, $new_cat_id);
            $title = dcCore::app()->blog->getCategory($new_cat_id);

            $ap->redirect(true, ['upd' => 6]);
        } else {
            $ap->beginPage(
                dcPage::breadcrumb(
                    [
                        html::escapeHTML(dcCore::app()->blog->name) => '',
                        $ap->getCallerTitle() => $ap->getRedirection(true),
                        __('Change category for this selection') => ''
                    ]
                )
            );
            // categories list
            // Getting categories
            $categories_combo = dcAdminCombos::getCategoriesCombo(
                dcCore::app()->blog->getCategories()
            );
            echo
            '<form action="' . $ap->getURI() . '" method="post">' .
            $ap->getCheckboxes() .
            '<p><label for="new_cat_id" class="classic">' . __('Category:') . '</label> ' .
            form::combo(['new_cat_id'], $categories_combo, '');

            if (dcCore::app()->auth->check('categories', dcCore::app()->blog->id)) {
                echo
                '<div>' .
                '<p id="new_cat">' . __('Create a new category for the element(s)') . '</p>' .
                '<p><label for="new_cat_title">' . __('Title:') . '</label> ' .
                form::field('new_cat_title', 30, 255, '', '') . '</p>' .
                '<p><label for="new_cat_parent">' . __('Parent:') . '</label> ' .
                form::combo('new_cat_parent', $categories_combo, '', '') .
                '</p>' .
                '</div>';
            }

            echo
            dcCore::app()->formNonce() .
            $ap->getHiddenFields() .
            form::hidden(['action'], 'category') .
            '<input type="submit" value="' . __('Save') . '" /></p>' .
            '</form>';
            $ap->endPage();
        }
    }

    public static function doChangeMapAuthor(dcMapsActionsPage $ap, $post)
    {
        if (isset($post['new_auth_id']) && dcCore::app()->auth->check('admin', dcCore::app()->blog->id)) {
            $new_user_id = $post['new_auth_id'];
            $posts_ids = $_POST['entries'];
            if (empty($posts_ids)) {
                throw new Exception(__('No element selected'));
            }
            if (dcCore::app()->getUser($new_user_id)->isEmpty()) {
                throw new Exception(__('This user does not exist'));
            }

            $cur = dcCore::app()->con->openCursor(dcCore::app()->prefix . 'post');
            $cur->user_id = $new_user_id;
            $cur->update('WHERE post_id ' . dcCore::app()->con->in($posts_ids));

            $ap->redirect(true, ['upd' => 7]);
        } else {
            $usersList = '';
            if (dcCore::app()->auth->check('admin', dcCore::app()->blog->id)) {
                $params = [
                    'limit' => 100,
                    'order' => 'nb_post DESC'
                ];
                $rs = dcCore::app()->getUsers($params);
                while ($rs->fetch()) {
                    $usersList .= ($usersList != '' ? ',' : '') . '"' . $rs->user_id . '"';
                }
            }
            $ap->beginPage(
                dcPage::breadcrumb(
                    [
                        html::escapeHTML(dcCore::app()->blog->name) => '',
                        $ap->getCallerTitle() => $ap->getRedirection(true),
                        __('Change author for this selection') => '']
                ),
                dcPage::jsLoad('js/jquery/jquery.autocomplete.js') .
                    '<script>' . "\n" .
                    "//<![CDATA[\n" .
                    'usersList = [' . $usersList . ']' . "\n" .
                    "\n//]]>\n" .
                    "</script>\n"
            );

            echo
            '<form action="' . $ap->getURI() . '" method="post">' .
            $ap->getCheckboxes() .
            '<p><label for="new_auth_id" class="classic">' . __('New author (author ID):') . '</label> ' .
            form::field('new_auth_id', 20, 255);

            echo
                dcCore::app()->formNonce() . $ap->getHiddenFields() .
                form::hidden(['action'], 'author') .
                '<input type="submit" value="' . __('Save') . '" /></p>' .
                '</form>';
            $ap->endPage();
        }
    }

    public static function doChangeMapLang(dcMapsActionsPage $ap, $post)
    {
        $posts_ids = $_POST['entries'];
        if (empty($posts_ids)) {
            throw new Exception(__('No element selected'));
        }
        if (isset($post['new_lang'])) {
            $new_lang = $post['new_lang'];
            $cur = dcCore::app()->con->openCursor(dcCore::app()->prefix . 'post');
            $cur->post_lang = $new_lang;
            $cur->update('WHERE post_id ' . dcCore::app()->con->in($posts_ids));

            $ap->redirect(true, ['upd' => 8]);
        } else {
            $ap->beginPage(
                dcPage::breadcrumb(
                    [
                        html::escapeHTML(dcCore::app()->blog->name) => '',
                        $ap->getCallerTitle() => $ap->getRedirection(true),
                        __('Change language for this selection') => ''
                    ]
                )
            );
            // lang list
            // Languages combo
            $rs = dcCore::app()->blog->getLangs(['order' => 'asc']);
            $all_langs = l10n::getISOcodes(0, 1);
            $lang_combo = ['' => '', __('Most used') => [], __('Available') => l10n::getISOcodes(1, 1)];
            while ($rs->fetch()) {
                if (isset($all_langs[$rs->post_lang])) {
                    $lang_combo[__('Most used')][$all_langs[$rs->post_lang]] = $rs->post_lang;
                    unset($lang_combo[__('Available')][$all_langs[$rs->post_lang]]);
                } else {
                    $lang_combo[__('Most used')][$rs->post_lang] = $rs->post_lang;
                }
            }
            unset($all_langs, $rs);

            echo
            '<form action="' . $ap->getURI() . '" method="post">' .
            $ap->getCheckboxes() .

            '<p><label for="new_lang" class="classic">' . __('Element language:') . '</label> ' .
            form::combo('new_lang', $lang_combo, '');

            echo
                dcCore::app()->formNonce() . $ap->getHiddenFields() .
                form::hidden(['action'], 'lang') .
                '<input type="submit" value="' . __('Save') . '" /></p>' .
                '</form>';
            $ap->endPage();
        }
    }
}
