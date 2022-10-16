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
require_once DC_ROOT . '/inc/admin/prepend.php';

dcPage::check(dcCore::app()->auth->makePermissions([dcAuth::PERMISSION_CONTENT_ADMIN]));

dt::setTZ(dcCore::app()->auth->getInfo('user_tz'));

$s = dcCore::app()->blog->settings->myGmaps;

$plugin_QmarkURL = dcCore::app()->blog->getQmarkURL();

$myGmaps_center = $s->myGmaps_center;
$myGmaps_zoom   = $s->myGmaps_zoom;
$myGmaps_type   = $s->myGmaps_type;

$post_id            = '';
$cat_id             = '';
$post_dt            = '';
$post_type          = 'map';
$element_type       = 'none';
$post_format        = dcCore::app()->auth->getOption('post_format');
$post_editor        = dcCore::app()->auth->getOption('editor');
$post_password      = '';
$post_url           = '';
$post_lang          = dcCore::app()->auth->getInfo('user_lang');
$post_title         = '';
$post_excerpt       = '';
$post_excerpt_xhtml = '';
$post_content       = '';
$post_content_xhtml = '';
$post_notes         = '';
$post_status        = dcCore::app()->auth->getInfo('user_post_status');
$post_selected      = false;
$post_open_comment  = '';
$post_open_tb       = '';

$post_media = [];

$page_title = __('New map element');

$can_view_page = true;
$can_edit_post = dcCore::app()->auth->check(dcCore::app()->auth->makePermissions([dcAuth::PERMISSION_CONTENT_ADMIN]), dcCore::app()->blog->id);
$can_publish   = dcCore::app()->auth->check(dcCore::app()->auth->makePermissions([dcAuth::PERMISSION_CONTENT_ADMIN]), dcCore::app()->blog->id);
$can_delete    = false;

$post_headlink = '<link rel="%s" title="%s" href="map.php?id=%s" />';
$post_link     = '<a href="' . dcCore::app()->admin->getPageURL() . '&amp;do=edit&amp;id=%s" title="%s">%s</a>';

$next_link = $prev_link = $next_headlink = $prev_headlink = null;

// If user can't publish
if (!$can_publish) {
    $post_status = -2;
}

// Getting categories
$categories_combo = dcAdminCombos::getCategoriesCombo(
    dcCore::app()->blog->getCategories(['post_type' => 'map'])
);

// Status combo
$status_combo = dcAdminCombos::getPostStatusesCombo();

$img_status_pattern = '<img class="img_select_option" alt="%1$s" title="%1$s" src="images/%2$s" />';

// Formaters combo
if (version_compare(DC_VERSION, '2.7-dev', '>=')) {
    $core_formaters    = dcCore::app()->getFormaters();
    $available_formats = ['' => ''];
    foreach ($core_formaters as $editor => $formats) {
        foreach ($formats as $format) {
            $available_formats[$format] = $format;
        }
    }
} else {
    foreach (dcCore::app()->getFormaters() as $v) {
        $available_formats[$v] = $v;
    }
}

// Languages combo
$rs         = dcCore::app()->blog->getLangs(['order' => 'asc']);
$lang_combo = dcAdminCombos::getLangsCombo($rs, true);

// Custom marker icons

$public_path = dcCore::app()->blog->public_path;
$public_url  = dcCore::app()->blog->settings->system->public_url;
$blog_url    = dcCore::app()->blog->url;

$icons_dir_path = $public_path . '/myGmaps/icons/';
$icons_dir_url  = http::concatURL(dcCore::app()->blog->url, $public_url . '/myGmaps/icons/');

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
$kmls_dir_url  = http::concatURL(dcCore::app()->blog->url, $public_url . '/myGmaps/kml_files/');

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
$map_styles_dir_url  = http::concatURL(dcCore::app()->blog->url, $public_url . '/myGmaps/styles/');

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

// Validation flag
$bad_dt = false;

// Get entry informations
if (!empty($_REQUEST['id'])) {
    $params['post_id']   = $_REQUEST['id'];
    $params['post_type'] = 'map';

    $post = dcCore::app()->blog->getPosts($params);

    if ($post->isEmpty()) {
        dcCore::app()->error->add(__('This map element does not exist.'));
        $can_view_page = false;
    } else {
        $post_id   = $post->post_id;
        $cat_id    = $post->cat_id;
        $post_dt   = date('Y-m-d H:i', strtotime($post->post_dt));
        $post_type = $post->post_type;

        $post_format        = $post->post_format;
        $post_password      = $post->post_password;
        $post_url           = $post->post_url;
        $post_lang          = $post->post_lang;
        $post_title         = $post->post_title;
        $post_excerpt       = $post->post_excerpt;
        $post_excerpt_xhtml = $post->post_excerpt_xhtml;
        $post_content       = $post->post_content;
        $post_content_xhtml = $post->post_content_xhtml;
        $post_notes         = $post->post_notes;
        $post_status        = $post->post_status;
        $post_selected      = (bool) $post->post_selected;
        $post_open_comment  = (bool) $post->post_open_comment;
        $post_open_tb       = (bool) $post->post_open_tb;

        $page_title = __('Edit map element');

        $can_edit_post = $post->isEditable();
        $can_delete    = $post->isDeletable();

        $next_rs = dcCore::app()->blog->getNextPost($post, 1);
        $prev_rs = dcCore::app()->blog->getNextPost($post, -1);

        if ($next_rs !== null) {
            $next_link = sprintf(
                $post_link,
                $next_rs->post_id,
                html::escapeHTML($next_rs->post_title),
                __('Next element') . '&nbsp;&#187;'
            );
            $next_headlink = sprintf(
                $post_headlink,
                'next',
                html::escapeHTML($next_rs->post_title),
                $next_rs->post_id
            );
        }

        if ($prev_rs !== null) {
            $prev_link = sprintf(
                $post_link,
                $prev_rs->post_id,
                html::escapeHTML($prev_rs->post_title),
                '&#171;&nbsp;' . __('Previous element')
            );
            $prev_headlink = sprintf(
                $post_headlink,
                'previous',
                html::escapeHTML($prev_rs->post_title),
                $prev_rs->post_id
            );
        }

        try {
            dcCore::app()->media = new dcMedia();
            $post_media          = dcCore::app()->media->getPostMedia($post_id);
        } catch (Exception $e) {
        }
    }
}

// Format excerpt and content
if (!empty($_POST) && $can_edit_post) {
    $meta = dcCore::app()->meta;

    $post_format  = $_POST['post_format'];
    $post_excerpt = $_POST['post_excerpt'];
    $post_content = $_POST['post_content'];

    $post_title = $_POST['post_title'];

    $cat_id = (int) $_POST['cat_id'];

    if (isset($_POST['post_status'])) {
        $post_status = (int) $_POST['post_status'];
    }

    if (empty($_POST['post_dt'])) {
        $post_dt = '';
    } else {
        try {
            $post_dt = strtotime($_POST['post_dt']);
            if ($post_dt == false || $post_dt == -1) {
                $bad_dt = true;

                throw new Exception(__('Invalid publication date'));
            }
            $post_dt = date('Y-m-d H:i', $post_dt);
        } catch (Exception $e) {
            dcCore::app()->error->add($e->getMessage());
        }
    }

    $post_open_comment = !empty($_POST['post_open_comment']);
    $post_open_tb      = !empty($_POST['post_open_tb']);
    $post_selected     = !empty($_POST['post_selected']);
    $post_lang         = $_POST['post_lang'];
    $post_password     = !empty($_POST['post_password']) ? $_POST['post_password'] : null;

    $post_notes = $_POST['post_notes'];

    if (isset($_POST['post_url'])) {
        $post_url = $_POST['post_url'];
    }

    dcCore::app()->blog->setPostContent(
        $post_id,
        $post_format,
        $post_lang,
        $post_excerpt,
        $post_excerpt_xhtml,
        $post_content,
        $post_content_xhtml
    );
}

// Delete post
if (!empty($_POST['delete']) && $can_delete) {
    try {
        // --BEHAVIOR-- adminBeforePostDelete
        dcCore::app()->callBehavior('adminBeforePostDelete', $post_id);
        dcCore::app()->blog->delPost($post_id);
        http::redirect('' . dcCore::app()->admin->getPageURL() . '&do=list');
    } catch (Exception $e) {
        dcCore::app()->error->add($e->getMessage());
    }
}

// Create or update post
if (!empty($_POST) && !empty($_POST['save']) && $can_edit_post) {
    if ($post_content == '' || $post_content == __('No description.') || $post_content == '<p>' . __('No description.') . '</p>') {
        if ($post_format == 'wiki') {
            $post_content = __('No description.');
            $description  = 'none';
        } elseif ($post_format == 'xhtml') {
            $post_content = '<p>' . __('No description.') . '</p>';
            $description  = 'none';
        }
    } else {
        $description = 'description';
    }
    // Create category
    if (!empty($_POST['new_cat_title']) && dcCore::app()->auth->check('categories', dcCore::app()->blog->id)) {
        $cur_cat            = dcCore::app()->con->openCursor(dcCore::app()->prefix . 'category');
        $cur_cat->cat_title = $_POST['new_cat_title'];
        $cur_cat->cat_url   = '';

        $parent_cat = !empty($_POST['new_cat_parent']) ? $_POST['new_cat_parent'] : '';

        // --BEHAVIOR-- adminBeforeCategoryCreate
        dcCore::app()->callBehavior('adminBeforeCategoryCreate', $cur_cat);

        $cat_id = dcCore::app()->blog->addCategory($cur_cat, (int) $parent_cat);

        // --BEHAVIOR-- adminAfterCategoryCreate
        dcCore::app()->callBehavior('adminAfterCategoryCreate', $cur_cat, $cat_id);
    }

    $cur = dcCore::app()->con->openCursor(dcCore::app()->prefix . 'post');

    $cur->post_title         = $post_title;
    $cur->cat_id             = ($cat_id ? $cat_id : null);
    $cur->post_dt            = $post_dt ? date('Y-m-d H:i:00', strtotime($post_dt)) : '';
    $cur->post_type          = $post_type;
    $cur->post_format        = $post_format;
    $cur->post_password      = $post_password;
    $cur->post_lang          = $post_lang;
    $cur->post_title         = $post_title;
    $cur->post_excerpt       = $post_excerpt;
    $cur->post_excerpt_xhtml = $post_excerpt_xhtml;
    $cur->post_content       = $post_content;
    $cur->post_content_xhtml = $post_content_xhtml;
    $cur->post_notes         = $post_notes;
    $cur->post_status        = $post_status;
    $cur->post_selected      = (int) $post_selected;
    $cur->post_open_comment  = (int) $post_open_comment;
    $cur->post_open_tb       = (int) $post_open_tb;

    if (isset($_POST['post_url'])) {
        $cur->post_url = $post_url;
    }

    // Back to UTC in order to keep UTC datetime for creadt/upddt
    dt::setTZ('UTC');

    // Update post
    if ($post_id) {
        try {
            // --BEHAVIOR-- adminBeforePostUpdate
            dcCore::app()->callBehavior('adminBeforePostUpdate', $cur, $post_id);
            dcCore::app()->con->begin();
            dcCore::app()->blog->updPost($post_id, $cur);
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
            dcCore::app()->con->commit();
            // --BEHAVIOR-- adminAfterPostUpdate
            dcCore::app()->callBehavior('adminAfterPostUpdate', $cur, $post_id);

            http::redirect('' . dcCore::app()->admin->getPageURL() . '&do=edit&id=' . $post_id . '&upd=1');
        } catch (Exception $e) {
            dcCore::app()->error->add($e->getMessage());
        }
    } else {
        $cur->user_id = dcCore::app()->auth->userID();

        try {
            // --BEHAVIOR-- adminBeforePostCreate
            dcCore::app()->callBehavior('adminBeforePostCreate', $cur);

            $return_id = dcCore::app()->blog->addPost($cur);

            // --BEHAVIOR-- adminAfterPostCreate
            dcCore::app()->callBehavior('adminAfterPostCreate', $cur, $return_id);

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
            http::redirect('' . dcCore::app()->admin->getPageURL() . '&do=edit&id=' . $return_id . '&crea=1');
        } catch (Exception $e) {
            dcCore::app()->error->add($e->getMessage());
        }
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

/* DISPLAY
-------------------------------------------------------- */
$default_tab = 'edit-entry';

$admin_post_behavior = '';
if ($post_editor && !empty($post_editor[$post_format])) {
    if ($post_format == 'xhtml') {
        $admin_post_behavior = dcCore::app()->callBehavior(
            'adminPostEditor',
            $post_editor[$post_format],
            'map',
            ['#post_content']
        );
    } elseif ($post_format == 'wiki') {
        $admin_post_behavior = dcCore::app()->callBehavior(
            'adminPostEditor',
            $post_editor[$post_format],
            'map',
            ['#post_content', '#post_excerpt']
        );
    }
}

if ($post_id) {
    switch ($post_status) {
        case 1:
            $img_status = sprintf($img_status_pattern, __('Published'), 'check-on.png');

            break;
        case 0:
            $img_status = sprintf($img_status_pattern, __('Unpublished'), 'check-off.png');

            break;
        case -1:
            $img_status = sprintf($img_status_pattern, __('Scheduled'), 'scheduled.png');

            break;
        case -2:
            $img_status = sprintf($img_status_pattern, __('Pending'), 'check-wrn.png');

            break;
        default:
            $img_status = '';
    }
    $edit_entry_str  = __('&ldquo;%s&rdquo;');
    $page_title_edit = sprintf($edit_entry_str, html::escapeHTML($post_title)) . ' ' . $img_status;
} else {
    $img_status = '';
}

?>
<html>
	<head>
		<title><?php echo $page_title; ?></title>
		<?php
        echo
        dcPage::jsModal() .
        dcPage::jsMetaEditor() .
        $admin_post_behavior .
        dcPage::jsLoad('js/_post.js') .
        dcPage::jsLoad(DC_ADMIN_URL . '?pf=myGmaps/js/element.map.js') .
        dcPage::jsConfirmClose('entry-form') .
        // --BEHAVIOR-- adminPostHeaders
        dcCore::app()->callBehavior('adminPostHeaders') .
        dcPage::jsPageTabs($default_tab) .
        $next_headlink . "\n" . $prev_headlink
?>
    <?php

    echo
    '<script>' . "\n" .
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

// Add default and user map styles

echo
'<script>' . "\n" .
    '//<![CDATA[' . "\n";

echo
    'var neutral_blue_styles = [{"featureType":"water","elementType":"geometry","stylers":[{"color":"#193341"}]},{"featureType":"landscape","elementType":"geometry","stylers":[{"color":"#2c5a71"}]},{"featureType":"road","elementType":"geometry","stylers":[{"color":"#29768a"},{"lightness":-37}]},{"featureType":"poi","elementType":"geometry","stylers":[{"color":"#406d80"}]},{"featureType":"transit","elementType":"geometry","stylers":[{"color":"#406d80"}]},{"elementType":"labels.text.stroke","stylers":[{"visibility":"on"},{"color":"#3e606f"},{"weight":2},{"gamma":0.84}]},{"elementType":"labels.text.fill","stylers":[{"color":"#ffffff"}]},{"featureType":"administrative","elementType":"geometry","stylers":[{"weight":0.6},{"color":"#1a3541"}]},{"elementType":"labels.icon","stylers":[{"visibility":"off"}]},{"featureType":"poi.park","elementType":"geometry","stylers":[{"color":"#2c5a71"}]}];' . "\n" .
    'var neutral_blue = new google.maps.StyledMapType(neutral_blue_styles,{name: "Neutral Blue"});' . "\n";

if (is_dir($map_styles_dir_path)) {
    $list = explode(',', $map_styles_list);
    foreach ($list as $map_style) {
        $map_style_content = file_get_contents($map_styles_dir_path . '/' . $map_style);
        $var_styles_name   = pathinfo($map_style, PATHINFO_FILENAME);
        $var_name          = preg_replace('/_styles/s', '', $var_styles_name);
        $nice_name         = ucwords(preg_replace('/_/s', ' ', $var_name));
        echo
        'var ' . $var_styles_name . ' = ' . $map_style_content . ';' . "\n" .
        'var ' . $var_name . ' = new google.maps.StyledMapType(' . $var_styles_name . ',{name: "' . $nice_name . '"});' . "\n";
    }
}

echo
    '//]]>' . "\n" .
'</script>';

?>

	</head>
	<body>
<?php
echo dcPage::breadcrumb(
    [
        html::escapeHTML(dcCore::app()->blog->name) => '',
        __('Google Maps')                           => dcCore::app()->admin->getPageURL() . '&amp;do=list',
        ($post_id ? $page_title_edit : $page_title) => '',
    ]
);

if ($post_id) {
    echo '<p class="nav_prevnext">';
    if ($prev_link) {
        echo $prev_link;
    }
    if ($next_link && $prev_link) {
        echo ' | ';
    }
    if ($next_link) {
        echo $next_link;
    }

    // --BEHAVIOR-- adminPostNavLinks
    dcCore::app()->callBehavior('adminPostNavLinks', $post ?? null);

    echo '</p>';
}

if (!empty($_GET['upd'])) {
    dcPage::success(__('Map element has been updated.'));
} elseif (!empty($_GET['crea'])) {
    dcPage::success(__('Map element has been created.'));
}

// XHTML conversion
if (!empty($_GET['xconv'])) {
    $post_excerpt = $post_excerpt_xhtml;
    $post_content = $post_content_xhtml;
    $post_format  = 'xhtml';

    echo '<p class="message">' . __('Don\'t forget to validate your XHTML conversion by saving your post.') . '</p>';
}

// Exit if we cannot view page
if (!$can_view_page) {
    dcPage::helpBlock('core_post');
    dcPage::close();
    exit;
}

/* Post form if we can edit post
-------------------------------------------------------- */
if ($can_edit_post) {
    $sidebar_items = new ArrayObject([
        'status-box' => [
            'title' => __('Status'),
            'items' => [
                'post_status' => '<p class="entry-status"><label for="post_status">' . __('Map element status') . ' ' . $img_status . '</label>' .
                    form::combo('post_status', $status_combo, $post_status, 'maximal', '', !$can_publish) .
                    '</p>',
                'post_dt' => '<p><label for="post_dt">' . __('Publication date and hour') . '</label>' .
                    form::datetime('post_dt', [
                        'default' => html::escapeHTML(dt::str('%Y-%m-%dT%H:%M', strtotime($post_dt))),
                        'class'   => ($bad_dt ? 'invalid' : ''),
                    ]) .
                    '</p>',
                'post_lang' => '<p><label for="post_lang">' . __('Element language') . '</label>' .
                    form::combo('post_lang', $lang_combo, $post_lang) .
                    '</p>',
                'post_format' => '<div>' .
                    '<h5 id="label_format"><label for="post_format" class="classic">' . __('Text formatting') . '</label></h5>' .
                    '<p>' . form::combo('post_format', $available_formats, $post_format, 'maximal') . '</p>' .
                    '<p class="format_control control_no_xhtml">' .
                    '<a id="convert-xhtml" class="button' . ($post_id && $post_format != 'wiki' ? ' hide' : '') . '" href="' .
                    dcCore::app()->adminurl->get('admin.post', ['id' => $post_id, 'xconv' => '1']) .
                    '">' .
                    __('Convert to XHTML') . '</a></p></div>', ], ],
        'options-box' => [
            'title' => __('Filing'),
            'items' => [
                'post_selected' => '<p><label for="post_selected" class="classic">' .
                    form::checkbox('post_selected', 1, $post_selected) . ' ' .
                    __('Selected element') . '</label></p>',
                'cat_id' => '<div>' .
                    '<h5 id="label_cat_id">' . __('Category') . '</h5>' .
                    '<p><label for="cat_id">' . __('Category:') . '</label>' .
                    form::combo('cat_id', $categories_combo, $cat_id, 'maximal') .
                    '</p>' .
                    (dcCore::app()->auth->check('categories', dcCore::app()->blog->id) ?
                        '<div>' .
                        '<h5 id="create_cat">' . __('Add a new category') . '</h5>' .
                        '<p><label for="new_cat_title">' . __('Title:') . ' ' .
                        form::field('new_cat_title', 30, 255, '', 'maximal') . '</label></p>' .
                        '<p><label for="new_cat_parent">' . __('Parent:') . ' ' .
                        form::combo('new_cat_parent', $categories_combo, '', 'maximal') .
                        '</label></p>' .
                        '</div>'
                    : '') .
                    '</div>', ], ],
    ]);

    $main_items = new ArrayObject(
        [
            'post_title' => '<p class="col">' .
                '<label class="required no-margin bold"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Title:') . '</label>' .
                form::field('post_title', 20, 255, html::escapeHTML($post_title), 'maximal') .
                '</p>',

            'post_excerpt' => '<label class="bold">' . __('Position:') . '</label>' .
                '<div class="map_toolbar">' . __('Search:') . '<span class="map_spacer">&nbsp;</span>' .
                '<input size="40" maxlength="255" type="text" id="address" class="qx" /><input id="geocode" type="submit" value="' . __('OK') . '" /><span class="map_spacer">&nbsp;</span>' .
                '<button id="add_marker" class="add_marker" type="button" title="' . __('Point of interest') . '"><span>' . __('Point of interest') . '</span></button>' .
                '<button id="add_polyline" class="add_polyline" type="button" title="' . __('Polyline') . '"><span>' . __('Polyline') . '</span></button>' .
                '<button id="add_polygon" class="add_polygon" type="button" title="' . __('Polygon') . '"><span>' . __('Polygon') . '</span></button>' .
                '<button id="add_rectangle" class="add_rectangle" type="button" title="' . __('Rectangle') . '"><span>' . __('Rectangle') . '</span></button>' .
                '<button id="add_circle" class="add_circle" type="button" title="' . __('Circle') . '"><span>' . __('Circle') . '</span></button>' .
                '<button id="add_kml" class="add_kml" type="button" title="' . __('Included Kml file') . '"><span>' . __('Included Kml file') . '</span></button>' .
                '<button id="add_georss" class="add_georss" type="button" title="' . __('GeoRSS Feed') . '"><span>' . __('GeoRSS Feed') . '</span></button>' .
                '<button id="add_directions" class="add_directions" type="button" title="' . __('Directions') . '"><span>' . __('Directions') . '</span></button>' .
                '<button id="delete_map" type="button" class="delete_map" title="' . __('Initialize map') . '"><span>' . __('Initialize map') . '</span></button>' .
                '</div>' .
                '<div id="map_box"><div class="area" id="map_canvas"></div><div id="panel"></div></div>' .
                '<div class="form-note info maximal mapinfo" style="width: 100%"><p>' . __('This map will not be displayed on the blog and is meant only to create, edit and position only one element at a time. Choose a tool and click on the map to create your element, then click on the element to edit its properties.') . '</p>' .
                '</div>' .
                '<p class="area" id="excerpt"><span style="display:none;">' . form::textarea('post_excerpt', 50, 5, html::escapeHTML($post_excerpt)) . '</span></p>',

            'post_content' => '<p class="area" id="content-area"><label class="bold" ' .
                'for="post_content">' . __('Description:') . '</label> ' .
                form::textarea('post_content', 50, dcCore::app()->auth->getOption('edit_size'), html::escapeHTML($post_content)) .
                '</p>',

            'post_notes' => '<p class="area" id="notes-area"><label for="post_notes" class="bold">' . __('Personal notes:') . ' <span class="form-note">' .
                __('Unpublished notes.') . '</span></label>' .
                form::textarea('post_notes', 50, 5, html::escapeHTML($post_notes)) .
                '</p>' .
                '<p><input type="text" class="hidden" id="blog_url" value="' . $blog_url . '" />' .
                '<input type="text" class="hidden" id="plugin_QmarkURL" value="' . $plugin_QmarkURL . '" />' .
                '<input type="text" class="hidden" id="icons_list" value="' . $icons_list . '" />' .
                '<input type="text" class="hidden" id="icons_base_url" value="' . $icons_base_url . '" />' .
                '<input type="text" class="hidden" id="kmls_list" value="' . $kmls_list . '" />' .
                '<input type="text" class="hidden" id="kmls_base_url" value="' . $kmls_base_url . '" />' .
                '<input type="text" class="hidden" id="map_styles_list" value="' . $map_styles_list . '" />' .
                '<input type="text" class="hidden" id="map_styles_base_url" value="' . $map_styles_base_url . '" /></p>',
        ]
    );

    // --BEHAVIOR-- adminPostFormItems
    dcCore::app()->callBehavior('adminPostFormItems', $main_items, $sidebar_items, $post ?? null);

    echo '<div class="multi-part" title="' . ($post_id ? __('Edit map element') : __('New map element')) . '" id="edit-entry">';
    echo '<form action="' . dcCore::app()->admin->getPageURL() . '&amp;do=edit" method="post" id="entry-form">';
    echo '<div id="entry-wrapper">';
    echo '<div id="entry-content"><div class="constrained">';

    echo '<h3 class="out-of-screen-if-js">' . __('Edit map element') . '</h3>';

    foreach ($main_items as $id => $item) {
        echo $item;
    }

    $meta = dcCore::app()->meta;

    if (isset($post)) {
        echo '<p>' . form::hidden('element_type', $meta->getMetaStr($post->post_meta, 'map')) . '</p>';
    } else {
        echo '<p>' . form::hidden('element_type', '') . '</p>';
    }

    if (isset($post)) {
        $meta_rs = $meta->getMetaStr($post->post_meta, 'map_options');
        if ($meta_rs) {
            $map_options    = explode(',', $meta_rs);
            $myGmaps_center = $map_options[0] . ',' . $map_options[1];
            $myGmaps_zoom   = $map_options[2];
            $myGmaps_type   = $map_options[3];
        }
    }

    echo
    '<p class="border-top">' .
    ($post_id ? form::hidden('id', $post_id) : '') .
    '<input type="submit" value="' . __('Save') . ' (s)" ' .
    'accesskey="s" name="save" /> ' .
    '<input type="hidden" name="myGmaps_center" id="myGmaps_center" value="' . $myGmaps_center . '" />' .
    '<input type="hidden" name="myGmaps_zoom" id="myGmaps_zoom" value="' . $myGmaps_zoom . '" />' .
    '<input type="hidden" name="myGmaps_type" id="myGmaps_type" value="' . $myGmaps_type . '" />';

    if (!$post_id) {
        echo
        '<a id="post-cancel" href="index.php" class="button" accesskey="c">' . __('Cancel') . ' (c)</a>';
    }

    echo($can_delete ? '<input type="submit" class="delete" value="' . __('Delete') . '" name="delete" />' : '') .
    dcCore::app()->formNonce() .
    '</p>';

    echo '</div></div>';		// End #entry-content
    echo '</div>';		// End #entry-wrapper

    echo '<div id="entry-sidebar">';

    foreach ($sidebar_items as $id => $c) {
        if (!empty($c['title'])) {
            echo '<div id="' . $id . '" class="sb-box">';
            echo '<h4>' . $c['title'] . '</h4>';
            foreach ($c['items'] as $e_name => $e_content) {
                echo $e_content;
            }
            echo '</div>';
        }
    }

    echo '</div>';		// End #entry-sidebar

    echo '</form>';

    // --BEHAVIOR-- adminPostForm

    echo '</div>';
}

dcPage::helpBlock('myGmap', 'core_wiki');
?>
	</body>
</html>
