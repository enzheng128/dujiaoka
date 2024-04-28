<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ServerJiang implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * 任务最大尝试次数。
     *
     * @var int
     */
    public $tries = 2;

    /**
     * 任务运行的超时时间。
     *
     * @var int
     */
    public $timeout = 30;

    /**
     * @var Order
     */
    private $order;

    /**
     * 商品服务层.
     * @var \App\Service\PayService
     */
    private $goodsService;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
        $this->goodsService = app('Service\GoodsService');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $goodInfo = $this->goodsService->detail($this->order->goods_id);
        $postdata = [
            'title' => "{$this->order->title}",
            'content' => "
            - " . __('goods.fields.in_stock') . "：{$goodInfo->in_stock}
            - " . __('order.fields.order_id') . "：{$this->order->id}
            - " . __('order.fields.order_sn') . "：{$this->order->order_sn}
            - " . __('order.fields.pay_id') . "：{$this->order->pay->pay_name}
            - " . __('order.fields.title') . "：{$this->order['ord_title']}
            - " . __('order.fields.actual_price') . "：{$this->order->actual_price}
            - " . __('order.fields.email') . "：{$this->order->email}
            - " . __('goods.fields.gd_name') . "：{$goodInfo->gd_name}
            - " . __('order.fields.order_created') . "：{$this->order->created_at}
            "
        ];
        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => 'Content-type: application/json',
                'content' => json_encode($postdata,JSON_UNESCAPED_UNICODE)
            ]
        ];
        $context = stream_context_create($opts);
        $apiToken = dujiaoka_config_get('server_jiang_token');
        file_get_contents('http://www.pushplus.plus/' . 'send/' . $apiToken, false, $context);
    }
}