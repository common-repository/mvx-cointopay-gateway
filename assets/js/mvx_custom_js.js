jQuery(function($) {
	if ($('#vendor_cointopay_altcoinid').length>0){
		var merchant_idd = $('#vendor_cointopay_altcoinid').val();
		if(merchant_idd != ''){
		var length_idd = merchant_idd.length;
		
			$.ajax ({
				url: ctp_mvx_ajax.ajaxurl,
				showLoader: true,
				data: {coinId:merchant_idd, action:'getMVXMerchantCoinsByAjax'},
				type: "POST",
				success: function(result) {
					if (result.length) {
						if (result == 0) {
							if ($('#vendor_cointopay_tag').length>0){
							$('#vendor_cointopay_tag').closest( '.form-group' ).hide();
							}
						} else {
							if ($('#vendor_cointopay_tag').length>0){
							$('#vendor_cointopay_tag').closest( '.form-group' ).show();
							} else {
								$('#vendor_cointopay_altcoinid').closest('.payment-gateway-mvx-cointopay').append('<div class="form-group"><label for="vendor_cointopay_tag" class="control-label col-sm-3 col-md-3">Coin Tag</label><div class="col-md-6 col-sm-9"><input id="vendor_cointopay_tag" class="form-control" type="text" name="vendor_cointopay_tag" value=""  placeholder="Coin Tag"> </div></div>')
							}
						}
						
					}
				}
			});
	
	$('#vendor_cointopay_altcoinid').on('change', function () {
		var merchant_id = $(this).val();
		var length_id = merchant_id.length;
		
			$.ajax ({
				url: ctp_mvx_ajax.ajaxurl,
				showLoader: true,
				data: {coinId:merchant_id, action:'getMVXMerchantCoinsByAjax'},
				type: "POST",
				success: function(result) {
					if (result.length) {
						if (result == 0) {
							if ($('#vendor_cointopay_tag').length>0){
							$('#vendor_cointopay_tag').closest( '.form-group' ).hide();
							}
						} else {
							if ($('#vendor_cointopay_tag').length>0){
							$('#vendor_cointopay_tag').closest( '.form-group' ).show();
							} else {
								$('#vendor_cointopay_altcoinid').closest('.payment-gateway-mvx-cointopay').append('<div class="form-group"><label for="vendor_cointopay_tag" class="control-label col-sm-3 col-md-3">Coin Tag</label><div class="col-md-6 col-sm-9"><input id="vendor_cointopay_tag" class="form-control" type="text" name="vendor_cointopay_tag" value=""  placeholder="Coin Tag"> </div></div>')
							}
						}
						
					}
				}
			});
		
	});
	}
	}
	
	if($('input[id="cointopay_mvx_merchant_id"]').length>0){
		var merchant_idd = $('input[id="cointopay_mvx_merchant_id"]').val();
		if(merchant_idd != ''){
		var length_idd = merchant_idd.length;
		
			console.log(ajaxurl);
			$.ajax ({
				url: ajaxurl,
				showLoader: true,
				data: {merchant:merchant_idd, action:'getMerchantCoinsByAjaxMVX'},
				type: "POST",
				success: function(result) {
					$('select[id="cointopay_mvx_alt_coin"]').html('');
					//$('input[id="cointopay_mvx_merchant_id"]').css('border','1px solid #adadad');
					//$('.incorrect-merchant').remove();
					if (result.length) {
							$('select[id="cointopay_mvx_alt_coin"]').html(result);
						
					} else {
						//$('input[id="cointopay_mvx_merchant_id"]').css('border','1px solid red');
						//$('input[id="cointopay_merchant_id"]').closest('td').append('<span style="color:red" class="incorrect-merchant">MerchantID should be type Integer, please correct. </span>');
					}
				}
			});
	
	$('input[id="cointopay_mvx_merchant_id"]').on('change', function () {
		var merchant_id = $(this).val();
		var length_id = merchant_id.length;
		
			$.ajax ({
				url: ajaxurl,
				showLoader: true,
				data: {merchant:merchant_id, action:'getMerchantCoinsByAjaxMVX'},
				type: "POST",
				success: function(result) {
					$('select[id="cointopay_mvx_alt_coin"]').html('');
					//$('input[id="cointopay_mvx_merchant_id"]').css('border','1px solid #adadad');
					//$('.incorrect-merchant').remove();
					if (result.length) {
						$('select[id="cointopay_mvx_alt_coin"]').html(result);
					} else {
						//$('input[id="cointopay_mvx_merchant_id"]').css('border','1px solid red');
						//$('input[id="cointopay_mvx_merchant_id"]').closest('td').append('<span style="color:red" class="incorrect-merchant">MerchantID should be type Integer, please correct. </span>');
					}
				}
			});
		
	});
	}
	}
});