{*
 * Click Tracker Module - Logs Template
 *
 * @author    Ettore Stani
 * @copyright 2024 Ettore Stani
 * @license   AFL-3.0
 *}

<div class="panel clicktracker-logs-panel">
    <div class="panel-heading">
        <i class="icon-list"></i> {l s='Click Logs' mod='clicktracker'}
        <span class="badge">{$total|intval}</span>

        <div class="clicktracker-panel-heading-action">
            <form method="post" action="{$moduleLink|escape:'htmlall':'UTF-8'}" class="form-inline pull-right">
                <input type="hidden" name="filter_context" value="{$filters.context_type|escape:'htmlall':'UTF-8'}">
                <input type="hidden" name="filter_element" value="{$filters.element_type|escape:'htmlall':'UTF-8'}">
                <input type="hidden" name="filter_date_from" value="{$filters.date_from|escape:'htmlall':'UTF-8'}">
                <input type="hidden" name="filter_date_to" value="{$filters.date_to|escape:'htmlall':'UTF-8'}">
                <input type="hidden" name="filter_search" value="{$filters.search|escape:'htmlall':'UTF-8'}">
                <button type="submit" name="exportCsv" class="btn btn-default">
                    <i class="icon-download"></i> {l s='Export CSV' mod='clicktracker'}
                </button>
            </form>
        </div>
    </div>

    {* Filters *}
    <div class="clicktracker-filters well">
        <form method="get" action="{$moduleLink|escape:'htmlall':'UTF-8'}" class="form-inline">
            <input type="hidden" name="controller" value="AdminModules">
            <input type="hidden" name="configure" value="clicktracker">
            <input type="hidden" name="section" value="logs">
            <input type="hidden" name="token" value="{$token|escape:'htmlall':'UTF-8'}">

            <div class="row">
                <div class="col-md-2">
                    <div class="form-group">
                        <label>{l s='Context' mod='clicktracker'}</label>
                        <select name="filter_context" class="form-control">
                            <option value="">{l s='All' mod='clicktracker'}</option>
                            {foreach from=$context_types key=key item=label}
                                <option value="{$key|escape:'htmlall':'UTF-8'}"{if $filters.context_type == $key} selected{/if}>{$label|escape:'htmlall':'UTF-8'}</option>
                            {/foreach}
                        </select>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="form-group">
                        <label>{l s='Element Type' mod='clicktracker'}</label>
                        <select name="filter_element" class="form-control">
                            <option value="">{l s='All' mod='clicktracker'}</option>
                            {foreach from=$element_types key=key item=label}
                                <option value="{$key|escape:'htmlall':'UTF-8'}"{if $filters.element_type == $key} selected{/if}>{$label|escape:'htmlall':'UTF-8'}</option>
                            {/foreach}
                        </select>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="form-group">
                        <label>{l s='From' mod='clicktracker'}</label>
                        <input type="date" name="filter_date_from" class="form-control" value="{$filters.date_from|escape:'htmlall':'UTF-8'}">
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="form-group">
                        <label>{l s='To' mod='clicktracker'}</label>
                        <input type="date" name="filter_date_to" class="form-control" value="{$filters.date_to|escape:'htmlall':'UTF-8'}">
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="form-group">
                        <label>{l s='Search' mod='clicktracker'}</label>
                        <input type="text" name="filter_search" class="form-control" placeholder="{l s='Product, URL...' mod='clicktracker'}" value="{$filters.search|escape:'htmlall':'UTF-8'}">
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class="icon-search"></i> {l s='Filter' mod='clicktracker'}
                            </button>
                            <a href="{$moduleLink|escape:'htmlall':'UTF-8'}" class="btn btn-default">
                                <i class="icon-refresh"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    {* Bulk actions form *}
    <form method="post" action="{$moduleLink|escape:'htmlall':'UTF-8'}" id="logsForm">
        <input type="hidden" name="filter_context" value="{$filters.context_type|escape:'htmlall':'UTF-8'}">
        <input type="hidden" name="filter_element" value="{$filters.element_type|escape:'htmlall':'UTF-8'}">
        <input type="hidden" name="filter_date_from" value="{$filters.date_from|escape:'htmlall':'UTF-8'}">
        <input type="hidden" name="filter_date_to" value="{$filters.date_to|escape:'htmlall':'UTF-8'}">
        <input type="hidden" name="filter_search" value="{$filters.search|escape:'htmlall':'UTF-8'}">

        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th class="text-center" width="30">
                            <input type="checkbox" id="selectAll" class="noborder">
                        </th>
                        <th width="50">{l s='ID' mod='clicktracker'}</th>
                        <th width="80">{l s='Context' mod='clicktracker'}</th>
                        <th width="100">{l s='Element' mod='clicktracker'}</th>
                        <th>{l s='Product/Page' mod='clicktracker'}</th>
                        <th width="120">{l s='Class' mod='clicktracker'}</th>
                        <th width="150">{l s='Date' mod='clicktracker'}</th>
                        <th width="100" class="text-center">{l s='Actions' mod='clicktracker'}</th>
                    </tr>
                </thead>
                <tbody>
                    {if $logs && count($logs) > 0}
                        {foreach from=$logs item=log}
                            <tr>
                                <td class="text-center">
                                    <input type="checkbox" name="logBox[]" value="{$log.id_clicktracker_log|intval}" class="noborder logCheckbox">
                                </td>
                                <td>{$log.id_clicktracker_log|intval}</td>
                                <td>
                                    <span class="label label-{if $log.context_type == 'product'}info{elseif $log.context_type == 'cms'}success{else}default{/if}">
                                        {if isset($context_types[$log.context_type])}{$context_types[$log.context_type]|escape:'htmlall':'UTF-8'}{else}{$log.context_type|escape:'htmlall':'UTF-8'}{/if}
                                    </span>
                                </td>
                                <td>
                                    <span class="clicktracker-element-type clicktracker-{$log.element_type|escape:'htmlall':'UTF-8'}">
                                        {if $log.element_type == 'whatsapp'}
                                            <i class="icon-comments"></i>
                                        {elseif $log.element_type == 'phone'}
                                            <i class="icon-phone"></i>
                                        {elseif $log.element_type == 'maps'}
                                            <i class="icon-map-marker"></i>
                                        {else}
                                            <i class="icon-link"></i>
                                        {/if}
                                        {if isset($element_types[$log.element_type])}{$element_types[$log.element_type]|escape:'htmlall':'UTF-8'}{else}{$log.element_type|escape:'htmlall':'UTF-8'}{/if}
                                    </span>
                                </td>
                                <td>
                                    {if $log.context_type == 'product' && $log.id_product}
                                        <strong>{$log.product_name|truncate:40:'...'|escape:'htmlall':'UTF-8'}</strong>
                                        <br><small class="text-muted">ID: {$log.id_product|intval}</small>
                                    {else}
                                        <span title="{$log.page_url|escape:'htmlall':'UTF-8'}">{$log.page_title|default:$log.page_url|truncate:50:'...'|escape:'htmlall':'UTF-8'}</span>
                                        {if $log.page_type}<br><small class="text-muted">{$log.page_type|escape:'htmlall':'UTF-8'}</small>{/if}
                                    {/if}
                                </td>
                                <td><code>{$log.clicked_class|escape:'htmlall':'UTF-8'}</code></td>
                                <td>{$log.date_add|escape:'htmlall':'UTF-8'}</td>
                                <td class="text-center">
                                    {if $log.has_safe_url}
                                        <a href="{$log.page_url|escape:'htmlall':'UTF-8'}" target="_blank" rel="noopener noreferrer" class="btn btn-default btn-xs" title="{l s='View Page' mod='clicktracker'}">
                                            <i class="icon-external-link"></i>
                                        </a>
                                    {/if}
                                    <form method="post" action="{$moduleLink|escape:'htmlall':'UTF-8'}" class="et-clicktracker-delete-form" style="display:inline;">
                                        <input type="hidden" name="id_log" value="{$log.id_clicktracker_log|intval}">
                                        <button type="submit" name="submitDeleteLog" class="btn btn-danger btn-xs" title="{l s='Delete' mod='clicktracker'}">
                                            <i class="icon-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        {/foreach}
                    {else}
                        <tr>
                            <td colspan="8" class="text-center">
                                <p class="text-muted">{l s='No click logs found.' mod='clicktracker'}</p>
                            </td>
                        </tr>
                    {/if}
                </tbody>
            </table>
        </div>

        {if $logs && count($logs) > 0}
            <div class="clicktracker-bulk-actions">
                <button type="submit" name="submitBulkDelete" class="btn btn-danger">
                    <i class="icon-trash"></i> {l s='Delete Selected' mod='clicktracker'}
                </button>
            </div>
        {/if}
    </form>

    {* Pagination *}
    {if $total_pages > 1}
        <div class="clicktracker-pagination text-center">
            <nav>
                <ul class="pagination">
                    {if $page > 1}
                        <li>
                            <a href="{$moduleLink|escape:'htmlall':'UTF-8'}&page={$page - 1}&{$filter_query|escape:'htmlall':'UTF-8'}">&laquo;</a>
                        </li>
                    {/if}

                    {for $i=1 to $total_pages}
                        {if $i == $page}
                            <li class="active"><span>{$i}</span></li>
                        {elseif $i <= 3 || $i > $total_pages - 3 || ($i >= $page - 2 && $i <= $page + 2)}
                            <li>
                                <a href="{$moduleLink|escape:'htmlall':'UTF-8'}&page={$i}&{$filter_query|escape:'htmlall':'UTF-8'}">{$i}</a>
                            </li>
                        {elseif $i == 4 || $i == $total_pages - 3}
                            <li><span>...</span></li>
                        {/if}
                    {/for}

                    {if $page < $total_pages}
                        <li>
                            <a href="{$moduleLink|escape:'htmlall':'UTF-8'}&page={$page + 1}&{$filter_query|escape:'htmlall':'UTF-8'}">&raquo;</a>
                        </li>
                    {/if}
                </ul>
            </nav>

            <p class="text-muted">
                {l s='Showing' mod='clicktracker'} {(($page - 1) * $per_page) + 1} - {min($page * $per_page, $total)} {l s='of' mod='clicktracker'} {$total|intval} {l s='entries' mod='clicktracker'}
            </p>
        </div>
    {/if}
</div>
