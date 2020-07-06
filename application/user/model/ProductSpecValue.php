<?php
/**
 * 易优CMS
 * ============================================================================
 * 版权所有 2016-2028 海南赞赞网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.eyoucms.com
 * ----------------------------------------------------------------------------
 * 如果商业用途务必到官方购买正版授权, 以免引起不必要的法律纠纷.
 * ============================================================================
 * Author: 陈风任 <491085389@qq.com>
 * Date: 2019-7-9
 */
namespace app\user\model;

use think\Model;
use think\Config;
use think\Db;

/**
 * 产品规格值ID，价格，库存表
 */
class ProductSpecValue extends Model
{
    //初始化
    protected function initialize()
    {
        // 需要调用`Model`的`initialize`方法
        parent::initialize();
    }

    public function SaveProducSpecValueStock($order_id = null, $users_id = null)
    {
        if (empty($order_id)) return false;

        $ProductSpecValue = [];
        $Where = [
            'users_id' => $users_id,
            'lang'     => get_home_lang(),
        ];
        if (is_array($order_id)) {
            $order_id = get_arr_column($order_id, 'order_id');
            $Where['order_id'] = ['IN',$order_id];
        }else{
            $Where['order_id'] = $order_id;
        }

        $OrderDetails = Db::name('shop_order_details')->where($Where)->field('product_id,num,data')->select();
        if (!empty($OrderDetails)) {
            $ProductIdWhere = $SpecValueIdWhere = $Array_new =[];
            foreach ($OrderDetails as $key => $value) {
                $value['data'] = unserialize($value['data']);
                $spec_value_id = htmlspecialchars_decode($value['data']['spec_value_id']);
                array_push($SpecValueIdWhere, $spec_value_id);
                array_push($ProductIdWhere, $value['product_id']);

                $Array_new[] = [
                    'spec_value_id' => $spec_value_id,
                    'spec_stock'    => $value['num'],
                ];
            }
            
            $ValueWhere = [
                'aid'  => ['IN',$ProductIdWhere],
                'lang' => get_home_lang(),
                'spec_value_id' => ['IN',$SpecValueIdWhere],
            ];
            $ProductSpecValue = Db::name('product_spec_value')->where($ValueWhere)->field('value_id,spec_value_id')->select();

            foreach ($ProductSpecValue as $key => $value) {
                foreach ($Array_new as $kk => $vv) {
                    if ($value['spec_value_id'] == $vv['spec_value_id']) {
                        $ProductSpecValue[$key]['spec_stock'] = Db::raw('spec_stock+'.($vv['spec_stock']));
                        $ProductSpecValue[$key]['spec_sales_num'] = Db::raw('spec_sales_num-'.($vv['spec_stock']));
                    }
                }
            }
            
            $this->saveAll($ProductSpecValue);
        }
    }
}