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
use ArrayObject;
use Dotclear\Core\Backend\Listing\Pager;
use Dotclear\Core\Backend\Listing\Listing;
use Dotclear\Helper\Date;
use Dotclear\Helper\Html\Html;
use form;

class BackendList extends Listing
{
    /**
     * Display admin post list
     *
     * @param      int     $page           The page
     * @param      int     $nb_per_page    The number of posts per page
     * @param      string  $enclose_block  The enclose block
     * @param      bool    $filter         The filter
     */
    public function display(int $page, int $nb_per_page, string $enclose_block = '', bool $filter = false)
    {
        if ($this->rs->isEmpty()) {
            if ($filter) {
                echo '<p><strong>' . __('No element matches the filter') . '</strong></p>';
            } else {
                echo '<p><strong>' . __('No map element') . '</strong></p>';
            }
        } else {
            $pager   = new Pager($page, (int) $this->rs_count, $nb_per_page, 10);
            $entries = [];
            if (isset($_REQUEST['entries'])) {
                foreach ($_REQUEST['entries'] as $v) {
                    $entries[(int) $v] = true;
                }
            }
            $html_block = '<div class="table-outer">' .
                '<table>';

            if ($filter) {
                $html_block .= '<caption>' . sprintf(__('List of %s elements matching the filter'), $this->rs_count) . '</caption>';
            } else {
                if (isset($_GET['act']) && $_GET['act'] === 'maps') {
                    $html_block .= '<caption>' .
                    sprintf(__('Available elements list (%s)'), $this->rs_count) . '</caption>';
                } else {
                    $html_block .= '<caption>' .
                    sprintf(__('Elements list (%s)'), $this->rs_count) . '</caption>';
                }
            }

            $cols = [
                'title'    => '<th colspan="2" class="first">' . __('Title') . '</th>',
                'date'     => '<th scope="col">' . __('Date') . '</th>',
                'category' => '<th scope="col">' . __('Category') . '</th>',
                'author'   => '<th scope="col">' . __('Author') . '</th>',
                'type'     => '<th scope="col">' . __('Type') . '</th>',
                'status'   => '<th scope="col">' . __('Status') . '</th>',
            ];
            $cols = new ArrayObject($cols);
            App::behavior()->callBehavior('adminPostListHeaderV2', $this->rs, $cols);

            // Cope with optional columns

            $this->userColumns('posts', $cols);

            $html_block .= '<tr>' . implode(iterator_to_array($cols)) . '</tr>%s</table>%s</div>';
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
                $fmt(__('Published'), 'check-on.svg') . ' - ' .
                $fmt(__('Unpublished'), 'check-off.svg') . ' - ' .
                $fmt(__('Scheduled'), 'scheduled.svg') . ' - ' .
                $fmt(__('Pending'), 'check-wrn.svg') . ' - ' .
                $fmt(__('Protected'), 'locker.svg') . ' - ' .
                $fmt(__('Selected'), 'selected.svg') . ' - ' .
                $fmt(__('Attachments'), 'attach.svg') .
                '</p>';

            echo $blocks[2];

            echo $pager->getLinks();
        }
    }

    /**
     * Get a line.
     *
     * @param      bool  $checked  The checked flag
     *
     * @return     string
     */
    private function postLine(bool $checked): string
    {
        if (App::auth()->check(App::auth()->makePermissions([
            App::auth()::PERMISSION_CATEGORIES,
        ]), App::blog()->id)) {
            $cat_link = '<a href="' . App::backend()->url()->get('admin.category', ['id' => '%s'], '&', true) . '">%s</a>';
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
            $cat_title = __('(No cat)');
        }

        $img        = '<img alt="%1$s" title="%1$s" src="images/%2$s" class="mark mark-%3$s" />';
        $img_status = '';
        $sts_class  = '';
        switch ($this->rs->post_status) {
            case App::blog()::POST_PUBLISHED:
                $img_status = sprintf($img, __('Published'), 'check-on.svg', 'published');
                $sts_class  = 'sts-online';

                break;
            case App::blog()::POST_UNPUBLISHED:
                $img_status = sprintf($img, __('Unpublished'), 'check-off.svg', 'unpublished');
                $sts_class  = 'sts-offline';

                break;
            case App::blog()::POST_SCHEDULED:
                $img_status = sprintf($img, __('Scheduled'), 'scheduled.svg', 'scheduled');
                $sts_class  = 'sts-scheduled';

                break;
            case App::blog()::POST_PENDING:
                $img_status = sprintf($img, __('Pending'), 'check-wrn.svg', 'pending');
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

        $res = '<tr class="line ' . ($this->rs->post_status != App::blog()::POST_PUBLISHED ? 'offline ' : '') . $sts_class . '"' .
        ' id="p' . $this->rs->post_id . '">';

        $meta    = App::meta();
        $meta_rs = $meta->getMetaStr($this->rs->post_meta, 'map');

        $cols = [
            'check' => '<td class="nowrap">' .
            form::checkbox(
                ['entries[]'],
                $this->rs->post_id,
                [
                    'checked'  => $checked,
                    'disabled' => !$this->rs->isEditable(),
                ]
            ) .
            '</td>',
            'title' => '<td class="maximal" scope="row"><a href="' . My::manageUrl() .
            '&act=map&id=' . $this->rs->post_id . '">' .
            html::escapeHTML($this->rs->post_title) . '</a></td>',
            'date' => '<td class="nowrap count">' .
                '<time datetime="' . Date::iso8601(strtotime($this->rs->post_dt), App::auth()->getInfo('user_tz')) . '">' .
                Date::dt2str(__('%Y-%m-%d %H:%M'), $this->rs->post_dt) .
                '</time>' .
                '</td>',
            'category' => '<td class="nowrap">' . $cat_title . '</td>',
            'author'   => '<td class="nowrap">' . Html::escapeHTML($this->rs->user_id) . '</td>',
            'type'     => '<td class="nowrap">' . __($meta_rs) . '</td>',
            'status'   => '<td class="nowrap status">' . $img_status . ' ' . $selected . ' ' . $protected . ' ' . $attach . '</td>',
        ];
        $cols = new ArrayObject($cols);
        App::behavior()->callBehavior('adminPostListValueV2', $this->rs, $cols);

        // Cope with optional columns
        $this->userColumns('posts', $cols);

        $res .= implode(iterator_to_array($cols));
        $res .= '</tr>';

        return $res;
    }
}
