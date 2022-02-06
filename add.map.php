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

dcPage::check('usage,contentadmin');

$p_url = 'plugin.php?p=' . basename(dirname(__FILE__));

$default_tab = isset($_GET['tab']) ? $_GET['tab'] : 'entries-list';

$s = &$core->blog->settings->myGmaps;

$meta = &$GLOBALS['core']->meta;

$page_title = __('Add elements');

$meta = &$GLOBALS['core']->meta;

$post_id = !empty($_GET['post_id']) ? $_GET['post_id'] : '';
$my_params['post_id'] = $post_id;
$my_params['no_content'] = true;
$my_params['post_type'] = ['post', 'page'];

$rs = $core->blog->getPosts($my_params);

$elements_list = $meta->getMetaStr($rs->post_meta, 'map');
$post_type = $rs->post_type;

if ($elements_list != '') {
    $maps_array = explode(',', $elements_list);
} else {
    $maps_array = [];
}

$__autoload['adminMapsList'] = dirname(__FILE__) . '/inc/lib.pager.php';

// Getting categories
try {
    $categories = $core->blog->getCategories(['post_type' => 'map']);
} catch (Exception $e) {
    $core->error->add($e->getMessage());
}

// Getting authors
try {
    $users = $core->blog->getPostsUsers();
} catch (Exception $e) {
    $core->error->add($e->getMessage());
}

// Getting dates
try {
    $dates = $core->blog->getDates(['type' => 'month', 'post_type' => 'map']);
} catch (Exception $e) {
    $core->error->add($e->getMessage());
}

// Getting langs
try {
    $langs = $core->blog->getLangs();
} catch (Exception $e) {
    $core->error->add($e->getMessage());
}

// Creating filter combo boxes
if (!$core->error->flag()) {
    // Filter form we'll put in html_block
    $users_combo = array_merge(
        ['-' => ''],
        dcAdminCombos::getUsersCombo($users)
    );

    $categories_combo = array_merge(
        [
            new formSelectOption('-', ''),
            new formSelectOption(__('(No cat)'), 'NULL')],
        dcAdminCombos::getCategoriesCombo($categories, false)
    );
    $categories_values = [];
    foreach ($categories_combo as $cat) {
        if (isset($cat->value)) {
            $categories_values[$cat->value] = true;
        }
    }

    $status_combo = array_merge(
        ['-' => ''],
        dcAdminCombos::getPostStatusesCombo()
    );

    $selected_combo = [
        '-' => '',
        __('Selected') => '1',
        __('Not selected') => '0'
    ];

    $attachment_combo = [
        '-' => '',
        __('With attachments') => '1',
        __('Without attachments') => '0'
    ];

    $elements_list_combo = [
        '-' => '',
        __('none') => 'none',
        __('point of interest') => 'point of interest',
        __('polyline') => 'polyline',
        __('polygon') => 'polygon',
        __('rectangle') => 'rectangle',
        __('circle') => 'circle',
        __('included kml file') => 'included kml file',
        __('GeoRSS feed') => 'GeoRSS feed',
        __('directions') => 'directions'
    ];

    // Months array
    $dt_m_combo = array_merge(
        ['-' => ''],
        dcAdminCombos::getDatesCombo($dates)
    );

    $lang_combo = array_merge(
        ['-' => ''],
        dcAdminCombos::getLangsCombo($langs, false)
    );

    $sortby_combo = [
        __('Date') => 'post_dt',
        __('Title') => 'post_title',
        __('Category') => 'cat_title',
        __('Author') => 'user_id',
        __('Status') => 'post_status',
        __('Selected') => 'post_selected',
        __('Number of comments') => 'nb_comment',
        __('Number of trackbacks') => 'nb_trackback'
    ];

    $order_combo = [
        __('Descending') => 'desc',
        __('Ascending') => 'asc'
    ];
}

/* Get posts
-------------------------------------------------------- */
$id = !empty($_GET['id']) ? $_GET['id'] : '';
$post_id = !empty($_GET['post_id']) ? $_GET['post_id'] : '';
// = !empty($_GET['post_type']) ?	$_GET['post_type'] : '';
$user_id = !empty($_GET['user_id']) ? $_GET['user_id'] : '';
$cat_id = !empty($_GET['cat_id']) ? $_GET['cat_id'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$selected = isset($_GET['selected']) ? $_GET['selected'] : '';
$attachment = isset($_GET['attachment']) ? $_GET['attachment'] : '';
$lang = !empty($_GET['lang']) ? $_GET['lang'] : '';
$month = !empty($_GET['month']) ? $_GET['month'] : '';
$sortby = !empty($_GET['sortby']) ? $_GET['sortby'] : 'post_dt';
$order = !empty($_GET['order']) ? $_GET['order'] : 'desc';

$show_filters = false;

$page = !empty($_GET['page']) ? (integer) $_GET['page'] : 1;
$nb_per_page = 30;

if (!empty($_GET['nb']) && (integer) $_GET['nb'] > 0) {
    if ($nb_per_page != $_GET['nb']) {
        $show_filters = true;
    }
    $nb_per_page = (integer) $_GET['nb'];
}

// - User filter
if ($user_id !== '' && in_array($user_id, $users_combo)) {
    $params['user_id'] = $user_id;
    $show_filters = true;
} else {
    $user_id = '';
}

// - Categories filter
if ($cat_id !== '' && isset($categories_values[$cat_id])) {
    $params['cat_id'] = $cat_id;
    $show_filters = true;
} else {
    $cat_id = '';
}

// - Status filter
if ($status !== '' && in_array($status, $status_combo)) {
    $params['post_status'] = $status;
    $show_filters = true;
} else {
    $status = '';
}

// - Selected filter
if ($selected !== '' && in_array($selected, $selected_combo)) {
    $params['post_selected'] = $selected;
    $show_filters = true;
} else {
    $selected = '';
}

// - Selected filter
if ($attachment !== '' && in_array($attachment, $attachment_combo)) {
    $params['media'] = $attachment;
    $params['link_type'] = 'attachment';
    $show_filters = true;
} else {
    $attachment = '';
}

// - Month filter
if ($month !== '' && in_array($month, $dt_m_combo)) {
    $params['post_month'] = substr($month, 4, 2);
    $params['post_year'] = substr($month, 0, 4);
    $show_filters = true;
} else {
    $month = '';
}

// - Lang filter
if ($lang !== '' && in_array($lang, $lang_combo)) {
    $params['post_lang'] = $lang;
    $show_filters = true;
} else {
    $lang = '';
}

// - Sortby and order filter
if ($sortby !== '' && in_array($sortby, $sortby_combo)) {
    if ($order !== '' && in_array($order, $order_combo)) {
        $params['order'] = $sortby . ' ' . $order;
    } else {
        $order = 'desc';
    }

    if ($sortby != 'post_dt' || $order != 'desc') {
        $show_filters = true;
    }
} else {
    $sortby = 'post_dt';
    $order = 'desc';
}

// - Map type filter
if ($elements_list != '' && in_array($elements_list, $elements_list_combo)) {
    $params['sql'] .= "AND post_meta LIKE '%" . $elements_list . "%' ";
    $show_filters = true;
} else {
    $elements_list = '';
}

// Get map elements

$params['limit'] = [(($page - 1) * $nb_per_page), $nb_per_page];
$params['no_content'] = true;
$params['post_type'] = 'map';
$params['post_status'] = '1';

try {
    $posts = $core->blog->getPosts($params);
    $counter = $core->blog->getPosts($params, true);
    $post_list = new adminMapsList($core, $posts, $counter->f(0));
} catch (Exception $e) {
    $core->error->add($e->getMessage());
}

// Save elements list

if (isset($_POST['updlist'])) {
    try {
        global $core;

        $entries = $_POST['entries'];
        $post_id = $_POST['post_id'];
        $post_type = $_POST['post_type'];

        $meta = &$GLOBALS['core']->meta;

        $meta->delPostMeta($post_id, 'map');

        $entries = implode(',', $entries);
        foreach ($meta->splitMetaValues($entries) as $tag) {
            $meta->setPostMeta($post_id, 'map', $tag);
        }

        $core->blog->triggerBlog();

        if ($post_type == 'page') {
            http::redirect('plugin.php?p=pages&act=page&id=' . $post_id . '&upd=1');
        } else {
            http::redirect(DC_ADMIN_URL . 'post.php?id=' . $post_id . '&upd=1');
        }
    } catch (Exception $e) {
        $core->error->add($e->getMessage());
    }
}

/* DISPLAY
-------------------------------------------------------- */
?>
<html>
	<head>
		<title><?php echo $page_title; ?></title>

		<?php
        $form_filter_title = __('Show filters and display options');
        $starting_script = dcPage::jsLoad(DC_ADMIN_URL . 'js/_posts_list.js');
        $starting_script .= dcPage::jsLoad(DC_ADMIN_URL . '?pf=myGmaps/js/filter-controls.js');
        $starting_script .=
        '<script>' . "\n" .
        '//<![CDATA[' . "\n" .
        dcPage::jsVar('dotclear.msg.show_filters', $show_filters ? 'true' : 'false') . "\n" .
        dcPage::jsVar('dotclear.msg.filter_posts_list', $form_filter_title) . "\n" .
        dcPage::jsVar('dotclear.msg.cancel_the_filter', __('Cancel filters and display options')) . "\n" .
        dcPage::jsVar('id', $id) . "\n" .
        '//]]>' .
        '</script>';

        echo $starting_script;
        ?>
		<link rel="stylesheet" type="text/css" href="<?php echo 'index.php?pf=myGmaps/css/admin.css' ?>" />
	</head>
	<body>
<?php

if (!$core->error->flag()) {
    $id = $post_id;
    $my_params['post_id'] = $id;
    $my_params['no_content'] = true;
    $my_params['post_type'] = ['post', 'page'];

    $rs = $core->blog->getPosts($my_params);
    $post_title = $rs->post_title;
    $post_status = $rs->post_status;
    $img_status_pattern = '<img class="img_select_option" alt="%1$s" title="%1$s" src="images/%2$s" />';

    echo dcPage::breadcrumb(
        [
            html::escapeHTML($core->blog->name) => '',
            __('Google Maps') => $p_url . '&amp;do=list',
            $page_title => ''
        ]
    );

    echo
    '<p class="clear">' . __('Select map elements for map attached to post:') . ' <a href="' . $core->getPostAdminURL($rs->post_type, $rs->post_id) . '">' . $post_title . '</a>';

    if ($id) {
        switch ($post_status) {
        case 1:
            $img_status = sprintf($img_status_pattern, __('published'), 'check-on.png');
            break;
        case 0:
            $img_status = sprintf($img_status_pattern, __('unpublished'), 'check-off.png');
            break;
        case -1:
            $img_status = sprintf($img_status_pattern, __('scheduled'), 'scheduled.png');
            break;
        case -2:
            $img_status = sprintf($img_status_pattern, __('pending'), 'check-wrn.png');
            break;
        default:
            $img_status = '';
    }
        echo '&nbsp;&nbsp;&nbsp;' . $img_status;
    }

    echo '</p>';

    echo
    '<form action="' . $p_url . '" method="get" id="filters-form">' .
    '<h3 class="out-of-screen-if-js">' . $form_filter_title . '</h3>' .
    '<div class="table">' .
    '<div class="cell">' .
    '<h4>' . __('Filters') . '</h4>' .
    '<p><label for="user_id" class="ib">' . __('Author:') . '</label> ' .
        form::combo('user_id', $users_combo, $user_id) . '</p>' .
        '<p><label for="cat_id" class="ib">' . __('Category:') . '</label> ' .
        form::combo('cat_id', $categories_combo, $cat_id) . '</p>' .
        '<p><label for="status" class="ib">' . __('Status:') . '</label> ' .
        form::combo('status', $status_combo, $status) . '</p> ' .
    '</div>' .

    '<div class="cell filters-sibling-cell">' .
        '<p><label for="selected" class="ib">' . __('Selected:') . '</label> ' .
        form::combo('selected', $selected_combo, $selected) . '</p>' .
        '<p><label for="element_type" class="ib">' . __('Type:') . '</label> ' .
        form::combo('element_type', $elements_list_combo, $elements_list) . '</p>' .
        '<p><label for="month" class="ib">' . __('Month:') . '</label> ' .
        form::combo('month', $dt_m_combo, $month) . '</p>' .
        '<p><label for="lang" class="ib">' . __('Lang:') . '</label> ' .
        form::combo('lang', $lang_combo, $lang) . '</p> ' .
    '</div>' .

    '<div class="cell filters-options">' .
        '<h4>' . __('Display options') . '</h4>' .
        '<p><label for="sortby" class="ib">' . __('Order by:') . '</label> ' .
        form::combo('sortby', $sortby_combo, $sortby) . '</p>' .
        '<p><label for="order" class="ib">' . __('Sort:') . '</label> ' .
        form::combo('order', $order_combo, $order) . '</p>' .
        '<p><span class="label ib">' . __('Show') . '</span> <label for="nb" class="classic">' .
        form::field('nb', 3, 3, $nb_per_page) . ' ' .
        __('Map elements per page') . '</label></p>' .
    '</div>' .
    '</div>' .

    '<p><input type="submit" name="maps_filters" value="' . __('Apply filters and display options') . '" />' .
    form::hidden(['add_map_filters'], 'myGmaps') .
    form::hidden(['post_id'], $post_id) .
    form::hidden(['p'], 'myGmaps') .
    $core->formNonce() .
    '</p>' .
    '</form>';

    $maplist = '';

    if (isset($maps_array)) {
        for ($i = 0; $i < sizeof($maps_array); ++$i) {
            $maplist .= '<p class="maplist" style="display:none">' . form::checkbox(['entries[]'], $maps_array[$i], 'true', '', '', '') . '</p>';
        }
    }

    // Show posts
    $post_list->display(
        $page,
        $nb_per_page,
        '<form action="' . $p_url . '" method="post" id="form-entries">' .

    '%s' .

    '<div class="two-cols">' .
    '<p class="col checkboxes-helpers"></p>' .

    $maplist .

    '<p class="col right">' .
    '<input type="submit" name="updlist" value="' . __('Add selected map elements') . '" /> <a class="button reset" href="post.php?id=' . $post_id . '">' . __('Cancel') . '</a></p>' .
    '<p>' . form::hidden(['post_id'], $post_id) .
    form::hidden(['post_type'], $post_type) .
    form::hidden(['user_id'], $user_id) .
    form::hidden(['cat_id'], $cat_id) .
    form::hidden(['status'], $status) .
    form::hidden(['month'], $month) .
    form::hidden(['sortby'], $sortby) .
    form::hidden(['order'], $order) .
    form::hidden(['page'], $page) .
    form::hidden(['nb'], $nb_per_page) .
    $core->formNonce() . '</p>' .
    '</div>' .
    '</form>',
        $show_filters
    );
}

dcPage::helpBlock('myGmapsadd');
?>
	</body>
</html>
