<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * @category Piwik
 * @package Updates
 */

namespace Piwik\Updates;

use Piwik\Updates;

/**
 * @package Updates
 */
class Updates_1_9_3_b3 extends Updates
{
    static function update()
    {
        // Insight was a temporary code name for Overlay
        $pluginToDelete = 'Insight';
        self::deletePluginFromConfigFile($pluginToDelete);
        \Piwik\PluginsManager::getInstance()->deletePluginFromFilesystem($pluginToDelete);

        // We also clean up 1.9.1 and delete Feedburner plugin
        \Piwik\PluginsManager::getInstance()->deletePluginFromFilesystem('Feedburner');
    }
}
