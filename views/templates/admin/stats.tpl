{*
 * Click Tracker Module - Statistics Template
 *
 * @author    Ettore Stani
 * @copyright 2024 Ettore Stani
 * @license   AFL-3.0
 *}

<div class="clicktracker-stats-wrapper">
    {* Date Range Filter *}
    <div class="panel">
        <div class="panel-heading">
            <i class="icon-calendar"></i> {l s='Date Range' mod='clicktracker'}
        </div>
        <div class="panel-body">
            <form method="get" action="{$moduleLink|escape:'htmlall':'UTF-8'}" class="form-inline">
                <input type="hidden" name="controller" value="AdminModules">
                <input type="hidden" name="configure" value="clicktracker">
                <input type="hidden" name="section" value="stats">
                <input type="hidden" name="token" value="{$admin_token|escape:'htmlall':'UTF-8'}">

                <div class="form-group">
                    <label>{l s='From' mod='clicktracker'}</label>
                    <input type="date" name="stats_date_from" class="form-control" value="{$date_from|escape:'htmlall':'UTF-8'}">
                </div>

                <div class="form-group">
                    <label>{l s='To' mod='clicktracker'}</label>
                    <input type="date" name="stats_date_to" class="form-control" value="{$date_to|escape:'htmlall':'UTF-8'}">
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="icon-search"></i> {l s='Apply' mod='clicktracker'}
                </button>

                <span class="help-block clicktracker-compare-note">
                    {l s='Comparison with the previous period:' mod='clicktracker'} {$prev_from|escape:'htmlall':'UTF-8'} &rarr; {$prev_to|escape:'htmlall':'UTF-8'}
                </span>
            </form>
        </div>
    </div>

    {* Overview Cards *}
    <div class="row clicktracker-cards">
        <div class="col-md-3">
            <div class="panel clicktracker-card clicktracker-card-total">
                <div class="panel-body text-center">
                    <div class="clicktracker-card-icon">
                        <i class="icon-mouse-pointer"></i>
                    </div>
                    <div class="clicktracker-card-value">{$period_compare.value|intval}</div>
                    <div class="clicktracker-card-label">{l s='Clicks in Period' mod='clicktracker'}</div>
                    <div class="clicktracker-card-delta">
                        {if isset($period_compare.pct)}
                            <span class="clicktracker-delta clicktracker-delta-{$period_compare.dir|escape:'htmlall':'UTF-8'}">{if $period_compare.dir == 'up'}&#9650;{elseif $period_compare.dir == 'down'}&#9660;{/if} {$period_compare.pct|intval}%</span>
                        {/if}
                        <small class="text-muted">{l s='previous period:' mod='clicktracker'} {$period_compare.previous|intval}</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="panel clicktracker-card clicktracker-card-month">
                <div class="panel-body text-center">
                    <div class="clicktracker-card-icon">
                        <i class="icon-calendar"></i>
                    </div>
                    <div class="clicktracker-card-value">{$month_compare.value|intval}</div>
                    <div class="clicktracker-card-label">{l s='This Month' mod='clicktracker'}</div>
                    <div class="clicktracker-card-delta">
                        {if isset($month_compare.pct)}
                            <span class="clicktracker-delta clicktracker-delta-{$month_compare.dir|escape:'htmlall':'UTF-8'}">{if $month_compare.dir == 'up'}&#9650;{elseif $month_compare.dir == 'down'}&#9660;{/if} {$month_compare.pct|intval}%</span>
                        {/if}
                        <small class="text-muted">{l s='same days last month:' mod='clicktracker'} {$month_compare.previous|intval}</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="panel clicktracker-card clicktracker-card-type">
                <div class="panel-body text-center">
                    <div class="clicktracker-card-icon">
                        <i class="icon-star"></i>
                    </div>
                    <div class="clicktracker-card-value">{$most_clicked_type|escape:'htmlall':'UTF-8'}</div>
                    <div class="clicktracker-card-label">{l s='Most Clicked Type' mod='clicktracker'}</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="panel clicktracker-card clicktracker-card-product">
                <div class="panel-body text-center">
                    <div class="clicktracker-card-icon">
                        <i class="icon-shopping-cart"></i>
                    </div>
                    <div class="clicktracker-card-value">
                        {if $top_product}
                            {$top_product.total_clicks|intval}
                        {else}
                            -
                        {/if}
                    </div>
                    <div class="clicktracker-card-label">
                        {if $top_product}
                            {$top_product.product_name|truncate:25:'...'|escape:'htmlall':'UTF-8'}
                        {else}
                            {l s='Top Product' mod='clicktracker'}
                        {/if}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {* Charts Row *}
    <div class="row">
        <div class="col-md-8">
            <div class="panel">
                <div class="panel-heading">
                    <i class="icon-line-chart"></i> {l s='Clicks Over Time' mod='clicktracker'}
                </div>
                <div class="panel-body">
                    {if $has_period_clicks}
                        <canvas id="clicksTimeChart" height="100"></canvas>
                    {else}
                        <p class="text-center text-muted">{l s='No data available for the selected period.' mod='clicktracker'}</p>
                    {/if}
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="panel">
                <div class="panel-heading">
                    <i class="icon-pie-chart"></i> {l s='Clicks by Element Type' mod='clicktracker'}
                </div>
                <div class="panel-body">
                    {if $pie_labels && $pie_labels != '[]'}
                        <canvas id="elementTypePieChart" height="200"></canvas>
                    {else}
                        <p class="text-center text-muted">{l s='No data available.' mod='clicktracker'}</p>
                    {/if}
                </div>
            </div>
        </div>
    </div>

    {* Sources and actions *}
    <div class="row">
        <div class="col-md-8">
            <div class="panel">
                <div class="panel-heading">
                    <i class="icon-random"></i> {l s='Clicks by Traffic Source' mod='clicktracker'}
                </div>
                <div class="panel-body">
                    {if !$schema_ready}
                        <p class="text-center text-muted">{l s='Available after the module upgrade.' mod='clicktracker'}</p>
                    {elseif $sources && count($sources) > 0}
                        <table class="table table-condensed">
                            <thead>
                                <tr>
                                    <th>{l s='Source' mod='clicktracker'}</th>
                                    <th class="text-right">{l s='Clicks' mod='clicktracker'}</th>
                                    <th class="text-right">%</th>
                                    <th class="text-right">{l s='Previous period' mod='clicktracker'}</th>
                                    <th class="text-right">{l s='Change' mod='clicktracker'}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {foreach from=$sources item=source}
                                    <tr>
                                        <td>{$source.label|escape:'htmlall':'UTF-8'}</td>
                                        <td class="text-right"><strong>{$source.value|intval}</strong></td>
                                        <td class="text-right">{$source.share|escape:'htmlall':'UTF-8'}%</td>
                                        <td class="text-right text-muted">{$source.previous|intval}</td>
                                        <td class="text-right">
                                            {if isset($source.pct)}
                                                <span class="clicktracker-delta clicktracker-delta-{$source.dir|escape:'htmlall':'UTF-8'}">{if $source.dir == 'up'}&#9650;{elseif $source.dir == 'down'}&#9660;{/if} {$source.pct|intval}%</span>
                                            {else}
                                                <span class="text-muted">-</span>
                                            {/if}
                                        </td>
                                    </tr>
                                {/foreach}
                            </tbody>
                        </table>
                        <p class="help-block">{l s='Clicks recorded before version 1.2.0 have a source only when the page URL contained tracking parameters (srsltid, fbclid, gclid, utm_*); the others are shown as Unknown.' mod='clicktracker'}</p>
                    {else}
                        <p class="text-center text-muted">{l s='No data available.' mod='clicktracker'}</p>
                    {/if}
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="panel">
                <div class="panel-heading">
                    <i class="icon-hand-o-up"></i> {l s='Clicks by Action' mod='clicktracker'}
                </div>
                <div class="panel-body">
                    {if $actions && count($actions) > 0}
                        <ul class="list-group">
                            {foreach from=$actions item=action}
                                <li class="list-group-item">
                                    <span class="badge">{$action.total|intval}</span>
                                    {$action.label|truncate:40:'...'|escape:'htmlall':'UTF-8'}
                                    {if $action.label != $action.selector}<br><small class="text-muted">{$action.selector|truncate:40:'...'|escape:'htmlall':'UTF-8'}</small>{/if}
                                </li>
                            {/foreach}
                        </ul>
                    {else}
                        <p class="text-center text-muted">{l s='No data available.' mod='clicktracker'}</p>
                    {/if}
                </div>
            </div>
        </div>
    </div>

    {* When: hours and weekdays *}
    <div class="row">
        <div class="col-md-6">
            <div class="panel">
                <div class="panel-heading">
                    <i class="icon-time"></i> {l s='Clicks by Hour of Day' mod='clicktracker'}
                </div>
                <div class="panel-body">
                    {if $has_period_clicks}
                        <canvas id="clicksHourChart" height="140"></canvas>
                    {else}
                        <p class="text-center text-muted">{l s='No data available.' mod='clicktracker'}</p>
                    {/if}
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="panel">
                <div class="panel-heading">
                    <i class="icon-calendar-o"></i> {l s='Clicks by Day of Week' mod='clicktracker'}
                </div>
                <div class="panel-body">
                    {if $has_period_clicks}
                        <canvas id="clicksWeekdayChart" height="140"></canvas>
                    {else}
                        <p class="text-center text-muted">{l s='No data available.' mod='clicktracker'}</p>
                    {/if}
                </div>
            </div>
        </div>
    </div>

    {* Devices, categories, brands *}
    <div class="row">
        <div class="col-md-4">
            <div class="panel">
                <div class="panel-heading">
                    <i class="icon-mobile"></i> {l s='Clicks by Device' mod='clicktracker'}
                </div>
                <div class="panel-body">
                    {if $devices && count($devices) > 0}
                        <ul class="list-group">
                            {foreach from=$devices item=device}
                                <li class="list-group-item">
                                    <span class="badge">{$device.total|intval}</span>
                                    {$device.label|escape:'htmlall':'UTF-8'} <small class="text-muted">({$device.share|escape:'htmlall':'UTF-8'}%)</small>
                                </li>
                            {/foreach}
                        </ul>
                        <p class="help-block">{l s='Only clicks recorded since version 1.2.0 include the device.' mod='clicktracker'}</p>
                    {else}
                        <p class="text-center text-muted">{l s='No device data yet: it is recorded for new clicks only.' mod='clicktracker'}</p>
                    {/if}
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="panel">
                <div class="panel-heading">
                    <i class="icon-folder-open"></i> {l s='Top 10 Categories' mod='clicktracker'}
                </div>
                <div class="panel-body">
                    {if $top_categories && count($top_categories) > 0}
                        <ul class="list-group">
                            {foreach from=$top_categories item=category name=topCategories}
                                <li class="list-group-item">
                                    <span class="badge">{$category.total_clicks|intval}</span>
                                    <span class="clicktracker-rank">{$smarty.foreach.topCategories.iteration}.</span>
                                    {if $category.name}{$category.name|truncate:30:'...'|escape:'htmlall':'UTF-8'} {/if}<small class="text-muted">#{$category.id_category|intval}</small>
                                </li>
                            {/foreach}
                        </ul>
                    {else}
                        <p class="text-center text-muted">{l s='No product clicks recorded.' mod='clicktracker'}</p>
                    {/if}
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="panel">
                <div class="panel-heading">
                    <i class="icon-tag"></i> {l s='Top 10 Brands' mod='clicktracker'}
                </div>
                <div class="panel-body">
                    {if $top_manufacturers && count($top_manufacturers) > 0}
                        <ul class="list-group">
                            {foreach from=$top_manufacturers item=manufacturer name=topManufacturers}
                                <li class="list-group-item">
                                    <span class="badge">{$manufacturer.total_clicks|intval}</span>
                                    <span class="clicktracker-rank">{$smarty.foreach.topManufacturers.iteration}.</span>
                                    {if $manufacturer.name}{$manufacturer.name|truncate:30:'...'|escape:'htmlall':'UTF-8'}{else}#{$manufacturer.id_manufacturer|intval}{/if}
                                </li>
                            {/foreach}
                        </ul>
                    {else}
                        <p class="text-center text-muted">{l s='No product clicks recorded.' mod='clicktracker'}</p>
                    {/if}
                </div>
            </div>
        </div>
    </div>

    {* Click-through rate *}
    <div class="panel">
        <div class="panel-heading">
            <i class="icon-bullseye"></i> {l s='Product Click-Through Rate' mod='clicktracker'}
            {if $ctr.rate !== null}<span class="badge">{$ctr.rate|escape:'htmlall':'UTF-8'}%</span>{/if}
        </div>
        <div class="panel-body">
            {if !$schema_ready}
                <p class="text-center text-muted">{l s='Available after the module upgrade.' mod='clicktracker'}</p>
            {elseif !$ctr_enabled && !$ctr.views}
                <p class="text-center text-muted">{l s='Product view counting is disabled in the configuration.' mod='clicktracker'}</p>
            {elseif !$ctr.from}
                <p class="text-center text-muted">{l s='Product views are counted since:' mod='clicktracker'} {$ctr.since|escape:'htmlall':'UTF-8'}. {l s='Choose a period after this date.' mod='clicktracker'}</p>
            {else}
                <p class="help-block">
                    {l s='Data since' mod='clicktracker'} {$ctr.from|escape:'htmlall':'UTF-8'}:
                    {$ctr.clicks|intval} {l s='clicks on' mod='clicktracker'} {$ctr.views|intval} {l s='product page views.' mod='clicktracker'}
                    {l s='Views are counted by the tracking script, so visitors without JavaScript and most bots are excluded.' mod='clicktracker'}
                </p>
                {if $ctr.products && count($ctr.products) > 0}
                    <table class="table table-condensed">
                        <thead>
                            <tr>
                                <th>{l s='Product' mod='clicktracker'}</th>
                                <th class="text-right">{l s='Views' mod='clicktracker'}</th>
                                <th class="text-right">{l s='Clicks' mod='clicktracker'}</th>
                                <th class="text-right">{l s='CTR' mod='clicktracker'}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach from=$ctr.products item=product}
                                <tr>
                                    <td>{$product.name|truncate:50:'...'|escape:'htmlall':'UTF-8'} <small class="text-muted">#{$product.id_product|intval}</small></td>
                                    <td class="text-right">{$product.views|intval}</td>
                                    <td class="text-right">{$product.clicks|intval}</td>
                                    <td class="text-right">{if $product.ctr !== null}<strong>{$product.ctr|escape:'htmlall':'UTF-8'}%</strong>{else}<span class="text-muted">-</span>{/if}</td>
                                </tr>
                            {/foreach}
                        </tbody>
                    </table>
                {else}
                    <p class="text-center text-muted">{l s='No product views recorded in this period.' mod='clicktracker'}</p>
                {/if}
            {/if}
        </div>
    </div>

    {* Context Distribution *}
    <div class="row">
        <div class="col-md-4">
            <div class="panel">
                <div class="panel-heading">
                    <i class="icon-sitemap"></i> {l s='Clicks by Context' mod='clicktracker'}
                </div>
                <div class="panel-body">
                    {if $clicks_by_context && count($clicks_by_context) > 0}
                        <ul class="list-group">
                            {foreach from=$clicks_by_context item=context}
                                <li class="list-group-item">
                                    <span class="badge">{$context.total|intval}</span>
                                    {if $context.context_type == 'product'}
                                        <i class="icon-shopping-cart"></i> {l s='Product Pages' mod='clicktracker'}
                                    {elseif $context.context_type == 'cms'}
                                        <i class="icon-file-text"></i> {l s='CMS/Blog Pages' mod='clicktracker'}
                                    {elseif isset($context_types[$context.context_type])}
                                        <i class="icon-file"></i> {$context_types[$context.context_type]|escape:'htmlall':'UTF-8'}
                                    {else}
                                        <i class="icon-file"></i> {$context.context_type|escape:'htmlall':'UTF-8'}
                                    {/if}
                                </li>
                            {/foreach}
                        </ul>
                    {else}
                        <p class="text-center text-muted">{l s='No data available.' mod='clicktracker'}</p>
                    {/if}
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="panel">
                <div class="panel-heading">
                    <i class="icon-trophy"></i> {l s='Top 10 Products' mod='clicktracker'}
                </div>
                <div class="panel-body">
                    {if $top_products && count($top_products) > 0}
                        <ul class="list-group">
                            {foreach from=$top_products item=product name=topProducts}
                                <li class="list-group-item">
                                    <span class="badge">{$product.total_clicks|intval}</span>
                                    <span class="clicktracker-rank">{$smarty.foreach.topProducts.iteration}.</span>
                                    {$product.product_name|truncate:30:'...'|escape:'htmlall':'UTF-8'}
                                </li>
                            {/foreach}
                        </ul>
                    {else}
                        <p class="text-center text-muted">{l s='No product clicks recorded.' mod='clicktracker'}</p>
                    {/if}
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="panel">
                <div class="panel-heading">
                    <i class="icon-file"></i> {l s='Top 10 Pages' mod='clicktracker'}
                </div>
                <div class="panel-body">
                    {if $top_pages && count($top_pages) > 0}
                        <ul class="list-group">
                            {foreach from=$top_pages item=page name=topPages}
                                <li class="list-group-item">
                                    <span class="badge">{$page.total_clicks|intval}</span>
                                    <span class="clicktracker-rank">{$smarty.foreach.topPages.iteration}.</span>
                                    <span title="{$page.page_url|escape:'htmlall':'UTF-8'}">
                                        {$page.page_title|default:$page.page_url|truncate:25:'...'|escape:'htmlall':'UTF-8'}
                                    </span>
                                </li>
                            {/foreach}
                        </ul>
                    {else}
                        <p class="text-center text-muted">{l s='No page clicks recorded.' mod='clicktracker'}</p>
                    {/if}
                </div>
            </div>
        </div>
    </div>
</div>

{* Chart.js initialization - loaded locally for PrestaShop Addons compliance *}
<script src="{$chart_js_path|escape:'htmlall':'UTF-8'}"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var clicksLabel = '{l s='Clicks' mod='clicktracker' js=1}';

    function barChart(canvasId, labels, counts) {
        var canvas = document.getElementById(canvasId);
        if (!canvas) {
            return;
        }
        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: clicksLabel,
                    data: counts,
                    backgroundColor: 'rgba(37, 185, 215, 0.7)',
                    borderColor: '#25b9d7',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
    }

    // Clicks Over Time Chart
    var timeChartCanvas = document.getElementById('clicksTimeChart');
    if (timeChartCanvas) {
        new Chart(timeChartCanvas, {
            type: 'line',
            data: {
                labels: {$chart_dates nofilter},
                datasets: [{
                    label: clicksLabel,
                    data: {$chart_counts nofilter},
                    borderColor: '#25b9d7',
                    backgroundColor: 'rgba(37, 185, 215, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
    }

    // Element Type Pie Chart (colors bound to the element type)
    var pieChartCanvas = document.getElementById('elementTypePieChart');
    if (pieChartCanvas) {
        new Chart(pieChartCanvas, {
            type: 'doughnut',
            data: {
                labels: {$pie_labels nofilter},
                datasets: [{
                    data: {$pie_counts nofilter},
                    backgroundColor: {$pie_colors nofilter},
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }

    barChart('clicksHourChart', {$hour_labels nofilter}, {$hour_counts nofilter});
    barChart('clicksWeekdayChart', {$weekday_labels nofilter}, {$weekday_counts nofilter});
});
</script>
