<?php
namespace App\Http\Controllers\Pay;


use App\Exceptions\RuleValidationException;
use App\Http\Controllers\PayController;
use GuzzleHttp\Client;
use Yansongda\Pay\Pay;

class WepayController extends PayController
{

    public function gateway(string $payway, string $orderSN)
    {
        try {
            // 加载网关
            $this->loadGateWay($orderSN, $payway);
            // 分割 merchant_id，获取 app_id 和 mch_id
            list($mch_id, $app_id) = explode('-', $this->payGateway->merchant_id . '-');
            $app_id_info = $app_id ?: $this->payGateway->merchant_key;
            $config = [
                'app_id' => $app_id_info,
                'miniapp_id' => $app_id_info,
                'mch_id' => $mch_id,
                'key' => $this->payGateway->merchant_pem,
                'notify_url' => url($this->payGateway->pay_handleroute . '/notify_url'),
                'return_url' => url('detail-order-sn', ['orderSN' => $this->order->order_sn]),
                'http' => [ // optional
                    'timeout' => 10.0,
                    'connect_timeout' => 10.0,
                ],
            ];
            $order = [
                'out_trade_no' => $this->order->order_sn,
                'total_fee' => bcmul($this->order->actual_price, 100, 0),
                'body' => $this->order->order_sn
            ];
            switch ($payway){
                case 'wescan':
                    try{
                        $result = Pay::wechat($config)->scan($order)->toArray();
                        $result['qr_code'] = $result['code_url'];
                        $result['payname'] =$this->payGateway->pay_name;
                        $result['actual_price'] = (float)$this->order->actual_price;
                        $result['orderid'] = $this->order->order_sn;
                        return $this->render('static_pages/qrpay', $result, __('dujiaoka.scan_qrcode_to_pay'));
                    } catch (\Exception $e) {
                        throw new RuleValidationException(__('dujiaoka.prompt.abnormal_payment_channel') . $e->getMessage());
                    }
                    break;
                case 'miniapp':
                    if (isset($_GET['openId'])) {
                        $openId = $_GET['openId'];
                        // 在订单信息中添加 openId
                        $order['openid'] = $openId;
                        $result = Pay::wechat($config)->miniapp($order)->toArray();
                        return json_encode([
                            'code' => 200,
                            'message' => $result
                        ], JSON_UNESCAPED_UNICODE);
                    } else {
                        try{
                            $result = [
                                'orderId' => $this->order->order_sn
                            ];
                            $wechatUrlLineUrl = $this->payGateway->merchant_key;
                            $client = new Client([
                                'headers' => [ 'Content-Type' => 'application/json' ]
                            ]);
                            $response = $client->post($wechatUrlLineUrl, ['body' => json_encode($result)]);
                            $body = json_decode($response->getBody()->getContents(), true);
                            if (!isset($body['code']) || $body['code'] != 200) {
                                return $this->err(__('dujiaoka.prompt.abnormal_payment_channel') . $body['message']);
                            }
                            $result['url_line'] = $body['data'];
                            $result['payname'] =$this->payGateway->pay_name;
                            $result['actual_price'] = (float)$this->order->actual_price;
                            return $this->render('static_pages/wepay', $result, __('dujiaoka.wx_miniapp_to_pay'));
                        } catch (\Exception $e) {
                            throw new RuleValidationException(__('dujiaoka.prompt.abnormal_payment_channel') . $e->getMessage());
                        }
                    }
                    break;

                }
        } catch (RuleValidationException $exception) {
            if (isset($_GET['openId'])) {
                header('Content-Type: application/json; charset=utf-8');
                return json_encode([
                    'code' => 500,
                    'message' => $exception->getMessage()
                ], JSON_UNESCAPED_UNICODE);
            }
            return $this->err($exception->getMessage());
        }
    }

    /**
     * 异步通知
     */
    public function notifyUrl()
    {
        $xml = file_get_contents('php://input');
        $arr = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        $oid = $arr['out_trade_no'];
        $order = $this->orderService->detailOrderSN($oid);
        if (!$order) {
            return 'error';
        }
        $payGateway = $this->payService->detail($order->pay_id);
        if (!$payGateway) {
            return 'error';
        }
        if($payGateway->pay_handleroute != '/pay/wepay'){
            return 'error';
        }
        $config = [
            'app_id' => $payGateway->merchant_id,
            'mch_id' => $payGateway->merchant_key,
            'key' => $payGateway->merchant_pem,
        ];
        $pay = Pay::wechat($config);
        try{
            // 验证签名
            $result = $pay->verify();
            $total_fee = bcdiv($result->total_fee, 100, 2);
            $this->orderProcessService->completedOrder($result->out_trade_no, $total_fee, $result->transaction_id);
            return 'success';
        } catch (\Exception $exception) {
            return 'fail';
        }
    }

}
