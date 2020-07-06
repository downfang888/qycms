<?php
/**
 * 易优CMS
 * ============================================================================
 * 版权所有 2016-2028 海南赞赞网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.eyoucms.com
 * ----------------------------------------------------------------------------
 * 如果商业用途务必到官方购买正版授权, 以免引起不必要的法律纠纷.
 * ============================================================================
 * Author: 小虎哥 <1105415366@qq.com>
 * Date: 2018-4-3
 */

namespace app\home\controller;

use app\user\logic\PayLogic;

class Index extends Base
{
    public function _initialize() {
        parent::_initialize();
        $this->alipay_return();
        $this->Express100();
    }

    public function index()
    {
        /*处理多语言首页链接最后带斜杆，进行301跳转*/
        $lang = input('param.lang/s');
        if (preg_match("/\?lang=".$this->home_lang."\/$/i", $this->request->url(true)) && $lang == $this->home_lang.'/') {
            $langurl = $this->request->url(true);
            $langurl = rtrim($langurl, '/');
            header('HTTP/1.1 301 Moved Permanently');
            header('Location: '.$langurl);
            exit;
        }
        /*end*/

        /*首页焦点*/
        $m = input('param.m/s');
        if (empty($m)) {
            $this->request->get(['m'=>'Index']);
        }
        /*end*/
        
        $filename = 'index.html';

        $seo_pseudo = config('ey_config.seo_pseudo');
        if (file_exists($filename) && 2 == $seo_pseudo && !isset($_GET['clear'])) {
            if ((isMobile() && !file_exists('./template/mobile')) || !isMobile()) {
                header('HTTP/1.1 301 Moved Permanently');
                header('Location:index.html');
                exit;
            }
        }

        /*获取当前页面URL*/
        $result['pageurl'] = request()->url(true);
        /*--end*/
        $eyou = array(
            'field' => $result,
        );
        $this->eyou = array_merge($this->eyou, $eyou);
        $this->assign('eyou', $this->eyou);
        
        /*模板文件*/
        $viewfile = 'index';
        /*--end*/

        /*多语言内置模板文件名*/
        if (!empty($this->home_lang)) {
            $viewfilepath = TEMPLATE_PATH.$this->theme_style.DS.$viewfile."_{$this->home_lang}.".$this->view_suffix;
            if (file_exists($viewfilepath)) {
                $viewfile .= "_{$this->home_lang}";
            }
        }
        /*--end*/

        $html = $this->fetch(":{$viewfile}");
        
        return $html;
    }

    /**
     * 支付宝回调
     */
    private function alipay_return()
    {
        $param = input('param.');
        if (isset($param['transaction_type']) && isset($param['is_ailpay_notify'])) {
            // 跳转处理回调信息
            $pay_logic = new PayLogic();
            $pay_logic->alipay_return();
        }
    }

    /**
     * 快递100返回时，重定向关闭父级弹框
     */
    private function Express100()
    {
        $coname = input('param.coname/s', '');
        $m = input('param.m/s', '');
        if (!empty($coname) || 'user' == $m) {
            if (isWeixin()) {
                $this->redirect(url('user/Shop/shop_centre'));
                exit;
            }else{
                $this->redirect(url('api/Rewrite/close_parent_layer'));
                exit;
            }
        }
    }
}