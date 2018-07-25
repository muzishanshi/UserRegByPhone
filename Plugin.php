<?php
/**
 * Typecho版本的通过手机号注册用户的插件
 * @package UserRegByPhone For Typecho
 * @author 二呆
 * @version 1.0.1
 * @link http://www.tongleer.com/
 * @date 2018-5-2
 */
class UserRegByPhone_Plugin implements Typecho_Plugin_Interface
{
    // 激活插件
    public static function activate(){
        return _t('插件已经激活，需先配置信息！');
    }

    // 禁用插件
    public static function deactivate(){
		//恢复原注册页面
		if(copy(dirname(__FILE__).'/register.php',dirname(__FILE__).'/../../../'.substr(__TYPECHO_ADMIN_DIR__,1,count(__TYPECHO_ADMIN_DIR__)-2).'/register.php')){
		}
		//删除页面模板
		$db = Typecho_Db::get();
		$queryTheme= $db->select('value')->from('table.options')->where('name = ?', 'theme'); 
		$rowTheme = $db->fetchRow($queryTheme);
		@unlink(dirname(__FILE__).'/../../themes/'.$rowTheme['value'].'/page_regbysms.php');
        return _t('插件已被禁用');
    }

    // 插件配置面板
    public static function config(Typecho_Widget_Helper_Form $form){
		//表单验证
        $alidayukey = new Typecho_Widget_Helper_Form_Element_Text('alidayukey', null, '', _t('阿里云短信服务appkey：'));
        $form->addInput($alidayukey->addRule('required', _t('appkey不能为空！')));

        $alidayusecret = new Typecho_Widget_Helper_Form_Element_Text('alidayusecret', null, '', _t('阿里云短信服务appsecret：'));
        $form->addInput($alidayusecret->addRule('required', _t('appsecret不能为空！')));
		
		$isindex = new Typecho_Widget_Helper_Form_Element_Radio('isindex', array(
            'y'=>_t('存在'),
            'n'=>_t('不存在')
        ), 'y', _t('存在index.php'), _t("前台文章url是否存在index.php："));
        $form->addInput($isindex->addRule('enum', _t(''), array('y', 'n')));
		
		$templatecode = new Typecho_Widget_Helper_Form_Element_Text('templatecode', null, '', _t('阿里云短信服务模版CODE：'));
        $form->addInput($templatecode->addRule('required', _t('模版CODE不能为空！')));
		
		$signname = new Typecho_Widget_Helper_Form_Element_Text('signname', null, '', _t('阿里云短信服务签名名称：'));
        $form->addInput($signname->addRule('required', _t('签名名称不能为空！')));
	
		$alidayukey = @isset($_POST['alidayukey']) ? addslashes(trim($_POST['alidayukey'])) : '';
		$isindex = @isset($_POST['isindex']) ? addslashes(trim($_POST['isindex'])) : '';
		if($alidayukey!=''){
			//$option = self::getConfig();
			$db = Typecho_Db::get();
			//判断目录权限
			$queryTheme= $db->select('value')->from('table.options')->where('name = ?', 'theme'); 
			$rowTheme = $db->fetchRow($queryTheme);
			if(!is_writable(dirname(__FILE__).'/../../themes/'.$rowTheme['value'])){
				Typecho_Widget::widget('Widget_Notice')->set(_t('主题目录不可写，请更改目录权限。'.__TYPECHO_THEME_DIR__.'/'.$rowTheme['value']), 'success');
			}
			if(!is_writable(dirname(__FILE__).'/../../../'.substr(__TYPECHO_ADMIN_DIR__,1,count(__TYPECHO_ADMIN_DIR__)-2).'/register.php')){
				Typecho_Widget::widget('Widget_Notice')->set(_t('后台目录不可写，请更改目录权限。'.__TYPECHO_ADMIN_DIR__.'register.php'), 'success');
			}
			//如果数据表没有添加注册页面就插入
			$query= $db->select('slug')->from('table.contents')->where('template = ?', 'page_regbysms.php'); 
			$row = $db->fetchRow($query);
			if(count($row)==0){
				$contents = array(
					'title'      =>  '注册用户',
					'slug'      =>  'reg',
					'created'   =>  Typecho_Date::time(),
					'text'=>  '<!--markdown-->',
					'password'  =>  '',
					'authorId'     =>  Typecho_Cookie::get('__typecho_uid'),
					'template'     =>  'page_regbysms.php',
					'type'     =>  'page',
					'status'     =>  'hidden',
				);
				$insert = $db->insert('table.contents')->rows($contents);
				$insertId = $db->query($insert);
				$slug=$contents['slug'];
			}else{
				$slug=$row['slug'];
			}
			//如果page_regbysms.php不存在就创建
			if(!file_exists(dirname(__FILE__).'/../../themes/'.$rowTheme['value']."/page_regbysms.php")){
				$regfile = fopen(dirname(__FILE__)."/page_regbysms.php", "r") or die("不能读取page_regbysms.php文件");
				$regtext=fread($regfile,filesize(dirname(__FILE__)."/page_regbysms.php"));
				fclose($regfile);
				$regpage = fopen(dirname(__FILE__).'/../../themes/'.$rowTheme['value']."/page_regbysms.php", "w") or die("不能写入page_regbysms.php文件");
				fwrite($regpage, $regtext);
				fclose($regpage);
			}
			//将跳转新注册页面的链接写入原register.php
			$querySiteUrl= $db->select('value')->from('table.options')->where('name = ?', 'siteUrl'); 
			$rowSiteUrl = $db->fetchRow($querySiteUrl);
			if($isindex=='y'){
				$siteUrl=$rowSiteUrl['value'].'/index.php/'.$slug.'.html';
			}else{
				$siteUrl=$rowSiteUrl['value'].'/'.$slug.'.html';
			}
			$registerphp='
				<?php
				include "common.php";
				if ($user->hasLogin() || !$options->allowRegister) {
					$response->redirect($options->siteUrl);
				}else{
					header("Location: '.$siteUrl.'");
				}
				?>
			';
			$regphp = fopen(dirname(__FILE__).'/../../../'.substr(__TYPECHO_ADMIN_DIR__,1,count(__TYPECHO_ADMIN_DIR__)-2).'/register.php', "w") or die("不能写入register.php文件");
			fwrite($regphp, $registerphp);
			fclose($regphp);
		}
    }

    // 个人用户配置面板
    public static function personalConfig(Typecho_Widget_Helper_Form $form){}

    // 获得插件配置信息
    public static function getConfig(){
        return Typecho_Widget::widget('Widget_Options')->plugin('UserRegByPhone');
    }
}