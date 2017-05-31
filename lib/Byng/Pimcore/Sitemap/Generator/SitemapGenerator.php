<?php

/**
 * This file is part of the pimcore-sitemap-plugin package.
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Byng\Pimcore\Sitemap\Generator;

use Byng\Pimcore\Sitemap\Gateway\DocumentGateway;
use Byng\Pimcore\Sitemap\Notifier\GoogleNotifier;
use Pimcore\Config;
use Pimcore\Model\Asset\Image\Thumbnail;
use Pimcore\Model\Document;
use SimpleXMLElement;

/**
 * Sitemap Generator
 *
 * @author Ioannis Giakoumidis <ioannis@byng.co>
 */
final class SitemapGenerator
{
    /**
     * @var string
     */
    const IMAGE_NAMESPACE = 'http://www.google.com/schemas/sitemap-image/1.1';

    /**
     * @var string
     */
    private $hostUrl;

    /**
     * @var SimpleXMLElement
     */
    private $host;

    /**
     * @var SimpleXMLElement
     */
    private $xml;

    /**
     * @var DocumentGateway
     */
    private $documentGateway;

    /**
     * @var array
     */
    private $sitesRoots = [];


    /**
     * SitemapGenerator constructor.
     */
    public function __construct()
    {
        $this->documentGateway = new DocumentGateway();
    }

    /**
     * Generates the sitemap.xml file for each available Pimcore\Model\Site
     *
     * @return void
     */
    public function generateXml()
    {
        // Retrieve site trees
        $config = simplexml_load_file(SITEMAP_CONFIGURATION_FILE);
        $siteRoots = $config->sites->site;

        // Build siteRoots ID array
        foreach ($siteRoots as $siteRoot) {
            $this->sitesRoots[] = (int)$siteRoot->rootId;
        }

        $notifySearchEngines = Config::getSystemConfig()->get("general")->get("environment") === "production";
        foreach ($siteRoots as $siteRoot) {
            $this->generateSiteXml($siteRoot);

            if ($notifySearchEngines) {
                $this->notifySearchEngines();
            }
        }
    }

    /**
     * Generates a sitemap xml file for a defined site
     *
     * @param SimpleXMLElement $siteConfig
     * @return string
     */
    private function generateSiteXml(SimpleXMLElement $siteConfig)
    {
        // Set current hostUrl
        $this->hostUrl = $siteConfig->protocol . '://' . $siteConfig->domain;
        $this->host = $siteConfig;

        // Initialise XML file
        $this->xml = new SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<?xml-stylesheet type="text/xsl" href="' . $this->hostUrl . '/plugins/PimcoreSitemapPlugin/static/sitemap.xsl"?>'
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="' . $this::IMAGE_NAMESPACE . '"></urlset>'
        );

        // Navigate through current document subtree to generate XML
        $rootDocument = Document::getById($siteConfig->rootId);
        $this->addUrlChild($rootDocument);
        $this->listAllChildren($rootDocument);

        // Save current XML
        $this->xml->asXML(SITEMAP_PLUGIN_FOLDER . '/' . $siteConfig->domain . '.xml');
    }

    /**
     * Finds all the children of a document recursively
     *
     * @param Document $document
     * @return void
     */
    private function listAllChildren(Document $document)
    {
        $children = $this->documentGateway->getChildren($document->getId());

        /* @var $child Document */
        foreach ($children as $child) {
            // If we are on a siteRoot, skipping it (handled in a different sitemap)
            if (in_array($child->getId(), $this->sitesRoots)) {
                continue;
            }

            $this->addUrlChild($child);
            $this->listAllChildren($child);
        }
    }

    /**
     * Adds a url child in the xml file.
     *
     * @param Document $document
     * @return void
     */
    private function addUrlChild(Document $document)
    {
        if (
            $document instanceof Document\Page &&
            !$document->getProperty("sitemap_exclude")
        ) {
            $fullPath = $document->getFullPath();

            // Remove the site path (if any) from the full path
            $rootPath = (string)$this->host->rootPath;
            if (!empty($rootPath) && strpos($fullPath, '/' . $rootPath) === 0) {
                // Special case for the website homepage
                $fullPath = ($fullPath === '/' . $rootPath) ? '' : str_replace('/' . $rootPath . '/', '/', $fullPath);
            }

            echo $this->hostUrl . $fullPath . "\n";
            $url = $this->xml->addChild("url");
            $url->addChild('loc', $this->hostUrl . $fullPath);
            $url->addChild('lastmod', $this->getDateFormat($document->getModificationDate()));
            $this->addImagesForPage($document, $url);
        }
    }


    /**
     * Lists and outputs all the images in a specific page
     *
     * @param Document\Page $page
     * @param SimpleXMLElement $url
     */
    private function addImagesForPage(Document\Page $page, SimpleXMLElement $url)
    {
        $elements = $page->getElements();
        $locale = $page->getProperty("language");

        foreach ($elements as $element) {
            /* @var Document\Tag\Image $element */
            if (is_a($element, 'Pimcore\Model\Document\Tag\Image') && $image = $element->getImage()) {
                $thumbnail = new Thumbnail($image);
                $image_path = $thumbnail->getPath(false);

                // Prepend current domain name in case of relative path
                if ('/' === substr($image_path, 0, 1)) {
                    $image_path = $this->hostUrl . $image_path;
                }

                $imageBlock = $url->addChild('image:image', null, $this::IMAGE_NAMESPACE);
                $imageBlock->addChild('image:loc', $image_path, $this::IMAGE_NAMESPACE);

                if ($title = $element->getAlt() ?: $image->getMetadata('title', $locale)) {
                    $imageBlock->addChild('image:title', strip_tags($title), $this::IMAGE_NAMESPACE);
                }
                if ($alt = $image->getMetadata('alt', $locale)) {
                    $imageBlock->addChild('image:caption', strip_tags($alt), $this::IMAGE_NAMESPACE);
                }
            }
        }
    }

    /**
     * Format a given date.
     *
     * @param $date
     * @return string
     */
    private function getDateFormat($date)
    {
        return gmdate(DATE_ATOM, $date);
    }

    /**
     * Notify search engines about the sitemap update.
     *
     * @param string $domain
     * @return void
     */
    private function notifySearchEngines()
    {
        $googleNotifier = new GoogleNotifier();

        if ($googleNotifier->notify($this->hostUrl)) {
            echo "Google has been notified \n";
        } else {
            echo "Google has not been notified \n";
        }
    }
}
