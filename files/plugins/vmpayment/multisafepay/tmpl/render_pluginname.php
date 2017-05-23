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
<span class="vmpayment">
  <?php
  if (!empty($viewData['logo'])) {
      ?>
      <span class="vmCartPaymentLogo" >
        <?php echo $viewData['logo'] ?>
      </span>
      <?php
  }
  ?>
  <span class="vmpayment_name"><?php echo $viewData['payment_name'] ?> </span>
  <?php
  if (!empty($viewData['payment_description'])) {
      ?>
      <span class="vmpayment_description"><?php echo $viewData['payment_description'] ?> </span>
      <?php
  }
  ?>


</span>



