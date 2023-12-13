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

use Dotclear\App;
use Dotclear\Core\Backend\Listing\Pager;
use Dotclear\Core\Backend\Listing\Listing;
use Dotclear\Helper\Date;
use Dotclear\Helper\Html\Html;

class BackendMiniList extends Listing
{
    /**
     * Display a list of pages
     *
     * @param      int     $page           The page
     * @param      int     $nb_per_page    The number of per page
     * @param      string  $enclose_block  The enclose block
     */
    public function display(int $page, int $nb_per_page, string $enclose_block = '', string $id = '', string $type = '')
    {
        if ($this->rs->isEmpty()) {
            echo '<p><strong>' . __('No entry') . '</strong></p>';
        } else {
            $pager            = new Pager($page, (int) $this->rs_count, $nb_per_page, 10);
            $pager->html_prev = $this->html_prev;
            $pager->html_next = $this->html_next;
            $pager->var_page  = 'page';

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

            echo $blocks[0];

            $count = 0;
            while ($this->rs->fetch()) {
                echo $this->postLine($count, isset($entries[$this->rs->post_id]), $id, $type);
                $count++;
            }

            echo $blocks[1];
        }
    }

    /**
     * Return a page line.
     *
     * @param      int     $count    The count
     * @param      bool    $checked  The checked
     *
     * @return     string
     */
    private function postLine(int $count, bool $checked, string $id = '', string $type = ''): string
    {
        $img = '<img alt="%1$s" title="%1$s" src="images/%2$s" />';
        switch ($this->rs->post_status) {
            case App::blog()::POST_PUBLISHED:
                $img_status = sprintf($img, __('published'), 'check-on.png');

                break;
            case App::blog()::POST_UNPUBLISHED:
                $img_status = sprintf($img, __('unpublished'), 'check-off.png');

                break;
            case App::blog()::POST_SCHEDULED:
                $img_status = sprintf($img, __('scheduled'), 'scheduled.png');

                break;
            case App::blog()::POST_PENDING:
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

        if (App::auth()->check('categories', App::blog()->id)) {
            $cat_link = '<a href="category.php?id=%s">%s</a>';
        } else {
            $cat_link = '%2$s';
        }
        if ($this->rs->cat_title) {
            $cat_title = sprintf(
                $cat_link,
                $this->rs->cat_id,
                Html::escapeHTML($this->rs->cat_title)
            );
        } else {
            $cat_title = __('None');
        }

        $meta    = App::meta();
        $meta_rs = $meta->getMetaStr($this->rs->post_meta, 'map');

        $res = '<tr class="line' . ($this->rs->post_status != 1 ? ' offline' : '') . '"' .
        ' id="p' . $this->rs->post_id . '">';

        $res .= '<td class="maximal"><a href="' . App::backend()->url()->get('admin.plugin.' . My::id()) . '&act=map&id=' . $this->rs->post_id . '" title="' . __('Edit map element') . ' : ' . Html::escapeHTML($this->rs->post_title) . '">' . Html::escapeHTML($this->rs->post_title) . '</a></td>' .
        '<td class="nowrap count">' . Date::dt2str(__('%Y-%m-%d %H:%M'), $this->rs->post_dt) . '</td>' .
        '<td class="nowrap">' . $cat_title . '</td>' .
        '<td class="nowrap">' . __($meta_rs) . '</td>' .
        '<td class="nowrap status">' . $img_status . ' ' . $selected . ' ' . $protected . ' ' . $attach . '</td>';
        if ($type == 'post') {
            $res .= '<td class="nowrap count"><a class="element-remove" href="' . App::postTypes()->get($type)->adminUrl($id) . '&remove=' . $this->rs->post_id . '" title="' . __('Remove map element') . ' : ' . Html::escapeHTML($this->rs->post_title) . '"><img src="images/trash.png" alt="supprimer" /></a></td>';
        } elseif ($type == 'page') {
            $res .= '<td class="nowrap count"><a class="element-remove" href="' . App::postTypes()->get($type)->adminUrl($id) . '&upd=1&remove=' . $this->rs->post_id . '" title="' . __('Remove map element') . ' : ' . Html::escapeHTML($this->rs->post_title) . '"><img src="images/trash.png" alt="supprimer" /></a></td>';
        }
        $res .= '</tr>';

        return $res;
    }
}
