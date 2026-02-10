{*
 * Click Tracker Module - Admin Tabs Template
 *
 * @author    Ettore Stani
 * @copyright 2024 Ettore Stani
 * @license   AFL-3.0
 *}

<div class="clicktracker-tabs-wrapper" style="margin-bottom: 20px;">
    <ul class="nav nav-tabs">
        {foreach from=$tabs key=tabKey item=tab}
            <li class="{if $activeTab == $tabKey}active{/if}">
                <a href="{$moduleLink nofilter}&section={$tabKey|escape:'htmlall':'UTF-8'}">
                    <i class="{$tab.icon|escape:'htmlall':'UTF-8'}"></i>
                    {$tab.title|escape:'htmlall':'UTF-8'}
                </a>
            </li>
        {/foreach}
    </ul>
</div>
