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
    
    private $includePathReady = false;
    private $manualIncludeDirs = array();
    private $composerAutoloader;
    private $appDir;
    private $vendorDir;

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
        self::instance()->getComposerAutoloader()->unregister();
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

        $this->prepareIncludePath();
        
        if ($resolvedPath = stream_resolve_include_path($classFile)) {
            return $resolvedPath;
        }
        
        if ($resolvedPath = $this->getComposerAutoloader()->findFile($class)) {
            return $resolvedPath;
        }
        
        foreach ($this->manualIncludeDirs as $dir) {
            if (file_exists($dir . DIRECTORY_SEPARATOR . $classFile)) {
                return $dir . DIRECTORY_SEPARATOR . $classFile;
            }
        }
        
        return false;
    }

    private function prepareIncludePath()
    {
        if ($this->includePathReady) {
            return;
        }
        
        $mageAppIncludeDirs = array();
        $vendorIncludeDirs = array();
        $mageLibIncludeDirs = array();
        $globalIncludeDirs = array();
        
        $dirPaths = array_merge(explode(PATH_SEPARATOR, get_include_path()), $this->manualIncludeDirs);
        $vendorPath = $this->getVendorDir();
        $magePath = $this->getMagentoBaseDir();
        
        foreach ($dirPaths as $dirPath) {
            if (0 === strpos($dirPath, $magePath . DIRECTORY_SEPARATOR . 'app')) {
                $mageAppIncludeDirs[] = $dirPath;
            } elseif (0 === strpos($dirPath, $magePath . DIRECTORY_SEPARATOR . 'lib')) {
                $mageLibIncludeDirs[] = $dirPath;
            } elseif (0 === strpos($dirPath, $vendorPath)) {
                $vendorIncludeDirs[] = $dirPath;
            } else {
                $globalIncludeDirs[] = $dirPath;
            }
        }
        
        set_include_path(implode(PATH_SEPARATOR, $mageAppIncludeDirs));
        $this->manualIncludeDirs = array_merge($vendorIncludeDirs, $mageLibIncludeDirs, $globalIncludeDirs);
        
        $this->includePathReady = true;
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
        $this->includePathReady = false;
        
        $autoloader = require $this->getVendorDir() . '/autoload.php';
        /* @var $autoloader \Composer\Autoload\ClassLoader */
        $autoloader->setUseIncludePath(false);
        
        return $autoloader;
    }
    
    private function getMagentoBaseDir()
    {
        if (is_null($this->appDir)) {
            if (defined('BP')) {
                $this->appDir = BP;
            } else {
                $this->appDir = dirname(dirname(dirname(dirname(__DIR__))));
            }
        }
        
        return $this->appDir;
    }
    
    private function getVendorDir()
    {
        if (is_null($this->vendorDir)) {
            $magentoBaseDir = $this->getMagentoBaseDir();
            $ds = DIRECTORY_SEPARATOR;
            if (file_exists($magentoBaseDir . $ds . 'vendor' . $ds . 'autoload.php')) {
                $this->vendorDir = $magentoBaseDir . $ds . 'vendor';
            } else {
                $this->vendorDir = dirname($magentoBaseDir) . $ds . 'vendor';
            }
        }
        
        return $this->vendorDir;
    }
}
