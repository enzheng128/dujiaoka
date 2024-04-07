<?php

namespace App\Admin\Forms;

use App\Models\Carmis;
use App\Models\Goods;
use Dcat\Admin\Widgets\Form;
use Illuminate\Support\Facades\Storage;

class ImportCarmis extends Form
{

    /**
     * Handle the form request.
     *
     * @param array $input
     *
     * @return mixed
     */
    public function handle(array $input)
    {
        if (empty($input['carmis_list']) && empty($input['carmis_txt'])) {
            return $this->response()->error(admin_trans('carmis.rule_messages.carmis_list_and_carmis_txt_can_not_be_empty'));
        }
        $carmisContent = "";
        if (!empty($input['carmis_txt'])) {
            $carmisContent = Storage::disk('public')->get($input['carmis_txt']);
        }
        if (!empty($input['carmis_list'])) {
            $carmisContent = $input['carmis_list'];
        }
        $carmisData = [];
        $tempList = explode(PHP_EOL, $carmisContent);
        foreach ($tempList as $val) {
            if (trim($val) != "") {
                $carmisData[] = [
                    'goods_id' => $input['goods_id'],
                    'carmi' => trim($val),
                    'status' => Carmis::STATUS_UNSOLD,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
            }
        }
        if ($input['remove_duplication'] == 1) {
            $carmisData = assoc_unique($carmisData, 'carmi');
        }
        // 计算即将导入的数据数量
        $importCount = count($carmisData);

        // 检查是否已经确认导入
        if (empty($input['confirmed'])) {
            // 弹出确认框，包含导入数量
            return $this->response()
                ->warning("{admin_trans('carmis.fields.are_you_import_sure_number')} {$importCount}")
                ->interceptor([ // 添加拦截器
                    'then' => <<<JS
                        // 用户点击确认后，再次提交表单，并附加 confirmed 参数
                        function (target, args) {
                            var form = target.parents('form');
                            $('<input>').attr({
                                type: 'hidden',
                                name: 'confirmed',
                                value: '1'
                            }).appendTo(form);
                            form.submit();
                        }
    JS
                ]);
        }
        Carmis::query()->insert($carmisData);
        // 删除文件
        Storage::disk('public')->delete($input['carmis_txt']);
        return $this
				->response()
				->success(admin_trans('carmis.rule_messages.import_carmis_success'))
				->location('/carmis');
    }

    /**
     * Build a form here.
     */
    public function form()
    {
        $this->confirm(admin_trans('carmis.fields.are_you_import_sure'));
        $this->select('goods_id')->options(
            Goods::query()->where('type', Goods::AUTOMATIC_DELIVERY)->pluck('gd_name', 'id')
        )->required();
        $this->textarea('carmis_list')
            ->rows(20)
            ->help(admin_trans('carmis.helps.carmis_list'));
        $this->file('carmis_txt')
            ->disk('public')
            ->uniqueName()
            ->accept('txt')
            ->maxSize(5120)
            ->help(admin_trans('carmis.helps.carmis_list'));
        $this->switch('remove_duplication');
    }

}
