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

namespace Byng\Pimcore\Sitemap\Plugin;

use Byng\Pimcore\Sitemap\SitemapPlugin;
use Pimcore\Config;
use Pimcore\Model\Property\Predefined as PredefinedProperty;
use Pimcore\Model\Site;
use Pimcore\Model\Staticroute;

/**
 * Class Installer
 *
 * @package Byng\Pimcore\Sitemap\Plugin
 * @author Yann Weyer <yann.weyer@orderbird.com>
 */
final class Installer
{
    /**
     * Creates predefined properties to be used in the admin interface
     */
    public function createProperties()
    {
        $data = [
            "key" => "sitemap_exclude",
            "name" => "Sitemap: Exclude page",
            "description" => "Add this property to exclude a page from the sitemap",
            "ctype" => "document",
            "type" => "bool",
            "inheritable" => false,
            "data" => true
        ];
        $property = PredefinedProperty::create();
        $property->setValues($data);

        $property->save();
    }

    /**
     * Deletes predefined properties
     */
    public function deleteProperties()
    {
        $property = PredefinedProperty::getByKey("sitemap_exclude");
        $property->delete();
    }

    /**
     * Creates the redirect rules used to display the appropriate sitemap file
     */
    public function createRedirectRule()
    {
        $route = Staticroute::create();
        $route->setName('sitemap')
            ->setPattern('/^\/sitemap\.xml$/')
            ->setReverse('/sitemap.xml')
            ->setModule('PimcoreSitemapPlugin')
            ->setController('sitemap')
            ->setAction('view')
            ->save();
    }

    /**
     * Removes the redirect rule
     */
    public function deleteRedirectRule()
    {
        $route = Staticroute::getByName('sitemap');
        $route->delete();
    }

    /**
     * Creates the sitemap folder
     */
    public function createSitemapFolder()
    {
        // Create sitemap folder (if doesn't exist already)
        if (!is_dir(SITEMAP_PLUGIN_FOLDER)) {
            mkdir(SITEMAP_PLUGIN_FOLDER, 0777, true);
        }
    }

    /**
     * Deletes the sitemap folder and its children
     */
    public function deleteSitemapFolder()
    {
        if (is_dir(SITEMAP_PLUGIN_FOLDER)) {
            if (!is_dir_empty(SITEMAP_PLUGIN_FOLDER)) {
                array_map('unlink', glob(SITEMAP_PLUGIN_FOLDER . '/*'));
            }
            rmdir(SITEMAP_PLUGIN_FOLDER);
        }
    }

    /**
     * Creates the configuration file.
     * @throws \Exception
     */
    public function createConfigFile()
    {
        // Get Sites FQDN map
        $sites = $this->getSitesProtocolMap();

        // Create xml config file
        $config = new \Zend_Config_Xml(PIMCORE_PLUGINS_PATH . '/PimcoreSitemapPlugin/install/config.xml', null, ['allowModifications' => true]);
        $config->sites = ['site' => $sites];

        if (!is_dir(dirname(SITEMAP_CONFIGURATION_FILE))) {
            if (!@mkdir(dirname(SITEMAP_CONFIGURATION_FILE), 0777, true)) {
                throw new \Exception('Sitemap: Unable to create plugin config directory');
            }
        }

        $configWriter = new \Zend_Config_Writer_Xml();
        $configWriter->setConfig($config);
        $configWriter->write(SITEMAP_CONFIGURATION_FILE);
    }

    /**
     * Deletes the configuration file
     */
    public function deleteConfigFile()
    {
        if (file_exists(SITEMAP_CONFIGURATION_FILE)) {
            unlink(SITEMAP_CONFIGURATION_FILE);
        }
    }

    /**
     * Lists the Pimcore sites and their properties (used to generate Config file)
     * @return array
     */
    private function getSitesProtocolMap()
    {
        $client = new \Zend_Http_Client();
        $sitesMap = [];

        // Add the main domain
        $defaultDomain = Config::getSystemConfig()->get("general")->get("domain");
        $sitesMap[] = [
            'rootId' => 1,
            'rootPath' => '',
            'protocol' => $this->getProtocolForDomain($defaultDomain, $client),
            'domain' => $defaultDomain
        ];

        // Retrieve site trees
        $siteRoots = new Site\Listing();
        $siteRoots = $siteRoots->load();

        // Build siteRoots table: [ ID => FQDN ]
        /* @var Site $siteRoot */
        foreach ($siteRoots as $siteRoot) {
            $sitesMap[] = [
                'rootId' => $siteRoot->getRootId(),
                'rootPath' => trim($siteRoot->getRootPath(), '/'),
                'protocol' => $this->getProtocolForDomain($siteRoot->getMainDomain(), $client),
                'domain' => $siteRoot->getMainDomain()
            ];
        }

        return $sitesMap;
    }


    /**
     * Tests https for a domain name and returns either https or http depending on the server answer
     * @param $domain
     * @param \Zend_Http_Client|null $client
     * @return string
     */
    private function getProtocolForDomain($domain, \Zend_Http_Client $client = null)
    {
        if (!$client) {
            $client = new \Zend_Http_Client();
        }
        $client->setUri('https://' . $domain);
        try {
            $client->request();
            $protocol = $client->getLastResponse()->getStatus() === 200 ? 'https' : 'http';
        } catch (\Exception $e) {
            $protocol = 'http';
        }

        return $protocol;
    }
}