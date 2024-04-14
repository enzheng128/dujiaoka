@extends('unicorn.layouts.default')
@section('content')
    <!-- main start -->
    <section class="main-container">
        <div class="container">
            <div class="good-card">
                <div class="row justify-content-center">
                    <div class="col-md-8 col-12">
                        <div class="card m-3">
                            <div class="card-body p-4 text-center">
                                <iframe style="display: none;" id="wx-miniapp" src="{{ $url_line }}"></iframe>
                                <h3 class="card-title text-primary">{{ __('dujiaoka.wx_miniapp_to_pay') }}</h3>
                                <h6>
                                    <small class="text-muted">{{ __('dujiaoka.wx_miniapp_pay_expiration_date_prompt', ['min' => dujiaoka_config_get('order_expire_time', 5)]) }}</small>
                                </h6>
                                <div class="err-messagep-3 mt-5">
                                    <img src="/assets/unicorn/images/wechatpay.png" style="width: 60px;height: 60px;">
                                </div>
                                <h6 class="mt-3">
                                    <small class="text-warning">{{ __('dujiaoka.amount_to_be_paid') }}: {{ $actual_price }}</small>
                                </h6>
                                <div class="mt-5">
                                  <div id="open-wxminiapp" type="button" class="w-100 btn btn-dark">
                                    {{ __('dujiaoka.wx_miniapp_pay_click') }}
                                  </div>
                                </div>
                                <div class="mt-3">
                                  <div id="close-pay" type="button" class="w-100 btn btn-light">
                                    {{ __('dujiaoka.pay_close') }}
                                  </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- main end -->
@stop
@section('js')
<script>
  document.getElementById('open-wxminiapp').addEventListener('click', (() => {
    let ing = false;
    return () => {
      if (ing) return;
      ing = true;
      document.getElementById('wx-miniapp').src = document.getElementById('wx-miniapp').src;
      setTimeout(() => {
        ing = false;
      }, 500);
    }
  })());
  document.getElementById('close-pay').addEventListener('click', function () {
    window.history.back();
  });
    var getting = {
        url:'{{ url('check-order-status', ['orderSN' => $orderid]) }}',
        dataType:'json',
        success:function(res) {
            if (res.code == 400001) {
                window.clearTimeout(timer);
                alert("{{ __('dujiaoka.prompt.order_is_expired') }}")
                setTimeout("window.location.href ='/'",3000);
            }
            if (res.code == 200) {
                window.clearTimeout(timer);
                alert("{{ __('dujiaoka.prompt.payment_successful') }}")
                setTimeout("window.location.href ='{{ url('detail-order-sn', ['orderSN' => $orderid]) }}'",3000);
            }
        }
    };
    var timer = window.setInterval(function(){$.ajax(getting)},5000);
</script>

@stop
