<nav class="designmenu-links" aria-label="{l s='Main menu' d='Modules.Designmenu.Shop'}">
  <ul class="designmenu-links__list">
    {foreach $designmenu_links as $designmenu_link}
      <li class="designmenu-links__item">
        <a class="designmenu-links__link" href="{$designmenu_link.url|escape:'html':'UTF-8'}">{$designmenu_link.label|escape:'html':'UTF-8'}</a>
      </li>
    {/foreach}
  </ul>
</nav>
