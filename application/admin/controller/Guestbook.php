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

namespace app\admin\controller;

use think\Page;
use think\Db;
use app\common\logic\ArctypeLogic;

class Guestbook extends Base
{
    // 模型标识
    public $nid = 'guestbook';
    // 模型ID
    public $channeltype = '';
    // 表单类型
    public $attrInputTypeArr = array();

    public function _initialize()
    {
        parent::_initialize();
        $channeltype_list       = config('global.channeltype_list');
        $this->channeltype      = $channeltype_list[$this->nid];
        $this->attrInputTypeArr = config('global.guestbook_attr_input_type');
    }

    /**
     * 留言列表
     */
    public function index()
    {
        $assign_data = array();
        $condition   = array();
        // 获取到所有GET参数
        $get    = input('get.');
        $typeid = input('typeid/d');
        $begin    = strtotime(input('param.add_time_begin/s'));
        $end    = input('param.add_time_end/s');
        !empty($end) && $end .= ' 23:59:59';
        $end    = strtotime($end);

        // 应用搜索条件
        foreach (['keywords', 'typeid'] as $key) {
            if (isset($get[$key]) && $get[$key] !== '') {
                if ($key == 'keywords') {
                    $attr_row           = Db::name('guestbook_attr')->field('aid')->where(array('attr_value' => array('LIKE', "%{$get[$key]}%")))->group('aid')->getAllWithIndex('aid');
                    $aids               = array_keys($attr_row);
                    $condition['a.aid'] = array('IN', $aids);
                } else if ($key == 'typeid') {
                    $condition['a.typeid'] = array('eq', $get[$key]);
                } else {
                    $condition['a.' . $key] = array('eq', $get[$key]);
                }
            }
        }

        // 时间检索
        if ($begin > 0 && $end > 0) {
            $condition['a.add_time'] = array('between',"$begin,$end");
        } else if ($begin > 0) {
            $condition['a.add_time'] = array('egt', $begin);
        } else if ($end > 0) {
            $condition['a.add_time'] = array('elt', $end);
        }

        // 多语言
        $condition['a.lang'] = array('eq', $this->admin_lang);

        /**
         * 数据查询，搜索出主键ID的值
         */
        $count = DB::name('guestbook')->alias('a')->where($condition)->count('aid');// 查询满足要求的总记录数
        $Page  = new Page($count, config('paginate.list_rows'));// 实例化分页类 传入总记录数和每页显示的记录数
        $list  = DB::name('guestbook')
            ->field("b.*, a.*")
            ->alias('a')
            ->join('__ARCTYPE__ b', 'a.typeid = b.id', 'LEFT')
            ->where($condition)
            ->order('a.add_time desc')
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->getAllWithIndex('aid');

        /**
         * 完善数据集信息
         * 在数据量大的情况下，经过优化的搜索逻辑，先搜索出主键ID，再通过ID将其他信息补充完整；
         */
        if ($list) {
            $where = [
                'b.aid'     => ['IN', array_keys($list)],
                'a.is_showlist' => 1,
                'a.lang'    => $this->admin_lang,
                'a.is_del'  => 0,
            ];
            $row       = DB::name('guestbook_attribute')
                ->field('a.attr_name, b.attr_value, b.aid, b.attr_id')
                ->alias('a')
                ->join('__GUESTBOOK_ATTR__ b', 'b.attr_id = a.attr_id', 'LEFT')
                ->where($where)
                ->order('b.aid desc, a.sort_order asc, a.attr_id asc')
                ->getAllWithIndex();
            $attr_list = array();
            foreach ($row as $key => $val) {
                if (preg_match('/(\.(jpg|gif|png|bmp|jpeg|ico|webp))$/i', $val['attr_value'])) {
                    if (!stristr($val['attr_value'], '|')) {
                        $val['attr_value'] = handle_subdir_pic($val['attr_value']);
                        $val['attr_value'] = "<img src='{$val['attr_value']}' width='60' height='60' style='float: unset;cursor: pointer;' onclick=\"Images('{$val['attr_value']}', 650, 350);\" />";
                    }
                } else {
                    $val['attr_value'] = str_replace(PHP_EOL, ' | ', $val['attr_value']);
                }
                $attr_list[$val['aid']][] = $val;
            }
            foreach ($list as $key => $val) {
                $list[$key]['attr_list'] = isset($attr_list[$val['aid']]) ? $attr_list[$val['aid']] : array();
            }
        }
        $assign_data['tab_list'] = Db::name('guestbook_attribute')->where([
                'typeid' => $typeid,
                'is_showlist' => 1,
                'lang'   => $this->admin_lang,
                'is_del'    => 0,
            ])->order('sort_order asc, attr_id asc')->select();
        $show                    = $Page->show(); // 分页显示输出
        $assign_data['page']     = $show; // 赋值分页输出
        $assign_data['list']     = $list; // 赋值数据集
        $assign_data['pager']    = $Page; // 赋值分页对象

        // 栏目ID
        $assign_data['typeid'] = $typeid; // 栏目ID
        /*当前栏目信息*/
        $arctype_info = array();
        if ($typeid > 0) {
            $arctype_info = Db::name('arctype')->field('typename')->find($typeid);
        }
        $assign_data['arctype_info'] = $arctype_info;
        /*--end*/

        /*选项卡*/
        $tab                = input('param.tab/d', 3);
        $assign_data['tab'] = $tab;
        /*--end*/

        $this->assign($assign_data);
        return $this->fetch();
    }

    /**
     * 删除
     */
    public function del()
    {
        $id_arr = input('del_id/a');
        $id_arr = eyIntval($id_arr);
        if (!empty($id_arr)) {
            $r = Db::name('guestbook')->where([
                'aid'  => ['IN', $id_arr],
                'lang' => $this->admin_lang,
            ])->delete();
            if ($r) {
                // ---------后置操作
                model('Guestbook')->afterDel($id_arr);
                // ---------end
                adminLog('删除留言-id：' . implode(',', $id_arr));
                $this->success('删除成功');
            } else {
                $this->error('删除失败');
            }
        } else {
            $this->error('参数有误');
        }
    }

    /**
     * 留言表单表单列表
     */
    public function attribute_index()
    {
        $assign_data = array();
        $condition = array();
        // 获取到所有GET参数
        $get = input('get.');
        $typeid = input('typeid/d');

        // 应用搜索条件
        foreach (['keywords','typeid'] as $key) {
            if (isset($get[$key]) && $get[$key] !== '') {
                if ($key == 'keywords') {
                    $condition['a.attr_name'] = array('LIKE', "%{$get[$key]}%");
                } else if ($key == 'typeid') {
                    $typeids = model('Arctype')->getHasChildren($get[$key]);
                    $condition['a.typeid'] = array('IN', array_keys($typeids));
                } else {
                    $condition['a.'.$key] = array('eq', $get[$key]);
                }
            }
        }

        $condition['b.id'] = ['gt', 0];
        $condition['a.is_del'] = 0;
        // 多语言
        $condition['a.lang'] = $this->admin_lang;

        /**
         * 数据查询，搜索出主键ID的值
         */
        $count = DB::name('guestbook_attribute')->alias('a')
            ->join('__ARCTYPE__ b', 'a.typeid = b.id', 'LEFT')
            ->where($condition)
            ->count();// 查询满足要求的总记录数
        $Page = new Page($count, config('paginate.list_rows'));// 实例化分页类 传入总记录数和每页显示的记录数
        $list = DB::name('guestbook_attribute')
            ->field("a.attr_id")
            ->alias('a')
            ->join('__ARCTYPE__ b', 'a.typeid = b.id', 'LEFT')
            ->where($condition)
            ->order('a.typeid desc, a.sort_order asc, a.attr_id asc')
            ->limit($Page->firstRow.','.$Page->listRows)
            ->getAllWithIndex('attr_id');

        /**
         * 完善数据集信息
         * 在数据量大的情况下，经过优化的搜索逻辑，先搜索出主键ID，再通过ID将其他信息补充完整；
         */
        if ($list) {
            $attr_ida = array_keys($list);
            $fields = "b.*, a.*";
            $row = DB::name('guestbook_attribute')
                ->field($fields)
                ->alias('a')
                ->join('__ARCTYPE__ b', 'a.typeid = b.id', 'LEFT')
                ->where('a.attr_id', 'in', $attr_ida)
                ->getAllWithIndex('attr_id');
            
            /*获取多语言关联绑定的值*/
            $row = model('LanguageAttr')->getBindValue($row, 'guestbook_attribute', $this->main_lang); // 多语言
            /*--end*/

            foreach ($row as $key => $val) {
                $val['fieldname'] = 'attr_'.$val['attr_id'];
                $row[$key] = $val;
            }
            foreach ($list as $key => $val) {
                $list[$key] = $row[$val['attr_id']];
            }
        }
        $show = $Page->show(); // 分页显示输出
        $assign_data['page'] = $show; // 赋值分页输出
        $assign_data['list'] = $list; // 赋值数据集
        $assign_data['pager'] = $Page; // 赋值分页对象

        /*获取当前模型栏目*/
        $select_html = allow_release_arctype($typeid, array($this->channeltype));
        $typeidNum = substr_count($select_html, '</option>');
        $this->assign('select_html',$select_html);
        $this->assign('typeidNum',$typeidNum);
        /*--end*/

        // 栏目ID
        $assign_data['typeid'] = $typeid; // 栏目ID
        /*当前栏目信息*/
        $arctype_info = array();
        if ($typeid > 0) {
            $arctype_info = Db::name('arctype')->field('typename')->find($typeid);
        }
        $assign_data['arctype_info'] = $arctype_info;
        /*--end*/

        /*选项卡*/
        $tab                = input('param.tab/d', 3);
        $assign_data['tab'] = $tab;
        /*--end*/

        $assign_data['attrInputTypeArr'] = $this->attrInputTypeArr; // 表单类型
        $this->assign($assign_data);
        return $this->fetch();
    }

    /**
     * 新增留言表单
     */
    public function attribute_add()
    {
        //防止php超时
        function_exists('set_time_limit') && set_time_limit(0);
        
        $this->language_access(); // 多语言功能操作权限

        if(IS_AJAX && IS_POST)//ajax提交验证
        {
            $model = model('GuestbookAttribute');

            $attr_values = str_replace('_', '', input('attr_values')); // 替换特殊字符
            $attr_values = str_replace('@', '', $attr_values); // 替换特殊字符
            $attr_values = trim($attr_values);

            /*过滤重复值*/
            $attr_values_arr = explode(PHP_EOL, $attr_values);
            foreach ($attr_values_arr as $key => $val) {
                $tmp_val = trim($val);
                if (empty($tmp_val)) {
                    unset($attr_values_arr[$key]);
                    continue;
                }
                $attr_values_arr[$key] = $tmp_val;
            }
            $attr_values_arr = array_unique($attr_values_arr);
            $attr_values = implode(PHP_EOL, $attr_values_arr);
            /*end*/
            
            $post_data = input('post.');
            $post_data['attr_values'] = $attr_values;
            $attr_input_type = isset($post_data['attr_input_type']) ? $post_data['attr_input_type'] : 0;

            /*前台输入是否JS验证*/
            $validate_type = 0;
            $validate_type_list = config("global.validate_type_list"); // 前台输入验证类型
            if (!empty($validate_type_list[$attr_input_type])) {
                $validate_type = $attr_input_type;
            }
            /*end*/

            $savedata = array(
                'attr_name'       => $post_data['attr_name'],
                'typeid'          => $post_data['typeid'],
                'attr_input_type' => $attr_input_type,
                'attr_values'     => isset($post_data['attr_values']) ? $post_data['attr_values'] : '',
                'is_showlist'     => $post_data['is_showlist'],
                'required'        => $post_data['required'],
                'validate_type'   => $validate_type,
                'sort_order'      => 100,
                'lang'            => $this->admin_lang,
                'add_time'        => getTime(),
                'update_time'     => getTime(),
            );

            // 数据验证            
            $validate = \think\Loader::validate('GuestbookAttribute');
            if(!$validate->batch()->check($savedata))
            {
                $error = $validate->getError();
                $error_msg = array_values($error);
                $return_arr = array(
                    'status' => -1,
                    'msg' => $error_msg[0],
                    'data' => $error,
                );
                respose($return_arr);
            } else {
                $model->data($savedata,true); // 收集数据
                $model->save(); // 写入数据到数据库
                $insertId = $model->getLastInsID();

                /*同步留言属性ID到多语言的模板变量里*/
                model('GuestbookAttribute')->syn_add_language_attribute($insertId);
                /*--end*/

                $return_arr = array(
                     'status' => 1,
                     'msg'   => '操作成功',                        
                     'data'  => array('url'=>url('Guestbook/attribute_index', array('typeid'=>$post_data['typeid']))),
                );
                adminLog('新增留言表单：'.$savedata['attr_name']);
                respose($return_arr);
            }
        }

        $typeid = input('param.typeid/d', 0);
        if ($typeid > 0) {
            $select_html = Db::name('arctype')->where('id', $typeid)->getField('typename');
            $select_html = !empty($select_html) ? $select_html : '该栏目不存在';
        } else {
            $arctypeLogic      = new ArctypeLogic();
            $map               = array(
                'channeltype' => $this->channeltype,
            );
            $arctype_max_level = intval(config('global.arctype_max_level'));
            $select_html       = $arctypeLogic->arctype_list(0, $typeid, true, $arctype_max_level, $map);
        }
        $assign_data['select_html'] = $select_html; //
        $assign_data['typeid']      = $typeid; // 栏目ID

        $assign_data['attrInputTypeArr'] = $this->attrInputTypeArr; // 表单类型

        $this->assign($assign_data);
        return $this->fetch();
    }

    /**
     * 编辑留言表单
     */
    public function attribute_edit()
    {
        if(IS_AJAX && IS_POST)//ajax提交验证
        {
            $model = model('GuestbookAttribute');

            $attr_values = str_replace('_', '', input('attr_values')); // 替换特殊字符
            $attr_values = str_replace('@', '', $attr_values); // 替换特殊字符
            $attr_values = trim($attr_values);

            /*过滤重复值*/
            $attr_values_arr = explode(PHP_EOL, $attr_values);
            foreach ($attr_values_arr as $key => $val) {
                $tmp_val = trim($val);
                if (empty($tmp_val)) {
                    unset($attr_values_arr[$key]);
                    continue;
                }
                $attr_values_arr[$key] = $tmp_val;
            }
            $attr_values_arr = array_unique($attr_values_arr);
            $attr_values = implode(PHP_EOL, $attr_values_arr);
            /*end*/
            
            $post_data = input('post.');
            $post_data['attr_values'] = $attr_values;
            $attr_input_type = isset($post_data['attr_input_type']) ? $post_data['attr_input_type'] : 0;

            /*前台输入是否JS验证*/
            $validate_type = 0;
            $validate_type_list = config("global.validate_type_list"); // 前台输入验证类型
            if (!empty($validate_type_list[$attr_input_type])) {
                $validate_type = $attr_input_type;
            }
            /*end*/

            $savedata = array(
                'attr_id'         => $post_data['attr_id'],
                'attr_name'       => $post_data['attr_name'],
                'typeid'          => $post_data['typeid'],
                'attr_input_type' => $attr_input_type,
                'attr_values'     => isset($post_data['attr_values']) ? $post_data['attr_values'] : '',
                'is_showlist'     => $post_data['is_showlist'],
                'required'        => $post_data['required'],
                'validate_type'   => $validate_type,
                'sort_order'      => 100,
                'update_time'     => getTime(),
            );
            // 数据验证            
            $validate = \think\Loader::validate('GuestbookAttribute');
            if(!$validate->batch()->check($savedata))
            {
                $error      = $validate->getError();
                $error_msg  = array_values($error);
                $return_arr = array(
                    'status' => -1,
                    'msg'    => $error_msg[0],
                    'data'   => $error,
                );
                respose($return_arr);
            } else {
                $model->data($savedata, true); // 收集数据
                $model->isUpdate(true, [
                    'attr_id' => $post_data['attr_id'],
                    'lang'    => $this->admin_lang,
                ])->save(); // 写入数据到数据库
                $return_arr = array(
                    'status' => 1,
                    'msg'    => '操作成功',
                    'data'   => array('url' => url('Guestbook/attribute_index', array('typeid' => $post_data['typeid']))),
                );
                adminLog('编辑留言表单：' . $savedata['attr_name']);
                respose($return_arr);
            }
        }

        $assign_data = array();

        $id = input('id/d');
        /*获取多语言关联绑定的值*/
        $new_id = model('LanguageAttr')->getBindValue($id, 'guestbook_attribute'); // 多语言
        !empty($new_id) && $id = $new_id;
        /*--end*/
        $info = Db::name('GuestbookAttribute')->where([
            'attr_id' => $id,
            'lang'    => $this->admin_lang,
        ])->find();
        if (empty($info)) {
            $this->error('数据不存在，请联系管理员！');
            exit;
        }
        $assign_data['field'] = $info;

        // 所在栏目
        $select_html                = Db::name('arctype')->where('id', $info['typeid'])->getField('typename');
        $select_html                = !empty($select_html) ? $select_html : '该栏目不存在';
        $assign_data['select_html'] = $select_html;

        $assign_data['attrInputTypeArr'] = $this->attrInputTypeArr; // 表单类型

        $this->assign($assign_data);
        return $this->fetch();
    }
    
    /**
     * 删除留言表单
     */
    public function attribute_del()
    {
        $this->language_access(); // 多语言功能操作权限

        $id_arr = input('del_id/a');
        $id_arr = eyIntval($id_arr);
        if (!empty($id_arr)) {
            /*多语言*/
            if (is_language()) {
                $attr_name_arr = [];
                foreach ($id_arr as $key => $val) {
                    $attr_name_arr[] = 'attr_' . $val;
                }
                $new_id_arr = Db::name('language_attr')->where([
                    'attr_name'  => ['IN', $attr_name_arr],
                    'attr_group' => 'guestbook_attribute',
                ])->column('attr_value');
                !empty($new_id_arr) && $id_arr = $new_id_arr;
            }
            /*--end*/
            $r = Db::name('GuestbookAttribute')->where([
                'attr_id' => ['IN', $id_arr],
            ])->update([
                'is_del'      => 1,
                    'update_time'   => getTime(),
                ]);
            if($r){
                adminLog('删除留言表单-id：'.implode(',', $id_arr));
                $this->success('删除成功');
            }else{
                $this->error('删除失败');
            }
        }else{
            $this->error('参数有误');
        }
    }

    /**
     * 查看详情
     */
    public function details()
    {
        $aid = input('param.aid/d');

        // 标记为已读和IP地区
        $row = Db::name('guestbook')->find($aid);
        $city = "";
        $city_arr = getCityLocation($row['ip']);
        if (!empty($city_arr)) {
            !empty($city_arr['location']) && $city .= $city_arr['location'];
        }
        $row['city'] = $city;
        $this->assign('row', $row);

        // 留言属性
        $condition['a.aid'] = $aid;
        $condition['a.lang'] =  $this->admin_lang;
        $attr_list = Db::name('guestbook_attr')
            ->field("b.*, a.*")
            ->alias('a')
            ->join('__GUESTBOOK_ATTRIBUTE__ b', 'a.attr_id = b.attr_id', 'LEFT')
            ->where($condition)
            ->order('a.attr_id asc')
            ->select();
        foreach ($attr_list as $key => &$val) {
            if (preg_match('/(\.(jpg|gif|png|bmp|jpeg|ico|webp))$/i', $val['attr_value'])) {
                if (!stristr($val['attr_value'], '|')) {
                    $val['attr_value'] = handle_subdir_pic($val['attr_value']);
                    $val['attr_value'] = "<a href='{$val['attr_value']}' target='_blank'><img src='{$val['attr_value']}' width='60' height='60' style='float: unset;cursor: pointer;' /></a>";
                }
            }elseif (preg_match('/(\.('.tpCache('basic.file_type').'))$/i', $val['attr_value'])){
                if (!stristr($val['attr_value'], '|')) {
                    $val['attr_value'] = handle_subdir_pic($val['attr_value']);
                    $val['attr_value'] = "<a href='{$val['attr_value']}' download='".time()."'><img src=\"".ROOT_DIR."/public/static/common/images/file.png\" alt=\"\" style=\"width: 16px;height:  16px;\">点击下载</a>";
                }
            }
        }
        $this->assign('attr_list', $attr_list);

        return $this->fetch();
    }

    /**    
     * excel导出
     */
    public function excel_export()
    {
        $id_arr          = input('aid/s');
        if (!empty($id_arr)) {
            $id_arr          = explode(',', $id_arr);
            $id_arr          = eyIntval($id_arr);
        }
        $typeid          = input('typeid/d');
        $start_time      = input('start_time/s');
        $end_time        = input('end_time/s');

        $strTable        = '<table width="500" border="1">';
        $where           = [];
        $where['typeid'] = $typeid;
        $where['lang']   = $this->admin_lang;
        $order           = 'add_time asc';
        //没有指定ID就导出全部
        if (!empty($id_arr)) {
            $where['aid'] = ['IN', $id_arr];
        }
        //根据日期导出
        if (!empty($start_time) && !empty($end_time)) {
            $start_time        = strtotime($start_time);
            $end_time          = strtotime("+1 day", strtotime($end_time)) - 1;
            $where['add_time'] = ['between', [$start_time, $end_time]];
        } elseif (!empty($start_time) && empty($end_time)) {
            $start_time        = strtotime($start_time);
            $where['add_time'] = ['>=', $start_time];
        } elseif (empty($start_time) && !empty($end_time)) {
            $end_time          = strtotime("+1 day", strtotime($end_time)) - 1;
            $where['add_time'] = ['<=', $end_time];
        }
        $row = Db::name('guestbook')->where($where)->order($order)->select();

        $title = Db::name('guestbook_attribute')->where([
                'typeid' => $typeid,
                'lang'   => $this->admin_lang,
                'is_del'    => 0,
            ])->order('sort_order asc, attr_id asc')->select();

        if ($row && $title) {
            $strTable .= '<tr>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">ID</td>';
            foreach ($title as &$key) {
                $strTable .= '<td style="text-align:center;font-size:12px;" width="*">' . $key['attr_name'] . '</td>';
            }
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">新增时间</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">更新时间</td>';
            $strTable .= '</tr>';

            foreach ($row as &$val) {
                $attr_value = Db::name('guestbook_attr')
                    ->where(['aid' => $val['aid'], 'lang' => $this->admin_lang])
                    ->getAllWithIndex('attr_id');
                $strTable   .= '<tr>';
                $strTable   .= '<td style="text-align:center;font-size:12px;">' . $val['aid'] . '</td>';
                foreach ($title as &$key) {
                    $strTable .= '<td style="text-align:center;font-size:12px;" style=\'vnd.ms-excel.numberformat:@\' width="*">' . $attr_value[$key['attr_id']]['attr_value'] . '</td>';
                }
                $strTable .= '<td style="text-align:left;font-size:12px;">' . date('Y-m-d H:i:s', $val['add_time']) . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . date('Y-m-d H:i:s', $val['update_time']) . '</td>';
                $strTable .= '</tr>';
            }
        }
        $strTable .= '</table>';
        downloadExcel($strTable, 'guestbook');
        exit();
    }
}