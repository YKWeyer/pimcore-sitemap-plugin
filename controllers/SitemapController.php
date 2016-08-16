<?php

/**
 * Pimcoresitemapplugin_SitemapController
 *
 * @author Yann Weyer
 */

use Byng\Pimcore\Sitemap\SitemapPlugin as SitemapPlugin;

class Pimcoresitemapplugin_SitemapController extends \Pimcore\Controller\Action\Frontend
{

    /*
     * Shows the sitemap.xml of the currently visited website
     */
    public function viewAction()
    {
        // Special case: subsite in the tree
        if (isset($this->document) && $site = \Pimcore\Tool\Frontend::getSiteForDocument($this->document)) {
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