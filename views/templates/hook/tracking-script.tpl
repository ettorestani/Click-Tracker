{*
 * Click Tracker Module - Frontend Tracking Script Template
 *
 * @author    Ettore Stani
 * @copyright 2024 Ettore Stani
 * @license   AFL-3.0
 *}

<script>
    window.et_clickTrackerConfig = {
        classes: {$et_clicktracker_classes nofilter},
        bodyClasses: {$et_clicktracker_body_classes nofilter},
        externalOnly: {$et_clicktracker_external_only nofilter},
        debug: {$et_clicktracker_debug nofilter},
        token: '{$et_clicktracker_token|escape:'javascript':'UTF-8'}',
        ajaxUrl: '{$et_clicktracker_ajax_url|escape:'javascript':'UTF-8'}',
        context: '{$et_clicktracker_context|escape:'javascript':'UTF-8'}',
        productData: {$et_clicktracker_product_data nofilter},
        shouldTrackByPageType: {$et_clicktracker_should_track nofilter}
    };
</script>
<script src="{$et_clicktracker_js_path|escape:'htmlall':'UTF-8'}" async></script>
