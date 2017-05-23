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
if (!class_exists('vmPSPlugin')) {
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}

if (!class_exists('MultiSafepayApi')) {
    require(JPATH_SITE . '/plugins/vmpayment/multisafepay_fco/multisafepay_fco/helpers/multisafepayapi.php');
}

class plgVmPaymentMultisafepay_Fco extends vmPSPlugin
{

    // instance of class
    private $customerData;
    private $tax_shipping = false;
    private $shipping_tax_rate = 0;
    private $shipper;
    public static $_this = false;
    private $_multisafepay_fco;
    public $_version = "2.2.0";

    //public $_currentMethod;

    function __construct(& $subject, $config)
    {
        parent::__construct($subject, $config);
        $this->_loggable = TRUE;
        $this->tableFields = array_keys($this->getTableSQLFields());
        $this->_tablepkey = 'id'; //virtuemart_sofort_id';
        $this->_tableId = 'id'; //'virtuemart_sofort_id';
        $varsToPush = $this->getVarsToPush();
        //$this->_currentMethod = new stdclass();
        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
    }

    function plgVmOnStoreInstallPaymentPluginTable($jplugin_id)
    {
        return $this->onStoreInstallPluginTable($jplugin_id);
    }

    public function getVmPluginCreateTableSQL()
    {
        return $this->createTableSQL('MultiSafepay FastCheckout Table');
    }

    function getTableSQLFields()
    {
        $SQLfields = array(
            'id' => 'int(11) UNSIGNED NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id' => 'int(1) UNSIGNED',
            'order_number' => 'char(64)',
            'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
            'payment_name' => 'varchar(5000)',
            'payment_order_total' => 'decimal(15,5) NOT NULL',
            'payment_currency' => 'smallint(1)',
            'email_currency' => 'smallint(1)',
            'cost_per_transaction' => 'decimal(10,2)',
            'cost_percent_total' => 'decimal(10,2)',
            'tax_id' => 'smallint(1)',
        );
        return $SQLfields;
    }

    /**
     * @param VirtuemartViewUser $user
     * @param                    $html
     * @param bool               $from_cart
     * @return bool|null
     */
    function plgVmDisplayLogin(VirtuemartViewUser $user, &$html, $from_cart = FALSE)
    {
        // only to display it in the cart, not in list orders view
        if (!$from_cart) {
            return NULL;
        }

        $vendorId = 1;
        if (!class_exists('VirtueMartCart')) {
            require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
        }

        $cart = VirtueMartCart::getCart();
        if ($this->getPluginMethods($cart->vendorId) === 0) {
            return FALSE;
        }
        if (isset($cart->pricesUnformatted['salesPrice']) AND $cart->pricesUnformatted['salesPrice'] <= 0.0) {
            return FALSE;
        }


        foreach ($this->methods as $paymethod) {
            if ($paymethod->payment_element == 'multisafepay_fco') {
                $this->_currentMethod->virtuemart_paymentmethod_id = $paymethod->virtuemart_paymentmethod_id;

                if ($cart->pricesCurrency == $paymethod->payment_currency) {
                    $can_show_button = true;
                } else {

                    $can_show_button = false;
                }
            }
        }
        if ($can_show_button) {
            $html .= $this->getFastCheckoutHtml($this->_currentMethod, $cart);
        }
        $session = JFactory::getSession();


        if ($session->get('msp_error')) {
            JError::raiseWarning(100, $session->get('msp_error'));
            $session->set('msp_error', '');
        }
    }

    /**
     * @param $cart
     * @param $payment_advertise
     * @return bool|null
     */
    function plgVmOnCheckoutAdvertise($cart, &$payment_advertise)
    {
        if ($this->getPluginMethods($cart->vendorId) === 0) {
            return FALSE;
        }
        if ($cart->pricesUnformatted['salesPrice'] <= 0.0) {
            return NULL;
        }

        foreach ($this->methods as $paymethod) {
            if ($paymethod->payment_element == 'multisafepay_fco') {
                $this->_currentMethod->virtuemart_paymentmethod_id = $paymethod->virtuemart_paymentmethod_id;
                if ($cart->pricesCurrency == $paymethod->payment_currency) {
                    $can_show_button = true;
                } else {
                    $can_show_button = false;
                }
            }
        }
        if ($can_show_button) {
            $payment_advertise[] = $this->getFastCheckoutHtml($this->_currentMethod, $cart);
        }
    }

    /**
     * @param $currentMethod
     * @param $cart
     * @return null|string
     */
    function getFastCheckoutHtml($currentMethod, $cart)
    {
        $html = '';
        $button = $this->getFastCheckoutButton($currentMethod);
        $html .= $this->renderByLayout('fastcheckout', array(
            'text' => vmText::_('VMPAYMENT_MULTISAFEPAY_FCO_BUTTON'),
            'img' => $button['img'],
            'link' => $button['link'],
            //'sandbox' => $this->_currentMethod->environment,
            'virtuemart_paymentmethod_id' => $this->_currentMethod->virtuemart_paymentmethod_id
                )
        );

        foreach ($this->methods as $paymethod) {
            if ($paymethod->payment_element == 'multisafepay_fco') {
                if ($paymethod->show_button_msg) {
                    $html .= '<p style="float:right;padding-right:20px;" class="fco_msg">' . vmText::_('VMPAYMENT_MULTISAFEPAY_FCO_BUTTON_MSG') . '</p>';
                    $html .= '<div style="clear:both;"></div>';
                }
            }
        }
        return $html;
    }

    function getFastCheckoutButton($currentMethod)
    {
        $button = array();
        $lang = jFactory::getLanguage();
        $lang_iso = str_replace('-', '_', $lang->gettag());
        $available_buttons = array('nl_NL');
        if (!in_array($lang_iso, $available_buttons)) {
            $lang_iso = 'nl_NL';
        }
        $button['link'] = JURI::root() . 'index.php?option=com_virtuemart&view=plugin&type=vmpayment&name=multisafepay_fco&action=SetFastCheckout&virtuemart_paymentmethod_id=' . $currentMethod->virtuemart_paymentmethod_id;
        $button['img'] = JURI::root() . 'plugins/vmpayment/multisafepay_fco/multisafepay_fco/assets/images/' . $lang_iso . '/button.png';
        return $button;
    }

    /**
     * @param $html
     * @return bool|null|string
     */
    function plgVmOnPaymentResponseReceived(&$html)
    {
        VmConfig::loadJLang('com_virtuemart_orders', TRUE);
        if (!class_exists('CurrencyDisplay')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'currencydisplay.php');
        }
        if (!class_exists('VirtueMartCart')) {
            require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
        }
        if (!class_exists('shopFunctionsF')) {
            require(JPATH_VM_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php');
        }
        if (!class_exists('VirtueMartModelOrders')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        }
        if (!class_exists('VirtueMartModelCountry')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'country.php');
        }

        if (!class_exists('VirtueMartModelShipmentmethod')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'shipmentmethod.php');
        }

        if (!class_exists('VirtueMartModelUser')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'user.php');
        }

        if (!class_exists('VirtueMartModelCalc')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'calc.php');
        }
        $taxes = VirtueMartModelCalc::getTaxes();


        $virtuemart_paymentmethod_id = vRequest::getInt('pm', 0);
        $redirect = vRequest::getString('type', 0);
        $transactionid = vRequest::getString('transactionid', 0);
        $orderid = vRequest::getString('od', 0);
        $orders = new VirtueMartModelOrders();
        $order = $orders->getOrder($orderid);
        $shipmentmethods = new VirtueMartModelShipmentmethod();
        $shippers = $shipmentmethods->getShipments();
        $countries = new VirtueMartModelCountry();


        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return NULL; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return NULL;
        }

        $msp = new MultiSafepayApi();
        if ($method->environment) {
            $msp->test = true;
        } else {
            $msp->test = false;
        }
        $msp->merchant['account_id'] = $method->account_id;
        $msp->merchant['site_id'] = $method->site_id;
        $msp->merchant['site_code'] = $method->site_secure_code;
        $msp->transaction['id'] = $transactionid;
        $status = $msp->getStatus();
        $details = $msp->details;


        $order['details']['BT']->virtuemart_paymentmethod_id = $virtuemart_paymentmethod_id;
        $order['details']['ST']->virtuemart_paymentmethod_id = $virtuemart_paymentmethod_id;

        //create order data array and and order data that will be used to update the existing order that was created before the payment
        $updateData = array();
        //update payment method data
        $updateData['old_virtuemart_paymentmethod_id'] = $order['details']['BT']->virtuemart_paymentmethod_id;
        $updateData['virtuemart_paymentmethod_id'] = $virtuemart_paymentmethod_id;

        //Set shippingmethod data
        $updateData['old_virtuemart_shipmentmethod_id'] = $order['details']['BT']->virtuemart_shipmentmethod_id;

        foreach ($shippers as $shipper) {

            if ($details['shipping']['name'] == $shipper->shipment_name) {
                $this->shipper = $shipper;
                $updateData['virtuemart_shipmentmethod_id'] = $shipper->virtuemart_shipmentmethod_id;
                $order['details']['BT']->virtuemart_shipmentmethod_id = $shipper->virtuemart_shipmentmethod_id;
                $order['details']['ST']->virtuemart_shipmentmethod_id = $shipper->virtuemart_shipmentmethod_id;
            }
        }

        //Set customer BT address data
        $updateData['BT_email'] = $details['customer']['email'];
        $updateData['BT_name'] = $details['customer']['firstname'] . ' ' . $details['customer']['lastname'];
        $updateData['BT_agreed'] = true;
        $updateData['BT_company'] = '';
        $updateData['BT_title'] = '';
        $updateData['BT_first_name'] = $details['customer']['firstname'];
        $updateData['BT_middle_name'] = '';
        $updateData['BT_last_name'] = $details['customer']['lastname'];
        $updateData['BT_address_1'] = $details['customer']['address1'] . ' ' . $details['customer']['housenumber'];
        $updateData['BT_address_2'] = $details['customer']['address2'];
        $updateData['BT_zip'] = $details['customer']['zipcode'];
        $updateData['BT_city'] = $details['customer']['city'];
        $updateData['BT_virtuemart_state_id'] = '';
        $updateData['BT_phone_1'] = $details['customer']['phone1'];
        $updateData['BT_phone_2'] = $details['customer']['phone2'];
        $bt_country = $countries->getCountryByCode($details['customer']['country']);
        $updateData['BT_virtuemart_country_id'] = $bt_country->virtuemart_country_id;


        //Set customer ST address data
        $updateData['ST_email'] = $details['customer-delivery']['email'];
        $updateData['ST_name'] = $details['customer-delivery']['firstname'] . ' ' . $details['customer-delivery']['lastname'];
        $updateData['ST_agreed'] = true;
        $updateData['ST_company'] = '';
        $updateData['ST_title'] = '';
        $updateData['ST_first_name'] = $details['customer-delivery']['firstname'];
        $updateData['ST_middle_name'] = '';
        $updateData['ST_last_name'] = $details['customer-delivery']['lastname'];
        $updateData['ST_address_1'] = $details['customer-delivery']['address1'] . ' ' . $details['customer-delivery']['housenumber'];
        $updateData['ST_address_2'] = $details['customer-delivery']['address2'];
        $updateData['ST_zip'] = $details['customer-delivery']['zipcode'];
        $updateData['ST_city'] = $details['customer-delivery']['city'];
        $updateData['ST_virtuemart_state_id'] = '';
        $updateData['ST_phone_1'] = $details['customer-delivery']['phone1'];
        $updateData['ST_phone_2'] = $details['customer-delivery']['phone2'];
        $st_country = $countries->getCountryByCode($details['customer-delivery']['country']);
        $updateData['ST_virtuemart_country_id'] = $st_country->virtuemart_country_id;

        if (!class_exists('shopFunctionsF'))
            require(JPATH_VM_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php');

        $vars['orderDetails'] = $order;
        $virtuemart_vendor_id = 1;
        $vendorModel = VmModel::getModel('vendor');
        $vendor = $vendorModel->getVendor($virtuemart_vendor_id);
        $vars['vendor'] = $vendor;
        $vendorEmail = $vendorModel->getVendorEmail($virtuemart_vendor_id);
        $vars['vendorEmail'] = $vendorEmail;


        if ($order['details']['BT']->order_status == 'P') {
            $orders->UpdateOrderHead($orderid, $updateData);
            /*
             * Email functions below need testing. Do we send the email twice now? The mail functi
             */

            //send email to customer
            $res = shopFunctionsF::renderMail('invoice', $order['details']['BT']->email, $vars, null, false, true);

            //send email to vendor
            if ($method->send_confirm_email) {
                $res = shopFunctionsF::renderMail('invoice', $vendorEmail, $vars, null, true, true);
            }
        }

        $fco_configured_tax = Shopfunctions::getTaxByID($method->tax_id);

        //Only the shippingmethods can change the totalprices, so if the shippingmethod is different then the one saved in the order we need to change order totals
        $shipping_cost_old = $order['details']['BT']->order_shipment;
        $shipping_tax_old = $order['details']['BT']->order_shipment_tax;
        $shipping_cost_new = $details['shipping']['cost'];


        if ($shipping_cost_old != $shipping_cost_new) {

            $_arr = array_filter(explode('|', $this->shipper->shipment_params));

            foreach ($_arr as $_p) {
                $_p = trim($_p);
                list($k, $v) = explode('=', $_p, 2);
                $this->shipper->parsed_params[$k] = str_replace('"', '', $v);
            }

            $shipping_tax_rate = $fco_configured_tax['calc_value'];
            $shipping_tax_rate = $shipping_tax_rate + 100;
            $shipping_tax_new = round($shipping_cost_new / $shipping_tax_rate * $fco_configured_tax['calc_value'], 5);

            $tax_total = $details['total-tax']['total'];
            $order_total = $details['order-total']['total'] + $shipping_cost_new;

            //we now have the new totals, we have to update the order. VM2 doesn't have a function to recalculate or set the data if the order is already created
            //because of this we need to update the order within the DB
            $db = JFactory::getDbo();
            $query = $db->getQuery(true);
            // $val = $order['details']['BT']->order_billTaxAmount + $shipping_tax_new;
            // Fields to update.
            $fields = array(
                $db->quoteName('order_total') . ' = ' . $order_total,
                $db->quoteName('order_billTaxAmount') . ' =' . ($tax_total + round($shipping_tax_new, 2)),
                $db->quoteName('order_shipment') . ' =' . ($shipping_cost_new - $shipping_tax_new),
                $db->quoteName('order_shipment_tax') . ' =' . $shipping_tax_new,
            );


            // Conditions for which records should be updated.
            $conditions = array(
                $db->quoteName('virtuemart_order_id') . ' = ' . $orderid . '',
            );

            $query->update($db->quoteName('#__virtuemart_orders'))->set($fields)->where($conditions);
            $db->setQuery($query);
            $result = $db->query();

            $table = '#__virtuemart_shipment_plg_' . $this->shipper->shipment_element;
            $db = & JFactory::getDBO();
            $query = "SELECT COUNT(*) FROM $table WHERE virtuemart_order_id = '$orderid'";
            $db->setQuery($query);
            $checkId = $db->loadResult();


            if ($checkId == 0) {
                $db = JFactory::getDbo();
                $ship_query = $db->getQuery(true);

                //add data to shipping method table:
                $fields_shipping = array(
                    $db->quoteName('virtuemart_order_id') . ' = ' . $orderid,
                    $db->quoteName('order_number') . ' =' . $db->quote($order['details']['BT']->order_number),
                    $db->quoteName('virtuemart_shipmentmethod_id') . ' =' . $this->shipper->virtuemart_shipmentmethod_id,
                    $db->quoteName('shipment_name') . ' =' . $db->quote($this->shipper->shipment_name),
                    $db->quoteName('shipment_cost') . ' =' . ($shipping_cost_new - $shipping_tax_new),
                    $db->quoteName('tax_id') . ' =' . $method->tax_id,
                );


                $ship_query->insert($db->quoteName('#__virtuemart_shipment_plg_' . $this->shipper->shipment_element))->set($fields_shipping);
                $db->setQuery($ship_query);
                $result = $db->query();
            }
        }


        //add data to payment method table multisafepay_fco

        $table = '#__virtuemart_payment_plg_multisafepay_fco';
        $db = JFactory::getDBO();
        $query = "SELECT COUNT(*) FROM $table WHERE virtuemart_order_id = '$orderid'";
        $db->setQuery($query);
        $checkId = $db->loadResult();


        if ($checkId == 0) {
            $db = JFactory::getDbo();
            $pay_query = $db->getQuery(true);

            //add data to shipping method table:
            $fields_payment = array(
                $db->quoteName('virtuemart_order_id') . ' = ' . $orderid,
                $db->quoteName('order_number') . ' =' . $db->quote($order['details']['BT']->order_number),
                $db->quoteName('virtuemart_paymentmethod_id') . ' =' . $method->virtuemart_paymentmethod_id,
                $db->quoteName('payment_name') . ' =' . $db->quote($method->payment_name),
                $db->quoteName('tax_id') . ' =' . $method->tax_id,
                $db->quoteName('payment_currency') . ' =' . $method->payment_currency,
                $db->quoteName('email_currency') . ' =' . $method->payment_currency,
                $db->quoteName('payment_order_total') . ' =' . ($details['transaction']['amount'] / 100),
            );


            $pay_query->insert($db->quoteName($table))->set($fields_payment);
            $db->setQuery($pay_query);
            $result = $db->query();
        }



        //update the order status and notify the customer
        switch ($status) {
            case "initialized":
                JRequest::setVar('multisafepay_msg', Jtext::_('VMPAYMENT_MULTISAFEPAY_MSG_INITIALIZED'));
                $html = $this->_getPaymentResponseHtml($details, $this->renderPluginName($method));
                break;
            case "completed":
                JRequest::setVar('multisafepay_msg', Jtext::_('VMPAYMENT_MULTISAFEPAY_MSG_COMPLETED'));
                $html = $this->_getPaymentResponseHtml($details, $this->renderPluginName($method));
                break;
            case "uncleared":
                JRequest::setVar('multisafepay_msg', Jtext::_('VMPAYMENT_UNCLEARED_MSG_UNCLEARED'));
                $html = $this->_getPaymentResponseHtml($details, $this->renderPluginName($method));
                break;
            case "void":
                JRequest::setVar('multisafepay_msg', Jtext::_('VMPAYMENT_MULTISAFEPAY_MSG_VOID'));
                $html = $this->_getPaymentResponseHtml($details, $this->renderPluginName($method));
                break;
            case "declined":
                JRequest::setVar('multisafepay_msg', Jtext::_('VMPAYMENT_MULTISAFEPAY_MSG_DECLINED'));
                $html = $this->_getPaymentResponseHtml($details, $this->renderPluginName($method));
                break;
            case "refunded":
                JRequest::setVar('multisafepay_msg', Jtext::_('VMPAYMENT_MULTISAFEPAY_MSG_REFUNDED'));
                $html = $this->_getPaymentResponseHtml($details, $this->renderPluginName($method));
                break;
            case "expired":
                JRequest::setVar('multisafepay_msg', Jtext::_('VMPAYMENT_MULTISAFEPAY_MSG_EXPIRED'));
                $html = $this->_getPaymentResponseHtml($details, $this->renderPluginName($method));
                break;
            case "cancelled":
                JRequest::setVar('multisafepay_msg', Jtext::_('VMPAYMENT_MULTISAFEPAY_MSG_CANCELED'));
                $html = $this->_getPaymentResponseHtml($details, $this->renderPluginName($method));
                break;
        }

        switch ($status) {
            case "initialized": // waiting
                $order['order_status'] = $method->status_initialized;
                break;
            case "completed":   // payment complete
                $order['order_status'] = $method->status_completed;
                break;
            case "uncleared":   // waiting (credit cards or direct debit)
                $order['order_status'] = $method->status_uncleared;
                break;
            case "void":        // canceled
                $order['order_status'] = $method->status_void;
                break;
            case "declined":    // declined
                $order['order_status'] = $method->status_declined;
                break;
            case "refunded":    // refunded
                $order['order_status'] = $method->status_refunded;
                break;
            case "expired":     // expired
                $order['order_status'] = $method->status_expired;
                break;
            case "cancelled":     // expired
                $order['order_status'] = $method->status_canceled;
                break;
        }


        if ($order['order_status'] != $order['details']['BT']->order_status AND $order['details']['BT']->order_status != 'S') {
            $order['virtuemart_order_id'] = $orderid;
            $order['comments'] = '';
            if ($order['order_status'] != $method->status_canceled) {
                $order['customer_notified'] = 1;
            } else {
                $order['customer_notified'] = 0;
            }
            $orders->updateStatusForOneOrder($orderid, $order, false);

            //When updating the status we somehow loose this shippingmethod id so now we update it.         
            $db = JFactory::getDbo();
            $query = $db->getQuery(true);


            // Fields to update.
            $fields = array(
                $db->quoteName('virtuemart_shipmentmethod_id') . ' = ' . $updateData['virtuemart_shipmentmethod_id'],
            );


            // Conditions for which records should be updated.
            $conditions = array(
                $db->quoteName('virtuemart_order_id') . ' = ' . $orderid . '',
            );



            $query->update($db->quoteName('#__virtuemart_orders'))->set($fields)->where($conditions);

            $db->setQuery($query);
            $result = $db->query();
        }


        //empty the cart
        if ($status == 'completed') {
            $cart = VirtueMartCart::getCart();
            $cart->emptyCart();
        }

        $type = JRequest::getVar('type');
        if (!empty($type)) {

            if (JRequest::getVar('type') == 'initial') {
                $link = "<a href=\"" . JURI::root() . "index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&pm=" . $virtuemart_paymentmethod_id . '&type=redirect&od=' . $orderid . '&transactionid=' . JRequest::getVar('transactionid') . "\">" . vmText::_('MULTISAFEPAY_PAYMENT_REDIRECT_LINK') . "</a>";
                echo $link;
                exit;
            } elseif (JRequest::setVar('type') == 'redirect') {
                return $html;
            }
        } else {
            echo 'OK';
            exit;
        }
    }

    function plgVmOnUserPaymentCancel()
    {
        JError::raiseWarning(100, vmText::_('MULTISAFEPAY_PAYMENT_CANCELLED'));
        return TRUE;
    }

    function _getPaymentResponseHtml($data, $payment_name)
    {
        $html = '<table cellspacing="0" cellpadding="0" class="multisafepay-table">' . "\n";
        $html .= JRequest::getVar('multisafepay_msg') . '<br /><br />';
        $html .= $this->getHtmlRow('MULTISAFEPAY_PAYMENT_NAME', $payment_name);
        $html .= $this->getHtmlRow('MULTISAFEPAY_STATUS', $data['ewallet']['status']);
        $html .= $this->getHtmlRow('MULTISAFEPAY_PAYMENT_TRANSACTIONID', vRequest::getString('od', 0));
        $html .= '</table>' . "\n";

        return $html;
    }

    /**
     * Display stored payment data for an order
     *
     * @see components/com_virtuemart/helpers/vmPSPlugin::plgVmOnShowOrderBEPayment()
     */
    function plgVmOnShowOrderBEPayment($virtuemart_order_id, $payment_method_id)
    {

        if (!$this->selectedThisByMethodId($payment_method_id)) {
            return NULL; // Another method was selected, do nothing
        }
        if (!($this->_currentMethod = $this->getVmPluginMethod($payment_method_id))) {
            return FALSE;
        }
        if (!($paymentTable = $this->getDataByOrderId($virtuemart_order_id))) {
            return NULL;
        }

        VmConfig::loadJLang('com_virtuemart');

        $html = '<table class="adminlist table">' . "\n";
        $html .= $this->getHtmlHeaderBE();
        $html .= $this->getHtmlRowBE('COM_VIRTUEMART_PAYMENT_NAME', $paymentTable->payment_name);
        $html .= $this->getHtmlRowBE('STANDARD_PAYMENT_TOTAL_CURRENCY', $paymentTable->payment_order_total . ' ' . $paymentTable->payment_currency);
        if ($paymentTable->email_currency) {
            $html .= $this->getHtmlRowBE('STANDARD_EMAIL_CURRENCY', $paymentTable->email_currency);
        }
        $html .= '</table>' . "\n";
        return $html;
    }

    /**
     * Check if the payment conditions are fulfilled for this payment method
     */
    protected function checkConditions($cart, $activeMethod, $cart_prices)
    {
        return FALSE;
    }

    /*     * **************** */
    /* Order cancelled */
    /* May be it is removed in VM 2.1
      /****************** */

    public function plgVmOnCancelPayment(&$order, $old_order_status)
    {
        return NULL;
    }

    /**
     * For FastCheckout
     * @param $type
     * @param $name
     * @param $render
     * @return bool|null
     */
    function plgVmOnSelfCallFE($type, $name, &$render)
    {
        if ($name != $this->_name || $type != 'vmpayment') {
            return FALSE;
        }
        $action = vRequest::getCmd('action');
        $virtuemart_paymentmethod_id = vRequest::getInt('virtuemart_paymentmethod_id');
        //Load the method
        if (!($this->_currentMethod = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return NULL; // Another method was selected, do nothing
        }
        if ($action != 'SetFastCheckout') {
            return false;
        }
        if (!class_exists('VirtueMartCart')) {
            require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
        }

        $cart = VirtueMartCart::getCart();
        $cart->virtuemart_paymentmethod_id = $virtuemart_paymentmethod_id;
        $cart->virtuemart_shipmentmethod_id = 0;
        $cart->setCartIntoSession();

        if (!class_exists('VirtueMartModelOrders')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        }

        $cart->getCartPrices();
        $cart->prepareCartData();



        $orders = new VirtueMartModelOrders();
        $orderID = $orders->createOrderFromCart($cart);
        $order = $orders->getOrder($orderID);

        $db = JFactory::getDbo();
        $query = $db->getQuery(true);


        // Fields to update.
        $fields = array(
            $db->quoteName('virtuemart_paymentmethod_id') . ' = ' . $virtuemart_paymentmethod_id,
        );


        // Conditions for which records should be updated.
        $conditions = array(
            $db->quoteName('virtuemart_order_id') . ' = ' . $orderID . '',
        );


        $query->update($db->quoteName('#__virtuemart_orders'))->set($fields)->where($conditions);

        $db->setQuery($query);
        $result = $db->query();


        $returnURL = JURI::root() . "index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&pm=" . $virtuemart_paymentmethod_id . '&type=redirect&od=' . $orderID;
        $Nurl = JURI::root() . "index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&pm=" . $virtuemart_paymentmethod_id . '&od=' . $orderID . '&type=initial';


        $msp = new MultiSafepayApi();
        $msp->cart = new MspCart();
        $msp->fields = new MspCustomFields();
        //$msp->cart->AddRoundingPolicy("UP", "TOTAL");
        if ($this->_currentMethod->environment) {
            $msp->test = true;
        } else {
            $msp->test = false;
        }
        $msp->merchant['account_id'] = $this->_currentMethod->account_id;
        $msp->merchant['site_id'] = $this->_currentMethod->site_id;
        $msp->merchant['site_code'] = $this->_currentMethod->site_secure_code;


        $msp->merchant['notification_url'] = $Nurl;
        $msp->merchant['cancel_url'] = JURI::root() . "index.php?option=com_virtuemart&view=pluginresponse&task=pluginUserPaymentCancel&pm=" . $virtuemart_paymentmethod_id . "&od=" . $orderID;
        $msp->merchant['redirect_url'] = $returnURL;
        $msp->merchant['close_window'] = false;

        $tax_array = array();
        //this is another format for the product data, this can be used to get data that isnt available within cart->products
        $productdata = $cart->prepareAjaxData();

        foreach ($cart->products as $product) {

            $percentage = $product->prices['taxAmount'] / $product->prices['discountedPriceWithoutTax'] * 100;

            $tax_array[$percentage] = $percentage;

            if (!empty($product->prices['discountedPriceWithoutTax'])) {
                $c_item = new MspItem($product->product_name, $product->product_s_desc, $product->quantity, $product->prices['discountedPriceWithoutTax'], 'KG', 0);
                $c_item->SetMerchantItemId($product->virtuemart_product_id);
                $c_item->SetTaxTableSelector($percentage);
                $msp->cart->AddItem($c_item);
            } else {
                $c_item = new MspItem($product->product_name, $product->product_s_desc, $product->quantity, $product->product_price, 'KG', 0);
                $c_item->SetMerchantItemId($product->virtuemart_product_id);
                $c_item->SetTaxTableSelector($percentage);
                $msp->cart->AddItem($c_item);
            }
        }


        //add taxes

        foreach ($cart->cartData['VatTax'] as $tax) {
            $table = new MspAlternateTaxTable();
            $table->name = $tax['calc_name'];
            $rate = $tax['calc_value'] / 100;
            $rule = new MspAlternateTaxRule($rate);
            $table->AddAlternateTaxRules($rule);
            $msp->cart->AddAlternateTaxTables($table);
        }

        foreach ($tax_array as $key => $value) {
            $table = new MspAlternateTaxTable();
            $table->name = $key;
            $rate = round($value / 100, 2);
            $rule = new MspAlternateTaxRule($rate);
            $table->AddAlternateTaxRules($rule);
            $msp->cart->AddAlternateTaxTables($table);
        }


        $table = new MspAlternateTaxTable();
        $table->name = 'BTW0';
        $rule = new MspAlternateTaxRule('0.00');
        $table->AddAlternateTaxRules($rule);
        $msp->cart->AddAlternateTaxTables($table);


        //todo add fees
        //todo add coupons
        require_once JPATH_VM_SITE . '/helpers/coupon.php';
        $coupondata = CouponHelper::getCouponDetails($cart->couponCode);


        if (!empty($coupondata)) {
            $c_item = new MspItem('Coupon - ' . $cart->couponCode, '', '1', -$coupondata->coupon_value, 'KG', 0);
            $c_item->SetMerchantItemId('Discount');
            $c_item->SetTaxTableSelector('BTW0');
            $msp->cart->AddItem($c_item);
        }

        //todo add discounts
        if (!empty($cart->pricesUnformatted['billDiscountAmount'])) {
            $c_item = new MspItem('Discount', '', '1', $cart->pricesUnformatted['billDiscountAmount'], 'KG', 0);
            $c_item->SetMerchantItemId('Discount');
            $c_item->SetTaxTableSelector('BTW0');
            $msp->cart->AddItem($c_item);
        }

        //todo add giftwraps
        // add shippingmethods
        require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'shipmentmethod.php');
        $shippers = new VirtueMartModelShipmentmethod();
        $shipmethods = $shippers->getShipments();
        $shippers->getShipment();

        foreach ($shipmethods as $carrier) {



            $_arr = array_filter(explode('|', $carrier->shipment_params));
            foreach ($_arr as $_p) {
                $_p = trim($_p);
                list($k, $v) = explode('=', $_p, 2);
                $carrier->parsed_params[$k] = str_replace('"', '', $v);
            }

            if ($carrier->parsed_params['tax_id'] != '-1') {
                $this->tax_shipping = true;
            }


            //require(JPATH_VM_ADMINISTRATOR.DS.'helpers'.DS.'shopfunctions.php');
            $functions = new ShopFunctions();

            $countryModel = VmModel::getModel('country');
            $countries = $countryModel->getCountries(TRUE, TRUE, FALSE);
            $countriestring = str_replace('[', '', $carrier->parsed_params['countries']);
            $countriestring = str_replace(']', '', $countriestring);
            $availablecountries = explode(',', $countriestring);

            $country_codes = array();
            foreach ($availablecountries as $country) {
                foreach ($countries as $countrym) {
                    if (!empty($countries[$country]->country_2_code)) {
                        if ($countrym->virtuemart_country_id == $country) {
                            $country_codes[] = $countrym->country_2_code;
                        }
                    }
                }
            }

            if (!empty($country_codes)) {
                $carrier->countrycodes = $country_codes;
            }


            $title = $carrier->shipment_name;



            if (isset($carrier->parsed_params['shipment_cost'])) {
                $cost = $carrier->parsed_params['shipment_cost'];
            } elseif (isset($carrier->parsed_params['cost'])) {
                $cost = $carrier->parsed_params['cost'];
            }

            $cost = $cost + $carrier->parsed_params['package_fee'];

            $shipping = new MspFlatRateShipping($title, $cost);

            $filter = new MspShippingFilters();
            if (!empty($carrier->countrycodes)) {
                foreach ($carrier->countrycodes as $country) {
                    $filter->AddAllowedPostalArea($country);
                    $shipping->AddShippingRestrictions($filter);
                }
                $shipping->AddShippingRestrictions($filter);
            }

            if ($carrier->published) {
                $msp->cart->AddShipping($shipping);
            }
        }



        if (!class_exists('shopFunctionsF')) {
            require(JPATH_VM_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php');
        }
        $fco_configured_tax = Shopfunctions::getTaxByID($this->_currentMethod->tax_id);



        if ($this->_currentMethod->tax_id == '-1') {
            $this->tax_shipping = false;
        } else {
            $this->tax_shipping = true;
        }

        $rule = new MspDefaultTaxRule($fco_configured_tax['calc_value'] / 100, $this->tax_shipping);
        $msp->cart->AddDefaultTaxRules($rule);


        $msp->transaction['id'] = uniqid();
        $msp->transaction['currency'] = 'EUR';
        $msp->transaction['amount'] = round($cart->pricesUnformatted['billTotal'], 2) * 100;
        $msp->transaction['description'] = 'Order #' . $msp->transaction['id'];
        $msp->plugin['shop'] = 'Virtuemart FCO';
        $msp->plugin['shop_version'] = '2';
        $msp->plugin['plugin_version'] = '2.2.0';
        $msp->plugin['partner'] = '';
        $msp->plugin['shop_root_url'] = JURI::root();
        $msp->plugin_name = $msp->plugin['shop'];
        $msp->version = $msp->plugin['plugin_version'];
        $msp->use_shipping_notification = false;




        $dbValues['order_number'] = $order['details']['BT']->order_number;
        $dbValues['payment_name'] = $this->renderPluginName($this->_currentMethod);
        $dbValues['virtuemart_paymentmethod_id'] = $virtuemart_paymentmethod_id;
        $dbValues['cost_per_transaction'] = $this->_currentMethod->cost_per_transaction;
        $dbValues['cost_percent_total'] = $this->_currentMethod->cost_percent_total;
        $dbValues['payment_currency'] = 'EUR';
        $dbValues['payment_order_total'] = $cart->pricesUnformatted['billTotal']; /////////
        $dbValues['tax_id'] = $this->_currentMethod->tax_id;
        $dbValues['multisafepay_order_id'] = $cart->virtuemart_order_id;
        //$this->storePSPluginInternalData($dbValues);


        $url = $msp->startCheckout();



        if ($msp->error) {
            $html = "Error " . $msp->error_code . ": " . $msp->error;
            $session = JFactory::getSession();
            $session->set('msp_error', $html);
            if (!class_exists('VirtueMartModelOrders')) {
                require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
            }

            $modelOrder = VmModel::getModel('orders');
            $order['order_status'] = 'X';
            $order['virtuemart_order_id'] = $orderID;
            $order['customer_notified'] = 0;
            $order['comments'] = JText::_('COM_VIRTUEMART_PAYMENT_CANCELLED_BY_SHOPPER');
            $modelOrder->updateStatusForOneOrder($orderID, $order, TRUE);

            $app = JFactory::getApplication();
            $app->redirect(JURI::root() . "index.php?option=com_virtuemart&view=cart");
            return false;
        } else {
            $app = JFactory::getApplication();
            $app->redirect($url);
        }

        exit;
    }

    //Calculate the price (value, tax_id) of the selected method, It is called by the calculator
    //This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.
    public function plgVmOnSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name)
    {

        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    // Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
    // The plugin must check first if it is the correct type
    function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter)
    {
        return $this->onCheckAutomaticSelected($cart, $cart_prices, $paymentCounter);
    }

    // This method is fired when showing the order details in the frontend.
    // It displays the method-specific data.
    public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name)
    {
        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }

    // This method is fired when showing when priting an Order
    // It displays the the payment method-specific data.
    function plgVmonShowOrderPrintPayment($order_number, $method_id)
    {
        return $this->onShowOrderPrint($order_number, $method_id);
    }

    function plgVmDeclarePluginParamsPayment($name, $id, &$data)
    {

        return $this->declarePluginParams('payment', $name, $id, $data);
    }

    function plgVmSetOnTablePluginParamsPayment($name, $id, &$table)
    {
        return $this->setOnTablePluginParams($name, $id, $table);
    }

    function plgVmDeclarePluginParamsPaymentVM3(&$data)
    {
        return $this->declarePluginParams('payment', $data);
    }

}

// No closing tag
