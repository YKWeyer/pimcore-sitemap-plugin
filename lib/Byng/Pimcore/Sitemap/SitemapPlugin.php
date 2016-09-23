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

namespace Byng\Pimcore\Sitemap;

use Pimcore\API\Plugin as PluginLib;
use Pimcore\Model\Property\Predefined as PredefinedProperty;
use Pimcore\Model\Schedule\Manager\Procedural as ProceduralScheduleManager;
use Pimcore\Model\Schedule\Maintenance\Job as MaintenanceJob;
use Byng\Pimcore\Sitemap\Generator\SitemapGenerator;
use Pimcore\Config;
use Pimcore\Model\Site;
use Pimcore\Model\Staticroute;

/**
 * Sitemap Plugin
 *
 * @author Ioannis Giakoumidis <ioannis@byng.co>
 */
class SitemapPlugin extends PluginLib\AbstractPlugin implements PluginLib\PluginInterface
{
    const MAINTENANCE_JOB_GENERATE_SITEMAP = "create-sitemap";
    const SITEMAP_FOLDER = '/var/plugins/Sitemap';
    const CONFIGURATION_FILE = PIMCORE_WEBSITE_PATH . '/var/plugins/sitemap/config.xml';

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();

        \Pimcore::getEventManager()->attach("system.maintenance", function ($event) {
            /** @var ProceduralScheduleManager $target */
            $target = $event->getTarget();
            $target->registerJob(new MaintenanceJob(
                self::MAINTENANCE_JOB_GENERATE_SITEMAP,
                new SitemapGenerator(),
                "generateXml"
            ));
        });
    }

    /**
     * {@inheritdoc}
     */
    public static function install()
    {
        if (!SitemapPlugin::isInstalled()) {
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

            // Create redirect rule
            $route = Staticroute::create();
            $route->setName('sitemap')
                ->setPattern('/\/sitemap\.xml/')
                ->setReverse('/sitemap.xml')
                ->setModule('PimcoreSitemapPlugin')
                ->setController('sitemap')
                ->setAction('view')
                ->save();

            // Create sitemap folder (if doesn't exist already)
            if (!is_dir(PIMCORE_WEBSITE_PATH . self::SITEMAP_FOLDER)) {
                mkdir(PIMCORE_WEBSITE_PATH . self::SITEMAP_FOLDER, 0777, true);
            }

            // Get Sites FQDN map
            $sites = self::getSitesProtocolMap();

            // Create xml config file
            $config = new \Zend_Config_Xml(PIMCORE_PLUGINS_PATH . '/PimcoreSitemapPlugin/install/config.xml', null, ['allowModifications' => true]);
            $config->sites = ['site' => $sites];

            $configFile = self::CONFIGURATION_FILE;
            if (!is_dir(dirname($configFile))) {
                if (!@mkdir(dirname($configFile), 0777, true)) {
                    throw new \Exception('Sitemap: Unable to create plugin config directory');
                }
            }

            $configWriter = new \Zend_Config_Writer_Xml();
            $configWriter->setConfig($config);
            $configWriter->write($configFile);

            return "Sitemap plugin successfully installed";
        }

        return "There was a problem during the installation";
    }


    /**
     * @return array
     */
    public static function getSitesProtocolMap()
    {
        $client = new \Zend_Http_Client();
        $sitesMap = [];

        // Add the main domain
        $defaultDomain = Config::getSystemConfig()->get("general")->get("domain");
        $sitesMap[] = [
            'rootId' => 1,
            'protocol' => self::getProtocolForDomain($defaultDomain, $client),
            'domain' => $defaultDomain
        ];

        // Retrieve site trees
        $siteRoots = new Site\Listing();
        $siteRoots = $siteRoots->load();

        // Build siteRoots table: [ ID => FQDN ]
        /* @var Site $siteRoot */
        foreach ($siteRoots as $siteRoot) {
            $protocol = self::getProtocolForDomain($siteRoot->getMainDomain(), $client);
            $sitesMap[] = [
                'rootId' => $siteRoot->getRootId(),
                'protocol' => $protocol,
                'domain' => $siteRoot->getMainDomain()
            ];
        }

        return $sitesMap;
    }

    /**
     * Test https for a domain name and returns either https or http depending on the server answer
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

    /**
     * {@inheritdoc}
     */
    public static function uninstall()
    {
        if (SitemapPlugin::isInstalled()) {
            $property = PredefinedProperty::getByKey("sitemap_exclude");
            $property->delete();

            $route = Staticroute::getByName('sitemap');
            $route->delete();

            // Remove config file
            if (file_exists(self::CONFIGURATION_FILE)) {
                unlink(self::CONFIGURATION_FILE);
            }

            return "Sitemap plugin is successfully uninstalled";
        }

        return "There was an error";
    }

    /**
     * {@inheritdoc}
     */
    public static function isInstalled()
    {
        $property = PredefinedProperty::getByKey("sitemap_exclude");
        if (!$property || !$property->getId()) {
            return false;
        }
        if (!file_exists(self::CONFIGURATION_FILE)) {
            return false;
        }
        return true;
    }

}
