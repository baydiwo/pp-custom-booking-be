@extends('layouts.login')

@section('content')
<div class="container">
    <div class="login-section agnt-cntnt">
        <div class="form-container login" style="width:100% !important; max-width:100% !important; max-height:100% !important; height:100% !important;">
            <h1 class="heading">Booking Details - Search</h1>
            <div class="w-form">
                <section class="aa_loginForm">
                    <div class="aa_error"></div>
                        <form automplete=off method="post" action="" onsubmit="return false">
                        <div class="form-group row">
                        	<div class="col-md-2">
                            	<label for="email" class="col-form-label text-md-right">{{ __('Enter Booking ID') }} <span style="color:red;font-weight:bold;">*</span></label>
                            </div>
                            <div class="col-md-6">
                                <input id="bookingId" type="text" class="form-control" name="bookingId" value="" required autofocus>
                            </div>
                            <div class="col-md-1"></div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary btn-search">
                                    {{ __('Search') }}
                                </button>
                            </div>
                        </div>
                            </form>
                </section>
            </div>
            
            <div class="w-form" id="resultSet">
                    <div class="aa_error"></div>
                        <div class="form-group row">
                        	<div class="col-md-3"></div>
                            <div class="col-md-9">
                                <div class="form-group row">
                                    <div class="col-md-3">Pencil Created :</div>
                                    <div class="col-md-6" id="pencil_created"></div>
                                    <div class="col-md-3"></div>
                                 </div>
                                <div class="form-group row">
                                    <div class="col-md-3">Expiry Date :</div>
                                    <div class="col-md-6" id="expiry_date"></div>
                                    <div class="col-md-3"></div>
                                 </div>
                                <div class="form-group row">
                                    <div class="col-md-3">Unconfirmed @ :</div>
                                    <div class="col-md-6" id="unconfirmed_status"></div>
                                    <div class="col-md-3"></div>
                                 </div>
                                <div class="form-group row">
                                    <div class="col-md-3">Reservation Updated in RMS @ :</div>
                                    <div class="col-md-6" id="reservation_update"></div>
                                    <div class="col-md-3"></div>
                                 </div>
                                <div class="form-group row">
                                    <div class="col-md-3">Guest Token Updated @ :</div>
                                    <div class="col-md-6" id="guest_token"></div>
                                    <div class="col-md-3"></div>
                                 </div>
                                <div class="form-group row">
                                    <div class="col-md-3">Txn Receipt Posted @:</div>
                                    <div class="col-md-6" id="txn_receipt"></div>
                                    <div class="col-md-3"></div>
                                 </div>
                                <div class="form-group row">
                                    <div class="col-md-3">Confirmed @ :</div>
                                    <div class="col-md-6" id="confirmed_status"></div>
                                    <div class="col-md-3"></div>
                                 </div>
                             </div>
                        </div>
            </div>
            
            
        </div>
    </div>
</div>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
<script type="text/javascript">
$(document).ready(function() {
	$('#resultSet').hide();
	$(document).on('click','.btn-search',function(e){
		var bid = $('#bookingId').val();
		$('#resultSet').hide();
		if(bid !='')
		{
			$.ajax({
				type: "POST",
				url: "expiry-timing",
				data: 'booking_id='+bid,
				success: function(resp){
					if(resp != ""){
						$('#resultSet').show();
						if(resp){
							$('#expiry_date').html(resp.expiry_date);
							$('#pencil_created').html(resp.pencil_created);
							$('#unconfirmed_status').html(resp.unconfirmed_status);
							$('#reservation_update').html(resp.reservation_update);
							$('#guest_token').html(resp.guest_token);
							$('#txn_receipt').html(resp.txn_receipt);
							$('#confirmed_status').html(resp.confirmed_status);
						}
					}
				}
			});
		}
		else
		{
			$('#bookingId').focus();
		}
	});
});
</script>
@endsection