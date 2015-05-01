<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category   Varien
 * @package    Varien_Autoload
 * @copyright  Copyright (c) 2008 Irubin Consulting Inc. DBA Varien (http://www.varien.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Classes source autoload
 * 
 * @author Josh Di Fabio <joshdifabio@gmail.com>
 */
class Varien_Autoload
{
    const SCOPE_FILE_PREFIX = '__';

    static protected $_instance;
    static protected $_scope = 'default';

    protected $_isIncludePathDefined= null;
    protected $_collectClasses      = false;
    protected $_collectPath         = null;
    protected $_arrLoadedClasses    = array();
    
    private $composerAutoloader;

    /**
     * Class constructor
     */
    public function __construct()
    {
        register_shutdown_function(array($this, 'destroy'));
        
        if (defined('COMPILER_INCLUDE_PATH') || defined('COMPILER_COLLECT_PATH')) {
            throw new \LogicException('The Composer bridge is not compatible with the Magento compiler.');
        }
        
        self::registerScope(self::$_scope);
    }

    /**
     * Singleton pattern implementation
     *
     * @return Varien_Autoload
     */
    static public function instance()
    {
        if (!self::$_instance) {
            self::$_instance = new Varien_Autoload();
        }
        
        return self::$_instance;
    }

    /**
     * Register SPL autoload function
     */
    static public function register()
    {
        spl_autoload_register(array(self::instance(), 'autoload'));
    }

    /**
     * Load class source code
     *
     * @param string $class
     */
    public function autoload($class)
    {
        if (!$resolvedPath = $this->findFile($class)) {
            return false;
        }
        
        return include $resolvedPath;
    }
    
    /**
     * @param string $class
     * @return false|string Path to file
     */
    public function findFile($class)
    {
        $classFile = str_replace(' ', DIRECTORY_SEPARATOR, ucwords(str_replace(array('_', '\\'), ' ', $class))) . '.php';
        
        if (!$resolvedPath = stream_resolve_include_path($classFile)) {
            $resolvedPath = $this->getComposerAutoloader()->findFile($class);
        }
        
        return $resolvedPath;
    }

    /**
     * This is here for backwards compatibility only
     *
     * @param string $code
     */
    static public function registerScope($code)
    {
        self::$_scope = $code;
    }

    /**
     * Get current autoload scope
     *
     * @return string
     */
    static public function getScope()
    {
        return self::$_scope;
    }

    /**
     * This is here for backwards compatibility only
     */
    public function destroy()
    {
        
    }

    /**
     * This is here for backwards compatibility only
     *
     * @return Varien_Autoload
     */
    protected function _saveCollectedStat()
    {
        return $this;
    }
    
    private function getComposerAutoloader()
    {
        if (!$this->composerAutoloader) {
            $this->composerAutoloader = $this->initComposerAutoloader();
        }
        
        return $this->composerAutoloader;
    }
    
    private function initComposerAutoloader()
    {
        // if vendor/ is child of Magento root
        $vendorParentPath = dirname(dirname(dirname(__DIR__)));
        
        if (!$autoloader = @include "$vendorParentPath/vendor/autoload.php") {
            // if vendor/ is sibling of Magento root
            $autoloader = require dirname($vendorParentPath) . '/vendor/autoload.php';
        }
        
        /* @var $autoloader \Composer\Autoload\ClassLoader */
        
        $autoloader->setUseIncludePath(false);
        
        return $autoloader;
    }
}
