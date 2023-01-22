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

class adminMapsList extends adminGenericList
{
    public function display($page, $nb_per_page, $enclose_block = '', $filter = false)
    {
        if ($this->rs->isEmpty()) {
            if ($filter) {
                echo '<p><strong>' . __('No element matches the filter') . '</strong></p>';
            } else {
                echo '<p><strong>' . __('No map element') . '</strong></p>';
            }
        } else {
            $pager   = new dcPager($page, $this->rs_count, $nb_per_page, 10);
            $entries = [];
            if (isset($_REQUEST['entries'])) {
                foreach ($_REQUEST['entries'] as $v) {
                    $entries[(int) $v] = true;
                }
            }
            $html_block = '<div class="table-outer">' .
            '<table>';

            if ($filter) {
                $html_block .= '<caption>' . sprintf(__('List of %s elements match the filter.'), $this->rs_count) . '</caption>';
            } else {
                $nb_published   = dcCore::app()->blog->getPosts(['post_type' => 'map', 'post_status' => 1], true)->f(0);
                $nb_pending     = dcCore::app()->blog->getPosts(['post_type' => 'map', 'post_status' => -2], true)->f(0);
                $nb_programmed  = dcCore::app()->blog->getPosts(['post_type' => 'map', 'post_status' => -1], true)->f(0);
                $nb_unpublished = dcCore::app()->blog->getPosts(['post_type' => 'map', 'post_status' => 0], true)->f(0);
                $html_block .= '<caption>' .
                sprintf(__('Elements list (%s)'), $this->rs_count) .
                    ($nb_published ?
                    sprintf(
                        __(', <a href="%s">published</a> (1)', ', <a href="%s">published</a> (%s)', $nb_published),
                        dcCore::app()->admin->getPageURL() . '&amp;do=list&status=1',
                        $nb_published
                    ) : '') .
                    ($nb_pending ?
                    sprintf(
                        __(', <a href="%s">pending</a> (1)', ', <a href="%s">pending</a> (%s)', $nb_pending),
                        dcCore::app()->admin->getPageURL() . '&amp;do=list&status=-2',
                        $nb_pending
                    ) : '') .
                    ($nb_programmed ?
                    sprintf(
                        __(', <a href="%s">programmed</a> (1)', ', <a href="%s">programmed</a> (%s)', $nb_programmed),
                        dcCore::app()->admin->getPageURL() . '&amp;do=list&status=-1',
                        $nb_programmed
                    ) : '') .
                    ($nb_unpublished ?
                    sprintf(
                        __(', <a href="%s">unpublished</a> (1)', ', <a href="%s">unpublished</a> (%s)', $nb_unpublished),
                        dcCore::app()->admin->getPageURL() . '&amp;do=list&status=0',
                        $nb_unpublished
                    ) : '') .
                    '</caption>';
            }

            $html_block .= '<tr>' .
            '<th colspan="2" class="first">' . __('Title') . '</th>' .
            '<th scope="col">' . __('Date') . '</th>' .
            '<th scope="col">' . __('Category') . '</th>' .
            '<th scope="col">' . __('Author') . '</th>' .
            '<th scope="col" class="nowrap">' . __('Type') . '</th>' .
            '<th scope="col">' . __('Status') . '</th>' .
            '</tr>%s</table></div>';

            if ($enclose_block) {
                $html_block = sprintf($enclose_block, $html_block);
            }

            echo $pager->getLinks();

            $blocks = explode('%s', $html_block);

            echo $blocks[0];

            while ($this->rs->fetch()) {
                echo $this->postLine(isset($entries[$this->rs->post_id]));
            }

            echo $blocks[1];

            $fmt = fn ($title, $image) => sprintf('<img alt="%1$s" title="%1$s" src="images/%2$s" /> %1$s', $title, $image);
            echo '<p class="info">' . __('Legend: ') .
                $fmt(__('Published'), 'check-on.png') . ' - ' .
                $fmt(__('Unpublished'), 'check-off.png') . ' - ' .
                $fmt(__('Scheduled'), 'scheduled.png') . ' - ' .
                $fmt(__('Pending'), 'check-wrn.png') . ' - ' .
                $fmt(__('Selected'), 'selected.png') .
                '</p>';

            echo $pager->getLinks();
        }
    }

    private function postLine($checked)
    {
        $s = dcCore::app()->blog->settings->myGmaps;

        if (dcCore::app()->auth->check('categories', dcCore::app()->blog->id)) {
            $cat_link = '<a href="category.php?id=%s">%s</a>';
        } else {
            $cat_link = '%2$s';
        }

        if ($this->rs->cat_title) {
            $cat_title = sprintf(
                $cat_link,
                $this->rs->cat_id,
                html::escapeHTML($this->rs->cat_title)
            );
        } else {
            $cat_title = __('(No cat)');
        }

        $img = '<img alt="%1$s" title="%1$s" src="images/%2$s" />';
        switch ($this->rs->post_status) {
            case 1:
                $img_status = sprintf($img, __('Published'), 'check-on.png');

                break;
            case 0:
                $img_status = sprintf($img, __('Unpublished'), 'check-off.png');

                break;
            case -1:
                $img_status = sprintf($img, __('Scheduled'), 'scheduled.png');

                break;
            case -2:
                $img_status = sprintf($img, __('Pending'), 'check-wrn.png');

                break;
        }

        $protected = '';
        if ($this->rs->post_password) {
            $protected = sprintf($img, __('Protected'), 'locker.png');
        }

        $selected = '';
        if ($this->rs->post_selected) {
            $selected = sprintf($img, __('Selected'), 'selected.png');
        }

        $attach   = '';
        $nb_media = $this->rs->countMedia();
        if ($nb_media > 0) {
            $attach_str = $nb_media == 1 ? __('%d attachment') : __('%d attachments');
            $attach     = sprintf($img, sprintf($attach_str, $nb_media), 'attach.png');
        }

        $res = '<tr class="line' . ($this->rs->post_status != 1 ? ' offline' : '') . '"' .
        ' id="p' . $this->rs->post_id . '">';

        $meta    = dcCore::app()->meta;
        $meta_rs = $meta->getMetaStr($this->rs->post_meta, 'map');

        if ($s->myGmaps_enabled) {
            $res .= '<td class="nowrap">' .
        form::checkbox(['entries[]'], $this->rs->post_id, $checked, '', '', !$this->rs->isEditable()) . '</td>' .
        '<td class="maximal"><a href="' . dcCore::app()->admin->getPageURL() . '&amp;do=edit&amp;id=' . $this->rs->post_id . '">' .
        html::escapeHTML($this->rs->post_title) . '</a></td>' .
        '<td class="nowrap count">' . dt::dt2str(__('%Y-%m-%d %H:%M'), $this->rs->post_dt) . '</td>' .
        '<td class="nowrap">' . $cat_title . '</td>' .
        '<td class="nowrap">' . html::escapeHTML($this->rs->user_id) . '</td>' .
        '<td class="nowrap">' . __($meta_rs) . '</td>' .
        '<td class="nowrap status">' . $img_status . ' ' . $selected . ' ' . $protected . ' ' . $attach . '</td>' .
        '</tr>';
        } else {
            $res .= '<td class="nowrap">' .
        form::checkbox(['entries[]'], $this->rs->post_id, $checked, '', '', !$this->rs->isEditable()) . '</td>' .
        '<td class="maximal">' .
        html::escapeHTML($this->rs->post_title) . '</td>' .
        '<td class="nowrap count">' . dt::dt2str(__('%Y-%m-%d %H:%M'), $this->rs->post_dt) . '</td>' .
        '<td class="nowrap">' . $cat_title . '</td>' .
        '<td class="nowrap">' . html::escapeHTML($this->rs->user_id) . '</td>' .
        '<td class="nowrap">' . __($meta_rs) . '</td>' .
        '<td class="nowrap status">' . $img_status . ' ' . $selected . ' ' . $protected . ' ' . $attach . '</td>' .
        '</tr>';
        }

        return $res;
    }
}
class adminMapsMiniList extends adminGenericList
{
    public function display($page, $nb_per_page, $enclose_block, $id, $type)
    {
        if ($this->rs->isEmpty()) {
            $res = '<p><strong>' . __('No entry') . '</strong></p>';
        } else {
            $pager = new dcPager($page, $this->rs_count, $nb_per_page, 10);

            $html_block = '<div class="table-outer clear">' .
            '<table><caption class="hidden">' . __('Elements list') . '</caption><tr>' .
            '<th scope="col">' . __('Title') . '</th>' .
            '<th scope="col">' . __('Date') . '</th>' .
            '<th scope="col">' . __('Category') . '</th>' .
            '<th scope="col">' . __('Type') . '</th>' .
            '<th scope="col">' . __('Status') . '</th>' .
            '<th scope="col">' . __('Actions') . '</th>' .
            '</tr>%s</table></div>';

            if ($enclose_block) {
                $html_block = sprintf($enclose_block, $html_block);
            }

            $blocks = explode('%s', $html_block);

            $res = $blocks[0];

            while ($this->rs->fetch()) {
                $res .= $this->postLine($id, $type);
            }

            $res .= $blocks[1];

            return $res;
        }
    }

    private function postLine($id, $type)
    {
        $img = '<img alt="%1$s" title="%1$s" src="images/%2$s" />';
        switch ($this->rs->post_status) {
            case dcBlog::POST_PUBLISHED:
                $img_status = sprintf($img, __('published'), 'check-on.png');

                break;
            case dcBlog::POST_UNPUBLISHED:
                $img_status = sprintf($img, __('unpublished'), 'check-off.png');

                break;
            case dcBlog::POST_SCHEDULED:
                $img_status = sprintf($img, __('scheduled'), 'scheduled.png');

                break;
            case dcBlog::POST_PENDING:
                $img_status = sprintf($img, __('pending'), 'check-wrn.png');

                break;
        }

        $protected = '';
        if ($this->rs->post_password) {
            $protected = sprintf($img, __('Protected'), 'locker.png');
        }

        $selected = '';
        if ($this->rs->post_selected) {
            $selected = sprintf($img, __('Selected'), 'selected.png');
        }

        $attach   = '';
        $nb_media = $this->rs->countMedia();
        if ($nb_media > 0) {
            $attach_str = $nb_media == 1 ? __('%d attachment') : __('%d attachments');
            $attach     = sprintf($img, sprintf($attach_str, $nb_media), 'attach.png');
        }

        if (dcCore::app()->auth->check('categories', dcCore::app()->blog->id)) {
            $cat_link = '<a href="category.php?id=%s">%s</a>';
        } else {
            $cat_link = '%2$s';
        }
        if ($this->rs->cat_title) {
            $cat_title = sprintf(
                $cat_link,
                $this->rs->cat_id,
                html::escapeHTML($this->rs->cat_title)
            );
        } else {
            $cat_title = __('None');
        }

        $meta    = dcCore::app()->meta;
        $meta_rs = $meta->getMetaStr($this->rs->post_meta, 'map');

        $res = '<tr class="line' . ($this->rs->post_status != 1 ? ' offline' : '') . '"' .
        ' id="p' . $this->rs->post_id . '">';

        $res .= '<td class="maximal"><a href="plugin.php?p=myGmaps&amp;do=edit&amp;id=' . $this->rs->post_id . '" title="' . __('Edit map element') . ' : ' . html::escapeHTML($this->rs->post_title) . '">' . html::escapeHTML($this->rs->post_title) . '</a></td>' .
        '<td class="nowrap count">' . dt::dt2str(__('%Y-%m-%d %H:%M'), $this->rs->post_dt) . '</td>' .
        '<td class="nowrap">' . $cat_title . '</td>' .
        '<td class="nowrap">' . __($meta_rs) . '</td>' .
        '<td class="nowrap status">' . $img_status . ' ' . $selected . ' ' . $protected . ' ' . $attach . '</td>';
        if ($type == 'post') {
            $res .= '<td class="nowrap count"><a class="element-remove" href="' . DC_ADMIN_URL . 'post.php?id=' . $id . '&amp;remove=' . $this->rs->post_id . '" title="' . __('Remove map element') . ' : ' . html::escapeHTML($this->rs->post_title) . '"><img src="images/trash.png" alt="supprimer" /></a></td>';
        } elseif ($type == 'page') {
            $res .= '<td class="nowrap count"><a class="element-remove" href="' . DC_ADMIN_URL . 'plugin.php?p=pages&amp;act=page&amp;id=' . $id . '&amp;upd=1&amp;remove=' . $this->rs->post_id . '" title="' . __('Remove map element') . ' : ' . html::escapeHTML($this->rs->post_title) . '"><img src="images/trash.png" alt="supprimer" /></a></td>';
        }
        $res .= '</tr>';

        return $res;
    }
}
