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
{function name="menu" nodes=[] depth=0}
    {if $nodes|count}
      <ul class="top-menu" {if $depth == 0}id="top-menu"{/if} data-depth="{$depth}">
        {foreach from=$nodes item=node}
            <li class="{$node.type}{if $node.current} current {/if}" id="{$node.page_identifier}">
              <a
                href="{$node.url}" data-depth="{$depth}"
                {if $node.open_in_new_window} target="_blank" {/if}
              >
                {$node.label}
              </a>
            </li>
        {/foreach}
      </ul>
    {/if}
{/function}

{function name="drawerRows" nodes=[]}
  <ul class="dp-menu__list">
    {foreach from=$nodes item=node}
      <li class="dp-menu__item">
        {if $node.children|count}
          <button type="button" class="dp-menu__row" data-dp-menu-open="dp-menu-view-{$node.page_identifier|regex_replace:'/[^A-Za-z0-9_-]/':'-'}">
            <span class="dp-menu__row-label">{$node.label}</span>
            {include file="`$smarty.const._PS_THEME_DIR_`assets/img/icons/chevron-right.svg"}
          </button>
        {else}
          <a class="dp-menu__row" href="{$node.url}"{if $node.open_in_new_window} target="_blank" rel="noopener"{/if}>
            <span class="dp-menu__row-label">{$node.label}</span>
            {include file="`$smarty.const._PS_THEME_DIR_`assets/img/icons/chevron-right.svg"}
          </a>
        {/if}
      </li>
    {/foreach}
  </ul>
{/function}

{function name="drawerViews" nodes=[] viewId='dp-menu-view-root' parentId='' title='' url=''}
  <div class="dp-menu__view" id="{$viewId}" data-dp-menu-view{if $parentId} data-dp-menu-parent="{$parentId}" hidden{/if}>
    {if $parentId}
      <button type="button" class="dp-menu__back" data-dp-menu-open="{$parentId}">
        {include file="`$smarty.const._PS_THEME_DIR_`assets/img/icons/chevron-left.svg"}
        <span>{l s='Back' d='Shop.Theme.Actions'}</span>
      </button>
      <p class="dp-menu__title">{$title}</p>
      <ul class="dp-menu__list">
        <li class="dp-menu__item">
          <a class="dp-menu__row dp-menu__row--all" href="{$url}">
            <span class="dp-menu__row-label">{l s='See all %category%' sprintf=['%category%' => $title] d='Shop.Theme.Catalog'}</span>
            {include file="`$smarty.const._PS_THEME_DIR_`assets/img/icons/chevron-right.svg"}
          </a>
        </li>
      </ul>
    {/if}
    {drawerRows nodes=$nodes}
  </div>
  {foreach from=$nodes item=node}
    {if $node.children|count}
      {drawerViews
        nodes=$node.children
        viewId="dp-menu-view-`$node.page_identifier|regex_replace:'/[^A-Za-z0-9_-]/':'-'`"
        parentId=$viewId
        title=$node.label
        url=$node.url}
    {/if}
  {/foreach}
{/function}

{assign var=menu_root value=$designmenu_menu|default:$menu}

<div class="menu js-top-menu position-static hidden-sm-down" id="_desktop_top_menu">
    {menu nodes=$menu_root.children}
    <div class="clearfix"></div>
</div>

<div class="dp-menu hidden-md-up" id="dp-menu" hidden>
  <div class="dp-menu__backdrop" data-dp-menu-close></div>
  <div class="dp-menu__panel" role="dialog" aria-modal="true" aria-label="{l s='Main menu' d='Shop.Theme.Global'}">
    <div class="dp-menu__head">
      <button type="button" class="dp-menu__close" data-dp-menu-close>
        {include file="`$smarty.const._PS_THEME_DIR_`assets/img/icons/close.svg"}
        <span class="sr-only">{l s='Close' d='Shop.Theme.Global'}</span>
      </button>
    </div>
    <nav class="dp-menu__views" aria-label="{l s='Main menu' d='Shop.Theme.Global'}">
      {drawerViews nodes=$menu_root.children}
    </nav>
    <div class="dp-menu__links">
      {if $customer.is_logged}
        <a class="dp-menu__link" href="{$urls.pages.my_account}" rel="nofollow">
          {include file="`$smarty.const._PS_THEME_DIR_`assets/img/icons/account.svg"}
          <span>{l s='Account' d='Shop.Theme.Customeraccount'}</span>
        </a>
      {else}
        <a class="dp-menu__link" href="{$urls.pages.authentication}?back={$urls.current_url|urlencode}" rel="nofollow">
          {include file="`$smarty.const._PS_THEME_DIR_`assets/img/icons/account.svg"}
          <span>{l s='Account' d='Shop.Theme.Customeraccount'}</span>
        </a>
      {/if}
      <a class="dp-menu__link" href="{$urls.pages.contact}">
        {include file="`$smarty.const._PS_THEME_DIR_`assets/img/icons/help.svg"}
        <span>{l s='Help' d='Shop.Theme.Global'}</span>
      </a>
      <a class="dp-menu__link" href="#blockEmailSubscription_displayFooterBefore">
        {include file="`$smarty.const._PS_THEME_DIR_`assets/img/icons/newsletter.svg"}
        <span>{l s='Newsletter' d='Shop.Theme.Global'}</span>
      </a>
    </div>
  </div>
</div>
