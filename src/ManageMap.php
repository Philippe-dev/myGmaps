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
use dcAdminCombos;
use dcBlog;
use dcCore;
use dcMedia;
use dcNsProcess;
use dcPage;
use dcAuth;
use Dotclear\Helper\Date;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Exception;
use form;

class ManageMap extends dcNsProcess
{
    public static function init(): bool
    {
        if (defined('DC_CONTEXT_ADMIN')) {
            static::$init = ($_REQUEST['act'] ?? 'list') === 'map';
        }

        return static::$init;
    }

    public static function process(): bool
    {
        if (!static::$init) {
            return false;
        }

        $params = [];
        dcPage::check(dcCore::app()->auth->makePermissions([
            dcCore::app()->auth::PERMISSION_CONTENT_ADMIN,
        ]));

        Date::setTZ(dcCore::app()->auth->getInfo('user_tz') ?? 'UTC');

        dcCore::app()->admin->redir_url = dcCore::app()->admin->getPageURL() . '&act=map';

        dcCore::app()->admin->post_id            = '';
        dcCore::app()->admin->post_dt            = '';
        dcCore::app()->admin->post_format        = dcCore::app()->auth->getOption('post_format');
        dcCore::app()->admin->post_editor        = dcCore::app()->auth->getOption('editor');
        dcCore::app()->admin->post_password      = '';
        dcCore::app()->admin->post_url           = '';
        dcCore::app()->admin->post_lang          = dcCore::app()->auth->getInfo('user_lang');
        dcCore::app()->admin->post_title         = '';
        dcCore::app()->admin->post_excerpt       = '';
        dcCore::app()->admin->post_excerpt_xhtml = '';
        dcCore::app()->admin->post_content       = '';
        dcCore::app()->admin->post_content_xhtml = '';
        dcCore::app()->admin->post_notes         = '';
        dcCore::app()->admin->post_status        = dcCore::app()->auth->getInfo('user_post_status');
        dcCore::app()->admin->post_open_comment  = false;
        dcCore::app()->admin->post_open_tb       = false;
        dcCore::app()->admin->post_selected      = false;

        dcCore::app()->admin->post_media = [];

        dcCore::app()->admin->page_title = __('New map element');

        dcCore::app()->admin->can_view_page = true;
        dcCore::app()->admin->can_edit_page = dcCore::app()->auth->check(dcCore::app()->auth->makePermissions([
            dcCore::app()->auth::PERMISSION_USAGE,
        ]), dcCore::app()->blog->id);
        dcCore::app()->admin->can_publish = dcCore::app()->auth->check(dcCore::app()->auth->makePermissions([
            dcCore::app()->auth::PERMISSION_PUBLISH,
            dcCore::app()->auth::PERMISSION_CONTENT_ADMIN,
        ]), dcCore::app()->blog->id);
        dcCore::app()->admin->can_delete = false;

        $post_headlink = '<link rel="%s" title="%s" href="' . Html::escapeURL(dcCore::app()->admin->redir_url) . '&amp;id=%s" />';

        dcCore::app()->admin->post_link = '<a href="' . Html::escapeURL(dcCore::app()->admin->redir_url) . '&amp;id=%s" title="%s">%s</a>';

        dcCore::app()->admin->next_link = dcCore::app()->admin->prev_link = dcCore::app()->admin->next_headlink = dcCore::app()->admin->prev_headlink = null;

        // If user can't publish
        if (!dcCore::app()->admin->can_publish) {
            dcCore::app()->admin->post_status = dcBlog::POST_PENDING;
        }

        // Getting categories
        dcCore::app()->admin->categories_combo = dcAdminCombos::getCategoriesCombo(
            dcCore::app()->blog->getCategories(['post_type' => 'map'])
        );

        // Status combo
        dcCore::app()->admin->status_combo = dcAdminCombos::getPostStatusesCombo();

        // Formaters combo
        $core_formaters    = dcCore::app()->getFormaters();
        $available_formats = ['' => ''];
        foreach ($core_formaters as $formats) {
            foreach ($formats as $format) {
                $available_formats[dcCore::app()->getFormaterName($format)] = $format;
            }
        }
        dcCore::app()->admin->available_formats = $available_formats;

        // Languages combo
        dcCore::app()->admin->lang_combo = dcAdminCombos::getLangsCombo(
            dcCore::app()->blog->getLangs(['order' => 'asc']),
            true
        );

        // Validation flag
        dcCore::app()->admin->bad_dt = false;

        // Get page informations

        dcCore::app()->admin->post = null;
        if (!empty($_REQUEST['id'])) {
            $params['post_id']   = $_REQUEST['id'];
            $params['post_type'] = 'map';

            dcCore::app()->admin->post = dcCore::app()->blog->getPosts($params);

            if (dcCore::app()->admin->post->isEmpty()) {
                dcCore::app()->error->add(__('This map element does not exist.'));
                dcCore::app()->admin->can_view_page = false;
            } else {
                dcCore::app()->admin->cat_id            = (int) dcCore::app()->admin->post->cat_id;
                dcCore::app()->admin->post_id            = (int) dcCore::app()->admin->post->post_id;
                dcCore::app()->admin->post_dt            = date('Y-m-d H:i', strtotime(dcCore::app()->admin->post->post_dt));
                dcCore::app()->admin->post_format        = dcCore::app()->admin->post->post_format;
                dcCore::app()->admin->post_password      = dcCore::app()->admin->post->post_password;
                dcCore::app()->admin->post_url           = dcCore::app()->admin->post->post_url;
                dcCore::app()->admin->post_lang          = dcCore::app()->admin->post->post_lang;
                dcCore::app()->admin->post_title         = dcCore::app()->admin->post->post_title;
                dcCore::app()->admin->post_excerpt       = dcCore::app()->admin->post->post_excerpt;
                dcCore::app()->admin->post_excerpt_xhtml = dcCore::app()->admin->post->post_excerpt_xhtml;
                dcCore::app()->admin->post_content       = dcCore::app()->admin->post->post_content;
                dcCore::app()->admin->post_content_xhtml = dcCore::app()->admin->post->post_content_xhtml;
                dcCore::app()->admin->post_notes         = dcCore::app()->admin->post->post_notes;
                dcCore::app()->admin->post_status        = dcCore::app()->admin->post->post_status;
                dcCore::app()->admin->post_open_comment  = (bool) dcCore::app()->admin->post->post_open_comment;
                dcCore::app()->admin->post_open_tb       = (bool) dcCore::app()->admin->post->post_open_tb;
                dcCore::app()->admin->post_selected      = (bool) dcCore::app()->admin->post->post_selected;

                dcCore::app()->admin->page_title = __('Edit map element');

                dcCore::app()->admin->can_edit_page = dcCore::app()->admin->post->isEditable();
                dcCore::app()->admin->can_delete    = dcCore::app()->admin->post->isDeletable();

                $next_rs = dcCore::app()->blog->getNextPost(dcCore::app()->admin->post, 1);
                $prev_rs = dcCore::app()->blog->getNextPost(dcCore::app()->admin->post, -1);

                if ($next_rs !== null) {
                    dcCore::app()->admin->next_link = sprintf(
                        dcCore::app()->admin->post_link,
                        $next_rs->post_id,
                        Html::escapeHTML(trim(Html::clean($next_rs->post_title))),
                        __('Next element') . '&nbsp;&#187;'
                    );
                    dcCore::app()->admin->next_headlink = sprintf(
                        $post_headlink,
                        'next',
                        Html::escapeHTML(trim(Html::clean($next_rs->post_title))),
                        $next_rs->post_id
                    );
                }

                if ($prev_rs !== null) {
                    dcCore::app()->admin->prev_link = sprintf(
                        dcCore::app()->admin->post_link,
                        $prev_rs->post_id,
                        Html::escapeHTML(trim(Html::clean($prev_rs->post_title))),
                        '&#171;&nbsp;' . __('Previous element')
                    );
                    dcCore::app()->admin->prev_headlink = sprintf(
                        $post_headlink,
                        'previous',
                        Html::escapeHTML(trim(Html::clean($prev_rs->post_title))),
                        $prev_rs->post_id
                    );
                }

                try {
                    dcCore::app()->media             = new dcMedia();
                    dcCore::app()->admin->post_media = dcCore::app()->media->getPostMedia(dcCore::app()->admin->post_id);
                } catch (Exception $e) {
                    dcCore::app()->error->add($e->getMessage());
                }
            }
        }

        if (!empty($_POST) && dcCore::app()->admin->can_edit_page) {
            // Format content

            dcCore::app()->admin->post_format  = $_POST['post_format'];
            dcCore::app()->admin->post_excerpt = $_POST['post_excerpt'];
            dcCore::app()->admin->post_content = $_POST['post_content'];

            dcCore::app()->admin->post_title = $_POST['post_title'];

            if (isset($_POST['post_status'])) {
                dcCore::app()->admin->post_status = (int) $_POST['post_status'];
            }

            if (empty($_POST['post_dt'])) {
                dcCore::app()->admin->post_dt = '';
            } else {
                try {
                    dcCore::app()->admin->post_dt = strtotime($_POST['post_dt']);
                    if (!dcCore::app()->admin->post_dt || dcCore::app()->admin->post_dt == -1) {
                        dcCore::app()->admin->bad_dt = true;

                        throw new Exception(__('Invalid publication date'));
                    }
                    dcCore::app()->admin->post_dt = date('Y-m-d H:i', dcCore::app()->admin->post_dt);
                } catch (Exception $e) {
                    dcCore::app()->error->add($e->getMessage());
                }
            }

            dcCore::app()->admin->post_open_comment = !empty($_POST['post_open_comment']);
            dcCore::app()->admin->post_open_tb      = !empty($_POST['post_open_tb']);
            dcCore::app()->admin->post_selected     = !empty($_POST['post_selected']);
            dcCore::app()->admin->post_lang         = $_POST['post_lang'];
            dcCore::app()->admin->post_password     = !empty($_POST['post_password']) ? $_POST['post_password'] : null;

            dcCore::app()->admin->post_notes = $_POST['post_notes'];

            if (isset($_POST['post_url'])) {
                dcCore::app()->admin->post_url = $_POST['post_url'];
            }

            [
                $post_excerpt, $post_excerpt_xhtml, $post_content, $post_content_xhtml
            ] = [
                dcCore::app()->admin->post_excerpt,
                dcCore::app()->admin->post_excerpt_xhtml,
                dcCore::app()->admin->post_content,
                dcCore::app()->admin->post_content_xhtml,
            ];

            dcCore::app()->blog->setPostContent(
                dcCore::app()->admin->post_id,
                dcCore::app()->admin->post_format,
                dcCore::app()->admin->post_lang,
                $post_excerpt,
                $post_excerpt_xhtml,
                $post_content,
                $post_content_xhtml
            );

            [
                dcCore::app()->admin->post_excerpt,
                dcCore::app()->admin->post_excerpt_xhtml,
                dcCore::app()->admin->post_content,
                dcCore::app()->admin->post_content_xhtml
            ] = [
                $post_excerpt, $post_excerpt_xhtml, $post_content, $post_content_xhtml,
            ];
        }

        if (!empty($_POST['delete']) && dcCore::app()->admin->can_delete) {
            // Delete page

            try {
                # --BEHAVIOR-- adminBeforePageDelete -- int
                dcCore::app()->callBehavior('adminBeforePageDelete', dcCore::app()->admin->post_id);
                dcCore::app()->blog->delPost(dcCore::app()->admin->post_id);
                Http::redirect(dcCore::app()->admin->getPageURL());
            } catch (Exception $e) {
                dcCore::app()->error->add($e->getMessage());
            }
        }

        if (!empty($_POST) && !empty($_POST['save']) && dcCore::app()->admin->can_edit_page && !dcCore::app()->admin->bad_dt) {
            // Create or update page

            $cur = dcCore::app()->con->openCursor(dcCore::app()->prefix . dcBlog::POST_TABLE_NAME);

            // Magic tweak :)
            dcCore::app()->blog->settings->system->post_url_format = '{t}';

            $cur->post_type          = 'map';
            $cur->post_dt            = dcCore::app()->admin->post_dt ? date('Y-m-d H:i:00', strtotime(dcCore::app()->admin->post_dt)) : '';
            $cur->post_format        = dcCore::app()->admin->post_format;
            $cur->post_password      = dcCore::app()->admin->post_password;
            $cur->post_lang          = dcCore::app()->admin->post_lang;
            $cur->post_title         = dcCore::app()->admin->post_title;
            $cur->post_excerpt       = dcCore::app()->admin->post_excerpt;
            $cur->post_excerpt_xhtml = dcCore::app()->admin->post_excerpt_xhtml;
            $cur->post_content       = dcCore::app()->admin->post_content;
            $cur->post_content_xhtml = dcCore::app()->admin->post_content_xhtml;
            $cur->post_notes         = dcCore::app()->admin->post_notes;
            $cur->post_status        = dcCore::app()->admin->post_status;
            $cur->post_open_comment  = (int) dcCore::app()->admin->post_open_comment;
            $cur->post_open_tb       = (int) dcCore::app()->admin->post_open_tb;
            $cur->post_selected      = (int) dcCore::app()->admin->post_selected;

            if (isset($_POST['post_url'])) {
                $cur->post_url = dcCore::app()->admin->post_url;
            }

            // Back to UTC in order to keep UTC datetime for creadt/upddt
            Date::setTZ('UTC');

            if (dcCore::app()->admin->post_id) {
                try {
                    if (isset($_POST['element_type'])) {
                        $tags           = $_POST['element_type'];
                        $myGmaps_center = $_POST['myGmaps_center'];
                        $myGmaps_zoom   = $_POST['myGmaps_zoom'];
                        $myGmaps_type   = $_POST['myGmaps_type'];
                        $meta           = dcCore::app()->meta;

                        $meta->delPostMeta($post_id, 'map');
                        $meta->delPostMeta($post_id, 'map_options');
                        $meta->delPostMeta($post_id, 'description');

                        foreach ($meta->splitMetaValues($tags) as $tag) {
                            $meta->setPostMeta($post_id, 'map', $tag);
                        }
                        $map_options = $myGmaps_center . ',' . $myGmaps_zoom . ',' . $myGmaps_type;
                        $meta->setPostMeta($post_id, 'map_options', $map_options);
                        $meta->setPostMeta($post_id, 'description', $description);
                    }
                    // --BEHAVIOR-- adminBeforePostUpdate
                    dcCore::app()->callBehavior('adminBeforePostUpdate', $cur, $post_id);

                    dcCore::app()->blog->updPost($post_id, $cur);

                    // --BEHAVIOR-- adminAfterPostUpdate
                    dcCore::app()->callBehavior('adminAfterPostUpdate', $cur, $post_id);

                    http::redirect('' . dcCore::app()->admin->getPageURL() . '&do=edit&id=' . $post_id . '&upd=1');
                } catch (Exception $e) {
                    dcCore::app()->error->add($e->getMessage());
                }
            } else {
                $cur->user_id = dcCore::app()->auth->userID();

                try {
                    if (isset($_POST['element_type'])) {
                        $tags           = $_POST['element_type'];
                        $myGmaps_center = $_POST['myGmaps_center'];
                        $myGmaps_zoom   = $_POST['myGmaps_zoom'];
                        $myGmaps_type   = $_POST['myGmaps_type'];
                        $meta           = dcCore::app()->meta;

                        foreach ($meta->splitMetaValues($tags) as $tag) {
                            $meta->setPostMeta($return_id, 'map', $tag);
                        }
                        $map_options = $myGmaps_center . ',' . $myGmaps_zoom . ',' . $myGmaps_type;
                        $meta->setPostMeta($return_id, 'map_options', $map_options);
                        $meta->setPostMeta($return_id, 'description', $description);
                    }

                    // --BEHAVIOR-- adminBeforePostCreate
                    dcCore::app()->callBehavior('adminBeforePostCreate', $cur);

                    $return_id = dcCore::app()->blog->addPost($cur);

                    // --BEHAVIOR-- adminAfterPostCreate
                    dcCore::app()->callBehavior('adminAfterPostCreate', $cur, $return_id);

                    http::redirect('' . dcCore::app()->admin->getPageURL() . '&do=edit&id=' . $return_id . '&crea=1');
                } catch (Exception $e) {
                    dcCore::app()->error->add($e->getMessage());
                }
            }

            if (!empty($_POST['delete']) && $can_delete) {
                try {
                    // --BEHAVIOR-- adminBeforePostDelete
                    dcCore::app()->callBehavior('adminBeforePostDelete', $post_id);
                    dcCore::app()->blog->delPost($post_id);
                    http::redirect(dcCore::app()->admin->getPageURL() . '&do=list');
                } catch (Exception $e) {
                    dcCore::app()->error->add($e->getMessage());
                }
            }
        }

        return true;
    }

    /**
     * Renders the page.
     */
    public static function render(): void
    {
        if (!static::$init) {
            return;
        }

        dcCore::app()->admin->default_tab = 'edit-entry';
        if (!dcCore::app()->admin->can_edit_page) {
            dcCore::app()->admin->default_tab = '';
        }
        if (!empty($_GET['co'])) {
            dcCore::app()->admin->default_tab = 'comments';
        }

        $admin_post_behavior = '';
        if (dcCore::app()->admin->post_editor) {
            $p_edit = $c_edit = '';
            if (!empty(dcCore::app()->admin->post_editor[dcCore::app()->admin->post_format])) {
                $p_edit = dcCore::app()->admin->post_editor[dcCore::app()->admin->post_format];
            }
            if (!empty(dcCore::app()->admin->post_editor['xhtml'])) {
                $c_edit = dcCore::app()->admin->post_editor['xhtml'];
            }
            if ($p_edit == $c_edit) {
                # --BEHAVIOR-- adminPostEditor -- string, string, string, array<int,string>, string
                $admin_post_behavior .= dcCore::app()->callBehavior(
                    'adminPostEditor',
                    $p_edit,
                    'map',
                    ['#post_excerpt', '#post_content', '#comment_content'],
                    dcCore::app()->admin->post_format
                );
            } else {
                # --BEHAVIOR-- adminPostEditor -- string, string, string, array<int,string>, string
                $admin_post_behavior .= dcCore::app()->callBehavior(
                    'adminPostEditor',
                    $p_edit,
                    'map',
                    ['#post_excerpt', '#post_content'],
                    dcCore::app()->admin->post_format
                );
                # --BEHAVIOR-- adminPostEditor -- string, string, string, array<int,string>, string
                $admin_post_behavior .= dcCore::app()->callBehavior(
                    'adminPostEditor',
                    $c_edit,
                    'comment',
                    ['#comment_content'],
                    'xhtml'
                );
            }
        }

        dcPage::openModule(
            dcCore::app()->admin->page_title . ' - ' . __('Google Maps'),
            dcPage::jsModal() .
            dcPage::jsJson('pages_page', ['confirm_delete_post' => __('Are you sure you want to delete this page?')]) .
            dcPage::jsLoad('js/_post.js') .
            $admin_post_behavior .
            dcPage::jsConfirmClose('entry-form') .
            # --BEHAVIOR-- adminPageHeaders --
            dcCore::app()->callBehavior('adminPageHeaders') .
            dcPage::jsPageTabs(dcCore::app()->admin->default_tab) .
            dcCore::app()->admin->next_headlink . "\n" . dcCore::app()->admin->prev_headlink
        );

        $img_status         = '';
        $img_status_pattern = '<img class="img_select_option" alt="%1$s" title="%1$s" src="images/%2$s" />';

        if (dcCore::app()->admin->post_id) {
            switch (dcCore::app()->admin->post_status) {
                case dcBlog::POST_PUBLISHED:
                    $img_status = sprintf($img_status_pattern, __('Published'), 'check-on.png');

                    break;
                case dcBlog::POST_UNPUBLISHED:
                    $img_status = sprintf($img_status_pattern, __('Unpublished'), 'check-off.png');

                    break;
                case dcBlog::POST_SCHEDULED:
                    $img_status = sprintf($img_status_pattern, __('Scheduled'), 'scheduled.png');

                    break;
                case dcBlog::POST_PENDING:
                    $img_status = sprintf($img_status_pattern, __('Pending'), 'check-wrn.png');

                    break;
            }
            $edit_entry_title = '&ldquo;' . Html::escapeHTML(trim(Html::clean(dcCore::app()->admin->post_title))) . '&rdquo;' . ' ' . $img_status;
        } else {
            $edit_entry_title = dcCore::app()->admin->page_title;
        }
        echo dcPage::breadcrumb(
            [
                Html::escapeHTML(dcCore::app()->blog->name) => '',
                __('Google Maps')                           => dcCore::app()->admin->getPageURL(),
                $edit_entry_title                           => '',
            ]
        );

        if (!empty($_GET['upd'])) {
            dcPage::success(__('Map element has been updated.'));
        } elseif (!empty($_GET['crea'])) {
            dcPage::success(__('Map element has been created.'));
        }

        # HTML conversion
        if (!empty($_GET['xconv'])) {
            dcCore::app()->admin->post_excerpt = dcCore::app()->admin->post_excerpt_xhtml;
            dcCore::app()->admin->post_content = dcCore::app()->admin->post_content_xhtml;
            dcCore::app()->admin->post_format  = 'xhtml';

            dcPage::message(__('Don\'t forget to validate your HTML conversion by saving your post.'));
        }

        echo '';

        if (dcCore::app()->admin->post_id) {
            echo
            '<p class="nav_prevnext">';
            if (dcCore::app()->admin->prev_link) {
                echo
                dcCore::app()->admin->prev_link;
            }
            if (dcCore::app()->admin->next_link && dcCore::app()->admin->prev_link) {
                echo
                ' | ';
            }
            if (dcCore::app()->admin->next_link) {
                echo
                dcCore::app()->admin->next_link;
            }

            # --BEHAVIOR-- adminPageNavLinks -- MetaRecord|null
            dcCore::app()->callBehavior('adminPageNavLinks', dcCore::app()->admin->post ?? null);

            echo
            '</p>';
        }

        # Exit if we cannot view page
        if (!dcCore::app()->admin->can_view_page) {
            dcPage::closeModule();

            return;
        }

        /* Post form if we can edit page
        -------------------------------------------------------- */
        if (dcCore::app()->admin->can_edit_page) {
            $sidebar_items = new ArrayObject([
                'status-box' => [
                    'title' => __('Status'),
                    'items' => [
                        'post_status' => '<p><label for="post_status">' . __('Element status') . '</label> ' .
                        form::combo(
                            'post_status',
                            dcCore::app()->admin->status_combo,
                            ['default' => dcCore::app()->admin->post_status, 'disabled' => !dcCore::app()->admin->can_publish]
                        ) .
                        '</p>',
                        'post_dt' => '<p><label for="post_dt">' . __('Publication date and hour') . '</label>' .
                        form::datetime('post_dt', [
                            'default' => Html::escapeHTML(Date::str('%Y-%m-%dT%H:%M', strtotime(dcCore::app()->admin->post_dt))),
                            'class'   => (dcCore::app()->admin->bad_dt ? 'invalid' : ''),
                        ]) .
                        '</p>',
                        'post_lang' => '<p><label for="post_lang">' . __('Element language') . '</label>' .
                        form::combo('post_lang', dcCore::app()->admin->lang_combo, dcCore::app()->admin->post_lang) .
                        '</p>',
                        'post_format' => '<div>' .
                        '<h5 id="label_format"><label for="post_format" class="classic">' . __('Text formatting') . '</label></h5>' .
                        '<p>' . form::combo('post_format', dcCore::app()->admin->available_formats, dcCore::app()->admin->post_format, 'maximal') . '</p>' .
                        '<p class="format_control control_wiki">' .
                        '<a id="convert-xhtml" class="button' . (dcCore::app()->admin->post_id && dcCore::app()->admin->post_format != 'wiki' ? ' hide' : '') .
                        '" href="' . Html::escapeURL(dcCore::app()->admin->redir_url) . '&amp;id=' . dcCore::app()->admin->post_id . '&amp;xconv=1">' .
                        __('Convert to HTML') . '</a></p></div>', ], ],
                'metas-box' => [
                    'title' => __('Filing'),
                    'items' => [
                        'post_selected' => '<p><label for="post_selected" class="classic">' .
                        form::checkbox('post_selected', 1, dcCore::app()->admin->post_selected) . ' ' .
                        __('Selected entry') . '</label></p>',
                        'cat_id' => '<div>' .
                        '<h5 id="label_cat_id">' . __('Category') . '</h5>' .
                        '<p><label for="cat_id">' . __('Category:') . '</label>' .
                        form::combo('cat_id', dcCore::app()->admin->categories_combo, dcCore::app()->admin->cat_id, 'maximal') .
                        '</p>' .
                        (dcCore::app()->auth->check(dcCore::app()->auth->makePermissions([
                            dcAuth::PERMISSION_CATEGORIES,
                        ]), dcCore::app()->blog->id) ?
                            '<div>' .
                            '<h5 id="create_cat">' . __('Add a new category') . '</h5>' .
                            '<p><label for="new_cat_title">' . __('Title:') . ' ' .
                            form::field('new_cat_title', 30, 255, ['class' => 'maximal']) . '</label></p>' .
                            '<p><label for="new_cat_parent">' . __('Parent:') . ' ' .
                            form::combo('new_cat_parent', dcCore::app()->admin->categories_combo, '', 'maximal') .
                            '</label></p>' .
                            '</div>' :
                            '') .
                        '</div>',
                    ],
                ],
            ]);
            $main_items = new ArrayObject(
                [
                    'post_title' => '<p class="col">' .
                    '<label class="required no-margin bold" for="post_title"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Title:') . '</label>' .
                    form::field('post_title', 20, 255, [
                        'default'    => Html::escapeHTML(dcCore::app()->admin->post_title),
                        'class'      => 'maximal',
                        'extra_html' => 'required placeholder="' . __('Title') . '" lang="' . dcCore::app()->admin->post_lang . '" spellcheck="true"',
                    ]) .
                    '</p>',

                    'post_excerpt' => '<p class="area" id="excerpt-area"><label for="post_excerpt" class="bold">' . __('Excerpt:') . ' <span class="form-note">' .
                    __('Introduction to the page.') . '</span></label> ' .
                    form::textarea(
                        'post_excerpt',
                        50,
                        5,
                        [
                            'default'    => Html::escapeHTML(dcCore::app()->admin->post_excerpt),
                            'extra_html' => 'lang="' . dcCore::app()->admin->post_lang . '" spellcheck="true"',
                        ]
                    ) .
                    '</p>',

                    'post_content' => '<p class="area" id="content-area"><label class="required bold" ' .
                    'for="post_content"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Content:') . '</label> ' .
                    form::textarea(
                        'post_content',
                        50,
                        dcCore::app()->auth->getOption('edit_size'),
                        [
                            'default'    => Html::escapeHTML(dcCore::app()->admin->post_content),
                            'extra_html' => 'required placeholder="' . __('Content') . '" lang="' . dcCore::app()->admin->post_lang . '" spellcheck="true"',
                        ]
                    ) .
                    '</p>',

                    'post_notes' => '<p class="area" id="notes-area"><label for="post_notes" class="bold">' . __('Personal notes:') . ' <span class="form-note">' .
                    __('Unpublished notes.') . '</span></label>' .
                    form::textarea(
                        'post_notes',
                        50,
                        5,
                        [
                            'default'    => Html::escapeHTML(dcCore::app()->admin->post_notes),
                            'extra_html' => 'lang="' . dcCore::app()->admin->post_lang . '" spellcheck="true"',
                        ]
                    ) .
                    '</p>',
                ]
            );

            # --BEHAVIOR-- adminPostFormItems -- ArrayObject, ArrayObject, MetaRecord|null
            dcCore::app()->callBehavior('adminPageFormItems', $main_items, $sidebar_items, dcCore::app()->admin->post ?? null);

            echo
            '<div class="multi-part" title="' . (dcCore::app()->admin->post_id ? __('Edit page') : __('New page')) .
            sprintf(' &rsaquo; %s', dcCore::app()->getFormaterName(dcCore::app()->admin->post_format)) . '" id="edit-entry">' .
            '<form action="' . Html::escapeURL(dcCore::app()->admin->redir_url) . '" method="post" id="entry-form">' .
            '<div id="entry-wrapper">' .
            '<div id="entry-content"><div class="constrained">' .
            '<h3 class="out-of-screen-if-js">' . __('Edit element') . '</h3>';

            foreach ($main_items as $item) {
                echo $item;
            }

            # --BEHAVIOR-- adminPageForm -- MetaRecord|null
            dcCore::app()->callBehavior('adminPageForm', dcCore::app()->admin->post ?? null);

            echo
            '<p class="border-top">' .
            (dcCore::app()->admin->post_id ? form::hidden('id', dcCore::app()->admin->post_id) : '') .
            '<input type="submit" value="' . __('Save') . ' (s)" accesskey="s" name="save" /> ';

            if (dcCore::app()->admin->post_id) {
                $preview_url = dcCore::app()->blog->url .
                    dcCore::app()->url->getURLFor(
                        'pagespreview',
                        dcCore::app()->auth->userID() . '/' .
                        Http::browserUID(DC_MASTER_KEY . dcCore::app()->auth->userID() . dcCore::app()->auth->cryptLegacy(dcCore::app()->auth->userID())) .
                        '/' . dcCore::app()->admin->post->post_url
                    );

                // Prevent browser caching on preview
                $preview_url .= (parse_url($preview_url, PHP_URL_QUERY) ? '&' : '?') . 'rand=' . md5((string) random_int(0, mt_getrandmax()));

                $blank_preview = dcCore::app()->auth->user_prefs->interface->blank_preview;

                $preview_class  = $blank_preview ? '' : ' modal';
                $preview_target = $blank_preview ? '' : ' target="_blank"';

                echo
                '<a id="post-preview" href="' . $preview_url . '" class="button' . $preview_class . '" accesskey="p"' . $preview_target . '>' . __('Preview') . ' (p)' . '</a>' .
                ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />';
            } else {
                echo
                '<a id="post-cancel" href="' . dcCore::app()->adminurl->get('admin.home') . '" class="button" accesskey="c">' . __('Cancel') . ' (c)</a>';
            }

            echo(dcCore::app()->admin->can_delete ?
                ' <input type="submit" class="delete" value="' . __('Delete') . '" name="delete" />' :
                '') .
            dcCore::app()->formNonce() .
            '</p>';

            echo
            '</div></div>' . // End #entry-content
            '</div>' .       // End #entry-wrapper

            '<div id="entry-sidebar" role="complementary">';

            foreach ($sidebar_items as $id => $c) {
                echo
                '<div id="' . $id . '" class="sb-box">' .
                '<h4>' . $c['title'] . '</h4>';
                foreach ($c['items'] as $e_content) {
                    echo $e_content;
                }
                echo
                '</div>';
            }

            # --BEHAVIOR-- adminPageFormSidebar -- MetaRecord|null
            dcCore::app()->callBehavior('adminPageFormSidebar', dcCore::app()->admin->post ?? null);

            echo
            '</div>' . // End #entry-sidebar
            '</form>';

            # --BEHAVIOR-- adminPostForm -- MetaRecord|null
            dcCore::app()->callBehavior('adminPageAfterForm', dcCore::app()->admin->post ?? null);

            echo
            '</div>'; // End

            if (dcCore::app()->admin->post_id && !empty(dcCore::app()->admin->post_media)) {
                echo
                '<form action="' . dcCore::app()->adminurl->get('admin.post.media') . '" id="attachment-remove-hide" method="post">' .
                '<div>' .
                form::hidden(['post_id'], dcCore::app()->admin->post_id) .
                form::hidden(['media_id'], '') .
                form::hidden(['remove'], 1) .
                dcCore::app()->formNonce() .
                '</div>' .
                '</form>';
            }
        }

        dcPage::helpBlock('myGmap', 'core_wiki');

        dcPage::closeModule();
    }
}
