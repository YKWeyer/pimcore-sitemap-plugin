<?php

/**
 * Pimcoresitemapplugin_SitemapController
 *
 * @author Yann Weyer
 */

use Byng\Pimcore\Sitemap\SitemapPlugin as SitemapPlugin;

class Pimcoresitemapplugin_SitemapController extends \Pimcore\Controller\Action\Frontend
{

    public function viewAction()
    {
        // Special case: subsite in the tree
        if (Site::isSiteRequest()) {
            $site = Site::getCurrentSite();
            $filename = '/' . $site->getMainDomain() . '.xml';
        } else {
            // Default filename
            $filename = '/'. \Pimcore\Config::getSystemConfig()->get("general")->get("domain") . '.xml';
        }

        // Outputting the XML
        header('Content-Type: text/xml');
        readfile(PIMCORE_WEBSITE_PATH . SitemapPlugin::SITEMAP_FOLDER . $filename);
        exit;
    }
}