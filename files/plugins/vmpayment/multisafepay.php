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
defined('_JEXEC') or die('Direct Access to ' . basename(__FILE__) . ' is not allowed.');

if (!class_exists('vmPSPlugin'))
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');

require_once(dirname(__FILE__) . '/multisafepay_api/MultiSafepay.combined.php');

class plgVMPaymentMultisafepay extends vmPSPlugin
{

    public static $_this = false;
    private $_multisafepay;
    public $_version = "2.2.0";

    function __construct(& $subject, $config)
    {
        parent::__construct($subject, $config);
        $this->_loggable = TRUE;
        $this->tableFields = array_keys($this->getTableSQLFields());
        $this->_tablepkey = 'id'; //virtuemart_sofort_id';
        $this->_tableId = 'id'; //'virtuemart_sofort_id';
        $varsToPush = $this->getVarsToPush();
        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
    }

    private function _getVersion()
    {
        return intval(str_replace(".", "", vmVersion::$RELEASE));
    }

    protected function getVmPluginCreateTableSQL()
    {
        return $this->createTableSQL('Payment Multisafepay Table');
    }

    function getTableSQLFields()
    {
        $SQLfields = array(
            'id' => 'int(11) unsigned NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id' => 'int(11) UNSIGNED DEFAULT NULL',
            'order_number' => 'char(32) DEFAULT NULL',
            'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED DEFAULT NULL',
            'payment_name' => 'char(255) NOT NULL DEFAULT \'\' ',
            'payment_order_total' => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\' ',
            'payment_currency' => 'char(3) ',
            'cost_per_transaction' => ' decimal(10,2) DEFAULT NULL ',
            'cost_percent_total' => ' decimal(10,2) DEFAULT NULL ',
            'tax_id' => 'smallint(11) DEFAULT NULL',
            'multisafepay_order_id' => 'int(11) UNSIGNED DEFAULT NULL',
            'multisafepay_transaction_id' => 'char(32) DEFAULT NULL',
            'multisafepay_gateway' => 'char(32) DEFAULT NULL',
            'multisafepay_ip_address' => 'char(32) DEFAULT NULL',
            'multisafepay_status' => 'char(32) DEFAULT \'NEW\''
        );
        return $SQLfields;
    }

    private function _getLangISO()
    {
        $lang = &JFactory::getLanguage();
        $arr = explode("-", $lang->get('tag'));
        return strtoupper($arr[0]);
    }

    function plgVmConfirmedOrder($cart, $order)
    {
        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        $lang = JFactory::getLanguage();
        $filename = 'com_virtuemart';
        $lang->load($filename, JPATH_ADMINISTRATOR);
        $vendorId = 0;

        if (!class_exists('VirtueMartModelOrders'))
            require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );

        $this->getPaymentCurrency($method);
        // END printing out HTML Form code (Payment Extra Info)
        $q = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' . $method->payment_currency . '" ';
        $db = &JFactory::getDBO();
        $db->setQuery($q);
        $currency_code_3 = $db->loadResult();
        $paymentCurrency = CurrencyDisplay::getInstance($method->payment_currency);
        $totalInPaymentCurrency = round($paymentCurrency->convertCurrencyTo($method->payment_currency, $order['details']['BT']->order_total, false), 2);
        $cd = CurrencyDisplay::getInstance($cart->pricesCurrency);

        $address = ((isset($order['details']['ST'])) ? $order['details']['ST'] : $order['details']['BT']);

        $this->_virtuemart_paymentmethod_id = $order['details']['BT']->virtuemart_paymentmethod_id;

        $dbValues['order_number'] = $order['details']['BT']->order_number;
        $dbValues['payment_name'] = $this->renderPluginName($method);
        $dbValues['virtuemart_paymentmethod_id'] = $this->_virtuemart_paymentmethod_id;
        $dbValues['cost_per_transaction'] = $method->cost_per_transaction;
        $dbValues['cost_percent_total'] = $method->cost_percent_total;
        $dbValues['payment_currency'] = $currency_code_3;
        $dbValues['payment_order_total'] = $totalInPaymentCurrency;
        $dbValues['tax_id'] = $method->tax_id;
        $dbValues['multisafepay_order_id'] = $cart->virtuemart_order_id;
        $dbValues['multisafepay_status'] = "NEW";
        $this->storePSPluginInternalData($dbValues);

        $amount = $totalInPaymentCurrency * 100;
        $returnURL = JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=multisafepayresponse&task=result&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id);
        $Nurl = JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=multisafepayresponse&task=notify&mode=notify&type=initial&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id);

        $items = '<ul>';
        foreach ($order['items'] as $k) {
            $items .= '<li>' . $k->product_quantity . ' x ' . $k->order_item_name . '</li>';
        }
        $items .= '</ul>';

        $lang = & JFactory::getLanguage();

        $locale = $lang->getTag();
        $locale = str_replace('-', '_', $locale);
        $msp = new MultiSafepay();
        $msp->test = $method->sandbox == 1 ? true : false;
        $msp->merchant['account_id'] = $method->multisafepay_account_id;
        $msp->merchant['site_id'] = $method->multisafepay_site_id;
        $msp->merchant['site_code'] = $method->multisafepay_secure_code;

        if (VM_VERSION < 3) {
            $msp->merchant['notification_url'] = $Nurl;
            $msp->merchant['cancel_url'] = JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginUserPaymentCancel&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id);
            $msp->merchant['redirect_url'] = $returnURL;
        } else {
            $msp->merchant['notification_url'] = JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginResponseReceived&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id . '&type=initial');
            $msp->merchant['cancel_url'] = JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginUserPaymentCancel&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id);
            $msp->merchant['redirect_url'] = JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginResponseReceived&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id . '&type=redirect');
        }
        $msp->customer['locale'] = $locale;
        $msp->customer['firstname'] = $order['details']['BT']->first_name;
        $msp->customer['lastname'] = $order['details']['BT']->last_name;
        $msp->customer['zipcode'] = $order['details']['BT']->zip;
        $msp->customer['city'] = $order['details']['BT']->city;
        $msp->customer['country'] = ShopFunctions::getCountryByID($address->virtuemart_country_id, 'country_2_code');
        $msp->customer['phone'] = $order['details']['BT']->phone_1;
        $msp->customer['email'] = $order['details']['BT']->email;
        $msp->parseCustomerAddress($order['details']['BT']->address_1);

        if ($msp->customer['housenumber'] == '') {
            $msp->customer['address1'] = $order['details']['BT']->address_1;
            $msp->customer['housenumber'] = $order['details']['BT']->address_2;
        }

        $msp->transaction['id'] = $order['details']['BT']->order_number; // generally the shop's order ID is used here
        $msp->transaction['currency'] = $currency_code_3;
        $msp->transaction['amount'] = $amount; //$totalInPaymentCurrency * 100; // cents
        $msp->transaction['description'] = 'Order #' . $msp->transaction['id'];
        $msp->transaction['items'] = $items;
        if ($method->multisafepay_gateway) {
            $msp->transaction['gateway'] = $method->multisafepay_gateway;
        }
        $msp->transaction['daysactive'] = $method->multisafepay_days_active;
        $msp->plugin_name = 'Virtuemart ' . VM_VERSION;
        $msp->version = '2.2.0';
        $msp->plugin['shop'] = 'Virtuemart';
        $msp->plugin['shop_version'] = VM_VERSION;
        $msp->plugin['plugin_version'] = '2.2.0';
        $msp->plugin['partner'] = '';
        $msp->plugin['shop_root_url'] = JURI::root();

        $issuer = $this->_getSelectedBank($order['details']['BT']->virtuemart_paymentmethod_id);

        if ($method->multisafepay_gateway == 'PAYAFTER' || $method->multisafepay_gateway == 'KLARNA') {
            $tax_array = array();

            $shipping_tax = $order['details']['BT']->order_shipment_tax;
            if ($shipping_tax != '0.00000' && $order['details']['BT']->order_shipment != '0.00') {
                $shipping_tax_percentage = round($shipping_tax / $order['details']['BT']->order_shipment, 2);

                if ($shipping_tax_percentage == 0) {
                    $shipping_tax_percentage = '0.00';
                }
            } else {
                $shipping_tax_percentage = '0.00';
            }

            if (!in_array($shipping_tax_percentage, $tax_array)) {
                $tax_array[] = $shipping_tax_percentage;
            }

            $shipping_price = $order['details']['BT']->order_shipment;
            $shipping_name = 'Shipping/Verzending';
            $c_item = new MspItem($shipping_name . " " . 'EUR', '', 1, $shipping_price, '', '');
            $msp->cart->AddItem($c_item);
            $c_item->SetMerchantItemId('shipping');
            $c_item->SetTaxTableSelector($shipping_tax_percentage);

            $payment_tax = $order['details']['BT']->order_payment_tax;

            if ($payment_tax != '0.00000' && $order['details']['BT']->order_payment != '0.00') {
                $payment_tax_percentage = round($payment_tax / $order['details']['BT']->order_payment, 2);
                if ($payment_tax_percentage == 0) {
                    $payment_tax_percentage = '0.00';
                }
            } else {
                $payment_tax_percentage = '0.00';
            }

            if (!in_array($payment_tax_percentage, $tax_array)) {
                $tax_array[] = $payment_tax_percentage;
            }

            $payment_price = $order['details']['BT']->order_payment;
            $payment_name = 'Payment Fee';
            $c_item = new MspItem($payment_name . " " . 'EUR', '', 1, $payment_price, '', '');
            $msp->cart->AddItem($c_item);
            $c_item->SetMerchantItemId('PaymentFee');
            $c_item->SetTaxTableSelector($payment_tax_percentage);

            foreach ($order['items'] as $item) {
                $product_tax = $item->product_tax;
                $product_tax_percentage = round($product_tax / $item->product_discountedPriceWithoutTax, 2);

                if ($product_tax_percentage == 0) {
                    $product_tax_percentage = '0.00';
                }

                if (!in_array($product_tax_percentage, $tax_array)) {
                    $tax_array[] = $product_tax_percentage;
                }

                $product_price = $item->product_discountedPriceWithoutTax;
                $product_name = $item->order_item_name;
                $c_item = new MspItem($product_name . " " . 'EUR', '', $item->product_quantity, $product_price, '', '');
                $msp->cart->AddItem($c_item);
                $c_item->SetMerchantItemId($item->virtuemart_product_id);
                $c_item->SetTaxTableSelector($product_tax_percentage);
            }

            if ($order['details']['BT']->coupon_discount != '0.00') {
                if (!in_array('0.00', $tax_array)) {
                    $tax_array[] = '0.00';
                }

                $coupon_price = $order['details']['BT']->coupon_discount;
                $coupon_name = 'Coupon';
                $c_item = new MspItem($coupon_name . " " . 'EUR', '', 1, $coupon_price, '', '');
                $msp->cart->AddItem($c_item);
                $c_item->SetMerchantItemId('Coupon');
                $c_item->SetTaxTableSelector('0.00');
            }

            //todo add discounts
            if (!empty($cart->pricesUnformatted['billDiscountAmount'])) {
                $c_item = new MspItem('Discount', '', '1', $cart->pricesUnformatted['billDiscountAmount'], 'KG', 0);
                $c_item->SetMerchantItemId('Discount');
                $c_item->SetTaxTableSelector('0.00');
                if (!in_array('0.00', $tax_array)) {
                    $tax_array[] = '0.00';
                }
                // $c_item->SetTaxTableSelector('BTW0');
                $msp->cart->AddItem($c_item);
            }

            foreach ($tax_array as $rule) {
                $table = new MspAlternateTaxTable();
                $table->name = $rule;
                $rule = new MspAlternateTaxRule($rule);
                $table->AddAlternateTaxRules($rule);
                $msp->cart->AddAlternateTaxTables($table);
            }

            $url = $msp->startCheckout();
        } elseif ($method->multisafepay_gateway == 'IDEAL' && !empty($issuer)) {
            $msp->extravars = $issuer;
            $url = $msp->startDirectXMLTransaction();
        } else {
            $url = $msp->startTransaction();
        }

        $url = htmlspecialchars_decode($url);

        if ($msp->error) {
            $html = "Error " . $msp->error_code . ": " . $msp->error;
            JRequest::setVar('html', $html);
            echo $html;
            die();
        }

        if (!class_exists('VirtueMartModelOrders'))
            require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
        if (!class_exists('VirtueMartCart'))
            require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');

        $modelOrder = VmModel::getModel('orders');

        $order['customer_notified'] = 1;
        $order['comments'] = '';
        $modelOrder->updateStatusForOneOrder($order['details']['BT']->virtuemart_order_id, $order, TRUE);

        $cart->_confirmDone = false;
        $cart->_dataValidated = false;
        $cart->setCartIntoSession();
        $allDone = & JFactory::getApplication();
        $allDone->redirect($url);
        die();
    }

    function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId)
    {
        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }
        $this->getPaymentCurrency($method);
        $paymentCurrencyId = $method->payment_currency;
    }

    /* Function to handle all responses */

    public function plgVmOnPaymentResponseReceived(&$html)
    {
        if (!class_exists('VirtueMartModelOrders'))
            require_once( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );

        $virtuemart_paymentmethod_id = JRequest::getInt('pm', 0);
        $vendorId = 0;
        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        if (isset($_GET['type'])) {
            if ($_GET['type'] == 'feed') {
                $this->processFeed();
                exit;
            } elseif ($_GET['type'] == 'initial') {
                
            }
        }

        $msp = new MultiSafepay();
        $order_number = $_GET['transactionid'];
        $modelOrder = new VirtueMartModelOrders();
        $order_id = $modelOrder->getOrderIdByOrderNumber($order_number);
        $order_object = $modelOrder->getOrder($order_id);

        $msp->test = $method->sandbox == 1 ? true : false;
        $msp->merchant['account_id'] = $method->multisafepay_account_id;
        $msp->merchant['site_id'] = $method->multisafepay_site_id;
        $msp->merchant['site_code'] = $method->multisafepay_secure_code;
        $msp->transaction['id'] = $order_number;
        $status = $msp->getStatus();
        $details = $msp->details;

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
            case "shipped":
                JRequest::setVar('multisafepay_msg', Jtext::_('VMPAYMENT_MULTISAFEPAY_MSG_SHIPPED'));
                $html = $this->_getPaymentResponseHtml($details, $this->renderPluginName($method));
                break;
        }
        $order = array();

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
            case "shipped":     // expired
                $order['order_status'] = $method->status_shipped;
                break;
        }

        if ($order['order_status'] != $order_object['details']['BT']->order_status AND $order_object['details']['BT']->order_status != 'S') {//$current_status != $status  && 
            $order['virtuemart_order_id'] = $order_id;
            $order['comments'] = '';
            if ($order['order_status'] != $method->status_canceled) {
                $order['customer_notified'] = 1; //validate this one, can we trigger the notify customer for the initial pending status? 
            } else {
                $order['customer_notified'] = 0;
            }
            $modelOrder->updateStatusForOneOrder($order_id, $order, true);
        }
        if ($status != 'cancelled') {
            $this->emptyCart(null);
        }


        if (isset($_GET['type'])) {
            if ($_GET['type'] == 'initial') {
                $url = JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginResponseReceived&on=' . $order_object['details']['BT']->order_number . '&pm=' . $order_object['details']['BT']->virtuemart_paymentmethod_id . '&transactionid=' . $_GET['transactionid'] . '&type=redirect');
                echo '<a href="' . $url . '">' . JText::_('MULTISAFEPAY_BACK_TO_STORE') . '</a>';
                exit;
            } elseif ($_GET['type'] == 'redirect') {
                return $html;
            } else {
                echo 'OK';
                exit;
            }
        } else {
            echo 'OK';
            exit;
        }
    }

    function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn)
    {

        if ($this->getPluginMethods($cart->vendorId) === 0) {
            if (empty($this->_name)) {
                $app = JFactory::getApplication();
                $app->enqueueMessage(vmText::_('COM_VIRTUEMART_CART_NO_' . strtoupper($this->_psType)));
                return false;
            } else {
                return false;
            }
        }
        $htmla = array();
        $html = '';
        vmdebug('methods', $this->methods);
        VmConfig::loadJLang('com_virtuemart');
        $currency = CurrencyDisplay::getInstance();
        foreach ($this->methods as $method) {

            if ($method->multisafepay_gateway == 'IDEAL') {

                if ($this->checkConditions($cart, $method, $cart->cartPrices)) {
                    $methodSalesPrice = $this->calculateSalesPrice($cart, $method, $cart->cartPrices);

                    $msp = new MultiSafepay();
                    $msp->test = $method->sandbox == 1 ? true : false;
                    $msp->merchant['account_id'] = $method->multisafepay_account_id;
                    $msp->merchant['site_id'] = $method->multisafepay_site_id;
                    $msp->merchant['site_code'] = $method->multisafepay_secure_code;
                    $relatedBanks = $msp->getIdealIssuers();

                    $selected_bank = self::_getSelectedBank($method->virtuemart_paymentmethod_id);

                    $relatedBanksDropDown = $this->getRelatedBanksDropDown($relatedBanks, $method->virtuemart_paymentmethod_id, $selected_bank);
                    $logo = $this->displayLogos($method->payment_logos);
                    $payment_cost = '';
                    if ($methodSalesPrice) {
                        $payment_cost = $currency->priceDisplay($methodSalesPrice);
                    }
                    if ($selected == $method->virtuemart_paymentmethod_id) {
                        $checked = 'checked="checked"';
                    } else {
                        $checked = '';
                    }
                    $html = $this->renderByLayout('display_payment', array(
                        'plugin' => $method,
                        'checked' => $checked,
                        'payment_logo' => $logo,
                        'payment_cost' => $payment_cost,
                        'relatedBanks' => $relatedBanksDropDown
                    ));

                    $htmla[] = $html;
                }
            } else {
                if ($this->checkConditions($cart, $method, $cart->cartPrices)) {
                    $methodSalesPrice = $this->calculateSalesPrice($cart, $method, $cart->cartPrices);
                    $logo = $this->displayLogos($method->payment_logos);
                    $payment_cost = '';
                    if ($methodSalesPrice) {
                        $payment_cost = $currency->priceDisplay($methodSalesPrice);
                    }
                    if ($selected == $method->virtuemart_paymentmethod_id) {
                        $checked = 'checked="checked"';
                    } else {
                        $checked = '';
                    }
                    $html = $this->renderByLayout('display_payment_no_html', array(
                        'plugin' => $method,
                        'checked' => $checked,
                        'payment_logo' => $logo,
                        'payment_cost' => $payment_cost,
                    ));

                    $htmla[] = $html;
                }
            }
        }
        if (!empty($htmla)) {
            $htmlIn[] = $htmla;
        }

        return true;
    }

    private function getRelatedBanksDropDown($relatedBanks, $paymentmethod_id, $selected_bank)
    {
        //vmdebug('getRelatedBanks', $relatedBanks);
        if (!($method = $this->getVmPluginMethod($paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }

        $sandbox = $method->sandbox == 1 ? true : false;

        $attrs = '';
        $idA = $id = 'multisafepay_ideal_bank_selected_' . $paymentmethod_id;
        $listOptions[] = array('value' => '', 'text' => vmText::_('VMPAYMENT_MULTISAFEPAY_IDEAL_PLEASE_SELECT_BANK'));


        if ($sandbox) {
            foreach ($relatedBanks['issuers'] as $key => $relatedBank) {
                $listOptions[] = JHTML::_('select.option', $relatedBank['code']['VALUE'], $relatedBank['description']['VALUE']);
            }
        } else {
            foreach ($relatedBanks['issuers']['issuer'] as $key => $relatedBank) {
                $listOptions[] = JHTML::_('select.option', $relatedBank['code']['VALUE'], $relatedBank['description']['VALUE']);
            }
        }
        return JHTML::_('select.genericlist', $listOptions, $idA, $attrs, 'value', 'text', $selected_bank);
    }

    private function _getSelectedBank($paymentmethod_id)
    {
        $session_params = self::_getMultiSafepayIdealFromSession();
        $var = 'multisafepay_ideal_bank_selected_' . $paymentmethod_id;
        if (!isset($session_params->$var)) {
            return NULL;
        }
        return $session_params->$var;
    }

    private static function _clearMultiSafepayIdealSession()
    {
        $session = JFactory::getSession();
        $session->clear('MultiSafepayIdeal', 'vm');
    }

    private static function _setMultiSafepayIdealIntoSession($data)
    {
        $session = JFactory::getSession();
        $session->set('MultiSafepayIdeal', json_encode($data), 'vm');
    }

    private static function _getMultiSafepayIdealFromSession()
    {
        $session = JFactory::getSession();
        $data = $session->get('MultiSafepayIdeal', 0, 'vm');

        if (empty($data)) {
            return NULL;
        }
        return json_decode($data);
    }

    function _getSelectedBankCode($paymentmethod_id)
    {
        $selected_bank = self::_getSelectedBank($paymentmethod_id);
        $selected_bank_decoded = $selected_bank;
        return $selected_bank_decoded->code;
    }

    protected function renderPluginName($method, $where = 'checkout')
    {
        $display_logos = "";
        $session_params = self::_getMultiSafepayIdealFromSession();
        if (empty($session_params)) {
            $payment_param = self::getEmptyPaymentParams($method->virtuemart_paymentmethod_id);
        } else {
            foreach ($session_params as $key => $session_param) {
                $payment_param[$key] = json_decode($session_param);
            }
        }

        $logos = $method->payment_logos;
        if (!empty($logos)) {
            $display_logos = $this->displayLogos($logos) . ' ';
        }
        $payment_name = $method->payment_name;
        $var = 'multisafepay_ideal_bank_selected_' . $method->virtuemart_paymentmethod_id;
        $bank_name = isset($session_params->$var) ? $session_params->$var : "";
        vmdebug('renderPluginName', $payment_param);
        $html = $this->renderByLayout('render_pluginname', array(
            'logo' => $display_logos,
            'payment_name' => $payment_name,
            'bank_name' => $bank_name,
            'payment_description' => $method->payment_desc,
        ));
        return $html;
    }

    private static function getEmptyPaymentParams($paymentmethod_id)
    {
        $payment_params['multisafepay_ideal_bank_selected_' . $paymentmethod_id] = "";
        return $payment_params;
    }

    public function processFeed()
    {
        echo 'process feed';
    }

    function plgVmOnUserPaymentCancel()
    {
        if (!class_exists('VirtueMartModelOrders'))
            require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );

        $order_number = $_GET['transactionid'];
        $virtuemart_paymentmethod_id = JRequest::getInt('pm', '');
        if (empty($order_number) or empty($virtuemart_paymentmethod_id) or ! $this->selectedThisByMethodId($virtuemart_paymentmethod_id)) {

            return null;
        }
        if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number))) {
            return null;
        }

        VmInfo(Jtext::_('VMPAYMENT_MULTISAFEPAY_STATUS_CANCELED_DESC'));
        $session = JFactory::getSession();
        $return_context = $session->getId();
        $this->handlePaymentUserCancel($virtuemart_order_id);
        return true;
    }

    function plgVmOnPaymentNotification()
    {
        
    }

    /**
     * Display stored payment data for an order
     * @see components/com_virtuemart/helpers/vmPSPlugin::plgVmOnShowOrderBEPayment()
     */
    function plgVmOnShowOrderBEPayment($virtuemart_order_id, $payment_method_id)
    {
        if (!$this->selectedThisByMethodId($payment_method_id)) {
            return null; // Another method was selected, do nothing
        }

        $db = JFactory::getDBO();
        $q = 'SELECT * FROM `' . $this->_tablename . '` '
                . 'WHERE `virtuemart_order_id` = ' . $virtuemart_order_id;
        $db->setQuery($q);

        if (!($paymentTable = $db->loadObject())) {
            return '';
        }
        $this->getPaymentCurrency($paymentTable);
        $q = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' . $paymentTable->payment_currency . '" ';
        $db = JFactory::getDBO();
        $db->setQuery($q);
        $currency_code_3 = $db->loadResult();
        $html = '<table class="adminlist">' . "\n";
        $html .=$this->getHtmlHeaderBE();
        $html .= $this->getHtmlRowBE('MULTISAFEPAY_PAYMENT_NAME', $paymentTable->payment_name);
        $html .= $this->getHtmlRowBE('MULTISAFEPAY_PAYMENT_TOTAL_CURRENCY', $paymentTable->payment_order_total . ' ' . $currency_code_3);

        $html .= '</table>' . "\n";
        return $html;
    }

    function _getPaymentResponseHtml($data, $payment_name)
    {
        $html = '<table cellspacing="0" cellpadding="0" class="multisafepay-table">' . "\n";
        $html .= $this->getHtmlRow('MULTISAFEPAY_PAYMENT_NAME', $payment_name);
        $html .= $this->getHtmlRow('MULTISAFEPAY_STATUS', $data['ewallet']['status']);
        $html .= $this->getHtmlRow('MULTISAFEPAY_PAYMENT_TRANSACTIONID', $data['transaction']['id']);
        $html .= '</table>' . "\n";
        return $html;
    }

    function getCosts(VirtueMartCart $cart, $method, $cart_prices)
    {
        if (preg_match('/%$/', $method->cost_percent_total)) {
            $cost_percent_total = substr($method->cost_percent_total, 0, -1);
        } else {
            $cost_percent_total = $method->cost_percent_total;
        }
        return ($method->cost_per_transaction + ($cart_prices['salesPrice'] * $cost_percent_total * 0.01));
    }

    /**
     * Check if the payment conditions are fulfilled for this payment method
     * @author: Valerie Isaksen
     *
     * @param $cart_prices: cart prices
     * @param $payment
     * @return true: if the conditions are fulfilled, false otherwise
     *
     */
    protected function checkConditions($cart, $method, $cart_prices)
    {
        if ($method->multisafepay_ip_validation) {
            $ip = explode(';', $method->multisafepay_ip_address);

            if (!in_array($_SERVER["REMOTE_ADDR"], $ip)) {
                $test = false;
            } else {
                $test = true;
            }
        } else {
            $test = true;
        }


        if (method_exists($cart, 'getST')) {
            $address = $cart->getST();
        } else {
            $address = (($cart->ST == 0 || $cart->STSameAsBT == 1) ? $cart->BT : $cart->ST);
        }

        $amount = $cart_prices['salesPrice'];
        $amount_cond = ($amount >= $method->min_amount AND $amount <= $method->max_amount
                OR ( $method->min_amount <= $amount AND ( $method->max_amount == 0) ));

        $countries = array();
        if (!empty($method->countries)) {
            if (!is_array($method->countries)) {
                $countries[0] = $method->countries;
            } else {
                $countries = $method->countries;
            }
        }
        // probably did not gave his BT:ST address
        if (!is_array($address)) {
            $address = array();
            $address['virtuemart_country_id'] = 0;
        }

        if (!isset($address['virtuemart_country_id']))
            $address['virtuemart_country_id'] = 0;
        if (in_array($address['virtuemart_country_id'], $countries) || count($countries) == 0) {
            if ($amount_cond && $test) {
                return true;
            }
        }
        return false;
    }

    /**
     * We must reimplement this triggers for joomla 1.7
     */

    /**
     * Create the table for this plugin if it does not yet exist.
     * This functions checks if the called plugin is active one.
     * When yes it is calling the standard method to create the tables
     * @author Val�rie Isaksen
     *
     */
    function plgVmOnStoreInstallPaymentPluginTable($jplugin_id)
    {
        return $this->onStoreInstallPluginTable($jplugin_id);
    }

    /**
     * This event is fired after the payment method has been selected. It can be used to store
     * additional payment info in the cart.
     *
     * @author Max Milbers
     * @author Val�rie isaksen
     *
     * @param VirtueMartCart $cart: the actual cart
     * @return null if the payment was not selected, true if the data is valid, error message if the data is not vlaid
     *
     */
    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart, &$msg)
    {
        if (!$this->selectedThisByMethodId($cart->virtuemart_paymentmethod_id)) {
            return NULL; // Another method was selected, do nothing
        }

        if (!($method = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }


        if ($method->multisafepay_gateway == 'IDEAL') {
            $payment_params['multisafepay_ideal_bank_selected_' . $cart->virtuemart_paymentmethod_id] = vRequest::getVar('multisafepay_ideal_bank_selected_' . $cart->virtuemart_paymentmethod_id);
            if (empty($payment_params['multisafepay_ideal_bank_selected_' . $cart->virtuemart_paymentmethod_id])) {
                vmInfo('VMPAYMENT_MULTISAFEPAY_IDEAL_PLEASE_SELECT_BANK');
                return false;
            }
            self::_setMultiSafepayIdealIntoSession($payment_params);
        }
        return true;
    }

    /*
     * plgVmonSelectedCalculatePricePayment
     * Calculate the price (value, tax_id) of the selected method
     * It is called by the calculator
     * This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.
     * @author Valerie Isaksen
     * @cart: VirtueMartCart the current cart
     * @cart_prices: array the new cart prices
     * @return null if the method was not selected, false if the shiiping rate is not valid any more, true otherwise
     *
     *
     */

    public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name)
    {
        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    /**
     * plgVmOnCheckAutomaticSelectedPayment
     * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
     * The plugin must check first if it is the correct type
     * @author Valerie Isaksen
     * @param VirtueMartCart cart: the cart object
     * @return null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
     *
     */
    function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array())
    {
        return $this->onCheckAutomaticSelected($cart, $cart_prices);
    }

    /**
     * This method is fired when showing the order details in the frontend.
     * It displays the method-specific data.
     *
     * @param integer $order_id The order ID
     * @return mixed Null for methods that aren't active, text (HTML) otherwise
     * @author Max Milbers
     * @author Valerie Isaksen
     */
    public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name)
    {
        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }

    /**
     * This event is fired during the checkout process. It can be used to validate the
     * method data as entered by the user.
     *
     * @return boolean True when the data was valid, false otherwise. If the plugin is not activated, it should return null.
     * @author Max Milbers
     */
    function plgVmOnCheckoutCheckDataPayment(VirtueMartCart $cart)
    {
        if (!$this->selectedThisByMethodId($cart->virtuemart_paymentmethod_id)) {
            return NULL; // Another method was selected, do nothing
        }

        if (!($method = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }

        if ($method->multisafepay_gateway == 'IDEAL') {
            $payment_params['multisafepay_ideal_bank_selected_' . $cart->virtuemart_paymentmethod_id] = vRequest::getVar('multisafepay_ideal_bank_selected_' . $cart->virtuemart_paymentmethod_id);

            if (empty($payment_params['multisafepay_ideal_bank_selected_' . $cart->virtuemart_paymentmethod_id])) {
                $payment_params['multisafepay_ideal_bank_selected_' . $cart->virtuemart_paymentmethod_id] = $this->_getSelectedBank($cart->virtuemart_paymentmethod_id);
            }

            if (empty($payment_params['multisafepay_ideal_bank_selected_' . $cart->virtuemart_paymentmethod_id])) {
                vmInfo('VMPAYMENT_MULTISAFEPAY_IDEAL_PLEASE_SELECT_BANK');
                return false;
            }
            self::_setMultiSafepayIdealIntoSession($payment_params);
        }
        return true;
    }

    private function _validate_multisafepayideal_data($payment_params, $paymentmethod_id, &$error_msg)
    {
        $errors = array();
        if (empty($payment_params['multisafepay_ideal_bank_selected_' . $paymentmethod_id])) {
            $errors[] = vmText::_('VMPAYMENT_MULTISAFEPAY_IDEAL_PLEASE_SELECT_BANK');
        }

        if (!empty($errors)) {
            $error_msg .= "</br />";
            foreach ($errors as $error) {
                $error_msg .= " -" . $error . "</br />";
            }
            return FALSE;
        }
        return TRUE;
    }

    /**
     * This method is fired when showing when priting an Order
     * It displays the the payment method-specific data.
     *
     * @param integer $_virtuemart_order_id The order ID
     * @param integer $method_id  method used for this order
     * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
     * @author Valerie Isaksen
     */
    function plgVmonShowOrderPrintPayment($order_number, $method_id)
    {
        return $this->onShowOrderPrint($order_number, $method_id);
    }

    /**
     * Save updated order data to the method specific table
     *
     * @param array $_formData Form data
     * @return mixed, True on success, false on failures (the rest of the save-process will be
     * skipped!), or null when this method is not actived.
     * @author Oscar van Eijk

      public function plgVmOnUpdateOrderPayment(  $_formData) {
      return null;
      }
     */
    /**
     * Save updated orderline data to the method specific table
     *
     * @param array $_formData Form data
     * @return mixed, True on success, false on failures (the rest of the save-process will be
     * skipped!), or null when this method is not actived.
     * @author Oscar van Eijk

      public function plgVmOnUpdateOrderLine(  $_formData) {
      return null;
      }
     */
    /**
     * plgVmOnEditOrderLineBE
     * This method is fired when editing the order line details in the backend.
     * It can be used to add line specific package codes
     *
     * @param integer $_orderId The order ID
     * @param integer $_lineId
     * @return mixed Null for method that aren't active, text (HTML) otherwise
     * @author Oscar van Eijk

      public function plgVmOnEditOrderLineBE(  $_orderId, $_lineId) {
      return null;
      }
     */

    /**
     * This method is fired when showing the order details in the frontend, for every orderline.
     * It can be used to display line specific package codes, e.g. with a link to external tracking and
     * tracing systems
     *
     * @param integer $_orderId The order ID
     * @param integer $_lineId
     * @return mixed Null for method that aren't active, text (HTML) otherwise
     * @author Oscar van Eijk

      public function plgVmOnShowOrderLineFE(  $_orderId, $_lineId) {
      return null;
      }
     */
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