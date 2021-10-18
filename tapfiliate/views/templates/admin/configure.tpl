<form action="https://app.dev.tap/integrations/prestashop/auth/check/" name="autoSubmit" method="POST">
   {foreach from=$payload key=k item=v}
   <input type="hidden" name="{$k}" value="{$v}" />
   {/foreach}
</form>
<script>
   document.autoSubmit.submit()
</script>
