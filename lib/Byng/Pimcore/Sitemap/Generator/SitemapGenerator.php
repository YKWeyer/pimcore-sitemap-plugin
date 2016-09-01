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
use Byng\Pimcore\Sitemap\SitemapPlugin;
use Pimcore\Config;
use Pimcore\Model\Document;
use Pimcore\Model\Site;
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
    private $hostUrl;

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
    private $sitesRoots = array();


    /**
     * SitemapGenerator constructor.
     */
    public function __construct()
    {
        $this->documentGateway = new DocumentGateway();
    }

    /**
     * Generates the sitemap.xml file
     *
     * @return void
     */
    public function generateXml()
    {
        // Retrieve site trees
        $siteRoots = new Site\Listing();
        $siteRoots = $siteRoots->load();

        // Build siteRoots table: [ ID => Domain ]
        /* @var Site $siteRoot */
        foreach ($siteRoots as $siteRoot) {
            $this->sitesRoots[$siteRoot->getRootId()] = $siteRoot->getMainDomain();
        }

        // Also append the default tree
        $this->sitesRoots[1] = Config::getSystemConfig()->get("general")->get("domain");

        $notifySearchEngines = Config::getSystemConfig()->get("general")->get("environment") === "production";
        foreach ($this->sitesRoots as $siteRootID => $siteRootDomain) {
            $this->generateSiteXml($siteRootID, $siteRootDomain);

            if ($notifySearchEngines) {
                $this->notifySearchEngines('https://' . $siteRootDomain);
            }
        }

    }

    /**
     * Generate a sitemap xml file for a defined site, with a specific hostUrl
     *
     * @param int $rootId
     * @param string $hostUrl
     * @return string
     */
    private function generateSiteXml($rootId, $hostUrl)
    {
        $this->xml = new SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>'
        );

        // Set current hostUrl
        $this->hostUrl = 'https://' . $hostUrl;

        $rootDocument = Document::getById($rootId);
        $this->addUrlChild($rootDocument);
        $this->listAllChildren($rootDocument);

        $this->xml->asXML(PIMCORE_WEBSITE_PATH . SitemapPlugin::SITEMAP_FOLDER . '/' . $hostUrl . '.xml');
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
            if (array_key_exists($child->getId(), $this->sitesRoots)) {
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
            && !array_key_exists($document->getId(), $this->sitesRoots)
        ) {
            echo $this->hostUrl . $document->getFullPath() . "\n";
            $url = $this->xml->addChild("url");
            $url->addChild('loc', $this->hostUrl . $document->getFullPath());
            $url->addChild('lastmod', $this->getDateFormat($document->getModificationDate()));
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
    private function notifySearchEngines($domain = null)
    {
        $googleNotifier = new GoogleNotifier();

        if ($googleNotifier->notify($domain)) {
            echo "Google has been notified \n";
        } else {
            echo "Google has not been notified \n";
        }
    }

}
