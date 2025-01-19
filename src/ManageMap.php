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
use Dotclear\Core\Backend\Combos;
use Dotclear\App;
use Dotclear\Core\Process;
use Dotclear\Core\Backend\Page;
use Dotclear\Core\Backend\Notices;
use Dotclear\Helper\Date;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Exception;
use form;

class ManageMap extends Process
{
    public static function init(): bool
    {
        if (My::checkContext(My::MANAGE)) {
            self::status(($_REQUEST['act'] ?? 'list') === 'map');
        }

        return self::status();
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        $params = [];
        Page::check(App::auth()->makePermissions([
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

        App::backend()->categories_combo = Combos::getCategoriesCombo(
            App::blog()->getCategories(['post_type' => 'map'])
        );

        // Status combo

        App::backend()->status_combo = Combos::getPostStatusesCombo();

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

        App::backend()->lang_combo = Combos::getLangsCombo(
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

                My::redirect(['tab' => 'entries-list']);
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
        if (!empty($_GET['co'])) {
            App::backend()->default_tab = 'comments';
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
            $images     = glob($icons_dir_path . '*.png');
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
                $style_script .= Page::jsJson($var_name, [
                    'style' => $map_style_content,
                    'name'  => $nice_name,
                ]);
            }
        }

        $starting_script .= '<script>' . "\n" .
        '//<![CDATA[' . "\n" .
        'var stroke_color_msg = \'' . __('Stroke color') . '\';' . "\n" .
        'var stroke_opacity_msg = \'' . __('Stroke opacity') . '\';' . "\n" .
        'var stroke_weight_msg = \'' . __('Stroke weight') . '\';' . "\n" .
        'var circle_radius_msg = \'' . __('Circle radius') . '\';' . "\n" .
        'var fill_color_msg = \'' . __('Fill color') . '\';' . "\n" .
        'var fill_opacity_msg = \'' . __('Fill opacity') . '\';' . "\n" .
        'var default_icons_msg = \'' . __('Default icons') . '\';' . "\n" .
        'var custom_icons_msg = \'' . __('Custom icons') . '\';' . "\n" .
        'var kml_url_msg = \'' . __('File URL:') . '\';' . "\n" .
        'var geoRss_url_msg = \'' . __('Feed URL:') . '\';' . "\n" .
        'var custom_kmls_msg = \'' . __('Custom Kml files') . '\';' . "\n" .
        'var directions_start_msg = \'' . __('Start:') . '\';' . "\n" .
        'var directions_end_msg = \'' . __('End:') . '\';' . "\n" .
        'var directions_show_msg = \'' . __('Display directions panel in public map') . '\';' . "\n" .
        '//]]>' . "\n" .
        '</script>';

        Page::openModule(
            App::backend()->page_title . ' - ' . My::name(),
            Page::jsModal() .
            Page::jsLoad('js/_post.js') .
            Page::jsMetaEditor() .
            $admin_post_behavior .
            $starting_script .
            $style_script .
            My::jsLoad('element.map.min.js') .
            Page::jsConfirmClose('entry-form') .
            # --BEHAVIOR-- adminPostHeaders --
            App::behavior()->callBehavior('adminPostHeaders') .
            Page::jsPageTabs(App::backend()->default_tab) .
            My::cssLoad('admin.css') .
            App::backend()->next_headlink . "\n" . App::backend()->prev_headlink
        );

        $img_status         = '';
        $img_status_pattern = '<img class="mark mark-%3$s" alt="%1$s" src="images/%2$s">';

        if (App::backend()->post_id) {
            try {
                $img_status = match ((int) App::backend()->post_status) {
                    App::status()->post()::PUBLISHED   => sprintf($img_status_pattern, __('Published'), 'published.svg', 'published'),
                    App::status()->post()::UNPUBLISHED => sprintf($img_status_pattern, __('Unpublished'), 'unpublished.svg', 'unpublished'),
                    App::status()->post()::SCHEDULED   => sprintf($img_status_pattern, __('Scheduled'), 'scheduled.svg', 'scheduled'),
                    App::status()->post()::PENDING     => sprintf($img_status_pattern, __('Pending'), 'pending.svg', 'pending'),
                };
            } catch (UnhandledMatchError) {
            }

            $edit_entry_title = '&ldquo;' . Html::escapeHTML(trim(Html::clean(App::backend()->post_title))) . '&rdquo;' . ' ' . $img_status;
        } else {
            $edit_entry_title = App::backend()->page_title;
        }
        echo Page::breadcrumb(
            [
                Html::escapeHTML(App::blog()->name) => '',
                My::name()                          => My::manageUrl() . '&tab=entries-list',
                $edit_entry_title                   => '',
            ]
        );

        if (!empty($_GET['upd'])) {
            Notices::success(__('Map element has been updated.'));
        } elseif (!empty($_GET['crea'])) {
            Notices::success(__('Map element has been created.'));
        }

        # HTML conversion
        if (!empty($_GET['xconv'])) {
            App::backend()->post_excerpt = App::backend()->post_excerpt_xhtml;
            App::backend()->post_content = App::backend()->post_content_xhtml;
            App::backend()->post_format  = 'xhtml';

            Page::message(__('Don\'t forget to validate your HTML conversion by saving your post.'));
        }

        if (App::backend()->post_id) {
            echo
            '<p class="nav_prevnext">';
            if (App::backend()->prev_link) {
                echo
                App::backend()->prev_link;
            }
            if (App::backend()->next_link) {
                echo
                App::backend()->next_link;
            }

            # --BEHAVIOR-- adminPostNavLinks -- MetaRecord|null, string
            App::behavior()->callBehavior('adminPostNavLinks', App::backend()->post ?? null, 'map');

            echo
            '</p>';
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
                        'post_status' => '<p><label for="post_status">' . __('Element status') . ' ' . $img_status . '</label>' .
                        form::combo(
                            'post_status',
                            App::backend()->status_combo,
                            ['default' => App::backend()->post_status, 'disabled' => !App::backend()->can_publish]
                        ) .
                        '</p>',
                        'post_dt' => '<p><label for="post_dt">' . __('Publication date and hour') . '</label>' .
                        form::datetime('post_dt', [
                            'default' => Html::escapeHTML(Date::str('%Y-%m-%dT%H:%M', strtotime(App::backend()->post_dt))),
                            'class'   => (App::backend()->bad_dt ? 'invalid' : ''),
                        ]) .
                        '</p>',
                        'post_lang' => '<p><label for="post_lang">' . __('Element language') . '</label>' .
                        form::combo('post_lang', App::backend()->lang_combo, App::backend()->post_lang) .
                        '</p>',
                        'post_format' => '<div>' .
                        '<h5 id="label_format"><label for="post_format" class="classic">' . __('Text formatting') . '</label></h5>' .
                        '<p>' . form::combo('post_format', App::backend()->available_formats, App::backend()->post_format, 'maximal') . '</p>' .
                        '<p class="format_control control_wiki">' .
                        '<a id="convert-xhtml" class="button' . (App::backend()->post_id && App::backend()->post_format != 'wiki' ? ' hide' : '') .
                        '" href="' . App::backend()->url()->get('admin.plugin.' . My::id(), ['act' => 'map', 'id' => App::backend()->post_id, 'xconv' => '1']) . '">' .
                        __('Convert to HTML') . '</a></p></div>', ], ],
                'metas-box' => [
                    'title' => __('Filing'),
                    'items' => [
                        'post_selected' => '<p><label for="post_selected" class="classic">' .
                        form::checkbox('post_selected', 1, App::backend()->post_selected) . ' ' .
                        __('Selected element') . '</label></p>',
                        'cat_id' => '<div>' .
                        '<h5 id="label_cat_id">' . __('Category') . '</h5>' .
                        '<p><label for="cat_id">' . __('Category:') . '</label>' .
                        form::combo('cat_id', App::backend()->categories_combo, App::backend()->cat_id, 'maximal') .
                        '</p>' .
                        (App::auth()->check(App::auth()->makePermissions([
                            App::auth()::PERMISSION_CATEGORIES,
                        ]), App::blog()->id) ?
                            '<div>' .
                            '<h5 id="create_cat">' . __('Add a new category') . '</h5>' .
                            '<p><label for="new_cat_title">' . __('Title:') . ' ' .
                            form::field('new_cat_title', 30, 255, ['class' => 'maximal']) . '</label></p>' .
                            '<p><label for="new_cat_parent">' . __('Parent:') . ' ' .
                            form::combo('new_cat_parent', App::backend()->categories_combo, '', 'maximal') .
                            '</label></p>' .
                            '</div>' :
                            '') .
                        '</div>',
                    ],
                ],
                'options-box' => [
                    'title' => __('Options'),
                    'items' => [
                        'post_open_comment_tb' => '<div>' .
                        '<h5 id="label_comment_tb">' . __('Comments and trackbacks list') . '</h5>' .
                        '<p><label for="post_open_comment" class="classic">' .
                        form::checkbox('post_open_comment', 1, App::backend()->post_open_comment) . ' ' .
                        __('Accept comments') . '</label></p>' .
                        (App::blog()->settings->system->allow_comments ?
                            (self::isContributionAllowed(App::backend()->post_id, strtotime(App::backend()->post_dt), true) ? '' : '<p class="form-note warn">' .
                            __('Warning: Comments are not more accepted for this entry.') . '</p>') :
                            '<p class="form-note warn">' .
                            __('Comments are not accepted on this blog so far.') . '</p>') .
                        '<p><label for="post_open_tb" class="classic">' .
                        form::checkbox('post_open_tb', 1, App::backend()->post_open_tb) . ' ' .
                        __('Accept trackbacks') . '</label></p>' .
                        (App::blog()->settings->system->allow_trackbacks ?
                            (self::isContributionAllowed(App::backend()->post_id, strtotime(App::backend()->post_dt), false) ? '' : '<p class="form-note warn">' .
                            __('Warning: Trackbacks are not more accepted for this entry.') . '</p>') :
                            '<p class="form-note warn">' . __('Trackbacks are not accepted on this blog so far.') . '</p>') .
                        '</div>',
                        'post_password' => '<p><label for="post_password">' . __('Password') . '</label>' .
                        form::field('post_password', 10, 32, Html::escapeHTML(App::backend()->post_password), 'maximal') .
                        '</p>',
                        'post_url' => '<div class="lockable">' .
                        '<p><label for="post_url">' . __('Edit basename') . '</label>' .
                        form::field('post_url', 10, 255, Html::escapeHTML(App::backend()->post_url), 'maximal') .
                        '</p>' .
                        '<p class="form-note warn">' .
                        __('Warning: If you set the URL manually, it may conflict with another entry.') .
                        '</p></div>',
                    ],
                ],
            ]);
            $main_items = new ArrayObject(
                [
                    'post_title' => '<p class="col">' .
                    '<label class="required no-margin bold" for="post_title"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Title:') . '</label>' .
                    form::field('post_title', 20, 255, [
                        'default'    => Html::escapeHTML(App::backend()->post_title),
                        'class'      => 'maximal',
                        'extra_html' => 'required placeholder="' . __('Title') . '" lang="' . App::backend()->post_lang . '" spellcheck="true"',
                    ]) .
                    '</p>',

                    'post_excerpt' => '<label for="post_excerpt" class="bold">' . __('Type and position:') . '</label>' .
                    '<div class="map_toolbar">' . __('Search:') . '<span class="map_spacer">&nbsp;</span>' .
                    '<input size="40" maxlength="255" type="text" id="address" class="qx"><input id="geocode" type="submit" value="' . __('OK') . '"><span class="map_spacer">&nbsp;</span>' .
                    '<button id="add_marker" class="add_marker" type="button" title="' . __('Point of interest') . '"><span>' . __('Point of interest') . '</span></button>' .
                    '<button id="add_polyline" class="add_polyline" type="button" title="' . __('Polyline') . '"><span>' . __('Polyline') . '</span></button>' .
                    '<button id="add_polygon" class="add_polygon" type="button" title="' . __('Polygon') . '"><span>' . __('Polygon') . '</span></button>' .
                    '<button id="add_rectangle" class="add_rectangle" type="button" title="' . __('Rectangle') . '"><span>' . __('Rectangle') . '</span></button>' .
                    '<button id="add_circle" class="add_circle" type="button" title="' . __('Circle') . '"><span>' . __('Circle') . '</span></button>' .
                    '<button id="add_kml" class="add_kml" type="button" title="' . __('Included Kml file') . '"><span>' . __('Included Kml file') . '</span></button>' .
                    '<button id="add_georss" class="add_georss" type="button" title="' . __('GeoRSS Feed') . '"><span>' . __('GeoRSS Feed') . '</span></button>' .
                    '<button id="add_directions" class="add_directions" type="button" title="' . __('Directions') . '"><span>' . __('Directions') . '</span></button>' .
                    '<button id="delete_map" type="button" class="delete_map" title="' . __('Erase') . '"><span>' . __('Erase') . '</span></button>' .
                    '</div>' .
                    '<div id="map_box"><div class="area" id="map_canvas"></div><div id="panel"></div></div>' .
                    '<div class="form-note info maximal mapinfo"><p>' . __('This map will not be displayed on the blog and is meant only to create, edit and position <strong>only one</strong> element at a time. Choose a tool and click on the map to create your element, then click on the element to edit its properties.') . '</p>' .
                    '</div>' .
                    '<p class="area" id="excerpt">' . form::textarea('post_excerpt', 50, 5, html::escapeHTML(App::backend()->post_excerpt)) . '</p>',

                    'post_content' => '<p class="area" id="content-area"><label class="bold" ' .
                    'for="post_content">' . __('Description:') . '</label> ' .
                    form::textarea(
                        'post_content',
                        50,
                        App::auth()->getOption('edit_size'),
                        [
                            'default'    => Html::escapeHTML(App::backend()->post_content),
                            'extra_html' => 'placeholder="' . __('Description') . '" lang="' . App::backend()->post_lang . '" spellcheck="true"',
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
                            'default'    => Html::escapeHTML(App::backend()->post_notes),
                            'extra_html' => 'lang="' . App::backend()->post_lang . '" spellcheck="true"',
                        ]
                    ) .
                    '</p>',
                ]
            );

            # --BEHAVIOR-- adminPostFormItems -- ArrayObject, ArrayObject, MetaRecord|null
            App::behavior()->callBehavior('adminPostFormItems', $main_items, $sidebar_items, App::backend()->post ?? null, 'post');

            echo
            '<div class="multi-part" title="' . (App::backend()->post_id ? __('Edit map element') : __('New element')) .
            sprintf(' &rsaquo; %s', App::formater()->getFormaterName(App::backend()->post_format)) . '" id="edit-entry">' .
            '<form action="' . App::backend()->url()->get('admin.plugin.' . My::id(), ['act' => 'map']) . '" method="post" id="entry-form">' .
            '<div id="entry-wrapper">' .
            '<div id="entry-content"><div class="constrained">' .
            '<h3 class="out-of-screen-if-js">' . __('Edit map element') . '</h3>';

            foreach ($main_items as $item) {
                echo $item;
            }

            # --BEHAVIOR-- adminPostForm -- MetaRecord|null
            App::behavior()->callBehavior('adminPostForm', App::backend()->post ?? null, 'map');

            $plugin_QmarkURL = App::blog()->getQmarkURL();

            echo(isset(App::backend()->post->post_id) ? form::hidden('id', App::backend()->post->post_id) : '') .
            form::hidden('myGmaps_center', $myGmaps_center) .
            form::hidden('myGmaps_zoom', $myGmaps_zoom) .
            form::hidden('myGmaps_type', $myGmaps_type) .
            form::hidden('blog_url', $blog_url) .
            form::hidden('plugin_QmarkURL', $plugin_QmarkURL) .
            form::hidden('icons_list', $icons_list) .
            form::hidden('icons_base_url', $icons_base_url) .
            form::hidden('kmls_list', $kmls_list) .
            form::hidden('kmls_base_url', $kmls_base_url) .
            form::hidden('map_styles_list', $map_styles_list) .
            form::hidden('map_styles_base_url', $map_styles_base_url) ;

            if (isset(App::backend()->post)) {
                echo '<p>' . form::hidden('element_type', $meta->getMetaStr(App::backend()->post->post_meta, 'map')) . '</p>';
            } else {
                echo '<p>' . form::hidden('element_type', '') . '</p>';
            }

            echo
            '<p class="border-top">' .
            '<input type="submit" value="' . __('Save') . ' (s)" ' .
            'accesskey="s" name="save"> ' ;

            if (!isset(App::backend()->post->post_id)) {
                echo
                '<a id="post-cancel" href="' . My::manageUrl() . '&act=list#entries-list' . '" class="button" accesskey="c">' . __('Cancel') . ' (c)</a>';
            }

            echo(App::backend()->can_delete ? '<input type="submit" class="delete" value="' . __('Delete') . '" name="delete">' : '') .
                    App::nonce()->getFormNonce() .
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

            # --BEHAVIOR-- adminPostFormSidebar -- MetaRecord|null
            App::behavior()->callBehavior('adminPostFormSidebar', App::backend()->post ?? null, 'map');

            echo
            '</div>' . // End #entry-sidebar
            '</form>';

            # --BEHAVIOR-- adminPostForm -- MetaRecord|null
            App::behavior()->callBehavior('adminPostAfterForm', App::backend()->post ?? null, 'map');

            echo
            '</div>'; // End
        }

        Page::helpBlock('myGmap', 'core_wiki');

        Page::closeModule();
    }

    /**
     * Controls comments or trakbacks capabilities
     *
     * @param      mixed   $id     The identifier
     * @param      mixed   $dt     The date
     * @param      bool    $com    The com
     *
     * @return     bool    True if contribution allowed, False otherwise.
     */
    protected static function isContributionAllowed($id, $dt, bool $com = true): bool
    {
        if (!$id) {
            return true;
        }
        if ($com) {
            if ((App::blog()->settings->system->comments_ttl == 0) || (time() - App::blog()->settings->system->comments_ttl * 86400 < $dt)) {
                return true;
            }
        } else {
            if ((App::blog()->settings->system->trackbacks_ttl == 0) || (time() - App::blog()->settings->system->trackbacks_ttl * 86400 < $dt)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Shows the comments or trackbacks.
     *
     * @param      MetaRecord   $rs          The recordset
     * @param      bool         $has_action  Indicates if action is possible
     * @param      bool         $tb          Is trackbacks?
     * @param      bool         $show_ip     Show ip?
     */
    protected static function showComments(MetaRecord $rs, bool $has_action, bool $tb = false, bool $show_ip = true): void
    {
        echo
            '<div class="table-outer">' .
            '<table class="comments-list"><tr>' .
            '<th colspan="2" class="first">' . __('Author') . '</th>' .
            '<th>' . __('Date') . '</th>' .
            (App::backend()->show_ip ? '<th class="nowrap">' . __('IP address') . '</th>' : '') .
            '<th>' . __('Status') . '</th>' .
            '<th>' . __('Edit') . '</th>' .
            '</tr>';

        $comments = [];
        if (isset($_REQUEST['comments'])) {
            foreach ($_REQUEST['comments'] as $v) {
                $comments[(int) $v] = true;
            }
        }

        while ($rs->fetch()) {
            $comment_url = App::backend()->url()->get('admin.comment', ['id' => $rs->comment_id]);

            $img        = '<img alt="%1$s" title="%1$s" src="images/%2$s">';
            $img_status = '';
            $sts_class  = '';
            switch ($rs->comment_status) {
                case App::blog()::COMMENT_PUBLISHED:
                    $img_status = sprintf($img, __('Published'), 'check-on.svg');
                    $sts_class  = 'sts-online';

                    break;
                case App::blog()::COMMENT_UNPUBLISHED:
                    $img_status = sprintf($img, __('Unpublished'), 'check-off.svg');
                    $sts_class  = 'sts-offline';

                    break;
                case App::blog()::COMMENT_PENDING:
                    $img_status = sprintf($img, __('Pending'), 'check-wrn.svg');
                    $sts_class  = 'sts-pending';

                    break;
                case App::blog()::COMMENT_JUNK:
                    $img_status = sprintf($img, __('Junk'), 'junk.svg');
                    $sts_class  = 'sts-junk';

                    break;
            }

            echo
            '<tr class="line ' . ($rs->comment_status != App::blog()::COMMENT_PUBLISHED ? ' offline ' : '') . $sts_class . '"' .
            ' id="c' . $rs->comment_id . '">' .

            '<td class="nowrap">' .
            ($has_action ?
                form::checkbox(
                    ['comments[]'],
                    $rs->comment_id,
                    [
                        'checked'    => isset($comments[$rs->comment_id]),
                        'extra_html' => 'title="' . ($tb ? __('select this trackback') : __('select this comment') . '"'),
                    ]
                ) :
                '') . '</td>' .
            '<td class="maximal">' . Html::escapeHTML($rs->comment_author) . '</td>' .
            '<td class="nowrap">' .
                '<time datetime="' . Date::iso8601(strtotime($rs->comment_dt), App::auth()->getInfo('user_tz')) . '">' .
                Date::dt2str(__('%Y-%m-%d %H:%M'), $rs->comment_dt) .
                '</time>' .
            '</td>' .
            ($show_ip ?
                '<td class="nowrap"><a href="' . App::backend()->url()->get('admin.comments', ['ip' => $rs->comment_ip]) . '">' . $rs->comment_ip . '</a></td>' :
                '') .
            '<td class="nowrap status">' . $img_status . '</td>' .
            '<td class="nowrap status"><a href="' . $comment_url . '">' .
            '<img src="images/edit-mini.svg" alt="" title="' . __('Edit this comment') . '"> ' . __('Edit') . '</a></td>' .
            '</tr>';
        }

        echo
        '</table></div>';
    }
}
