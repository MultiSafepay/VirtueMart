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
// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

// Load the controller framework
jimport('joomla.application.component.controller');
require_once(dirname(__FILE__) . '/pluginresponse.php');

class VirtueMartControllerMultisafepayresponse extends VirtueMartControllerPluginresponse
{

    /**
     * Construct the cart
     *
     * @access public
     */
    public function __construct()
    {
        parent::__construct();
    }

    function notify()
    {
        $notify = ($_GET['mode'] == "notify");

        if (!class_exists('vmPSPlugin'))
            require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
        JPluginHelper::importPlugin('vmpayment');

        $return_context = "";
        $dispatcher = JDispatcher::getInstance();
        $html = "";
        $returnValues = $dispatcher->trigger('plgVmOnPaymentResponseReceived', array('html' => &$html));

        //If this script is reached then there was no post data
        echo 'ok';
        exit();
    }

    function result()
    {
        if (!class_exists('vmPSPlugin'))
            require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
        JPluginHelper::importPlugin('vmpayment');

        $return_context = "";
        $dispatcher = JDispatcher::getInstance();
        $html = "";
        $returnValues = $dispatcher->trigger('plgVmOnPaymentResponseReceived', array('html' => &$html));
        if (isset($_REQUEST['transactionid'])) {
            $view = $this->getView('pluginresponse', 'html');
            if (version_compare(JVERSION, '2.5.0', 'ge')) {
                $view->assignRef('paymentResponse', Jtext::_('COM_VIRTUEMART_CART_THANKYOU'));
                $view->assignRef('paymentResponseHtml', $html, 'post');
            } else {
                JRequest::setVar('paymentResponse', Jtext::_('COM_VIRTUEMART_CART_THANKYOU'));
                JRequest::setVar('paymentResponseHtml', $html, 'post');
            }
        } else {
            $view = $this->getView('cart', 'html');
        }
        $layoutName = JRequest::getVar('layout', 'default');
        $view->setLayout($layoutName);
        $view->display();
    }

}
