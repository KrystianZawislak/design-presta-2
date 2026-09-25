{**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 *}
{extends file='parent:_partials/header.tpl'}

{block name='header_nav'}
  <nav class="header-nav" aria-label="{l s='Header' d='Shop.Theme.Global'}">
    <div class="container">
      <div class="header-nav-inner hidden-sm-down">
        <div id="_desktop_logo">
          {if $shop.logo_details}
            {if $page.page_name == 'index'}
              <h1>
                {renderLogo}
              </h1>
            {else}
              {renderLogo}
            {/if}
          {/if}
        </div>
        <div class="header-nav-actions">
          <div class="header-nav-actions-1">
            {hook h='displayNav1' mod='ps_contactinfo'}
            <a class="header-nav-store-locator" href="{$urls.pages.stores|escape:'html':'UTF-8'}">
              {include file="`$smarty.const._PS_THEME_DIR_`assets/img/icons/pin.svg"}
              {l s='Find a store' d='Shop.Theme.Global'}
            </a>
          </div>
          <div class="header-nav-actions-2">
            {if $customer.is_logged}
              <a class="header-nav-wishlist" href="{$link->getModuleLink('blockwishlist', 'lists')|escape:'html':'UTF-8'}" rel="nofollow">
                {include file="`$smarty.const._PS_THEME_DIR_`assets/img/icons/heart.svg"}
                <span class="sr-only">{l s='Wishlist' d='Shop.Theme.Global'}</span>
              </a>
            {/if}
            {hook h='displayNav2' mod='ps_customersignin'}
            {hook h='displayNav2' mod='ps_shoppingcart'}
          </div>
        </div>
        {capture name='designMenuModes'}{hook h='displayDesignMenuModes'}{/capture}
        {if $smarty.capture.designMenuModes|trim}
          <div class="header-nav-actions-3">
            {$smarty.capture.designMenuModes nofilter}
          </div>
        {/if}
      </div>
      <div class="hidden-md-up text-sm-center mobile">
        <div class="float-xs-left" id="menu-icon">
          <i class="material-icons d-inline">&#xE5D2;</i>
        </div>
        <div class="float-xs-right" id="_mobile_cart"></div>
        <div class="float-xs-right" id="_mobile_user_info"></div>
        <div class="top-logo" id="_mobile_logo"></div>
        <div class="clearfix"></div>
      </div>
    </div>
  </nav>
{/block}

{block name='header_top'}
  <div class="header-top">
    <div class="container">
       <div class="row">
        <div class="header-top-right col-md-12 col-sm-12 position-static">
          {hook h='displayDesignMenuLinks'}
          {hook h='displayTop'}
        </div>
      </div>
      <div id="mobile_top_menu_wrapper" class="row hidden-md-up" style="display:none;">
        <div class="js-top-menu mobile" id="_mobile_top_menu"></div>
        <div class="js-top-menu-bottom">
          <div id="_mobile_currency_selector"></div>
          <div id="_mobile_language_selector"></div>
          <div id="_mobile_contact_link"></div>
        </div>
      </div>
    </div>
  </div>
{/block}
