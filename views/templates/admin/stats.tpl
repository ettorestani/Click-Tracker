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
                    <div class="clicktracker-card-value">{$total_clicks|intval}</div>
                    <div class="clicktracker-card-label">{l s='Total Clicks' mod='clicktracker'}</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="panel clicktracker-card clicktracker-card-month">
                <div class="panel-body text-center">
                    <div class="clicktracker-card-icon">
                        <i class="icon-calendar"></i>
                    </div>
                    <div class="clicktracker-card-value">{$this_month_clicks|intval}</div>
                    <div class="clicktracker-card-label">{l s='This Month' mod='clicktracker'}</div>
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
                            {$top_product.product_name|escape:'htmlall':'UTF-8'|truncate:25:'...'}
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
        {* Clicks Over Time Chart *}
        <div class="col-md-8">
            <div class="panel">
                <div class="panel-heading">
                    <i class="icon-line-chart"></i> {l s='Clicks Over Time' mod='clicktracker'}
                </div>
                <div class="panel-body">
                    {if $chart_dates && $chart_dates != '[]'}
                        <canvas id="clicksTimeChart" height="100"></canvas>
                    {else}
                        <p class="text-center text-muted">{l s='No data available for the selected period.' mod='clicktracker'}</p>
                    {/if}
                </div>
            </div>
        </div>

        {* Clicks by Element Type *}
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
                                    {else}
                                        <i class="icon-file-text"></i> {l s='CMS/Blog Pages' mod='clicktracker'}
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

        {* Top Products *}
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
                                    {$product.product_name|escape:'htmlall':'UTF-8'|truncate:30:'...'}
                                </li>
                            {/foreach}
                        </ul>
                    {else}
                        <p class="text-center text-muted">{l s='No product clicks recorded.' mod='clicktracker'}</p>
                    {/if}
                </div>
            </div>
        </div>

        {* Top Pages *}
        <div class="col-md-4">
            <div class="panel">
                <div class="panel-heading">
                    <i class="icon-file"></i> {l s='Top 10 CMS Pages' mod='clicktracker'}
                </div>
                <div class="panel-body">
                    {if $top_pages && count($top_pages) > 0}
                        <ul class="list-group">
                            {foreach from=$top_pages item=page name=topPages}
                                <li class="list-group-item">
                                    <span class="badge">{$page.total_clicks|intval}</span>
                                    <span class="clicktracker-rank">{$smarty.foreach.topPages.iteration}.</span>
                                    <span title="{$page.page_url|escape:'htmlall':'UTF-8'}">
                                        {$page.page_title|default:$page.page_url|escape:'htmlall':'UTF-8'|truncate:25:'...'}
                                    </span>
                                </li>
                            {/foreach}
                        </ul>
                    {else}
                        <p class="text-center text-muted">{l s='No CMS page clicks recorded.' mod='clicktracker'}</p>
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
    // Clicks Over Time Chart
    var timeChartCanvas = document.getElementById('clicksTimeChart');
    if (timeChartCanvas) {
        var chartDates = {$chart_dates nofilter};
        var chartCounts = {$chart_counts nofilter};

        if (chartDates.length > 0) {
            new Chart(timeChartCanvas, {
                type: 'line',
                data: {
                    labels: chartDates,
                    datasets: [{
                        label: '{l s='Clicks' mod='clicktracker' js=1}',
                        data: chartCounts,
                        borderColor: '#25b9d7',
                        backgroundColor: 'rgba(37, 185, 215, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }
    }

    // Element Type Pie Chart
    var pieChartCanvas = document.getElementById('elementTypePieChart');
    if (pieChartCanvas) {
        var pieLabels = {$pie_labels nofilter};
        var pieCounts = {$pie_counts nofilter};

        if (pieLabels.length > 0) {
            new Chart(pieChartCanvas, {
                type: 'doughnut',
                data: {
                    labels: pieLabels,
                    datasets: [{
                        data: pieCounts,
                        backgroundColor: [
                            '#25D366', // WhatsApp green
                            '#007bff', // Phone blue
                            '#EA4335', // Maps red
                            '#6c757d'  // Other gray
                        ],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
    }
});
</script>
