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
use Dotclear\Core\Backend\Listing\Listing;
use Dotclear\Core\Backend\Listing\Pager;
use Dotclear\Core\Backend\Page;
use Dotclear\Helper\Date;
use Dotclear\Helper\Html\Form\Caption;
use Dotclear\Helper\Html\Form\Checkbox;
use Dotclear\Helper\Html\Form\Div;
use Dotclear\Helper\Html\Form\Img;
use Dotclear\Helper\Html\Form\Link;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Set;
use Dotclear\Helper\Html\Form\Table;
use Dotclear\Helper\Html\Form\Tbody;
use Dotclear\Helper\Html\Form\Td;
use Dotclear\Helper\Html\Form\Text;
use Dotclear\Helper\Html\Form\Th;
use Dotclear\Helper\Html\Form\Thead;
use Dotclear\Helper\Html\Form\Timestamp;
use Dotclear\Helper\Html\Form\Tr;
use Dotclear\Helper\Html\Html;

/**
 * @brief   Posts list pager form helper.
 *
 * @since   2.20
 */
class BackendList extends Listing
{
    /**
     * Display admin post list.
     *
     * @param   int     $page           The page
     * @param   int     $nb_per_page    The number of posts per page
     * @param   string  $enclose_block  The enclose block
     * @param   bool    $filter         The filter
     */
    public function display(int $page, int $nb_per_page, string $enclose_block = '', bool $filter = false, bool $include_type = false): void
    {
        if ($this->rs->isEmpty()) {
            echo (new Para())
                ->items([
                    (new Text('strong', $filter ? __('No element matches the filter') : __('No element'))),
                ])
            ->render();

            return;
        }

        $pager   = (new Pager($page, (int) $this->rs_count, $nb_per_page, 10))->getLinks();
        $entries = [];
        if (isset($_REQUEST['entries'])) {
            foreach ($_REQUEST['entries'] as $v) {
                $entries[(int) $v] = true;
            }
        }

        $cols = [
            'title' => (new Th())
                ->scope('col')
                ->colspan(2)
                ->class('first')
                ->text(__('Title'))
            ->render(),
            'date' => (new Th())
                ->scope('col')
                ->text(__('Date'))
            ->render(),
            'category' => (new Th())
                ->scope('col')
                ->text(__('Category'))
            ->render(),
            'author' => (new Th())
                ->scope('col')
                ->text(__('Author'))
            ->render(),
            'type' => (new Th())
                ->scope('col')
                ->text(__('Type'))
            ->render(),
            'status' => (new Th())
                ->scope('col')
                ->text(__('Status'))
            ->render(),
        ];

        if ($include_type) {
            $cols = array_merge($cols, [
                'type' => (new Th())
                    ->scope('col')
                    ->text(__('Type'))
                ->render(),
            ]);
        }

        $cols = new ArrayObject($cols);
        # --BEHAVIOR-- adminPostListHeaderV2 -- MetaRecord, ArrayObject
        App::behavior()->callBehavior('adminPostListHeaderV2', $this->rs, $cols);

        // Cope with optional columns
        $this->userColumns('posts', $cols);

        // Prepare listing

        $lines = [];
        $types = [];
        while ($this->rs->fetch()) {
            $lines[] = $this->postLine(isset($entries[$this->rs->post_id]), $include_type);
            if (!in_array($this->rs->post_type, $types)) {
                $types[] = $this->rs->post_type;
            }
        }

        if ($filter) {
            $caption = sprintf(
                __('List of %s entry matching the filter.', 'List of %s entries matching the filter.', $this->rs_count),
                $this->rs_count
            );
        } elseif (count($types) === 1) {
            $stats = [
                (new Text(null, sprintf(__('List of elements (%s)'), $this->rs_count))),
            ];
            foreach (App::status()->post()->dump(false) as $status) {
                $nb = (int) App::blog()->getPosts(['post_type' => 'map', 'post_status' => $status->level()], true)->f(0);
                if ($nb !== 0) {
                    $stats[] = (new Set())
                        ->separator(' ')
                        ->items([
                            (new Link())
                                ->href(App::backend()->url()->get('admin.plugin', ['p' => My::id(), 'status' => $status->level()]))
                                ->text(__($status->name(), $status->pluralName(), $nb)),
                            (new Text(null, sprintf('(%d)', $nb))),
                        ]);
                }
            }
            $caption = (new Set())
                ->separator(', ')
                ->items($stats)
            ->render();
        }

        $buffer = (new Div())
            ->class('table-outer')
            ->items([
                (new Table())
                    ->class(['maximal', 'dragable'])
                    ->caption(new Caption($caption))
                    ->items([
                        (new Thead())
                            ->rows([
                                (new Tr())
                                    ->items([
                                        (new Text(null, implode('', iterator_to_array($cols)))),
                                    ]),
                            ]),
                        (new Tbody())
                            ->id('pageslist')
                            ->rows($lines),
                    ]),
                (new Para())
                    ->class('info')
                    ->items([
                        (new Text(
                            null,
                            __('Legend: ') . (new Set())
                            ->separator(' - ')
                            ->items([
                                ... array_map(fn ($k): Img|Set|Text => App::status()->post()->image($k->id(), true), App::status()->post()->dump(false)),
                                self::getMyRowImage(__('Selected'), 'images/selected.svg', 'selected', true),
                            ])
                            ->render(),
                        )),
                    ]),
            ])
        ->render();
        if ($enclose_block !== '') {
            $buffer = sprintf($enclose_block, $buffer);
        }

        echo $pager . $buffer . $pager;
    }

    /**
     * Get a line.
     *
     * @param   bool    $checked    The checked flag
     */
    private function postLine(bool $checked, bool $include_type): Tr
    {
        $post_classes = ['line'];
        if (App::status()->post()->isRestricted((int) $this->rs->post_status)) {
            $post_classes[] = 'offline';
        }
        $post_classes[] = 'sts-' . App::status()->post()->id((int) $this->rs->post_status);

        $status = [];

        switch ((int) $this->rs->post_status) {
            case App::status()->post()::PUBLISHED:
                $status[] = self::getMyRowImage(__('Published'), 'images/published.svg', 'published');

                break;
            case App::status()->post()::UNPUBLISHED:
                $status[] = self::getMyRowImage(__('Unpublished'), 'images/unpublished.svg', 'unpublished');

                break;
            case App::status()->post()::SCHEDULED:
                $status[] = self::getMyRowImage(__('Scheduled'), 'images/scheduled.svg', 'scheduled');

                break;
            case App::status()->post()::PENDING:
                $status[] = self::getMyRowImage(__('Pending'), 'images/pending.svg', 'pending');

                break;
        }

        if ($this->rs->post_selected) {
            $status[] = self::getMyRowImage(__('Selected'), 'images/selected.svg', 'selected');
        }

        if ($this->rs->cat_title) {
            if (App::auth()->check(App::auth()->makePermissions([
                App::auth()::PERMISSION_CATEGORIES,
            ]), App::blog()->id())) {
                $category = (new Link())
                    ->href(App::backend()->url()->get('admin.category', ['id' => $this->rs->cat_id], '&amp;', true))
                    ->text(Html::escapeHTML($this->rs->cat_title));
            } else {
                $category = (new Text(null, Html::escapeHTML($this->rs->cat_title)));
            }
        } else {
            $category = (new Text(null, __('(No cat)')));
        }

        $cols = [
            'check' => (new Td())
                ->class('nowrap')
                ->items([
                    (new Checkbox(['entries[]'], $checked))
                        ->value($this->rs->post_id)
                        ->disabled(!$this->rs->isEditable())
                        ->title(__('Select this post')),
                ])
            ->render(),
            'title' => (new Td())
                ->class('maximal')
                ->items([
                    (new Link())
                        ->href(My::manageUrl() . '&act=map&id=' . $this->rs->post_id)
                        ->text(Html::escapeHTML(trim(Html::clean($this->rs->post_title)))),
                ])
            ->render(),
            'date' => (new Td())
                ->class(['nowrap', 'count'])
                ->items([
                    (new Timestamp(Date::dt2str(__('%Y-%m-%d %H:%M'), $this->rs->post_dt)))
                        ->datetime(Date::iso8601((int) strtotime($this->rs->post_dt), App::auth()->getInfo('user_tz'))),
                ])
            ->render(),
            'category' => (new Td())
                ->class('nowrap')
                ->items([
                    $category,
                ])
            ->render(),
            'author' => (new Td())
                ->class('nowrap')
                ->text($this->rs->user_id)
            ->render(),
            'type' => (new Td())
                ->class(['nowrap'])
                ->items([
                    (new Img(self::getImgInfo('light')['src'])
                        ->class(['light-only', 'mark', 'mark-map'])
                        ->alt(self::getImgInfo('light')['title'])
                        ->title(self::getImgInfo('light')['title'])
                    ),
                    (new Img(self::getImgInfo('dark')['src'])
                        ->class(['dark-only', 'mark', 'mark-map'])
                        ->alt(self::getImgInfo('dark')['title'])
                        ->title(self::getImgInfo('dark')['title'])
                    ),
                ])
            ->render(),
            'status' => (new Td())
                ->class(['nowrap', 'status'])
                ->separator(' ')
                ->items([
                    ... $status,
                ])
            ->render(),
        ];

        if ($include_type) {
            $cols = array_merge($cols, [
                'type' => (new Td())
                    ->class(['nowrap', 'status'])
                    ->separator(' ')
                    ->items([
                        App::postTypes()->image($this->rs->post_type),
                    ])
                ->render(),
            ]);
        }

        $cols = new ArrayObject($cols);
        # --BEHAVIOR-- adminPostListValueV2 -- MetaRecord, ArrayObject
        App::behavior()->callBehavior('adminPostListValueV2', $this->rs, $cols);

        // Cope with optional columns
        $this->userColumns('posts', $cols);

        return (new Tr())
            ->id('p' . $this->rs->post_id)
            ->class($post_classes)
            ->items([
                (new Text(null, implode('', iterator_to_array($cols)))),
            ]);
    }

    /**
     * Get image title and src for status icons
     *
     * @param string $title the image title
     * @param string $image the image source
     * @param string $class the class to apply to the image
     * @param bool   $with_text Whether to include the title as text next to the image
     * @return Img|Text
     */
    public static function getMyRowImage(string $title, string $image, string $class, bool $with_text = false): Img|Text
    {
        $img = (new Img($image))
            ->alt(Html::escapeHTML($title))
            ->class(['mark', 'mark-' . $class])
            ->title(Html::escapeHTML($title));

        return $with_text ?
            (new Text(null, $img->render() . ' ' . Html::escapeHTML($title))) :
            $img;
    }

    /**
    * Get image title and src for elements icons
     *
     * @param string $mode The mode, 'light' or 'dark'
     * @return array ['title' => string, 'src' => string]
     */
    private function getImgInfo(string $mode = 'light'): array
    {
        $meta    = App::meta();
        $meta_rs = $meta->getMetaStr($this->rs->post_meta, 'map');

        $info = [
            'title' => '',
            'src'   => '',
        ];

        switch ($meta_rs) {
            case 'point of interest':
                $info['title'] = __('Point of interest');
                $info['src']   = Page::getPF(My::id()) . '/css/img/marker' . ($mode === 'dark' ? '-dark' : '') . '.svg';
                break;
            case 'polyline':
                $info['title'] = __('Polyline');
                $info['src']   = Page::getPF(My::id()) . '/css/img/polyline' . ($mode === 'dark' ? '-dark' : '') . '.svg';
                break;
            case 'polygon':
                $info['title'] = __('Polygon');
                $info['src']   = Page::getPF(My::id()) . '/css/img/polygon' . ($mode === 'dark' ? '-dark' : '') . '.svg';
                break;
            case 'circle':
                $info['title'] = __('Circle');
                $info['src']   = Page::getPF(My::id()) . '/css/img/circle' . ($mode === 'dark' ? '-dark' : '') . '.svg';
                break;
            case 'rectangle':
                $info['title'] = __('Rectangle');
                $info['src']   = Page::getPF(My::id()) . '/css/img/rectangle' . ($mode === 'dark' ? '-dark' : '') . '.svg';
                break;
            case 'included kml file':
                $info['title'] = __('Included kml file');
                $info['src']   = Page::getPF(My::id()) . '/css/img/kml' . ($mode === 'dark' ? '-dark' : '') . '.svg';
                break;
            case 'GeoRSS feed':
                $info['title'] = __('GeoRSS Feed');
                $info['src']   = Page::getPF(My::id()) . '/css/img/feed' . ($mode === 'dark' ? '-dark' : '') . '.svg';
                break;
            case 'directions':
                $info['title'] = __('Directions');
                $info['src']   = Page::getPF(My::id()) . '/css/img/directions' . ($mode === 'dark' ? '-dark' : '') . '.svg';
                break;
            default:
        }

        return $info;
    }
}
