<body onload="document.netpluspay_redirect.submit()">
<form name="netpluspay_redirect" action="<?php echo $this->url ; ?>" method="post">
    <input type="hidden" name="merchant_id" value="<?php echo $_REQUEST['merchant_id']; ?>" />
    <input type="hidden" name="order_id" value="<?php echo $_REQUEST['order_id']; ?>" />
    <input type="hidden" name="email" value="<?php echo $_REQUEST['email']; ?>" />
    <input type="hidden" name="total_amount" value="<?php echo $_REQUEST['total_amount']; ?>" />
    <input type="hidden" name="narration" value="<?php echo $_REQUEST['narration']; ?>" />
    <input type="hidden" name="full_name" value="<?php echo $_REQUEST['full_name']; ?>" />
    <input type="hidden" name="return_url" value="<?php echo $_REQUEST['return_url']; ?>" />
    <input type="hidden" name="currency_code" value="<?php echo $_REQUEST['currency_code']; ?>" />
</form></body>