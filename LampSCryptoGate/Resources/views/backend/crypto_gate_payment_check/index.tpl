{extends file="parent:backend/_base/layout.tpl"}

{block name="content/main"}
   <form class="form-horizontal pairing-form" method="post">

      <div class="form-group">
         <div class="col-sm-10">
            <input type="hidden" name="check_now" value="check_now">
            <button type="submit" class="btn btn-primary">
               {s name="backend/crypto_gate_payment_check/check_now" namespace="backend/crypto_gate_payment_check"}Check now{/s}
            </button>
         </div>
      </div>
   </form>

   {if $success}
      <h2>{$success}</h2>
      URL {s name="backend/crypto_gate_payment_check/is" namespace="backend/crypto_gate_payment_check"}is{/s}: <a target="_blank" href="{$payment_url}">{$payment_url}</a>

   {/if}
   {$error}
   {if $error}
      {$error}
      <br />{$error_message}
      <pre>
         {$error_trace}
      </pre>
   {/if}

{/block}