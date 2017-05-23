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

use Byng\Pimcore\Sitemap\SitemapPlugin as SitemapPlugin;
use Pimcore\Controller\Action\Frontend as Frontend;

final class Pimcoresitemapplugin_SitemapController extends Frontend
{

    /**
     * Shows the sitemap.xml of the currently visited website
     *
     * @return void
     */
    public function viewAction()
    {
        // Special case: subsite in the tree
        if (isset($this->document) && $site = \Pimcore\Tool\Frontend::getSiteForDocument($this->document)) {
            $filename = '/' . $site->getMainDomain() . '.xml';
        } else {
            // Default filename
            $filename = '/' . \Pimcore\Config::getSystemConfig()->get("general")->get("domain") . '.xml';
        }

        // Outputting the XML
        header('Content-Type: text/xml');
        readfile(SITEMAP_PLUGIN_FOLDER . $filename);
        exit;
    }
}
