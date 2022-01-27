{if $is_order neq true}<script src='{$script_url nofilter}' type="text/javascript" async></script>{/if}
<script type="text/javascript">
    {literal}(function(t,a,p){t.TapfiliateObject=a;t[a]=t[a]||function(){
    (t[a].q=t[a].q||[]).push(arguments)}})(window,'tap');{/literal}
    tap('create', '{$tapfiliate_id nofilter}', { integration: "prestashop" });
    {if $is_order eq true}tap('conversion', '{$external_id nofilter}', {$conversion_amount nofilter}, {
        customer_id: '{$customer_id nofilter}',
        currency: '{$order_currency nofilter}',
        coupons: {$coupons|@json_encode nofilter}
    });{else}tap('detect');{/if}
</script>
