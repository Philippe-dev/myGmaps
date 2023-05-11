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

use dcCore;
use dcPage;

class BackendBehaviors
{
    public static function adminEntryListValue($core, $rs, $cols)
    {
        $settings = dcCore::app()->blog->settings->get(My::id());

        $postTypes = ['post', 'page'];

        if (in_array($rs->post_type, $postTypes)) {
            $cols['status'] = '<td class="nowrap">' . self::getFormat($rs->post_format) . '</td>';
        }
    }

    public static function adminPostListValue($rs, $cols)
    {
        self::adminEntryListValue(dcCore::app(), $rs, $cols);
    }

    public static function adminPagesListValue($rs, $cols)
    {
        self::adminEntryListValue(dcCore::app(), $rs, $cols);
    }

    private static function getFormat(string $format = ''): string
    {
        $images = [
            'markdown' => dcPage::getPF('formatting-markdown/img/markdown.svg'),
            'xhtml'    => dcPage::getPF('formatting-markdown/img/xhtml.svg'),
            'wiki'     => dcPage::getPF('formatting-markdown/img/wiki.svg'),
        ];
        if (array_key_exists($format, $images)) {
            return '<img style="width: 1.25em; height: 1.25em;" src="' . $images[$format] . '" title="' . dcCore::app()->getFormaterName($format) . '" />';
        }

        return $format;
    }
}
