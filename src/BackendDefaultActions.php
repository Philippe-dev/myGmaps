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
use dcBlog;
use dcCore;
use dcPage;
use dcDefaultPostActions;
use Exception;

class BackendDefaultActions
{
    /**
     * Set pages actions
     *
     * @param      BackendActions  $ap     Admin actions instance
     */
    public static function adminPagesActionsPage(BackendActions $ap): void
    {
        if (dcCore::app()->auth->check(dcCore::app()->auth->makePermissions([
            dcCore::app()->auth::PERMISSION_PUBLISH,
            dcCore::app()->auth::PERMISSION_CONTENT_ADMIN,
        ]), dcCore::app()->blog->id)) {
            $ap->addAction(
                [__('Status') => [
                    __('Publish')         => 'publish',
                    __('Unpublish')       => 'unpublish',
                    __('Schedule')        => 'schedule',
                    __('Mark as pending') => 'pending',
                ]],
                [dcDefaultPostActions::class, 'doChangePostStatus']
            );
        }
        if (dcCore::app()->auth->check(dcCore::app()->auth->makePermissions([
            dcCore::app()->auth::PERMISSION_ADMIN,
        ]), dcCore::app()->blog->id)) {
            $ap->addAction(
                [__('Change') => [
                    __('Change author') => 'author', ]],
                [dcDefaultPostActions::class, 'doChangePostAuthor']
            );
        }
        if (dcCore::app()->auth->check(dcCore::app()->auth->makePermissions([
            dcCore::app()->auth::PERMISSION_DELETE,
            dcCore::app()->auth::PERMISSION_CONTENT_ADMIN,
        ]), dcCore::app()->blog->id)) {
            $ap->addAction(
                [__('Delete') => [
                    __('Delete') => 'delete', ]],
                [dcDefaultPostActions::class, 'doDeletePost']
            );
        }
        
    }

    /**
     * Does reorder pages.
     *
     * @param      BackendActions  $ap  Admin actions instance
     * @param      ArrayObject     $post   The post
     *
     * @throws     Exception             If user permission not granted
     */
    public static function doReorderPages(BackendActions $ap, ArrayObject $post): void
    {
        foreach ($post['order'] as $post_id => $value) {
            if (!dcCore::app()->auth->check(dcCore::app()->auth->makePermissions([
                dcCore::app()->auth::PERMISSION_PUBLISH,
                dcCore::app()->auth::PERMISSION_CONTENT_ADMIN,
            ]), dcCore::app()->blog->id)) {
                throw new Exception(__('You are not allowed to change this entry status'));
            }

            $strReq = "WHERE blog_id = '" . dcCore::app()->con->escape(dcCore::app()->blog->id) . "' " .
            'AND post_id ' . dcCore::app()->con->in($post_id);

            #If user can only publish, we need to check the post's owner
            if (!dcCore::app()->auth->check(dcCore::app()->auth->makePermissions([
                dcCore::app()->auth::PERMISSION_CONTENT_ADMIN,
            ]), dcCore::app()->blog->id)) {
                $strReq .= "AND user_id = '" . dcCore::app()->con->escape(dcCore::app()->auth->userID()) . "' ";
            }

            $cur = dcCore::app()->con->openCursor(dcCore::app()->prefix . dcBlog::POST_TABLE_NAME);

            $cur->post_position = (int) $value - 1;
            $cur->post_upddt    = date('Y-m-d H:i:s');

            $cur->update($strReq);
            dcCore::app()->blog->triggerBlog();
        }

        dcPage::addSuccessNotice(__('Selected pages have been successfully reordered.'));
        $ap->redirect(false);
    }
}
