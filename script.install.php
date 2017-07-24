<?php

/**
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade the MultiSafepay plugin
 * to newer versions in the future. If you wish to customize the plugin for your
 * needs please document your changes and make backups before you update.
 *
 * @category    MultiSafepay
 * @package     Connect
 * @author      TechSupport <techsupport@multisafepay.com>
 * @copyright   Copyright (c) 2017 MultiSafepay, Inc. (http://www.multisafepay.com)
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
 * INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR
 * PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN
 * ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */
defined('_JEXEC') or die('Restricted access');

jimport('joomla.application.component.model');

defined('DS') or define('DS', DIRECTORY_SEPARATOR);
defined('JPATH_VM_ADMINISTRATOR') or define('JPATH_VM_ADMINISTRATOR', JPATH_ROOT . DS . 'administrator' . DS . 'components' . DS . 'com_virtuemart');

// hack to prevent defining these twice in 1.6 installation
if (!defined('_VM_SCRIPT_INCLUDED')) {
    define('_VM_SCRIPT_INCLUDED', true);

    class com_multisafepayInstallerScript
    {

        /**
         * Constructor
         *
         * @param   JAdapterInstance  $adapter  The object responsible for running this script
         */
        public function __constructor($adapter)
        {
            
        }

        /**
         * Called before any type of action
         *
         * @param   string  $route  Which action is happening (install|uninstall|discover_install)
         * @param   JAdapterInstance  $adapter  The object responsible for running this script
         *
         * @return  boolean  True on success
         */
        public function preflight($route, $adapter)
        {
            
        }

        /**
         * Called after any type of action
         *
         * @param   string  $route  Which action is happening (install|uninstall|discover_install)
         * @param   JAdapterInstance  $adapter  The object responsible for running this script
         *
         * @return  boolean  True on success
         */
        public function postflight($type, $parent = null)
        {
            return true;
        }

        /**
         * Retrieves the #__extensions IDs of a component given the component name (eg "com_somecomponent")
         *
         * @param   string   $component The component's name
         * @return   array   An array of component IDs
         */
        protected function getExtensionIds($component)
        {
            $db = JFactory::getDbo();
            $query = $db->getQuery(true);
            $query->select('extension_id');
            $query->from('#__extensions');
            $cleanComponent = filter_var($component, FILTER_SANITIZE_MAGIC_QUOTES);
            $query->where($query->qn('name') . ' = ' . $query->quote($cleanComponent));
            $db->setQuery($query);
            $ids = $db->loadResultArray();
            return $ids;
        }

        /**
         * Removes the admin menu item for a given component
         *
         * This method was pilfered from JInstallerComponent::_removeAdminMenus()
         * 	
         * @param   int      $id The component's #__extensions id
         * @return   bool   true on success, false on failure
         */
        protected function removeAdminMenus(&$id)
        {
            // Initialise Variables
            $db = JFactory::getDbo();
            $table = JTable::getInstance('menu');
            // Get the ids of the menu items
            $query = $db->getQuery(true);
            $query->select('id');
            $query->from('#__menu');
            $query->where($query->qn('client_id') . ' = 1');
            $query->where($query->qn('component_id') . ' = ' . (int) $id);
            $db->setQuery($query);
            $ids = $db->loadColumn();

            // Check for error
            if ($error = $db->getErrorMsg()) {
                return false;
            } elseif (!empty($ids)) {
                // Iterate the items to delete each one.
                foreach ($ids as $menuid) {
                    if (!$table->delete((int) $menuid)) {
                        return false;
                    }
                }
                // Rebuild the whole tree
                $table->rebuild();
            }
            return true;
        }

        /**
         * Called on installation
         *
         * @param   JAdapterInstance  $adapter  The object responsible for running this script
         *
         * @return  boolean  True on success
         */
        public function install($loadVm = true)
        {
            if (!$this->loadVm()) {
                return false;
            }
            $this->installPlugin('VM - Payment, Multisafepay', 'plugin', 'multisafepay', 'vmpayment');
            $this->installFCOPlugin('VM - Payment, Multisafepay FastCheckout', 'plugin', 'multisafepay_fco', 'vmpayment');

            $componentName = 'multisafepay'; //The name you're using in the manifest
            $extIds = $this->getExtensionIds($componentName);
            if (count($extIds)) {
                foreach ($extIds as $id) {
                    if (!$this->removeAdminMenus($id)) {
                        echo JText::_('Can\'t remove menu items');
                    }
                }
            }

            //Plugin should now be installed so now we will add the payment methods to Hika shop
            $paymentMethods = array();
            $paymentMethods[] = array('name' => 'MultiSafepay IDEAL', 'code' => 'IDEAL');
            $paymentMethods[] = array('name' => 'MultiSafepay VISA', 'code' => 'VISA');
            $paymentMethods[] = array('name' => 'MultiSafepay MASTERCARD', 'code' => 'MASTERCARD');
            $paymentMethods[] = array('name' => 'MultiSafepay Banktransfer', 'code' => 'BANKTRANS');
            $paymentMethods[] = array('name' => 'MultiSafepay Maestro', 'code' => 'Maestro');
            $paymentMethods[] = array('name' => 'MultiSafepay MisterCash', 'code' => 'MISTERCASH');
            $paymentMethods[] = array('name' => 'MultiSafepay Giropay', 'code' => 'GIROPAY');
            $paymentMethods[] = array('name' => 'MultiSafepay Sofort', 'code' => 'DIRECTEBANK');
			$paymentMethods[] = array('name' => 'MultiSafepay Paysafecard', 'code' => 'PSAFECARD');
            $paymentMethods[] = array('name' => 'MultiSafepay Direct Debit', 'code' => 'DIRDEB');
            $paymentMethods[] = array('name' => 'MultiSafepay Betaal na Ontvangst', 'code' => 'PAYAFTER');
            $paymentMethods[] = array('name' => 'MultiSafepay FastCheckout', 'code' => 'FCO');


            foreach ($paymentMethods as $paymentmethod) {

                //get languages and add pament methods to tables.
            }
        }

        /**
         * Called on update
         *
         * @param   JAdapterInstance  $adapter  The object responsible for running this script
         *
         * @return  boolean  True on success
         */
        public function update($adapter)
        {
            $this->uninstall($adapter);
            $this->install($adapter);
        }

        /**
         * Called on uninstallation
         *
         * @param   JAdapterInstance  $adapter  The object responsible for running this script
         */
        public function uninstall($adapter = null)
        {
            
        }

        public function loadVm()
        {
            $this->path = JInstaller::getInstance()->getPath('extension_administrator');
            if (!class_exists('VmConfig')) {
                $file = JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_virtuemart' . DS . 'helpers' . DS . 'config.php';
                if (file_exists($file)) {
                    require($file);
                } else {
                    $app = JFactory::getApplication();
                    $app->enqueueMessage(get_class($this) . ':: VirtueMart2 must be installed ');
                    return false;
                }
            }
            return true;
        }

        public function checkIfUpdate()
        {
            return false;
        }

        public function createIndexFolder($path)
        {
            if (!class_exists('JFile')) {
                require(JPATH_VM_LIBRARIES . DS . 'joomla' . DS . 'filesystem' . DS . 'file.php');
            }
            if (JFolder::create($path)) {
                if (!JFile::exists($path . DS . 'index.html')) {
                    JFile::copy(JPATH_ROOT . DS . 'components' . DS . 'index.html', $path . DS . 'index.html');
                }
                return true;
            }
            return false;
        }

        private function recurse_copy($src, $dst)
        {
            $dir = opendir($src);
            $this->createIndexFolder($dst);

            if (is_resource($dir)) {
                while (false !== ( $file = readdir($dir))) {
                    if (( $file != '.' ) && ( $file != '..' )) {
                        if (is_dir($src . DS . $file)) {
                            $this->recurse_copy($src . DS . $file, $dst . DS . $file);
                        } else {
                            if (JFile::exists($dst . DS . $file)) {
                                if (!JFile::delete($dst . DS . $file)) {
                                    $app = JFactory::getApplication();
                                    $app->enqueueMessage('Couldnt delete ' . $dst . DS . $file);
                                }
                            }
                            if (!JFile::move($src . DS . $file, $dst . DS . $file)) {
                                $app = JFactory::getApplication();
                                $app->enqueueMessage('Couldnt move ' . $src . DS . $file . ' to ' . $dst . DS . $file);
                            }
                        }
                    }
                }
                closedir($dir);
                if (is_dir($src))
                    JFolder::delete($src);
            }
            else {
                $app = JFactory::getApplication();
                $app->enqueueMessage('Couldnt read dir ' . $dir . ' source ' . $src);
            }
        }

        private function installPlugin($name, $type, $element, $group)
        {
            $task = JRequest::getCmd('task');
            if ($task != 'updateDatabase') {
                $data = array();

                if (version_compare(JVERSION, '1.7.0', 'ge')) {
                    // Joomla! 1.7 code here
                    $table = JTable::getInstance('extension');
                    $data['enabled'] = 1;
                    $data['access'] = 1;
                    $tableName = '#__extensions';
                    $idfield = 'extension_id';
                } elseif (version_compare(JVERSION, '1.6.0', 'ge')) {
                    // Joomla! 1.6 code here
                    $table = JTable::getInstance('extension');
                    $data['enabled'] = 1;
                    $data['access'] = 1;
                    $tableName = '#__extensions';
                    $idfield = 'extension_id';
                } else {
                    // Joomla! 1.5 code here
                    $table = JTable::getInstance('plugin');
                    $data['published'] = 1;
                    $data['access'] = 0;
                    $tableName = '#__plugins';
                    $idfield = 'id';
                }

                $data['name'] = $name;
                $data['type'] = $type;
                $data['element'] = $element;
                $data['folder'] = $group;
                $data['client_id'] = 0;

                $src = $this->path . DS . 'plugins' . DS . $group . DS . $element;

                $db = JFactory::getDBO();
                $q = 'SELECT ' . $idfield . ' FROM `' . $tableName . '` WHERE `name` = "' . $name . '" ';
                $db->setQuery($q);
                $count = $db->loadResult();

                if (!empty($count)) {
                    $table->load($count);
                    if (empty($table->manifest_cache)) {
                        if (version_compare(JVERSION, '1.6.0', 'ge')) {
                            $data['manifest_cache'] = json_encode(JApplicationHelper::parseXMLInstallFile($src . DS . $element . '.xml'));
                        }
                    }
                }

                if (!$table->bind($data)) {
                    $app = JFactory::getApplication();
                    $app->enqueueMessage('VMInstaller table->bind throws error for ' . $name . ' ' . $type . ' ' . $element . ' ' . $group);
                }

                if (!$table->check($data)) {
                    $app = JFactory::getApplication();
                    $app->enqueueMessage('VMInstaller table->check throws error for ' . $name . ' ' . $type . ' ' . $element . ' ' . $group);
                }

                if (!$table->store($data)) {
                    $app = JFactory::getApplication();
                    $app->enqueueMessage('VMInstaller table->store throws error for ' . $name . ' ' . $type . ' ' . $element . ' ' . $group);
                }

                $errors = $table->getErrors();
                foreach ($errors as $error) {
                    $app = JFactory::getApplication();
                    $app->enqueueMessage(get_class($this) . '::store ' . $error);
                }
            }
            if (version_compare(JVERSION, '1.7.0', 'ge')) {
                // Joomla! 1.7 code here
                $dst = JPATH_ROOT . DS . 'plugins' . DS . $group . DS . $element;
            } elseif (version_compare(JVERSION, '1.6.0', 'ge')) {
                // Joomla! 1.6 code here
                $dst = JPATH_ROOT . DS . 'plugins' . DS . $group . DS . $element;
            } else {
                // Joomla! 1.5 code here
                $dst = JPATH_ROOT . DS . 'plugins' . DS . $group;
            }

            /* Copy plugin files */
            $src = $this->path . DS . 'files' . DS . 'plugins' . DS . 'vmpayment';
            $this->recurse_copy($src, $dst);

            /* Copy admin */
            $src = $this->path . DS . 'files' . DS . 'administrator';
            $dst = JPATH_ROOT . DS . 'administrator';
            $this->recurse_copy($src, $dst);

            /* Copy component files */
            $src = $this->path . DS . 'files' . DS . 'components';
            $dst = JPATH_ROOT . DS . 'components';
            $this->recurse_copy($src, $dst);
        }

        private function installFCOPlugin($name, $type, $element, $group)
        {
            $task = JRequest::getCmd('task');
            if ($task != 'updateDatabase') {
                $data = array();

                if (version_compare(JVERSION, '1.7.0', 'ge')) {
                    // Joomla! 1.7 code here
                    $table = JTable::getInstance('extension');
                    $data['enabled'] = 1;
                    $data['access'] = 1;
                    $tableName = '#__extensions';
                    $idfield = 'extension_id';
                } elseif (version_compare(JVERSION, '1.6.0', 'ge')) {
                    // Joomla! 1.6 code here
                    $table = JTable::getInstance('extension');
                    $data['enabled'] = 1;
                    $data['access'] = 1;
                    $tableName = '#__extensions';
                    $idfield = 'extension_id';
                } else {
                    // Joomla! 1.5 code here
                    $table = JTable::getInstance('plugin');
                    $data['published'] = 1;
                    $data['access'] = 0;
                    $tableName = '#__plugins';
                    $idfield = 'id';
                }

                $data['name'] = $name;
                $data['type'] = $type;
                $data['element'] = $element;
                $data['folder'] = $group;
                $data['client_id'] = 0;

                $src = $this->path . DS . 'plugins' . DS . $group . DS . $element;

                $db = JFactory::getDBO();
                $q = 'SELECT ' . $idfield . ' FROM `' . $tableName . '` WHERE `name` = "' . $name . '" ';
                $db->setQuery($q);
                $count = $db->loadResult();

                if (!empty($count)) {
                    $table->load($count);
                    if (empty($table->manifest_cache)) {
                        if (version_compare(JVERSION, '1.6.0', 'ge')) {
                            $data['manifest_cache'] = json_encode(JApplicationHelper::parseXMLInstallFile($src . DS . $element . '.xml'));
                        }
                    }
                }

                if (!$table->bind($data)) {
                    $app = JFactory::getApplication();
                    $app->enqueueMessage('VMInstaller table->bind throws error for ' . $name . ' ' . $type . ' ' . $element . ' ' . $group);
                }

                if (!$table->check($data)) {
                    $app = JFactory::getApplication();
                    $app->enqueueMessage('VMInstaller table->check throws error for ' . $name . ' ' . $type . ' ' . $element . ' ' . $group);
                }

                if (!$table->store($data)) {
                    $app = JFactory::getApplication();
                    $app->enqueueMessage('VMInstaller table->store throws error for ' . $name . ' ' . $type . ' ' . $element . ' ' . $group);
                }

                $errors = $table->getErrors();
                foreach ($errors as $error) {
                    $app = JFactory::getApplication();
                    $app->enqueueMessage(get_class($this) . '::store ' . $error);
                }
            }
            if (version_compare(JVERSION, '1.7.0', 'ge')) {
                // Joomla! 1.7 code here
                $dst = JPATH_ROOT . DS . 'plugins' . DS . $group . DS . $element;
            } elseif (version_compare(JVERSION, '1.6.0', 'ge')) {
                // Joomla! 1.6 code here
                $dst = JPATH_ROOT . DS . 'plugins' . DS . $group . DS . $element;
            } else {
                // Joomla! 1.5 code here
                $dst = JPATH_ROOT . DS . 'plugins' . DS . $group;
            }

            /* Copy plugin files */
            $src = $this->path . DS . 'files' . DS . 'plugins' . DS . 'multisafepay_fco';
            $this->recurse_copy($src, $dst);
        }

    }

    /* 1.5 support */

    function com_install()
    {
        $vmInstall = new com_multisafepayInstallerScript();
        $upgrade = $vmInstall->checkIfUpdate();

        if (version_compare(JVERSION, '1.6.0', 'ge')) {
            // Joomla! 1.6 code here
        } else {
            // Joomla! 1.5 code here
            $method = ($upgrade) ? 'update' : 'install';
            $vmInstall->$method();
            $vmInstall->postflight($method);
        }
        return true;
    }

    /**
     * Legacy j1.5 function to use the 1.6 class uninstall
     *
     * @return boolean True on success
     * @deprecated
     */
    function com_uninstall()
    {
        $vmInstall = new com_multisafepayInstallerScript();

        if (version_compare(JVERSION, '1.6.0', 'ge')) {
            // Joomla! 1.6 code here
        } else {
            $vmInstall->uninstall();
            $vmInstall->postflight('uninstall');
        }
        return true;
    }

}