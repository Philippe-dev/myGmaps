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

use ArrayObject;
use Dotclear\App;
use Dotclear\Core\Backend\Action\ActionsComments;
use Dotclear\Helper\Process\TraitProcess;
use Dotclear\Database\MetaRecord;
use Dotclear\Helper\Date;
use Dotclear\Helper\Html\Form\Btn;
use Dotclear\Helper\Html\Form\Capture;
use Dotclear\Helper\Html\Form\Checkbox;
use Dotclear\Helper\Html\Form\Datetime;
use Dotclear\Helper\Html\Form\Div;
use Dotclear\Helper\Html\Form\Form;
use Dotclear\Helper\Html\Form\Hidden;
use Dotclear\Helper\Html\Form\Input;
use Dotclear\Helper\Html\Form\Label;
use Dotclear\Helper\Html\Form\Link;
use Dotclear\Helper\Html\Form\None;
use Dotclear\Helper\Html\Form\Note;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Password;
use Dotclear\Helper\Html\Form\Select;
use Dotclear\Helper\Html\Form\Span;
use Dotclear\Helper\Html\Form\Submit;
use Dotclear\Helper\Html\Form\Text;
use Dotclear\Helper\Html\Form\Textarea;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Dotclear\Helper\Text as Txt;
use Exception;

class ManageMap
{
    use TraitProcess;
    
    public static function init(): bool
    {
        $params = [];
        App::backend()->page()->check(App::auth()->makePermissions([
            App::auth()::PERMISSION_USAGE,
            App::auth()::PERMISSION_CONTENT_ADMIN,
        ]));

        Date::setTZ(App::auth()->getInfo('user_tz') ?? 'UTC');

        App::backend()->post_id            = '';
        App::backend()->cat_id             = '';
        App::backend()->post_dt            = '';
        App::backend()->post_format        = App::auth()->getOption('post_format');
        App::backend()->post_editor        = App::auth()->getOption('editor');
        App::backend()->post_password      = '';
        App::backend()->post_url           = '';
        App::backend()->post_lang          = App::auth()->getInfo('user_lang');
        App::backend()->post_title         = '';
        App::backend()->post_excerpt       = '';
        App::backend()->post_excerpt_xhtml = '';
        App::backend()->post_content       = '';
        App::backend()->post_content_xhtml = '';
        App::backend()->post_notes         = '';
        App::backend()->post_status        = App::auth()->getInfo('user_post_status');
        App::backend()->post_selected      = false;
        App::backend()->post_open_comment  = App::blog()->settings()->system->allow_comments;
        App::backend()->post_open_tb       = App::blog()->settings()->system->allow_trackbacks;

        App::backend()->page_title = __('New element');

        App::backend()->can_view_page = true;
        App::backend()->can_edit_post = App::auth()->check(App::auth()->makePermissions([
            App::auth()::PERMISSION_USAGE,
            App::auth()::PERMISSION_CONTENT_ADMIN,
        ]), App::blog()->id());
        App::backend()->can_publish = App::auth()->check(App::auth()->makePermissions([
            App::auth()::PERMISSION_PUBLISH,
            App::auth()::PERMISSION_CONTENT_ADMIN,
        ]), App::blog()->id());
        App::backend()->can_delete = false;

        $post_headlink            = '<link rel="%s" title="%s" href="' . App::backend()->url()->get('admin.post', ['id' => '%s'], '&amp;', true) . '">';
        App::backend()->post_link = '<a href="' . App::backend()->url()->get('admin.post', ['id' => '%s'], '&amp;', true) . '" class="%s" title="%s">%s</a>';

        App::backend()->next_link     = null;
        App::backend()->prev_link     = null;
        App::backend()->next_headlink = null;
        App::backend()->prev_headlink = null;

        # If user can't publish
        if (!App::backend()->can_publish) {
            App::backend()->post_status = App::status()->post()::PENDING;
        }

        # Getting categories
        App::backend()->categories_combo = App::backend()->combos()->getCategoriesCombo(
            App::blog()->getCategories()
        );

        App::backend()->status_combo = App::status()->post()->combo();

        // Formats combo
        $core_formaters    = App::formater()->getFormaters();
        $available_formats = ['' => ''];
        foreach ($core_formaters as $formats) {
            foreach ($formats as $format) {
                $available_formats[App::formater()->getFormaterName($format)] = $format;
            }
        }
        App::backend()->available_formats = $available_formats;

        // Languages combo
        App::backend()->lang_combo = App::backend()->combos()->getLangsCombo(
            App::blog()->getLangs([
                'order_by' => 'nb_post',
                'order'    => 'desc',
            ]),
            true
        );

        // Validation flag
        App::backend()->bad_dt = false;

        // Trackbacks
        App::backend()->tb      = App::trackback();
        App::backend()->tb_urls = App::backend()->tb_excerpt = '';

        // Get entry informations

        App::backend()->post = null;

        if (!empty($_REQUEST['id'])) {
            App::backend()->page_title = __('Edit element');

            $params['post_id']   = $_REQUEST['id'];
            $params['post_type'] = 'map';

            App::backend()->post = App::blog()->getPosts($params);

            if (App::backend()->post->isEmpty()) {
                App::backend()->notices()->addErrorNotice('This entry does not exist.');
                App::backend()->url()->redirect('admin.posts');
            } else {
                App::backend()->post_id            = App::backend()->post->post_id;
                App::backend()->cat_id             = App::backend()->post->cat_id;
                App::backend()->post_dt            = date('Y-m-d H:i', (int) strtotime(App::backend()->post->post_dt));
                App::backend()->post_format        = App::backend()->post->post_format;
                App::backend()->post_password      = App::backend()->post->post_password;
                App::backend()->post_url           = App::backend()->post->post_url;
                App::backend()->post_lang          = App::backend()->post->post_lang;
                App::backend()->post_title         = App::backend()->post->post_title;
                App::backend()->post_excerpt       = App::backend()->post->post_excerpt;
                App::backend()->post_excerpt_xhtml = App::backend()->post->post_excerpt_xhtml;
                App::backend()->post_content       = App::backend()->post->post_content;
                App::backend()->post_content_xhtml = App::backend()->post->post_content_xhtml;
                App::backend()->post_notes         = App::backend()->post->post_notes;
                App::backend()->post_status        = App::backend()->post->post_status;
                App::backend()->post_selected      = (bool) App::backend()->post->post_selected;
                App::backend()->post_open_comment  = (bool) App::backend()->post->post_open_comment;
                App::backend()->post_open_tb       = (bool) App::backend()->post->post_open_tb;

                App::backend()->can_edit_post = App::backend()->post->isEditable();
                App::backend()->can_delete    = App::backend()->post->isDeletable();

                $next_rs = App::blog()->getNextPost(App::backend()->post, 1);
                $prev_rs = App::blog()->getNextPost(App::backend()->post, -1);

                if ($next_rs instanceof MetaRecord) {
                    App::backend()->next_link = sprintf(
                        App::backend()->post_link,
                        $next_rs->post_id,
                        'next',
                        Html::escapeHTML(trim(Html::clean($next_rs->post_title))),
                        __('Next element') . '&nbsp;&#187;'
                    );
                    App::backend()->next_headlink = sprintf(
                        $post_headlink,
                        'next',
                        Html::escapeHTML(trim(Html::clean($next_rs->post_title))),
                        $next_rs->post_id
                    );
                }

                if ($prev_rs instanceof MetaRecord) {
                    App::backend()->prev_link = sprintf(
                        App::backend()->post_link,
                        $prev_rs->post_id,
                        'prev',
                        Html::escapeHTML(trim(Html::clean($prev_rs->post_title))),
                        '&#171;&nbsp;' . __('Previous element')
                    );
                    App::backend()->prev_headlink = sprintf(
                        $post_headlink,
                        'previous',
                        Html::escapeHTML(trim(Html::clean($prev_rs->post_title))),
                        $prev_rs->post_id
                    );
                }

                // Sanitize trackbacks excerpt
                $buffer = empty($_POST['tb_excerpt']) ?
                    App::backend()->post_excerpt_xhtml . ' ' . App::backend()->post_content_xhtml :
                    $_POST['tb_excerpt'];
                $buffer = preg_replace(
                    '/\s+/ms',
                    ' ',
                    Txt::cutString(Html::escapeHTML(Html::decodeEntities(Html::clean($buffer))), 255)
                );
                App::backend()->tb_excerpt = $buffer;
            }
        }
        $anchor = isset($_REQUEST['section']) && $_REQUEST['section'] == 'trackbacks' ? 'trackbacks' : 'comments';

        App::backend()->comments_actions_page = new ActionsComments(
            App::backend()->url()->get('admin.post'),
            [
                'id'            => App::backend()->post_id,
                'action_anchor' => $anchor,
                'section'       => $anchor,
            ]
        );

        if (App::backend()->comments_actions_page->process()) {
            return self::status(false);
        }

        return self::status(true);
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        $params = [];
        App::backend()->page()->check(App::auth()->makePermissions([
            App::auth()::PERMISSION_CONTENT_ADMIN,
        ]));

        Date::setTZ(App::auth()->getInfo('user_tz') ?? 'UTC');

        App::backend()->post_id            = '';
        App::backend()->cat_id             = '';
        App::backend()->post_dt            = '';
        App::backend()->post_format        = App::auth()->getOption('post_format');
        App::backend()->post_editor        = App::auth()->getOption('editor');
        App::backend()->post_url           = '';
        App::backend()->post_lang          = App::auth()->getInfo('user_lang');
        App::backend()->post_title         = '';
        App::backend()->post_excerpt       = '';
        App::backend()->post_excerpt_xhtml = '';
        App::backend()->post_content       = '';
        App::backend()->post_content_xhtml = '';
        App::backend()->post_notes         = '';
        App::backend()->post_status        = App::auth()->getInfo('user_post_status');
        App::backend()->post_selected      = false;

        App::backend()->post_media = [];

        App::backend()->page_title = __('New map element');

        App::backend()->can_view_page = true;
        App::backend()->can_edit_post = App::auth()->check(App::auth()->makePermissions([
            App::auth()::PERMISSION_USAGE,
        ]), App::blog()->id);
        App::backend()->can_publish = App::auth()->check(App::auth()->makePermissions([
            App::auth()::PERMISSION_PUBLISH,
            App::auth()::PERMISSION_CONTENT_ADMIN,
        ]), App::blog()->id);
        App::backend()->can_delete = false;

        $post_headlink            = '<link rel="%s" title="%s" href="' . App::backend()->url()->get('admin.plugin.' . My::id(), ['act' => 'map','id' => '%s'], '&', true) . '">';
        App::backend()->post_link = '<a href="' . App::backend()->url()->get('admin.plugin.' . My::id(), ['act' => 'map','id' => '%s'], '&', true) . '" title="%s">%s</a>';

        App::backend()->next_link     = null;
        App::backend()->prev_link     = null;
        App::backend()->next_headlink = null;
        App::backend()->prev_headlink = null;
        // If user can't publish

        if (!App::backend()->can_publish) {
            App::backend()->post_status = App::blog()::POST_PENDING;
        }

        // Getting categories

        App::backend()->categories_combo = App::backend()->combos()->getCategoriesCombo(
            App::blog()->getCategories(['post_type' => 'map'])
        );

        // Status combo

        App::backend()->status_combo = App::backend()->combos()->getPostStatusesCombo();

        // Formaters combo

        $core_formaters    = App::formater()->getFormaters();
        $available_formats = ['' => ''];
        foreach ($core_formaters as $formats) {
            foreach ($formats as $format) {
                $available_formats[App::formater()->getFormaterName($format)] = $format;
            }
        }
        App::backend()->available_formats = $available_formats;

        // Languages combo

        App::backend()->lang_combo = App::backend()->combos()->getLangsCombo(
            App::blog()->getLangs(['order' => 'asc']),
            true
        );

        // Validation flag

        App::backend()->bad_dt = false;

        // Get page informations

        App::backend()->post = null;
        if (!empty($_REQUEST['id'])) {
            $params['post_id']   = $_REQUEST['id'];
            $params['post_type'] = 'map';

            App::backend()->post = App::blog()->getPosts($params);

            if (App::backend()->post->isEmpty()) {
                App::error()->add(__('This map element does not exist.'));
                App::backend()->can_view_page = false;
            } else {
                App::backend()->cat_id             = (int) App::backend()->post->cat_id;
                App::backend()->post_id            = (int) App::backend()->post->post_id;
                App::backend()->post_dt            = date('Y-m-d H:i', strtotime(App::backend()->post->post_dt));
                App::backend()->post_format        = App::backend()->post->post_format;
                App::backend()->post_url           = App::backend()->post->post_url;
                App::backend()->post_lang          = App::backend()->post->post_lang;
                App::backend()->post_title         = App::backend()->post->post_title;
                App::backend()->post_excerpt       = App::backend()->post->post_excerpt;
                App::backend()->post_excerpt_xhtml = App::backend()->post->post_excerpt_xhtml;
                App::backend()->post_content       = App::backend()->post->post_content;
                App::backend()->post_content_xhtml = App::backend()->post->post_content_xhtml;
                App::backend()->post_notes         = App::backend()->post->post_notes;
                App::backend()->post_status        = App::backend()->post->post_status;
                App::backend()->post_selected      = (bool) App::backend()->post->post_selected;

                App::backend()->page_title = __('Edit map element');

                App::backend()->can_edit_post = App::backend()->post->isEditable();
                App::backend()->can_delete    = App::backend()->post->isDeletable();

                $next_rs = App::blog()->getNextPost(App::backend()->post, 1);
                $prev_rs = App::blog()->getNextPost(App::backend()->post, -1);

                if ($next_rs !== null) {
                    App::backend()->next_link = sprintf(
                        App::backend()->post_link,
                        $next_rs->post_id,
                        Html::escapeHTML(trim(Html::clean($next_rs->post_title))),
                        __('Next element') . '&nbsp;&#187;'
                    );
                    App::backend()->next_headlink = sprintf(
                        $post_headlink,
                        'next',
                        Html::escapeHTML(trim(Html::clean($next_rs->post_title))),
                        $next_rs->post_id
                    );
                }

                if ($prev_rs !== null) {
                    App::backend()->prev_link = sprintf(
                        App::backend()->post_link,
                        $prev_rs->post_id,
                        Html::escapeHTML(trim(Html::clean($prev_rs->post_title))),
                        '&#171;&nbsp;' . __('Previous element')
                    );
                    App::backend()->prev_headlink = sprintf(
                        $post_headlink,
                        'previous',
                        Html::escapeHTML(trim(Html::clean($prev_rs->post_title))),
                        $prev_rs->post_id
                    );
                }

                try {
                    App::backend()->post_media = App::media()->getPostMedia(App::backend()->post_id);
                } catch (Exception $e) {
                    App::error()->add($e->getMessage());
                }
            }
        }

        if (!empty($_POST) && App::backend()->can_edit_post) {
            // Format content

            App::backend()->cat_id       = (int) $_POST['cat_id'];
            App::backend()->post_format  = $_POST['post_format'];
            App::backend()->post_excerpt = $_POST['post_excerpt'];
            App::backend()->post_content = $_POST['post_content'];

            App::backend()->post_title = $_POST['post_title'];

            if (isset($_POST['post_status'])) {
                App::backend()->post_status = (int) $_POST['post_status'];
            }

            if (empty($_POST['post_dt'])) {
                App::backend()->post_dt = '';
            } else {
                try {
                    App::backend()->post_dt = strtotime($_POST['post_dt']);
                    if (!App::backend()->post_dt || App::backend()->post_dt == -1) {
                        App::backend()->bad_dt = true;

                        throw new Exception(__('Invalid publication date'));
                    }
                    App::backend()->post_dt = date('Y-m-d H:i', App::backend()->post_dt);
                } catch (Exception $e) {
                    App::error()->add($e->getMessage());
                }
            }

            App::backend()->post_selected = !empty($_POST['post_selected']);
            App::backend()->post_lang     = $_POST['post_lang'];

            App::backend()->post_notes = $_POST['post_notes'];

            if (isset($_POST['post_url'])) {
                App::backend()->post_url = $_POST['post_url'];
            }

            [
                $post_excerpt, $post_excerpt_xhtml, $post_content, $post_content_xhtml
            ] = [
                App::backend()->post_excerpt,
                App::backend()->post_excerpt_xhtml,
                App::backend()->post_content,
                App::backend()->post_content_xhtml,
            ];

            App::blog()->setPostContent(
                App::backend()->post_id,
                App::backend()->post_format,
                App::backend()->post_lang,
                $post_excerpt,
                $post_excerpt_xhtml,
                $post_content,
                $post_content_xhtml
            );

            [
                App::backend()->post_excerpt,
                App::backend()->post_excerpt_xhtml,
                App::backend()->post_content,
                App::backend()->post_content_xhtml
            ] = [
                $post_excerpt, $post_excerpt_xhtml, $post_content, $post_content_xhtml,
            ];
        }

        if (!empty($_POST['delete']) && App::backend()->can_delete) {
            // Delete page

            try {
                # --BEHAVIOR-- adminBeforePostDelete -- int
                App::behavior()->callBehavior('adminBeforePostDelete', App::backend()->post_id);
                App::blog()->delPost(App::backend()->post_id);

                My::redirect();
            } catch (Exception $e) {
                App::error()->add($e->getMessage());
            }
        }

        if (!empty($_POST) && !empty($_POST['save']) && App::backend()->can_edit_post && !App::backend()->bad_dt) {
            // Create or update post

            $cur = App::con()->openCursor(App::con()->prefix() . App::blog()::POST_TABLE_NAME);

            if ($_POST['post_content'] == '' || $_POST['post_content'] == __('No description.') || $_POST['post_content'] == '<p>' . __('No description.') . '</p>') {
                if (App::backend()->post_format == 'xhtml') {
                    App::backend()->post_content = '<p>' . __('No description.') . '</p>';
                    $description                 = 'none';
                } else {
                    App::backend()->post_content = __('No description.');
                    $description                 = 'none';
                }
            } else {
                $description = 'description';
            }

            // Magic tweak :)

            App::blog()->settings->system->post_url_format = '{t}';

            $cur->post_type          = 'map';
            $cur->post_dt            = App::backend()->post_dt ? date('Y-m-d H:i:00', strtotime(App::backend()->post_dt)) : '';
            $cur->cat_id             = (App::backend()->cat_id ?: null);
            $cur->post_format        = App::backend()->post_format;
            $cur->post_lang          = App::backend()->post_lang;
            $cur->post_title         = App::backend()->post_title;
            $cur->post_excerpt       = App::backend()->post_excerpt;
            $cur->post_excerpt_xhtml = App::backend()->post_excerpt_xhtml;
            $cur->post_content       = App::backend()->post_content;
            $cur->post_content_xhtml = App::backend()->post_content_xhtml;
            $cur->post_notes         = App::backend()->post_notes;
            $cur->post_status        = App::backend()->post_status;
            $cur->post_selected      = (int) App::backend()->post_selected;

            if (isset($_POST['post_url'])) {
                $cur->post_url = App::backend()->post_url;
            }

            // Back to UTC in order to keep UTC datetime for creadt/upddt

            Date::setTZ('UTC');

            if (App::backend()->post_id) {
                try {
                    if (isset($_POST['element_type'])) {
                        $tags           = $_POST['element_type'];
                        $myGmaps_center = $_POST['myGmaps_center'];
                        $myGmaps_zoom   = $_POST['myGmaps_zoom'];
                        $myGmaps_type   = $_POST['myGmaps_type'];
                        $meta           = App::meta();

                        $meta->delPostMeta(App::backend()->post_id, 'map');
                        $meta->delPostMeta(App::backend()->post_id, 'map_options');
                        $meta->delPostMeta(App::backend()->post_id, 'description');

                        foreach ($meta->splitMetaValues($tags) as $tag) {
                            $meta->setPostMeta(App::backend()->post_id, 'map', $tag);
                        }
                        $map_options = $myGmaps_center . ',' . $myGmaps_zoom . ',' . $myGmaps_type;
                        $meta->setPostMeta(App::backend()->post_id, 'map_options', $map_options);
                        $meta->setPostMeta(App::backend()->post_id, 'description', $description);
                    }
                    // --BEHAVIOR-- adminBeforePostUpdate
                    App::behavior()->callBehavior('adminBeforePostUpdate', $cur, App::backend()->post_id);

                    App::blog()->updPost(App::backend()->post_id, $cur);

                    // --BEHAVIOR-- adminAfterPostUpdate
                    App::behavior()->callBehavior('adminAfterPostUpdate', $cur, App::backend()->post_id);

                    My::redirect(['act' => 'map', 'id' => App::backend()->post_id, 'upd' => 1]);
                } catch (Exception $e) {
                    App::error()->add($e->getMessage());
                }
            } else {
                $cur->user_id = App::auth()->userID();

                try {
                    $return_id = App::blog()->addPost($cur);

                    if (isset($_POST['element_type'])) {
                        $tags           = $_POST['element_type'];
                        $myGmaps_center = $_POST['myGmaps_center'];
                        $myGmaps_zoom   = $_POST['myGmaps_zoom'];
                        $myGmaps_type   = $_POST['myGmaps_type'];
                        $meta           = App::meta();

                        foreach ($meta->splitMetaValues($tags) as $tag) {
                            $meta->setPostMeta($return_id, 'map', $tag);
                        }
                        $map_options = $myGmaps_center . ',' . $myGmaps_zoom . ',' . $myGmaps_type;
                        $meta->setPostMeta($return_id, 'map_options', $map_options);
                        $meta->setPostMeta($return_id, 'description', $description);
                    }

                    // --BEHAVIOR-- adminBeforePostCreate
                    App::behavior()->callBehavior('adminBeforePostCreate', $cur);

                    // --BEHAVIOR-- adminAfterPostCreate
                    App::behavior()->callBehavior('adminAfterPostCreate', $cur, $return_id);

                    My::redirect(['act' => 'map', 'id' => $return_id, 'crea' => 1]);
                } catch (Exception $e) {
                    App::error()->add($e->getMessage());
                }
            }

            if (!empty($_POST['delete']) && App::backend()->can_delete) {
                try {
                    // --BEHAVIOR-- adminBeforePostDelete
                    App::behavior()->callBehavior('adminBeforePostDelete', App::backend()->post_id);
                    App::blog()->delPost(App::backend()->post_id);
                    My::redirect(['act' => 'list']);
                } catch (Exception $e) {
                    App::error()->add($e->getMessage());
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
        if (!self::status()) {
            return;
        }

        $myGmaps_center = My::settings()->myGmaps_center;
        $myGmaps_zoom   = My::settings()->myGmaps_zoom;
        $myGmaps_type   = My::settings()->myGmaps_type;

        App::backend()->default_tab = 'edit-entry';
        if (!App::backend()->can_edit_post) {
            App::backend()->default_tab = '';
        }

        $admin_post_behavior = '';
        if (App::backend()->post_editor) {
            $p_edit = $c_edit = '';
            if (!empty(App::backend()->post_editor[App::backend()->post_format])) {
                $p_edit = App::backend()->post_editor[App::backend()->post_format];
            }
            if (!empty(App::backend()->post_editor['xhtml'])) {
                $c_edit = App::backend()->post_editor['xhtml'];
            }
            if ($p_edit == $c_edit) {
                # --BEHAVIOR-- adminPostEditor -- string, string, array<int,string>, string
                $admin_post_behavior .= App::behavior()->callBehavior(
                    'adminPostEditor',
                    $p_edit,
                    'map',
                    ['#post_content'],
                    App::backend()->post_format
                );
            } else {
                # --BEHAVIOR-- adminPostEditor -- string, string, array<int,string>, string
                $admin_post_behavior .= App::behavior()->callBehavior(
                    'adminPostEditor',
                    $p_edit,
                    'map',
                    ['#post_content'],
                    App::backend()->post_format
                );
            }
        }

        // Custom marker icons

        $public_path = App::blog()->public_path;
        $public_url  = App::blog()->settings->system->public_url;
        $blog_url    = App::blog()->url;

        $icons_dir_path = $public_path . '/myGmaps/icons/';
        $icons_dir_url  = http::concatURL(App::blog()->url, $public_url . '/myGmaps/icons/');

        if (is_dir($icons_dir_path)) {
            $images = array_merge(
                glob($icons_dir_path . '*.png'),
                glob($icons_dir_path . '*.svg'),
                glob($icons_dir_path . '*.jpg'),
            );
            $icons_list = [];
            foreach ($images as $image) {
                $image = basename($image);
                array_push($icons_list, $image);
            }
            $icons_list     = implode(',', $icons_list);
            $icons_base_url = $icons_dir_url;
        } else {
            $icons_list      = '';
            $icons_base_path = '';
            $icons_base_url  = '';
        }

        // Custom Kml files

        $kmls_dir_path = $public_path . '/myGmaps/kml_files/';
        $kmls_dir_url  = http::concatURL(App::blog()->url, $public_url . '/myGmaps/kml_files/');

        if (is_dir($kmls_dir_path)) {
            $kmls      = glob($kmls_dir_path . '*.kml');
            $kmls_list = [];
            foreach ($kmls as $kml) {
                $kml = basename($kml);
                array_push($kmls_list, $kml);
            }
            $kmls_list     = implode(',', $kmls_list);
            $kmls_base_url = $kmls_dir_url;
        } else {
            $kmls_list     = '';
            $kmls_base_url = '';
        }

        // Custom map styles

        $map_styles_dir_path = $public_path . '/myGmaps/styles/';
        $map_styles_dir_url  = http::concatURL(App::blog()->url, $public_url . '/myGmaps/styles/');

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

        $starting_script = '<script>' . "\n" .
            '(g=>{var h,a,k,p="The Google Maps JavaScript API",c="google",l="importLibrary",q="__ib__",m=document,b=window;b=b[c]||(b[c]={});var d=b.maps||(b.maps={}),r=new Set,e=new URLSearchParams,u=()=>h||(h=new Promise(async(f,n)=>{await (a=m.createElement("script"));e.set("libraries",[...r]+"");for(k in g)e.set(k.replace(/[A-Z]/g,t=>"_"+t[0].toLowerCase()),g[k]);e.set("callback",c+".maps."+q);a.src=`https://maps.${c}apis.com/maps/api/js?`+e;d[q]=f;a.onerror=()=>h=n(Error(p+" could not load."));a.nonce=m.querySelector("script[nonce]")?.nonce||"";m.head.append(a)}));d[l]?console.warn(p+" only loads once. Ignoring:",g):d[l]=(f,...n)=>r.add(f)&&u().then(()=>d[l](f,...n))})({' . "\n" .
                'key: "' . My::settings()->myGmaps_API_key . '",' . "\n" .
                'v: "weekly",' . "\n" .
            '});' . "\n" .
        '</script>' . "\n" ;

        $style_script = '';

        if (is_dir($map_styles_dir_path)) {
            $list = explode(',', $map_styles_list);
            foreach ($list as $map_style) {
                $map_style_content = json_decode(file_get_contents($map_styles_dir_path . '/' . $map_style));
                $var_styles_name   = pathinfo($map_style, PATHINFO_FILENAME);
                $var_name          = preg_replace('/_styles/s', '', $var_styles_name);
                $nice_name         = ucwords(preg_replace('/_/s', ' ', $var_name));
                $style_script .= App::backend()->page()->jsJson($var_name, [
                    'style' => $map_style_content,
                    'name'  => $nice_name,
                ]);
            }
        }

        $starting_script .= '<script>' . "\n" .
        '//<![CDATA[' . "\n" .
        'const stroke_color_msg = \'' . __('Stroke color') . '\';' . "\n" .
        'const stroke_opacity_msg = \'' . __('Stroke opacity') . '\';' . "\n" .
        'const stroke_weight_msg = \'' . __('Stroke weight') . '\';' . "\n" .
        'const circle_radius_msg = \'' . __('Circle radius') . '\';' . "\n" .
        'const fill_color_msg = \'' . __('Fill color') . '\';' . "\n" .
        'const fill_opacity_msg = \'' . __('Fill opacity') . '\';' . "\n" .
        'const default_icons_msg = \'' . __('Default icons') . '\';' . "\n" .
        'const custom_icons_msg = \'' . __('Custom icons') . '\';' . "\n" .
        'const kml_url_msg = \'' . __('File URL:') . '\';' . "\n" .
        'const geoRss_url_msg = \'' . __('Feed URL:') . '\';' . "\n" .
        'const custom_kmls_msg = \'' . __('Custom Kml files') . '\';' . "\n" .
        'const directions_start_msg = \'' . __('Start:') . '\';' . "\n" .
        'const directions_end_msg = \'' . __('End:') . '\';' . "\n" .
        'const directions_show_msg = \'' . __('Display directions panel in public map') . '\';' . "\n" .
        '//]]>' . "\n" .
        '</script>';

        App::backend()->page()->openModule(
            App::backend()->page_title . ' - ' . My::name(),
            App::backend()->page()->jsModal() .
            App::backend()->page()->jsLoad('js/_post.js') .
            App::backend()->page()->jsMetaEditor() .
            $admin_post_behavior .
            $starting_script .
            $style_script .
            My::jsLoad('element.map.min.js') .
            App::backend()->page()->jsConfirmClose('entry-form') .
            # --BEHAVIOR-- adminPostHeaders --
            App::behavior()->callBehavior('adminPostHeaders') .
            App::backend()->page()->jsPageTabs(App::backend()->default_tab) .
            My::cssLoad('admin.css') .
            My::cssLoad('adminelement.css') .
            App::backend()->next_headlink . "\n" . App::backend()->prev_headlink
        );

        if (App::backend()->post_id) {
            $img_status       = App::status()->post()->image((int) App::backend()->post_status)->render();
            $edit_entry_title = '&ldquo;' . Html::escapeHTML(trim(Html::clean(App::backend()->post_title))) . '&rdquo;' . ' ' . $img_status;
        } else {
            $img_status       = '';
            $edit_entry_title = App::backend()->page_title;
        }

        echo App::backend()->page()->breadcrumb(
            [
                Html::escapeHTML(App::blog()->name) => '',
                My::name()                          => My::manageUrl(),
                $edit_entry_title                   => '',
            ]
        );

        if (!empty($_GET['upd'])) {
            App::backend()->notices()->success(__('Map element has been updated.'));
        } elseif (!empty($_GET['crea'])) {
            App::backend()->notices()->success(__('Map element has been created.'));
        }

        # HTML conversion
        if (!empty($_GET['xconv'])) {
            App::backend()->post_excerpt = App::backend()->post_excerpt_xhtml;
            App::backend()->post_content = App::backend()->post_content_xhtml;
            App::backend()->post_format  = 'xhtml';

            App::backend()->page()->message(__('Don\'t forget to validate your HTML conversion by saving your post.'));
        }

        if (App::backend()->post_id) {
            $items = [];
            if (App::backend()->prev_link) {
                $items[] = new Text(null, App::backend()->prev_link);
            }
            if (App::backend()->next_link) {
                $items[] = new Text(null, App::backend()->next_link);
            }

            # --BEHAVIOR-- adminPageNavLinks -- MetaRecord|null
            $items[] = new Capture(App::behavior()->callBehavior(...), ['adminPosNavLinks', App::backend()->post ?? null, 'post']);

            echo (new Para())
                ->class('nav_prevnext')
                ->items($items)
            ->render();
        }

        # Exit if we cannot view page

        if (!App::backend()->can_view_page) {
            dcPost::closeModule();

            return;
        }

        /* Post form if we can edit page
        -------------------------------------------------------- */
        if (App::backend()->can_edit_post) {
            $meta = App::meta() ?? '';

            if (isset(App::backend()->post)) {
                $meta_rs = $meta->getMetaStr(App::backend()->post->post_meta, 'map_options');
                if ($meta_rs) {
                    $map_options    = explode(',', $meta_rs);
                    $myGmaps_center = $map_options[0] . ',' . $map_options[1];
                    $myGmaps_zoom   = $map_options[2];
                    $myGmaps_type   = $map_options[3];
                }
            }

            $sidebar_items = new ArrayObject([
                'status-box' => [
                    'title' => __('Status'),
                    'items' => [
                        'post_status' => (new Para())->class('entry-status')->items([
                            (new Select('post_status'))
                                ->items(App::backend()->status_combo)
                                ->default(App::backend()->post_status)
                                ->disabled(!App::backend()->can_publish)
                                ->label(new Label(__('Element status') . ' ' . $img_status, Label::OUTSIDE_LABEL_BEFORE)),
                        ])
                        ->render(),

                        'post_dt' => (new Para())->items([
                            (new Datetime('post_dt'))
                                ->value(Html::escapeHTML(Date::str('%Y-%m-%dT%H:%M', strtotime(App::backend()->post_dt))))
                                ->class(App::backend()->bad_dt ? 'invalid' : [])
                                ->label(new Label(__('Publication date and hour'), Label::OUTSIDE_LABEL_BEFORE)),
                        ])
                        ->render(),

                        'post_lang' => (new Para())->items([
                            (new Select('post_lang'))
                                ->items(App::backend()->lang_combo)
                                ->default(App::backend()->post_lang)
                                ->translate(false)
                                ->label(new Label(__('Element language'), Label::OUTSIDE_LABEL_BEFORE)),
                        ])
                        ->render(),

                        'post_format' => (new Para())->items([
                            (new Select('post_format'))
                                ->items(App::backend()->available_formats)
                                ->default(App::backend()->post_format)
                                ->label((new Label(__('Text formatting'), Label::OUTSIDE_LABEL_BEFORE))->id('label_format')),
                            (new Div())
                                ->class(['format_control', 'control_no_xhtml'])
                                ->items([
                                    (new Link('convert-xhtml'))
                                        ->class(['button', App::backend()->post_id && App::backend()->post_format != 'wiki' ? ' hide' : ''])
                                        ->href(App::backend()->url()->get('admin.post', ['id' => App::backend()->post_id, 'xconv' => '1']))
                                        ->text(__('Convert to HTML')),
                                ]),
                        ])
                        ->render(),
                    ],
                ],

                'metas-box' => [
                    'title' => __('Filing'),
                    'items' => [
                        'post_selected' => (new Para())->items([
                            (new Checkbox('post_selected', App::backend()->post_selected))
                                ->value(1)
                                ->label(new Label(__('Selected element'), Label::IL_FT)),
                        ])
                        ->render(),

                        'cat_id' => (new Div())->items([
                            (new Text('h5', __('Category')))
                                ->id('label_cat_id'),
                            (new Para())
                                ->items([
                                    (new Select('cat_id'))
                                        ->items(App::backend()->categories_combo)
                                        ->default(App::backend()->cat_id)
                                        ->class('maximal')
                                        ->label(new Label(__('Category:'), Label::OL_TF)),
                                ]),
                            App::auth()->check(App::auth()->makePermissions([App::auth()::PERMISSION_CATEGORIES]), App::blog()->id()) ?
                            (new Div())
                                ->items([
                                    (new Text('h5', __('Add a new category')))
                                        ->id('create_cat'),
                                    (new Para())
                                        ->items([
                                            (new Input('new_cat_title'))
                                                ->size(30)
                                                ->maxlength(255)
                                                ->class('maximal')
                                                ->autocomplete('off')
                                                ->label(new Label(__('Title:'), Label::OL_TF)),
                                        ]),
                                    (new Para())
                                        ->items([
                                            (new Select('new_cat_parent'))
                                                ->items(App::backend()->categories_combo)
                                                ->class('maximal')
                                                ->label(new Label(__('Parent:'), Label::OL_TF)),
                                        ]),
                                ]) :
                            (new None()),
                        ])
                        ->render(),
                    ],
                ],

                'options-box' => [
                    'title' => __('Options'),
                    'items' => [

                        'post_password' => (new Para())->items([
                            (new Password('post_password'))
                                            ->autocomplete('new-password')
                                            ->class('maximal')
                                            ->value(Html::escapeHTML(App::backend()->post_password))
                                            ->size(10)
                                            ->maxlength(32)
                                            ->translate(false)
                                            ->label((new Label(__('Password'), Label::OUTSIDE_TEXT_BEFORE))),
                        ])
                        ->render(),

                        'post_url' => (new Div())->class('lockable')->items([
                            (new Para())->items([
                                (new Input('post_url'))
                                    ->class('maximal')
                                    ->value(Html::escapeHTML(App::backend()->post_url))
                                    ->size(10)
                                    ->maxlength(255)
                                    ->translate(false)
                                    ->label((new Label(__('Edit basename'), Label::OUTSIDE_TEXT_BEFORE))),
                            ]),
                            (new Note())
                                            ->class(['form-note', 'warn'])
                                            ->text(__('Warning: If you set the URL manually, it may conflict with another entry.')),
                        ])
                        ->render(),
                    ],
                ],
            ]);

            $main_items = new ArrayObject(
                [
                    'post_title' => (new Para())->items([
                        (new Input('post_title'))
                            ->value(Html::escapeHTML(App::backend()->post_title))
                            ->size(20)
                            ->maxlength(255)
                            ->required(true)
                            ->class('maximal')
                            ->placeholder(__('Title'))
                            ->lang(App::backend()->post_lang)
                            ->spellcheck(true)
                            ->label(
                                (new Label(
                                    (new Span('*'))->render() . __('Title:'),
                                    Label::OUTSIDE_TEXT_BEFORE
                                ))
                                ->class(['required', 'no-margin', 'bold'])
                            )
                            ->title(__('Required field')),
                    ])
                    ->render(),

                    'post_excerpt' => (new Para())->class('area')->id('excerpt-area')->items([
                        (new Label(__('Type and position:'), Label::OUTSIDE_LABEL_AFTER))->for('post_excerpt')->class('bold'),
                        (new Div())
                        ->class('map_toolbar')
                        ->id('map_toolbar')
                        ->items([
                            (new Text('span', __('Search:')))->class('search'),
                            (new Text('span', ''))->class('map_spacer'),
                            (new Hidden('address'))->class('qx'),
                            (new Input('geocode'))
                                ->type('submit')
                                ->value(__('OK')),
                            (new Text('span', ''))->class('map_spacer'),
                            (new Btn('add_marker'))
                                ->class(['add_marker'])
                                ->id('add_marker')
                                ->type('button')
                                ->title(__('Point of interest')),
                            (new Btn('add_polyline'))
                                ->class(['add_polyline'])
                                ->id('add_polyline')
                                ->type('button')
                                ->title(__('Polyline')),
                            (new Btn('add_polygon'))
                                ->class(['add_polygon'])
                                ->id('add_polygon')
                                ->type('button')
                                ->title(__('Polygon')),
                            (new Btn('add_rectangle'))
                                ->class(['add_rectangle'])
                                ->id('add_rectangle')
                                ->type('button')
                                ->title(__('Rectangle')),
                            (new Btn('add_circle'))
                                ->class(['add_circle'])
                                ->id('add_circle')
                                ->type('button')
                                ->title(__('Circle')),
                            (new Btn('add_kml'))
                                ->class(['add_kml'])
                                ->id('add_kml')
                                ->type('button')
                                ->title(__('Included kml file')),
                            (new Btn('add_georss'))
                                ->class(['add_georss'])
                                ->id('add_georss')
                                ->type('button')
                                ->title(__('GeoRSS Feed')),
                            (new Btn('add_directions'))
                                ->class(['add_directions'])
                                ->id('add_directions')
                                ->type('button')
                                ->title(__('Directions')),
                            (new Btn('delete_map'))
                                ->class(['delete_map'])
                                ->id('delete_map')
                                ->type('button')
                                ->title(__('Erase')),

                        ]),
                        (new Div())
                            ->id('map_box')
                            ->items([
                                (new Div())
                                    ->class('area')
                                    ->id('map_canvas'),
                                (new Div())
                                    ->id('panel'),
                            ]),
                        (new Para())
                            ->class(['form-note', 'info', 'maximal', 'mapinfo'])
                            ->items([
                                (new Text(null, __('This map will not be displayed on the blog and is meant only to create, edit and position <strong>only one</strong> element at a time. Choose a tool and click on the map to create your element, then click on the element to edit its properties.'))),
                            ]),
                        (new Para())->class('area')->id('excerpt')->items([
                            (new Textarea('post_excerpt'))
                                ->value(html::escapeHTML(App::backend()->post_excerpt))
                                ->cols(50)
                                ->rows(5),
                        ]),

                    ])

                    ->render(),

                    'post_content' => (new Para())->class('area')->id('content-area')->items([
                        (new Textarea('post_content'))
                            ->value(Html::escapeHTML(App::backend()->post_content))
                            ->cols(50)
                            ->rows(5)
                            ->lang(App::backend()->post_lang)
                            ->spellcheck(true)
                            ->placeholder(__('Description'))
                            ->label(
                                (new Label(
                                    __('Description:'),
                                    Label::OUTSIDE_TEXT_BEFORE
                                ))
                                ->class(['bold'])
                            ),
                    ])
                    ->render(),

                    'post_notes' => (new Para())->class('area')->id('notes-area')->items([
                        (new Textarea('post_notes'))
                            ->value(Html::escapeHTML(App::backend()->post_notes))
                            ->cols(50)
                            ->rows(5)
                            ->lang(App::backend()->post_lang)
                            ->spellcheck(true)
                            ->label(
                                (new Label(
                                    __('Personal notes:') . ' ' . (new Span(__('Unpublished notes.')))->class('form-note')->render(),
                                    Label::OUTSIDE_TEXT_BEFORE
                                ))
                                ->class('bold')
                            ),
                    ])
                    ->render(),
                ]
            );

            # --BEHAVIOR-- adminPostFormItems -- ArrayObject, ArrayObject, MetaRecord|null, string
            App::behavior()->callBehavior('adminPostFormItems', $main_items, $sidebar_items, App::backend()->post ?? null, 'post');

            // Prepare main and side parts
            $side_part_items = [];
            foreach ($sidebar_items as $id => $c) {
                $side_part_items[] = (new Div())
                    ->id($id)
                    ->class('sb-box')
                    ->items([
                        (new Text('h4', $c['title'])),
                        (new Text(null, implode('', $c['items']))),
                    ])
                    ->render();
            }
            $side_part = implode('', $side_part_items);
            $main_part = implode('', iterator_to_array($main_items));

            // Prepare buttons
            $buttons   = [];
            $buttons[] = (new Submit(['save'], __('Save') . ' (s)'))
                ->accesskey('s');

            if (App::backend()->can_delete) {
                $buttons[] = (new Submit(['delete'], __('Delete')))
                    ->class('delete');
            }

            if (App::backend()->post_id) {
                $buttons[] = (new Hidden('id', (string) App::backend()->post_id));
                $buttons[] = (new Hidden('element_type', $meta->getMetaStr(App::backend()->post->post_meta, 'map')));
            } else {
                $buttons[] = (new Hidden('element_type', ''));
            }

            $buttons[] = (new Hidden('myGmaps_center', $myGmaps_center));
            $buttons[] = (new Hidden('myGmaps_zoom', (string) $myGmaps_zoom));
            $buttons[] = (new Hidden('myGmaps_type', $myGmaps_type));
            $buttons[] = (new Hidden('blog_url', App::blog()->url()));
            $buttons[] = (new Hidden('plugin_QmarkURL', App::blog()->getQmarkURL()));
            $buttons[] = (new Hidden('icons_list', $icons_list));
            $buttons[] = (new Hidden('icons_base_url', $icons_base_url));
            $buttons[] = (new Hidden('kmls_list', $kmls_list));
            $buttons[] = (new Hidden('kmls_base_url', $kmls_base_url));
            $buttons[] = (new Hidden('map_styles_list', $map_styles_list));
            $buttons[] = (new Hidden('map_styles_base_url', $map_styles_base_url));

            $format = (new Span(' &rsaquo; ' . App::formater()->getFormaterName(App::backend()->post_format)));
            $title  = (App::backend()->post_id ? __('Edit map element') : __('New element')) . $format->render();

            // Everything is ready, time to display this form
            echo (new Div())
                ->class('multi-part')
                ->title($title)
                ->id('edit-entry')
                ->items([
                    (new Form('entry-form'))
                        ->method('post')
                        ->action(App::backend()->url()->get('admin.plugin.' . My::id(), ['act' => 'map']))
                        ->fields([
                            (new Div())
                                ->id('entry-wrapper')
                                ->items([
                                    (new Div())
                                        ->id('entry-content')
                                        ->items([
                                            (new Div())
                                                ->class('constrained')
                                                ->items([
                                                    (new Text('h3', __('Edit element')))
                                                        ->class('out-of-screen-if-js'),
                                                    (new Note())
                                                        ->class('form-note')
                                                        ->text(sprintf(__('Fields preceded by %s are mandatory.'), (new Span('*'))->class('required')->render())),
                                                    (new Text(null, $main_part)),
                                                    (new Capture(App::behavior()->callBehavior(...), ['adminPostForm', App::backend()->post ?? null, 'post'])),
                                                    (new Para())
                                                        ->class(['border-top', 'form-buttons'])
                                                        ->items([
                                                            App::nonce()->formNonce(),
                                                            ...$buttons,
                                                        ]),
                                                    (new Capture(App::behavior()->callBehavior(...), ['adminPostAfterButtons', App::backend()->post ?? null])),
                                                ]),
                                        ]),
                                ]),
                            (new Div())
                                ->id('entry-sidebar')
                                ->role('complementary')
                                ->items([
                                    (new Text(null, $side_part)),
                                    (new Capture(App::behavior()->callBehavior(...), ['adminPostFormSidebar', App::backend()->post ?? null])),
                                ]),
                        ]),
                    (new Capture(App::behavior()->callBehavior(...), ['adminPostAfterForm', App::backend()->post ?? null, 'post'])),
                ])
            ->render();
        }

        App::backend()->page()->helpBlock('myGmap', 'core_wiki');
        App::backend()->page()->closeModule();
    }
}
