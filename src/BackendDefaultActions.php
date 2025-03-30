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
use Dotclear\Core\Backend\Page;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Core\Backend\Action\ActionsPosts;
use Dotclear\Helper\Html\Form\Div;
use Dotclear\Helper\Html\Form\Form;
use Dotclear\Helper\Html\Form\Hidden;
use Dotclear\Helper\Html\Form\Input;
use Dotclear\Helper\Html\Form\Label;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Text;
use Dotclear\Helper\Html\Form\Select;
use Dotclear\Helper\Html\Form\Submit;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\L10n;
use Dotclear\Schema\Extension\User;
use Exception;

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
            $ap->addAction(
                [__('Status') => App::status()->post()->action()],
                self::doChangePostStatus(...)
            );
        }
        if (App::auth()->check(App::auth()->makePermissions([
            App::auth()::PERMISSION_PUBLISH,
            App::auth()::PERMISSION_CONTENT_ADMIN,
        ]), App::blog()->id())) {
            $ap->addAction(
                [__('First publication') => [
                    __('Never published')   => 'never',
                    __('Already published') => 'already',
                ]],
                self::doChangePostFirstPub(...)
            );
        }
        $ap->addAction(
            [__('Mark') => [
                __('Mark as selected')   => 'selected',
                __('Mark as unselected') => 'unselected',
            ]],
            self::doUpdateSelectedPost(...)
        );
        $ap->addAction(
            [__('Change') => [
                __('Change category') => 'category',
            ]],
            self::doChangePostCategory(...)
        );
        $ap->addAction(
            [__('Change') => [
                __('Change language') => 'lang',
            ]],
            self::doChangePostLang(...)
        );
        if (App::auth()->check(App::auth()->makePermissions([
            App::auth()::PERMISSION_ADMIN,
        ]), App::blog()->id())) {
            $ap->addAction(
                [__('Change') => [
                    __('Change author') => 'author', ]],
                self::doChangePostAuthor(...)
            );
        }
        if (App::auth()->check(App::auth()->makePermissions([
            App::auth()::PERMISSION_DELETE,
            App::auth()::PERMISSION_CONTENT_ADMIN,
        ]), App::blog()->id())) {
            $ap->addAction(
                [__('Delete') => [
                    __('Delete') => 'delete', ]],
                self::doDeletePost(...)
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
     * Does a change post status.
     *
     * @param   BackendActions    $ap     The BackendActions instance
     *
     * @throws  Exception
     */
    public static function doChangePostFirstPub(BackendActions $ap): void
    {
        $status = match ($ap->getAction()) {
            'never'   => 0,
            'already' => 1,
            default   => null,
        };

        if (!is_null($status)) {
            $ids = $ap->getIDs();
            if ($ids === []) {
                throw new Exception(__('No element selected'));
            }

            // Set first publication flag of elements
            App::blog()->updPostsFirstPub($ids, $status);

            Notices::addSuccessNotice(
                sprintf(
                    __(
                        '%d element has been successfully updated as: "%s"',
                        '%d elements have been successfully updated as: "%s"',
                        count($ids)
                    ),
                    count($ids),
                    $status !== 0 ? __('Already published') : __('Never published')
                )
            );
        }
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
        if ($ids === []) {
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
        if ($ids === []) {
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
            if ($ids === []) {
                throw new Exception(__('No element selected'));
            }
            $new_cat_id = (int) $post['new_cat_id'];
            if (!empty($post['new_cat_title']) && App::auth()->check(App::auth()->makePermissions([
                App::auth()::PERMISSION_CATEGORIES,
            ]), App::blog()->id())) {
                $cur_cat            = App::blog()->categories()->openCategoryCursor();
                $cur_cat->cat_title = $post['new_cat_title'];
                $cur_cat->cat_url   = '';

                $parent_cat = empty($post['new_cat_parent']) ? '' : $post['new_cat_parent'];

                # --BEHAVIOR-- adminBeforeCategoryCreate -- Cursor
                App::behavior()->callBehavior('adminBeforeCategoryCreate', $cur_cat);

                $new_cat_id = App::blog()->addCategory($cur_cat, (int) $parent_cat);

                # --BEHAVIOR-- adminAfterCategoryCreate -- Cursor, string
                App::behavior()->callBehavior('adminAfterCategoryCreate', $cur_cat, $new_cat_id);
            }

            App::blog()->updPostsCategory($ids, $new_cat_id);
            $title = __('(No cat)');
            if ($new_cat_id !== 0) {
                $title = App::blog()->getCategory($new_cat_id)->cat_title;
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
                        Html::escapeHTML(App::blog()->name())    => '',
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

            $items = [
                $ap->checkboxes(),
                (new Para())
                    ->items([
                        (new Label(__('Category:'), Label::OUTSIDE_LABEL_BEFORE))
                            ->for('new_cat_id'),
                        (new Select('new_cat_id'))
                            ->items($categories_combo)
                            ->default(''),
                    ]),
            ];

            if (App::auth()->check(App::auth()->makePermissions([
                App::auth()::PERMISSION_CATEGORIES,
            ]), App::blog()->id())) {
                $items[] = (new Div())
                    ->items([
                        (new Text('p', __('Create a new category for the post(s)')))
                            ->id('new_cat'),
                        (new Para())
                            ->items([
                                (new Label(__('Title:'), Label::OUTSIDE_LABEL_BEFORE))
                                    ->for('new_cat_title'),
                                (new Input('new_cat_title'))
                                    ->size(30)
                                    ->maxlength(255)
                                    ->value(''),
                            ]),
                        (new Para())
                            ->items([
                                (new Label(__('Parent:'), Label::OUTSIDE_LABEL_BEFORE))
                                    ->for('new_cat_parent'),
                                (new Select('new_cat_parent'))
                                    ->items($categories_combo)
                                    ->default(''),
                            ]),
                    ]);
            }

            $items[] = (new Para())
                ->items([
                    App::nonce()->formNonce(),
                    ...$ap->hiddenFields(),
                    (new Hidden('action', 'category')),
                    (new Submit('save'))
                        ->value(__('Save')),
                ]);

            echo (new Form('dochangepostcategory'))
                ->method('post')
                ->action($ap->getURI())
                ->fields($items)
                ->render();

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
        ]), App::blog()->id())) {
            $new_user_id = $post['new_auth_id'];
            $ids         = $ap->getIDs();
            if ($ids === []) {
                throw new Exception(__('No element selected'));
            }
            if (App::users()->getUser($new_user_id)->isEmpty()) {
                throw new Exception(__('This user does not exist'));
            }

            $cur          = App::blog()->openPostCursor();
            $cur->user_id = $new_user_id;

            $sql = new UpdateStatement();
            $sql
                ->where('post_id ' . $sql->in($ids))
                ->update($cur);

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
            ]), App::blog()->id())) {
                $params = [
                    'limit' => 100,
                    'order' => 'nb_post DESC',
                ];
                $rs       = App::users()->getUsers($params);
                $rsStatic = $rs->toStatic();
                $rsStatic->extend(User::class);
                $rsStatic = $rsStatic->toExtStatic();
                $rsStatic->lexicalSort('user_id');
                while ($rsStatic->fetch()) {
                    $usersList[] = $rsStatic->user_id;
                }
            }
            $ap->beginPage(
                Page::breadcrumb(
                    [
                        Html::escapeHTML(App::blog()->name())  => '',
                        $ap->getCallerTitle()                  => $ap->getRedirection(true),
                        __('Change author for this selection') => '', ]
                ),
                Page::jsLoad('js/jquery/jquery.autocomplete.js') .
                Page::jsJson('users_list', $usersList)
            );

            echo (new Form('dochangepostauthor'))
                ->method('post')
                ->action($ap->getURI())
                ->fields([
                    $ap->checkboxes(),
                    (new Para())
                        ->items([
                            (new Label(__('New author (author ID):'), Label::OUTSIDE_LABEL_BEFORE))
                                ->for('new_auth_id'),
                            (new Input('new_auth_id'))
                                ->size(20)
                                ->maxlength(255)
                                ->value(''),
                        ]),
                    (new Para())
                        ->items([
                            App::nonce()->formNonce(),
                            ...$ap->hiddenFields(),
                            (new Hidden('action', 'author')),
                            (new Submit('save'))
                                ->value(__('Save')),

                        ]),
                ])
                ->render();

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
        $ids = $ap->getIDs();
        if ($ids === []) {
            throw new Exception(__('No element selected'));
        }
        if (isset($post['new_lang'])) {
            $new_lang       = $post['new_lang'];
            $cur            = App::blog()->openPostCursor();
            $cur->post_lang = $new_lang;

            $sql = new UpdateStatement();
            $sql
                ->where('post_id ' . $sql->in($ids))
                ->update($cur);

            Notices::addSuccessNotice(
                sprintf(
                    __(
                        '%d element has been successfully set to language "%s"',
                        '%d elements have been successfully set to language "%s"',
                        count($ids)
                    ),
                    count($ids),
                    Html::escapeHTML(L10n::getLanguageName($new_lang))
                )
            );
            $ap->redirect(true);
        } else {
            $ap->beginPage(
                Page::breadcrumb(
                    [
                        Html::escapeHTML(App::blog()->name())    => '',
                        $ap->getCallerTitle()                    => $ap->getRedirection(true),
                        __('Change language for this selection') => '',
                    ]
                )
            );
            // Prepare languages combo
            $lang_combo = Combos::getLangsCombo(
                App::blog()->getLangs([
                    'order_by' => 'nb_post',
                    'order'    => 'desc',
                ]),
                true    // Also show never used languages
            );

            echo (new Form('dochangepostlang'))
                ->method('post')
                ->action($ap->getURI())
                ->fields([
                    $ap->checkboxes(),
                    (new Para())
                        ->items([
                            (new Label(__('Element language:'), Label::OUTSIDE_LABEL_BEFORE))
                                ->for('new_lang'),
                            (new Select('new_lang'))
                                ->items($lang_combo)
                                ->default(''),
                        ]),
                    (new Para())
                        ->items([
                            App::nonce()->formNonce(),
                            ...$ap->hiddenFields(),
                            (new Hidden('action', 'lang')),
                            (new Submit('save'))
                                ->value(__('Save')),

                        ]),
                ])
                ->render();

            $ap->endPage();
        }
    }
}
