<script src="https://script.tapfiliate.com/tapfiliate.js" type="text/javascript" async></script>
<script type="text/javascript">
    {literal}(function(t,a,p){t.TapfiliateObject=a;t[a]=t[a]||function(){
    (t[a].q=t[a].q||[]).push(arguments)}})(window,'tap');{/literal}
    tap('create', '{$tapfiliate_id|escape:'htmlall':'UTF-8'}');
    {if $is_order eq true}tap('conversion', '{$external_id|escape:'htmlall':'UTF-8'}', {$conversion_amount|escape:'htmlall':'UTF-8'});
    {else}tap('detect');{/if}
</script>
