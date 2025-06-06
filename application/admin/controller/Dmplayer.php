<?php
namespace app\admin\controller;
use think\Db;

class Dmplayer extends Base
{
    public function __construct()
    {
        parent::__construct();
		if (!class_exists('PDO')){
			die("<br><font style='color:red;'>请先安装php的pdo扩展！</font>，安装方式请自己百度</br>");  
		}
		if (!extension_loaded('pdo_sqlite') ) {
			die("<br><font style='color:red;'>请先安装php的 pdo_sqlite 扩展！</font>，安装方式请自己百度</br>");  
		}
		$this->dmplayer_Path = ROOT_PATH .'static/player/cj/dplayer/';
    }

	public function system()
	{
		$confing = $this->dmplayer_Path .'save/data.php';
		if (Request()->isPost()) {
			$param = input();
			if(empty($param['yzm'])){
				return $this->error('参数错误！');
			}
			$param['yzm']['group'] = 0;
			$param['yzm']['autoplay'] = $param['yzm']['autoplay']?$param['yzm']['autoplay']:0;
			$param['yzm']['background_color'] = '#8B008B 0%,#a87d6d 80%';
			$res = mac_arr2file($confing, $param);
			if($res===false){
				return $this->error('保存配置失败！请检查['.$confing.']文件写入权限！');
			}
			return $this->success('保存配置成功');
		}
		$group = model('Group')->getCache();
		$data = include $confing;
        $this->assign('yzm',$data['yzm']);
		$this->assign('group',$group);
        $this->assign('title','弹幕播放器设置');
        return $this->fetch('admin@dmplayer/system');		
	}


}
