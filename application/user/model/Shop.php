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
 * Date: 2019-3-20
 */

namespace app\user\model;

use think\Model;
use think\Db;
use think\Config;
use think\Page;

/**
 * 商城
 */
class Shop extends Model
{
    //初始化
    protected function initialize()
    {
        // 需要调用`Model`的`initialize`方法
        parent::initialize();
        $this->home_lang = get_home_lang();
    }

    // 处理购买订单，超过指定时间修改为已订单过期，针对未付款订单
    public function UpdateShopOrderData($users_id){
        $time  = getTime() - Config::get('global.get_shop_order_validity');
        $where = array(
            'users_id'     => $users_id,
            'order_status' => 0,
            'add_time'     => array('<',$time),
        );
        $data = [
            'order_status'    => 4,  // 状态修改为订单过期
            'pay_name'        => '', // 订单过期则清空支付方式标记
            'wechat_pay_type' => '', // 订单过期则清空微信支付类型标记
            'update_time'     => getTime(),
        ];

        // 查询订单id数组用于添加订单操作记录
        $OrderIds = Db::name('shop_order')->field('order_id')->where($where)->select();

        // 订单过期，更新规格数量
        model('ProductSpecValue')->SaveProducSpecValueStock($OrderIds, $users_id);

        //批量修改订单状态 
        Db::name('shop_order')->where($where)->update($data);
        
        // 添加订单操作记录
        if (!empty($OrderIds)) {
	        AddOrderAction($OrderIds,$users_id,'0','4','0','0','订单过期！','会员未在订单有效期内支付，订单过期！');
        }
    }

    // 通过商品名称模糊查询订单信息
    public function QueryOrderList($pagesize,$users_id,$keywords,$query_get){
        // 商品名称模糊查询订单明细表，获取订单主表ID
        $DetailsWhere = [
            'users_id' => $users_id,
            'lang'     => $this->home_lang,
        ];
        $DetailsWhere['product_name'] =  ['LIKE', "%{$keywords}%"];
        $DetailsData = Db::name('shop_order_details')->field('order_id')->where($DetailsWhere)->select();
        // 若查无数据，则返回false
        if (empty($DetailsData)) {
            return false;
        }

        $order_ids = '';
        // 处理订单ID，查询订单主表信息
        foreach ($DetailsData as $key => $value) {
            if ('0' < $key) {
                $order_ids .= ',';
            }
            $order_ids .= $value['order_id'];
        }
        // 查询条件
        $OrderWhere = [
            'users_id' => $users_id,
            'lang'     => $this->home_lang,
            'order_id' => ['IN', $order_ids],
        ];

        $paginate_type = 'userseyou';
        if (isMobile()) {
            $paginate_type = 'usersmobile';
        }

        $paginate = array(
            'type'     => $paginate_type,
            'var_page' => config('paginate.var_page'),
            'query'    => $query_get,
        );

        $pages = Db::name('shop_order')
            ->field("*")
            ->where($OrderWhere)
            ->order('add_time desc')
            ->paginate($pagesize, false, $paginate);

        $data['list']  = $pages->items();
        $data['pages'] = $pages;

        return $data;
    }

    public function GetOrderIsEmpty($users_id,$keywords,$select_status){
        // 基础查询条件
        $OrderWhere = [
            'users_id' => $users_id,
            'lang'     => $this->home_lang,
        ];

        // 应用搜索条件
        if (!empty($keywords)) {
            $OrderWhere['order_code'] =  ['LIKE', "%{$keywords}%"];
        }

        // 订单状态搜索
        if (!empty($select_status)) {
            if ('dzf' === $select_status) {
                $select_status = 0;
            }
            $OrderWhere['order_status'] = $select_status;
        }

        $order = Db::name('shop_order')->where($OrderWhere)->count();
        // 查询存在数据，则返回1
        if (!empty($order)) {
            return 1; exit;
        }
        
        // 查询订单明细表
        if (empty($order) && !empty($keywords)) {
            $DetailsWhere = [
                'users_id' => $users_id,
                'lang'     => $this->home_lang,
            ];
            $DetailsWhere['product_name'] =  ['LIKE', "%{$keywords}%"];
            $DetailsData = Db::name('shop_order_details')->field('order_id')->where($DetailsWhere)->select();
            // 查询无数据，则返回0
            if (empty($DetailsData)) {
                return 0; exit;
            }

            $order_ids = '';
            // 处理订单ID，查询订单主表信息
            foreach ($DetailsData as $key => $value) {
                if (0 < $key) {
                    $order_ids .= ',';
                }
                $order_ids .= $value['order_id'];
            }
            // 查询条件
            $OrderWhere = [
                'users_id' => $users_id,
                'lang'     => $this->home_lang,
                'order_id' => ['IN', $order_ids],
            ];

            $order2 = Db::name('shop_order')->where($OrderWhere)->count();
            if (!empty($order2)) {
                return 1; exit;
            }else{
                return 0; exit;
            }
        }
    }

    // 获取微信公众号access_token
    // 传入微信公众号appid
    // 传入微信公众号secret
    // 返回data
    public function GetWeChatAccessToken($appid,$secret){
        // 获取公众号access_token，接口限制10万次/天
        $time = getTime();
        $get_token_url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.$appid.'&secret='.$secret;
        $TokenData = httpRequest($get_token_url);
        $TokenData = json_decode($TokenData, true);
        if (!empty($TokenData['access_token'])) {
            // 存入缓存配置
            $WechatData  = [
                'wechat_token_value' => $TokenData['access_token'],
                'wechat_token_time'  => $time,
            ];
            getUsersConfigData('wechat',$WechatData);
            $data = [
                'status' => true,
                'token'  => $WechatData['wechat_token_value'],
            ];
        }else{
            $data = [
                'status' => false,
                'prompt' => '错误提示：101，后台配置配置AppId或AppSecret不正确，请检查！',
            ];
        }
        return $data;
    }

    // 获取微信公众号jsapi_ticket
    // 传入微信公众号accesstoken
    // 返回data
    public function GetWeChatJsapiTicket($accesstoken){
        // 获取公众号jsapi_ticket
        $time = getTime();
        $get_ticket_url = 'https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token='.$accesstoken.'&type=jsapi';
        $TicketData = httpRequest($get_ticket_url);
        $TicketData = json_decode($TicketData, true);
        if (!empty($TicketData['ticket'])) {
            // 存入缓存配置
            $WechatData  = [
                'wechat_ticket_value' => $TicketData['ticket'],
                'wechat_ticket_time'  => $time,
            ];
            getUsersConfigData('wechat',$WechatData);
            $data = [
                'status' => true,
                'ticket' => $WechatData['wechat_ticket_value'],
            ];
        }else{
            $data = [
                'status' => false,
                'prompt' => '错误提示：102，后台配置配置AppId或AppSecret不正确，请检查！',
            ];
        }
        return $data;
    }

    // 获取随机字符串
    // 长度 length
    // 结果 str
    public function GetRandomString($length){
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str   = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    // 旧产品属性处理
    public function ProductAttrProcessing($value = array())
    {
        $attr_value = '';
        $AttrWhere = [
            'a.aid'     => $value['aid'],
            'b.lang'    => $this->home_lang
        ];
        $attrData = Db::name('product_attr')
            ->alias('a')
            ->field('a.attr_value as value, b.attr_name as name')
            ->join('__PRODUCT_ATTRIBUTE__ b', 'a.attr_id = b.attr_id', 'LEFT')
            ->where($AttrWhere)
            ->order('b.sort_order asc, a.attr_id asc')
            ->select();
        foreach ($attrData as $val) {
            $attr_value .= $val['name'].'：'.$val['value'].'<br/>';
        }
        return $attr_value;
    }

    // 新产品属性处理
    public function ProductNewAttrProcessing($value = array())
    {
        $attr_value = '';
        $where = [
            'a.list_id' => $value['attrlist_id'],
            'a.status'  => 1,
            'b.aid'     => $value['aid']
        ];
        $attrData = Db::name('shop_product_attribute')
            ->alias('a')
            ->field('a.attr_name as name, b.attr_value as value')
            ->join('__SHOP_PRODUCT_ATTR__ b', 'a.attr_id = b.attr_id', 'LEFT')
            ->where($where)
            ->order('sort_order asc')
            ->select();
        foreach ($attrData as $val) {
            $attr_value .= $val['name'].'：'.$val['value'].'<br/>';
        }
        return $attr_value;
    }

    // 产品规格处理
    public function ProductSpecProcessing($value = array())
    {
        $spec_value_s = '';
        if (!empty($value['spec_value_id'])) {
            $spec_value_id = explode('_', $value['spec_value_id']);
            if (!empty($spec_value_id)) {
                $SpecWhere = [
                    'aid'           => $value['aid'],
                    'lang'          => $this->home_lang,
                    'spec_value_id' => ['IN',$spec_value_id],
                ];
                $ProductSpecData = Db::name("product_spec_data")->where($SpecWhere)->field('spec_name,spec_value')->select();
                foreach ($ProductSpecData as $spec_value) {
                    $spec_value_s .= $spec_value['spec_name'].'：'.$spec_value['spec_value'].'<br/>';
                }
            }
        }
        return $spec_value_s;
    }

    // 产品库存处理
    public function ProductStockProcessing($SpecValue = array())
    {   
        $SpecUpData = []; // 有规格
        $ArcUpData  = []; // 无规格
        foreach ($SpecValue as $key => $value) {
            if (!empty($value['value_id'])) {
                $SpecUpData[] = [
                    'value_id'   => $value['value_id'],
                    'spec_stock' => Db::raw('spec_stock-'.($value['quantity'])),
                    'spec_sales_num' => Db::raw('spec_sales_num+'.($value['quantity'])),
                ];
                
                $ArcUpData[] = [
                    'aid'         => $value['aid'],
                    'stock_count' => Db::raw('stock_count-' . ($value['quantity'])),
                    'sales_num'   => Db::raw('sales_num+' . ($value['quantity']))
                ];
            }else{
                $ArcUpData[] = [
                    'aid'         => $value['aid'],
                    'stock_count' => Db::raw('stock_count-'.($value['quantity'])),
                    'sales_num'   => Db::raw('sales_num+' . ($value['quantity']))
                ];
            }
        }

        // 更新规格库存销量
        if (!empty($SpecUpData)) model('ProductSpecValue')->saveAll($SpecUpData);

        // 更新商品库存销量
        if (!empty($ArcUpData)) model('Archives')->saveAll($ArcUpData);
    }
}