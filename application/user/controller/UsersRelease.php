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
 * Date: 2019-7-3
 */

namespace app\user\controller;

use think\Db;
use think\Config;
use think\Verify;
use think\Page;
use think\Request;

/**
 * 会员投稿发布
 */
class UsersRelease extends Base
{
    public function _initialize() {
        parent::_initialize();
        $this->users_menu_db   = Db::name('users_menu');  // 会员中心栏目表
        $this->users_level_db  = Db::name('users_level'); // 会员等级表
        $this->channeltype_db  = Db::name('channeltype'); // 模型表
        $this->archives_db     = Db::name('archives');    // 文档内容表

        // 菜单名称
        $this->MenuTitle = $this->users_menu_db->where([
                'mca'   => 'user/UsersRelease/release_centre',
                'lang'  => $this->home_lang,
            ])->getField('title');
        $this->assign('MenuTitle',$this->MenuTitle);

        /*模型数据，是否开启缩略图用于会员投稿*/
        // 目前仅有文章模型用于会员投稿
        $ChannelId = request()->get('channel_id');
        if (empty($ChannelId) || !isset($ChannelId)) $ChannelId = 1;
        $ChannelData = $this->channeltype_db->where('id', $ChannelId)->field('is_release, is_litpic_users_release')->find();
        $this->assign('is_release', $ChannelData['is_release']);
        $this->assign('is_litpic_users_release', $ChannelData['is_litpic_users_release']);
        /* END */

        // 当前访问的方法名
        $Method = request()->action();
        $list   = request()->get('list');
        // 如果访问的方式是release_centre，并且没有list标识，则默认重定向至文章发布页
        // 如果$ChannelData['is_release']为空则表示模型未开启投稿
        if ('release_centre' == $Method && !empty($ChannelData['is_release']) && (empty($list) || !isset($list))) {
            $this->redirect('user/UsersRelease/article_add');
        }
        $this->assign('Method',$Method);

        // 会员投稿配置
        $this->UsersConfig = getUsersConfigData('users');
        $this->assign('UsersConfig',$this->UsersConfig);
    }

    // 会员投稿--文章首页
    public function release_centre()
    {
        $result = [];
        $condition = [
            'a.lang'     => $this->home_lang,
            'a.users_id' => $this->users_id,
            'a.is_del'   => 0,  // 伪删除至回收站
        ];
        // 分页
        $count = $this->archives_db->alias('a')->where($condition)->count();
        $Page = new Page($count, config('paginate.list_rows'));
        $show = $Page->show();
        // 数据查询
        $result['data'] = $this->archives_db
            ->field('t.*,a.*')
            ->alias('a')
            ->join('__ARCTYPE__ t', 'a.typeid = t.id', 'LEFT')
            ->where($condition)
            ->order('a.sort_order asc, a.aid desc')
            ->limit($Page->firstRow.','.$Page->listRows)
            ->select();

        $seo_pseudo = tpCache('seo.seo_pseudo');
        foreach ($result['data'] as $key => $value) {
            if ($seo_pseudo == 2) { // 生成静态模式下，以动态URL预览
                $arcurl  = arcurl('home/View/index', $value, true, true, 1, 1);
            } else {
                $arcurl  = arcurl('home/View/index', $value);
            }
            $result['data'][$key]['arcurl']  = $arcurl;
            $result['data'][$key]['editurl'] = url('user/UsersRelease/article_edit', array('aid'=>$value['aid']));
        }

        $result['delurl']  = url('user/UsersRelease/article_del');
        $eyou = array(
            'field' => $result,
        );
        // 会员投稿发布的文章状态
        $home_article_arcrank = Config::get('global.home_article_arcrank');
        $this->assign('home_article_arcrank',$home_article_arcrank);
        // 数据
        $this->assign('eyou', $eyou);
        // 分页
        $this->assign('page',$show);
        $this->assign('pager',$Page);
        return $this->fetch('users/release_centre');
    }

    // 会员投稿--文章添加
    public function article_add()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');

            // 会员投稿次数开启，查询会员投稿次数是否超过级别设置次数
            if (!empty($this->UsersConfig['is_open_posts_count'])) {
                $where = [
                    'level_id'    => $this->users['level'],
                    'posts_count' => ['<=', $this->GetOneDayReleaseCount()],
                ];
                $result = $this->users_level_db->where($where)->count();
                if (!empty($result)) {
                    $this->error('投稿次数超过会员级别限制，请先升级！');
                }
            }

            // 判断POST参数：文章标题是否为空、所属栏目是否为空、是否重复标题、是否重复提交
            $this->PostParameCheck($post);

            // POST参数处理后返回
            $post = $this->PostParameDealWith($post);

            // 拼装会员ID及用户名
            $post['users_id'] = $this->users['users_id'];
            $post['author']   = $this->users['username'];

            // 存储数据
            $newData = array(
                'typeid'      => empty($post['typeid']) ? 0 : $post['typeid'],
                'channel'     => 1,
                'lang'        => $this->home_lang,
                'sort_order'  => 100,
                'add_time'    => getTime(),
                'update_time' => getTime(),
            );
            $data = array_merge($post, $newData);
            // 添加
            $aid = $this->archives_db->insertGetId($data);
            if ($aid) {
                /*后置操作*/
                model('UsersRelease')->afterSave($aid, $data, 'add');
                /* END */
                $url = url('user/UsersRelease/release_centre',['list'=>1]);
                $this->success("投稿成功！", $url);
            }
            $this->error("投稿失败！");
        }

        // 模型ID
        $channel_id = 1; //input('get.channel_id');

        // 获取加载到页面的数据
        $assign_data = $this->GetAssignData($channel_id);

        // 加载数据
        $this->assign($assign_data);
        return $this->fetch('users/article_add');
    }

    // 会员投稿--文章编辑
    public function article_edit()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            // 判断POST参数：文章标题是否为空、所属栏目是否为空、是否重复标题、是否重复提交
            $this->PostParameCheck($post);

            // POST参数处理后返回
            $post = $this->PostParameDealWith($post, 'edit');

            // 更新数据
            $newData = array(
                'typeid'      => empty($post['typeid']) ? 0 : $post['typeid'],
                'channel'     => 1,
                'update_time' => getTime(),
            );
            $data = array_merge($post, $newData);
            // 更新条件
            $where = [
                'aid'      => $data['aid'],
                'lang'     => $this->home_lang,
                'users_id' => $this->users_id,
            ];
            $r = $this->archives_db->where($where)->update($data);
            if ($r) {
                /*后置操作*/
                model('UsersRelease')->afterSave($data['aid'], $data, 'edit');
                /* END */
                $url = url('user/UsersRelease/release_centre',['list'=>1]);
                $this->success("编辑成功！", $url);
            }
            $this->error("编辑失败！");
        }

        // 模型ID
        $channel_id = 1; // input('get.channel_id');
        // 文章ID
        $aid  = input('get.aid');
        $info = model('UsersRelease')->getInfo($aid, null, false);

        // 获取加载到页面的数据
        $assign_data = $this->GetAssignData($channel_id, $aid, $info);

        // 拼装文章数据
        $assign_data['ArchivesData'] = $info;

        // 加载数据
        $this->assign($assign_data);
        return $this->fetch('users/article_edit');
    }

    // 会员投稿--文章删除
    public function article_del()
    {
        if(IS_AJAX_POST){
            $del_id = input('del_id/a');
            $aids   = eyIntval($del_id);
            if(!empty($del_id)){
                $Where = [
                    'aid'  => ['IN', $aids],
                    'lang' => $this->home_lang,
                    'users_id' => $this->users_id,
                ];
                $return = $this->archives_db->where($Where)->delete();
                if ($return) {
                    $this->success('删除成功');
                }else{
                    $this->error('删除失败');
                }
            }
        }
    }

    // 处理加载到页面的数据
    private function GetAssignData($channel_id = null, $aid = null, $info = array())
    {
        /* 自定义字段 */
        if (!empty($aid) && !empty($info)) {
            $addonFieldExtList = model('UsersRelease')->GetUsersReleaseData($channel_id, $info['typeid'] ,$aid , 'edit');
        }else{
            $addonFieldExtList = model('UsersRelease')->GetUsersReleaseData($channel_id);
        }
        $assign_data['addonFieldExtList'] = $addonFieldExtList;
        /* END */

        /*允许发布文档列表的栏目*/
        $typeid = 0;
        if (!empty($info['typeid'])) {
            $typeid = $info['typeid'];
        }
        $arctype_html = $this->allow_release_arctype($typeid, $channel_id);
        $assign_data['arctype_html'] = $arctype_html;
        /* END */

        /*封装表单验证隐藏域*/
        static $request = null;
        if (null == $request) { $request = Request::instance(); }  
        $token = $request->token();
        $assign_data['TokenValue'] = " <input type='hidden' name='__token__' value='{$token}'/> ";
        /* END */

        return $assign_data;
    }

    // 判断POST参数：文章标题是否为空、所属栏目是否为空、是否重复标题、是否重复提交
    private function PostParameCheck($post = array(), $channel_id = 1)
    {
        if (empty($post)) $this->error('提交错误！', null, 'title');

        // 判断文章标题是否为空
        if (empty($post['title'])) $this->error('请填写文章标题！', null, 'title');

        // 判断所属栏目是否为空
        if (empty($post['typeid'])) $this->error('请选择所属栏目！', null, 'typeid');

        // 如果模型不允许重复标题则执行
        $is_repeat_title = $this->channeltype_db->where('id',$channel_id)->getField('is_repeat_title');
        if (empty($is_repeat_title)) {
            $where = [
                'title' => $post['title'],
                // 'channel' => $channel_id,
            ];
            if (!empty($post['aid'])) {
                $where['aid'] = ['NOT IN',$post['aid']];
            }
            $count = $this->archives_db->where($where)->count();
            if(!empty($count)) $this->error('文档标题不允许重复！', null, 'title');
        }

        // 数据验证
        $rule = [
            'title' => 'require|token',
        ];
        $message = [
            'title.require' => '不可为空！',
        ];
        $validate = new \think\Validate($rule, $message);
        if(!$validate->check($post)) $this->error('不允许连续提交！');
    }

    // POST参数处理后返回
    private function PostParameDealWith($post = array(), $type = 'add')
    {
        // 内容详情
        $content = input('post.addonFieldExt.content', '', null);

        // 自动获取内容第一张图片作为封面图
        $litpic = $post['litpic_inpiut'];
        if (empty($litpic)) {
            $litpic = get_html_first_imgurl($content);
        }
        $post['litpic'] = $litpic;

        /*是否有封面图*/
        if (empty($post['litpic'])) {
            $post['is_litpic'] = 0; // 无封面图
        } else {
            $post['is_litpic'] = 1; // 有封面图
        }

        // SEO描述
        if ('add' == $type && !empty($content)) {
            $post['seo_description']= @msubstr(checkStrHtml($content), 0, config('global.arc_seo_description_length'), false);
        }

        $post['addonFieldExt']['content'] = htmlspecialchars(strip_sql($content));

        if (empty($this->UsersConfig['is_automatic_review'])) {
            // 自动审核关闭，文章默认待审核
            $post['arcrank']  = -1; // 待审核
        }else{
            // 自动审核开启，文章默认已审核
            $post['arcrank']  = 0;  // 已审核
        }

        return $post;
    }

    // 计算获取一天内的投稿文档合计次数
    private function GetOneDayReleaseCount()
    {
        $time1 = strtotime(date('Y-m-d', time()));
        $time2 = $time1 + 86399;
        $where = [
            'lang'     => $this->home_lang,
            'users_id' => $this->users_id,
            'add_time' => ['between time', [$time1, $time2]],
        ];
        $AidCount = $this->archives_db->where($where)->count();
        return $AidCount;
    }

    /**
     * 允许会员投稿发布的栏目列表
     */
    private function allow_release_arctype($typeid = 0, $channel_id = null)
    {
        $where['lang']   = $this->home_lang; // 多语言 by 小虎哥
        $where['is_del'] = 0; // 回收站功能
        $where['status'] = 1; 
        $where['is_release'] = 1; // 查询应用于投稿的栏目
        $where['current_channel'] = $channel_id;
        $field = 'id, parent_id, typename, is_release';

        // 查询所有可投稿的栏目
        $ArcTypeData = Db::name('arctype')->field($field)->where($where)->select();

        // 读取上级ID并去重读取上级栏目
        $ParentIds = array_unique(get_arr_column($ArcTypeData, 'parent_id'));
        $PidData = Db::name('arctype')->field($field)->where('id', 'IN', $ParentIds)->select();

        // 处理顶级栏目
        $PidDataNew = [];
        foreach ($ArcTypeData as $key => $value) {
            if (empty($value['parent_id'])) {
                array_push($PidDataNew, $value);
            }
        }
        // 合并顶级栏目
        $PidData = array_merge($PidData, $PidDataNew);
        
        // 下拉框拼装
        $HtmlCode = '<select name="typeid" id="typeid">';
        $HtmlCode .= '<option value="0">请选择栏目</option>';
        foreach ($PidData as $yik => $yiv) {
            /*是否禁用*/
            $style0 = 0 == $yiv['is_release'] ? 'disabled="true"' : '';
            /* END */
            /*是否选中*/
            $style1 = $typeid == $yiv['id'] ? 'selected' : '';
            /* END */
            if (0 == $yiv['parent_id']) {
                /*一级下拉框*/
                $HtmlCode .= '<option value="'.$yiv['id'].'" '.$style0.' '.$style1.'>'.$yiv['typename'].'</option>';
                /* END */
                $type = 0;
            }else{
                /*二级下拉框*/
                $HtmlCode .= '<option value="'.$yiv['id'].'" '.$style0.' '.$style1.'>&nbsp; &nbsp;'.$yiv['typename'].'</option>';
                /* END */
                $type = 1;
            }

            foreach ($ArcTypeData as $erk => $erv) {
                if ($erv['parent_id'] == $yiv['id']) {
                    if (0 == $type) {
                        /*是否禁用*/
                        $style0 = 0 == $erv['is_release'] ? 'disabled="true"' : '';
                        /* END */
                        /*是否选中*/
                        $style1 = $typeid == $erv['id'] ? 'selected' : '';
                        /* END */
                        /*二级下拉框*/
                        $HtmlCode .= '<option value="'.$erv['id'].'" '.$style0.' '.$style1.'>&nbsp; &nbsp;'.$erv['typename'].'</option>';
                        /* END */
                    }else{
                        /*是否选中*/
                        $style0 = $typeid == $erv['id'] ? 'selected' : '';
                        /* END */
                        /*三级下拉框*/
                        $HtmlCode .= '<option value="'.$erv['id'].'" '.$style0.'>&nbsp; &nbsp; &nbsp; &nbsp;'.$erv['typename'].'</option>';
                        /* END */
                    }
                }
            }
        }

        $HtmlCode .= '</select>';
        return $HtmlCode;
    }
}