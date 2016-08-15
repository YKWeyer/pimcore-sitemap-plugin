<?php

/**
 * Pimcoresitemapplugin_SitemapController
 *
 * @author Yann Weyer
 */
class Pimcoresitemapplugin_SitemapController extends \Pimcore\Controller\Action\Frontend
{

    public function viewAction()
    {
        // Default filename
        $filename = 'sitemap.xml';

        // Special case: subsite in the tree
        if (Site::isSiteRequest()) {
            $site = Site::getCurrentSite();
            $filename = $site->getMainDomain() . '.xml';
        }

        // Outputting the XML
        header('Content-Type: text/xml');
        readfile($filename);
        exit;
    }
}