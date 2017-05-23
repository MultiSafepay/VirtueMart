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
defined('_JEXEC') or die();
?>
<div class="post_payment_payment_name" style="width: 100%">
  <span class=post_payment_payment_name_title"><?php echo vmText::_('VMPAYMENT_MSP_PAYMENT_INFO'); ?> </span>
  <?php echo $viewData["payment_name"]; ?>
</div>

<div class="post_payment_order_number" style="width: 100%">
  <span class=post_payment_order_number_title"><?php echo vmText::_('COM_MSP_ORDER_NUMBER'); ?> </span>
  <?php echo $viewData["order_number"]; ?>
</div>

<div class="post_payment_order_total" style="width: 100%">
  <span class="post_payment_order_total_title"><?php echo vmText::_('COM_MSP_ORDER_PRINT_TOTAL'); ?> </span>
  <?php echo $viewData['displayTotalInPaymentCurrency']; ?>
</div>
<a class="vm-button-correct" href="<?php echo JRoute::_('index.php?option=com_virtuemart&view=orders&layout=details&order_number=' . $viewData["order_number"] . '&order_pass=' . $viewData["order_pass"], false) ?>"><?php echo vmText::_('COM_VIRTUEMART_ORDER_VIEW_ORDER'); ?></a>






