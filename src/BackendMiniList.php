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

use Dotclear\App;
use Dotclear\Core\Backend\Page;
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
        $img = '<img alt="%1$s" src="images/%2$s" class="mark mark-%3$s">';
        switch ($this->rs->post_status) {
            case App::blog()::POST_PUBLISHED:
                $img_status = sprintf($img, __('Published'), 'published.svg', 'published');
                $sts_class  = 'sts-online';

                break;
            case App::blog()::POST_UNPUBLISHED:
                $img_status = sprintf($img, __('Unpublished'), 'unpublished.svg', 'unpublished');
                $sts_class  = 'sts-offline';

                break;
            case App::blog()::POST_SCHEDULED:
                $img_status = sprintf($img, __('Scheduled'), 'scheduled.svg', 'scheduled');
                $sts_class  = 'sts-scheduled';

                break;
            case App::blog()::POST_PENDING:
                $img_status = sprintf($img, __('Pending'), 'pending.svg', 'pending');
                $sts_class  = 'sts-pending';

                break;
        }

        $protected = '';
        if ($this->rs->post_password) {
            $protected = sprintf($img, __('Protected'), 'locker.svg', 'locked');
        }

        $selected = '';
        if ($this->rs->post_selected) {
            $selected = sprintf($img, __('Selected'), 'selected.svg', 'selected');
        }

        $attach   = '';
        $nb_media = $this->rs->countMedia();
        if ($nb_media > 0) {
            $attach_str = $nb_media == 1 ? __('%d attachment') : __('%d attachments');
            $attach     = sprintf($img, sprintf($attach_str, $nb_media), 'attach.svg', 'attach');
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

        $imgf = '<img alt="%1$s" title="%1$s" src="' . Page::getPF(My::id()) . '/css/img/%2$s" class="mark mark-%3$s">';

        $img_type = '';

        if ($meta_rs === 'point of interest') {
            $img_type = sprintf($imgf, __('point of interest'), 'marker.svg', 'map');
        } elseif ($meta_rs === 'polyline') {
            $img_type = sprintf($imgf, __('polyline'), 'polyline.svg', 'map');
        } elseif ($meta_rs === 'polygon') {
            $img_type = sprintf($imgf, __('polygon'), 'polygon.svg', 'map');
        } elseif ($meta_rs === 'circle') {
            $img_type = sprintf($imgf, __('circle'), 'circle.svg', 'map');
        } elseif ($meta_rs === 'rectangle') {
            $img_type = sprintf($imgf, __('rectangle'), 'rectangle.svg', 'map');
        } elseif ($meta_rs === 'included kml file') {
            $img_type = sprintf($imgf, __('included kml file'), 'kml.svg', 'map');
        } elseif ($meta_rs === 'GeoRSS feed') {
            $img_type = sprintf($imgf, __('GeoRSS feed'), 'feed.svg', 'map');
        } elseif ($meta_rs === 'directions') {
            $img_type = sprintf($imgf, __('directions'), 'directions.svg', 'map');
        }

        $res = '<tr class="line ' . ($this->rs->post_status != App::blog()::POST_PUBLISHED ? 'offline ' : '') . $sts_class . '"' .
        ' id="p' . $this->rs->post_id . '">';

        $res .= '<td class="maximal"><a href="' . App::backend()->url()->get('admin.plugin.' . My::id()) . '&act=map&id=' . $this->rs->post_id . '" title="' . __('Edit map element') . ' : ' . Html::escapeHTML($this->rs->post_title) . '">' . Html::escapeHTML($this->rs->post_title) . '</a></td>' .
        '<td class="nowrap count">' . Date::dt2str(__('%Y-%m-%d %H:%M'), $this->rs->post_dt) . '</td>' .
        '<td class="nowrap">' . $cat_title . '</td>' .
        '<td class="nowrap count">' . $img_type . '</td>' .
        '<td class="nowrap status">' . $img_status . ' ' . $selected . ' ' . $protected . ' ' . $attach . '</td>';
        if ($type == 'post') {
            $res .= '<td class="nowrap count"><a class="mark element-remove" href="' . App::postTypes()->get($type)->adminUrl($id) . '&remove=' . $this->rs->post_id . '" title="' . __('Remove map element') . ' : ' . Html::escapeHTML($this->rs->post_title) . '"><img class="mark element-remove" src="images/trash.svg" alt="supprimer"></a></td>';
        } elseif ($type == 'page') {
            $res .= '<td class="nowrap count"><a class="mark element-remove" href="' . App::postTypes()->get($type)->adminUrl($id) . '&upd=1&remove=' . $this->rs->post_id . '" title="' . __('Remove map element') . ' : ' . Html::escapeHTML($this->rs->post_title) . '"><img class="mark element-remove" src="images/trash.svg" alt="supprimer"></a></td>';
        }
        $res .= '</tr>';

        return $res;
    }
}
