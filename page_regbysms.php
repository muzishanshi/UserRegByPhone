<?php
/**
 * 手机注册页面
 *
 * @package custom
 */
?>
<?php session_start();if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php
$db = Typecho_Db::get();
$action = isset($_POST['action']) ? addslashes(trim($_POST['action'])) : '';
/** 如果已经登录 */
if ($this->user->hasLogin()) {
	/** 直接返回 */
	$this->response->redirect($this->options->index);
}
if($action=='regbysms'){
	$name = isset($_POST['name']) ? addslashes(trim($_POST['name'])) : '';
	$code = isset($_POST['code']) ? addslashes(trim($_POST['code'])) : '';
	if($name&&$code){
		$sessionCode = isset($_SESSION['code']) ? $_SESSION['code'] : '';
		if(strcasecmp($code,$sessionCode)==0){
			$query= $db->select('uid')->from('table.users')->where('name = ?', $name); 
			$user = $db->fetchRow($query);
			if($user){
				/*登录*/
				$authCode = function_exists('openssl_random_pseudo_bytes') ?
                    bin2hex(openssl_random_pseudo_bytes(16)) : sha1(Typecho_Common::randString(20));
                $user['authCode'] = $authCode;

                Typecho_Cookie::set('__typecho_uid', $user['uid'], 0);
                Typecho_Cookie::set('__typecho_authCode', Typecho_Common::hash($authCode), 0);

                /*更新最后登录时间以及验证码*/
                $db->query($db
                ->update('table.users')
                ->expression('logged', 'activated')
                ->rows(array('authCode' => $authCode))
                ->where('uid = ?', $user['uid']));
				
				/*压入数据*/
				$this->push($user);
				$this->_user = $user;
				$this->_hasLogin = true;
				$this->pluginHandle()->loginSucceed($this, $name, '', false);
				
				/*重置短信验证码*/
				$randCode = '';
				$chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHIJKLMNPRSTUVWXYZ23456789';
				for ( $i = 0; $i < 5; $i++ ){
					$randCode .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
				}
				$_SESSION['code'] = strtoupper($randCode);

				$this->widget('Widget_Notice')->set(_t('用户已存在，已为您登录 '), 'success');
				/*跳转验证后地址*/
				if (NULL != $this->request->referer) {
					$this->response->redirect($this->request->referer);
				} else if (!$this->user->pass('contributor', true)) {
					/*不允许普通用户直接跳转后台*/
					$this->response->redirect($this->options->profileUrl);
				} else {
					$this->response->redirect($this->options->adminUrl);
				}
			}else{
				/*注册*/
				/** 如果已经登录 */
				if ($this->user->hasLogin() || !$this->options->allowRegister) {
					/** 直接返回 */
					$this->response->redirect($this->options->index);
				}
				$hasher = new PasswordHash(8, true);
				$generatedPassword = Typecho_Common::randString(7);

				$dataStruct = array(
					'name'      =>  $name,
					'mail'      =>  $name.'@tongleer.com',
					'screenName'=>  $name,
					'password'  =>  $hasher->HashPassword($generatedPassword),
					'created'   =>  $this->options->time,
					'group'     =>  'subscriber'
				);
				
				$insert = $db->insert('table.users')->rows($dataStruct);
				$insertId = $db->query($insert);

				$this->pluginHandle()->finishRegister($this);

				$this->user->login($name, $generatedPassword);

				Typecho_Cookie::delete('__typecho_first_run');
				
				/*重置短信验证码*/
				$randCode = '';
				$chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHIJKLMNPRSTUVWXYZ23456789';
				for ( $i = 0; $i < 5; $i++ ){
					$randCode .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
				}
				$_SESSION['code'] = strtoupper($randCode);

				$this->widget('Widget_Notice')->set(_t('用户 <strong>%s</strong> 已经成功注册, 密码为 <strong>%s</strong>', $this->screenName, $generatedPassword), 'success');
				$this->response->redirect($this->options->adminUrl);
			}
		}else{
			echo'<script>alert("验证码错误！");</script>';
		}
	}
}
?>
<?php $this->need('header.php'); ?>
<link rel="stylesheet" href="//cdn.bootcss.com/mdui/0.4.1/css/mdui.min.css">
<script src="//cdn.bootcss.com/mdui/0.4.1/js/mdui.min.js"></script>
<!-- content section -->
<section>
	<div class="mdui-shadow-10 mdui-center" style="width:300px;">
		<div class="mdui-typo mdui-valign mdui-color-blue mdui-text-color-white">
		  <h6 class="mdui-center">用户注册</h6>
		</div>
		<form action="" method="post" class="mdui-p-x-1 mdui-p-y-1">
			<div class="mdui-textfield mdui-textfield-floating-label">
			  <label class="mdui-textfield-label"><?php _e('手机号'); ?></label>
			  <input class="mdui-textfield-input" id="name" name="name" type="text" required value="<?php echo @$_POST['name']; ?>"/>
			  <div class="mdui-textfield-error">手机号不能为空</div>
			</div>
			<div class="mdui-textfield mdui-textfield-floating-label">
			  <label class="mdui-textfield-label"><?php _e('验证码'); ?></label>
			  <input class="mdui-textfield-input" id="code" name="code" type="text" required value="<?php echo @$_POST['code']; ?>"/>
			  <div class="mdui-textfield-error">验证码不能为空</div>
			</div>
			<div class="mdui-row-xs-2">
			  <div id="smsmsg" class="mdui-col">
				<button id="sendsmsmsg" class="mdui-btn mdui-color-blue mdui-text-color-white">发送验证码</button>
			  </div>
			  <div class="mdui-col">
				<input type="hidden" id="sitetitle" value="<?php $this->options->title();?>" />
				<input type="hidden" name="action" value="regbysms" />
				<button id="reg" class="mdui-btn mdui-btn-block mdui-btn-raised mdui-color-theme-accent mdui-ripple mdui-color-blue mdui-text-color-white"><?php _e('注册'); ?></button>
			  </div>
			</div>
		</form>
	</div>
</section>
<!-- end content section -->
<?php $this->need('footer.php'); ?>
<script>
$("#sendsmsmsg").click(function(){
	var name=$("#name").val();
	var regexp = /^(((13[0-9]{1})|(15[0-9]{1})|(18[0-9]{1}))+\d{8})$/; 
	if(!regexp.test(name)){
		alert('请输入有效的手机号码！'); 
		return false; 
	}
	settime();
	$.post("<?php $this->options->siteUrl(); ?>usr/plugins/UserRegByPhone/sendsms.php",{name:name,sitetitle:$('#sitetitle').val()},function(data){
		if(data=='toofast'){
			alert('发送频率太快了~');
			clearTimeout(timer);
			settime();
		}
	});
});
$("#reg").click(function(e){
	var name=$("#name").val();
	var regexp = /^(((13[0-9]{1})|(15[0-9]{1})|(18[0-9]{1}))+\d{8})$/; 
	if(!regexp.test(name)){
		alert('请输入有效的手机号码！'); 
		return; 
	}
	var yzm = $("input[name=code]").val().replace(/(^\s*)|(\s*$)/g, "");
	if(yzm==""){
		alert("请输入短信验证码");
		return;
	}
	$('form').submit();
});
var timer;
var countdown=60;
function settime() {
	if (countdown == 0) {
		$("#smsmsg").html("<button id='sendsmsmsg' class='mdui-btn mdui-btn-raised mdui-color-blue mdui-text-color-white'>发送验证码</button>");
		countdown = 60;
		clearTimeout(timer);
		return;
	} else {
		$("#smsmsg").html("等待("+countdown+")秒");
		countdown--; 
	} 
	timer=setTimeout(function() { 
		settime() 
	},1000) 
}
</script>