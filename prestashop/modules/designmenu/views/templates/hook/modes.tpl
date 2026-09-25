<form class="designmenu-modes" action="{$designmenu_select_url|escape:'html':'UTF-8'}" method="post" aria-label="{l s='Shop section' d='Modules.Designmenu.Shop'}">
  <input type="hidden" name="back" value="{$designmenu_back|escape:'html':'UTF-8'}">
  <ul class="designmenu-modes__list">
    {foreach $designmenu_modes as $designmenu_mode}
      <li class="designmenu-modes__item">
        <button type="submit" class="designmenu-modes__button{if $designmenu_mode.active} designmenu-modes__button--active{/if}" name="id_designmenu_mode" value="{if $designmenu_mode.active}0{else}{$designmenu_mode.id|intval}{/if}" aria-pressed="{if $designmenu_mode.active}true{else}false{/if}">{$designmenu_mode.label|escape:'html':'UTF-8'}</button>
      </li>
    {/foreach}
  </ul>
</form>
