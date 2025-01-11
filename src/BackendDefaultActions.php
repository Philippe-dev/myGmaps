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

use ArrayObject;
use Dotclear\App;
use Dotclear\Core\Backend\Combos;
use Dotclear\Core\Backend\Notices;
use Dotclear\Core\Backend\Action\ActionsPostsDefault;
use Dotclear\Helper\Html\Html;
use Dotclear\Core\Backend\Page;
use Dotclear\Helper\L10n;
use Exception;
use form;

class BackendDefaultActions
{
    /**
     * Set pages actions
     *
     * @param      BackendActions  $ap     Admin actions instance
     */
    public static function adminPostsActionsPage(BackendActions $ap): void
    {
        if (App::auth()->check(App::auth()->makePermissions([
            App::auth()::PERMISSION_PUBLISH,
            App::auth()::PERMISSION_CONTENT_ADMIN,
        ]), App::blog()->id())) {
            $actions = [];
            foreach (App::status()->post()->dump(false) as $status) {
                $actions[__($status->name())] = $status->id();
            }
            $ap->addAction(
                [__('Status') => $actions],
                self::doChangePostStatus(...)
            );
        }

        $ap->addAction(
            [__('Mark') => [
                __('Mark as selected')   => 'selected',
                __('Mark as unselected') => 'unselected',
            ]],
            [self::class, 'doUpdateSelectedPost']
        );
        $ap->addAction(
            [__('Change') => [
                __('Change category') => 'category',
            ]],
            [self::class, 'doChangePostCategory']
        );
        $ap->addAction(
            [__('Change') => [
                __('Change language') => 'lang',
            ]],
            [self::class, 'doChangePostLang']
        );
        if (App::auth()->check(App::auth()->makePermissions([
            App::auth()::PERMISSION_ADMIN,
        ]), App::blog()->id)) {
            $ap->addAction(
                [__('Change') => [
                    __('Change author') => 'author', ]],
                [self::class, 'doChangePostAuthor']
            );
        }
        if (App::auth()->check(App::auth()->makePermissions([
            App::auth()::PERMISSION_DELETE,
            App::auth()::PERMISSION_CONTENT_ADMIN,
        ]), App::blog()->id)) {
            $ap->addAction(
                [__('Delete') => [
                    __('Delete') => 'delete', ]],
                [self::class, 'doDeletePost']
            );
        }
    }

    /**
     * Does a change post status.
     *
     * @param      BackendActions  $ap
     *
     * @throws     Exception             (description)
     */
    public static function doChangePostStatus(BackendActions $ap): void
    {
        // unknown to published
        $status = App::status()->post()->has((string) $ap->getAction()) ?
            App::status()->post()->level((string) $ap->getAction()) :
            App::status()->post()::PUBLISHED;

        $ids = $ap->getIDs();
        if ($ids === []) {
            throw new Exception(__('No element selected'));
        }

        // Do not switch to scheduled already published entries
        if ($status === App::status()->post()::SCHEDULED) {
            $rs           = $ap->getRS();
            $excluded_ids = [];
            if ($rs->rows()) {
                while ($rs->fetch()) {
                    if ((int) $rs->post_status >= App::status()->post()::PUBLISHED) {
                        $excluded_ids[] = (int) $rs->post_id;
                    }
                }
            }
            if ($excluded_ids !== []) {
                $ids = array_diff($ids, $excluded_ids);
            }
        }
        if ($ids === []) {
            throw new Exception(__('Published elements cannot be set to scheduled'));
        }

        // Set status of remaining entries
        App::blog()->updPostsStatus($ids, $status);

        Notices::addSuccessNotice(
            sprintf(
                __(
                    '%d element has been successfully updated to status : "%s"',
                    '%d elements have been successfully updated to status : "%s"',
                    count($ids)
                ),
                count($ids),
                App::status()->post()->name($status)
            )
        );

        $ap->redirect(true);
    }

    /**
     * Does an update selected post.
     *
     * @param      BackendActions  $ap
     *
     * @throws     Exception
     */
    public static function doUpdateSelectedPost(BackendActions $ap): void
    {
        $ids = $ap->getIDs();
        if (empty($ids)) {
            throw new Exception(__('No element selected'));
        }

        $action = $ap->getAction();
        App::blog()->updPostsSelected($ids, $action === 'selected');
        if ($action == 'selected') {
            Notices::addSuccessNotice(
                sprintf(
                    __(
                        '%d element has been successfully marked as selected',
                        '%d elements have been successfully marked as selected',
                        count($ids)
                    ),
                    count($ids)
                )
            );
        } else {
            Notices::addSuccessNotice(
                sprintf(
                    __(
                        '%d element has been successfully marked as unselected',
                        '%d elements have been successfully marked as unselected',
                        count($ids)
                    ),
                    count($ids)
                )
            );
        }
        $ap->redirect(true);
    }

    /**
     * Does a delete post.
     *
     * @param      BackendActions  $ap
     *
     * @throws     Exception
     */
    public static function doDeletePost(BackendActions $ap): void
    {
        $ids = $ap->getIDs();
        if (empty($ids)) {
            throw new Exception(__('No element selected'));
        }
        // Backward compatibility
        foreach ($ids as $id) {
            # --BEHAVIOR-- adminBeforePostDelete -- int
            App::behavior()->callBehavior('adminBeforePostDelete', (int) $id);
        }

        # --BEHAVIOR-- adminBeforePostsDelete -- array<int,string>
        App::behavior()->callBehavior('adminBeforePostsDelete', $ids);

        App::blog()->delPosts($ids);
        Notices::addSuccessNotice(
            sprintf(
                __(
                    '%d element has been successfully deleted',
                    '%d elements have been successfully deleted',
                    count($ids)
                ),
                count($ids)
            )
        );

        $ap->redirect(false);
    }

    /**
     * Does a change post category.
     *
     * @param      BackendActions       $ap
     * @param      ArrayObject          $post   The parameters ($_POST)
     *
     * @throws     Exception             If no entry selected
     */
    public static function doChangePostCategory(BackendActions $ap, ArrayObject $post): void
    {
        if (isset($post['new_cat_id'])) {
            $ids = $ap->getIDs();
            if (empty($ids)) {
                throw new Exception(__('No element selected'));
            }
            $new_cat_id = $post['new_cat_id'];
            if (!empty($post['new_cat_title']) && App::auth()->check(App::auth()->makePermissions([
                App::auth()::PERMISSION_CATEGORIES,
            ]), App::blog()->id)) {
                $cur_cat            = App::con()->openCursor(App::con()->prefix() . dcCategories::CATEGORY_TABLE_NAME);
                $cur_cat->cat_title = $post['new_cat_title'];
                $cur_cat->cat_url   = '';
                $title              = $cur_cat->cat_title;

                $parent_cat = !empty($post['new_cat_parent']) ? $post['new_cat_parent'] : '';

                # --BEHAVIOR-- adminBeforeCategoryCreate -- Cursor
                App::behavior()->callBehavior('adminBeforeCategoryCreate', $cur_cat);

                $new_cat_id = App::blog()->addCategory($cur_cat, (int) $parent_cat);

                # --BEHAVIOR-- adminAfterCategoryCreate -- Cursor, string
                App::behavior()->callBehavior('adminAfterCategoryCreate', $cur_cat, $new_cat_id);
            }

            App::blog()->updPostsCategory($ids, $new_cat_id);
            $title = __('(No cat)');
            if ($new_cat_id) {
                $title = App::blog()->getCategory((int) $new_cat_id)->cat_title;
            }
            Notices::addSuccessNotice(
                sprintf(
                    __(
                        '%d element has been successfully moved to category "%s"',
                        '%d elements have been successfully moved to category "%s"',
                        count($ids)
                    ),
                    count($ids),
                    Html::escapeHTML($title)
                )
            );

            $ap->redirect(true);
        } else {
            $ap->beginPage(
                Page::breadcrumb(
                    [
                        Html::escapeHTML(App::blog()->name)      => '',
                        $ap->getCallerTitle()                    => $ap->getRedirection(true),
                        __('Change category for this selection') => '',
                    ]
                )
            );
            # categories list
            # Getting categories
            $categories_combo = Combos::getCategoriesCombo(
                App::blog()->getCategories()
            );
            echo
            '<form action="' . $ap->getURI() . '" method="post">' .
            $ap->getCheckboxes() .
            '<p><label for="new_cat_id" class="classic">' . __('Category:') . '</label> ' .
            form::combo(['new_cat_id'], $categories_combo);

            if (App::auth()->check(App::auth()->makePermissions([
                App::auth()::PERMISSION_CATEGORIES,
            ]), App::blog()->id)) {
                echo
                '<div>' .
                '<p id="new_cat">' . __('Create a new category for the element(s)') . '</p>' .
                '<p><label for="new_cat_title">' . __('Title:') . '</label> ' .
                form::field('new_cat_title', 30, 255) . '</p>' .
                '<p><label for="new_cat_parent">' . __('Parent:') . '</label> ' .
                form::combo('new_cat_parent', $categories_combo) .
                    '</p>' .
                    '</div>';
            }

            echo
            App::nonce()->getFormNonce() .
            $ap->getHiddenFields() .
            form::hidden(['action'], 'category') .
            '<input type="submit" value="' . __('Save') . '"></p>' .
                '</form>';
            $ap->endPage();
        }
    }

    /**
     * Does a change post author.
     *
     * @param      BackendActions  $ap
     * @param      ArrayObject           $post   The parameters ($_POST)
     *
     * @throws     Exception             If no entry selected
     */
    public static function doChangePostAuthor(BackendActions $ap, ArrayObject $post): void
    {
        if (isset($post['new_auth_id']) && App::auth()->check(App::auth()->makePermissions([
            App::auth()::PERMISSION_ADMIN,
        ]), App::blog()->id)) {
            $new_user_id = $post['new_auth_id'];
            $ids         = $ap->getIDs();
            if (empty($ids)) {
                throw new Exception(__('No element selected'));
            }
            if (App::users()->getUser($new_user_id)->isEmpty()) {
                throw new Exception(__('This user does not exist'));
            }

            $cur          = App::con()->openCursor(App::con()->prefix() . App::blog()::POST_TABLE_NAME);
            $cur->user_id = $new_user_id;
            $cur->update('WHERE post_id ' . App::con()->in($ids));
            Notices::addSuccessNotice(
                sprintf(
                    __(
                        '%d element has been successfully set to user "%s"',
                        '%d elements have been successfully set to user "%s"',
                        count($ids)
                    ),
                    count($ids),
                    Html::escapeHTML($new_user_id)
                )
            );

            $ap->redirect(true);
        } else {
            $usersList = [];
            if (App::auth()->check(App::auth()->makePermissions([
                App::auth()::PERMISSION_ADMIN,
            ]), App::blog()->id)) {
                $params = [
                    'limit' => 100,
                    'order' => 'nb_post DESC',
                ];
                $rs       = App::users()->getUsers($params);
                $rsStatic = $rs->toStatic();
                $rsStatic->extend('rsExtUser');
                $rsStatic = $rsStatic->toExtStatic();
                $rsStatic->lexicalSort('user_id');
                while ($rsStatic->fetch()) {
                    $usersList[] = $rsStatic->user_id;
                }
            }
            $ap->beginPage(
                Page::breadcrumb(
                    [
                        Html::escapeHTML(App::blog()->name)    => '',
                        $ap->getCallerTitle()                  => $ap->getRedirection(true),
                        __('Change author for this selection') => '', ]
                ),
                Page::jsLoad('js/jquery/jquery.autocomplete.js') .
                Page::jsJson('users_list', $usersList)
            );

            echo
            '<form action="' . $ap->getURI() . '" method="post">' .
            $ap->getCheckboxes() .
            '<p><label for="new_auth_id" class="classic">' . __('New author (author ID):') . '</label> ' .
            form::field('new_auth_id', 20, 255);

            echo
            App::nonce()->getFormNonce() . $ap->getHiddenFields() .
            form::hidden(['action'], 'author') .
            '<input type="submit" value="' . __('Save') . '"></p>' .
                '</form>';
            $ap->endPage();
        }
    }

    /**
     * Does a change post language.
     *
     * @param      BackendActions  $ap
     * @param      ArrayObject           $post   The parameters ($_POST)
     *
     * @throws     Exception             If no entry selected
     */
    public static function doChangePostLang(BackendActions $ap, ArrayObject $post): void
    {
        $post_ids = $ap->getIDs();
        if (empty($post_ids)) {
            throw new Exception(__('No element selected'));
        }
        if (isset($post['new_lang'])) {
            $new_lang       = $post['new_lang'];
            $cur            = App::con()->openCursor(App::con()->prefix() . App::blog()::POST_TABLE_NAME);
            $cur->post_lang = $new_lang;
            $cur->update('WHERE post_id ' . App::con()->in($post_ids));
            Notices::addSuccessNotice(
                sprintf(
                    __(
                        '%d element has been successfully set to language "%s"',
                        '%d elements have been successfully set to language "%s"',
                        count($post_ids)
                    ),
                    count($post_ids),
                    Html::escapeHTML(L10n::getLanguageName($new_lang))
                )
            );
            $ap->redirect(true);
        } else {
            $ap->beginPage(
                Page::breadcrumb(
                    [
                        Html::escapeHTML(App::blog()->name)      => '',
                        $ap->getCallerTitle()                    => $ap->getRedirection(true),
                        __('Change language for this selection') => '',
                    ]
                )
            );
            # lang list
            # Languages combo
            $rs         = App::blog()->getLangs(['order' => 'asc']);
            $all_langs  = L10n::getISOcodes(false, true);
            $lang_combo = ['' => '', __('Most used') => [], __('Available') => L10n::getISOcodes(true, true)];
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
            form::combo('new_lang', $lang_combo);

            echo
            App::nonce()->getFormNonce() . $ap->getHiddenFields() .
            form::hidden(['action'], 'lang') .
            '<input type="submit" value="' . __('Save') . '"></p>' .
                '</form>';
            $ap->endPage();
        }
    }
}
