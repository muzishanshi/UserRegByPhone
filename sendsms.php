<?php 
session_start();
date_default_timezone_set('Asia/Shanghai');
include "alidayu/TopSdk.php";
include '../../../config.inc.php';


if(@$_COOKIE["sendcodetime"]!=''){
	echo 'toofast';
	return;
}
setcookie("sendcodetime", time(), time()+10);
$query= $db->select('value')->from('table.options')->where('name = ?', 'plugin:UserRegByPhone'); 
$row = $db->fetchRow($query);
$arr=explode(':',$row['value']);
$appkeystr=$arr[6];
$secretstr=$arr[10];
$aliCode=$arr[18];
$signname=$arr[22];
$appkey=substr($appkeystr,1,count($appkeystr)-4);
$secret=substr($secretstr,1,count($secretstr)-4);
$aliCode=substr($aliCode,1,count($aliCode)-4);
$signname=substr($signname,1,count($signname)-4);

//重置短信验证码
$randCode = '';
$chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHIJKLMNPRSTUVWXYZ23456789';
for ( $i = 0; $i < 5; $i++ ){
	$randCode .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
}
$_SESSION['code'] = strtoupper($randCode);

$name = isset($_POST['name']) ? addslashes(trim($_POST['name'])) : '';//发送到的用户名
$sitetitle = isset($_POST['sitetitle']) ? addslashes(trim($_POST['sitetitle'])) : '';

$c = new TopClient;
$c->appkey = $appkey;
$c->secretKey = $secret;
$req = new AlibabaAliqinFcSmsNumSendRequest;
$req->setExtend($name);
$req->setSmsType("normal");
$req->setSmsFreeSignName($signname);
$req->setSmsParam("{\"code\":\"".@$_SESSION['code']."\",\"product\":\"$sitetitle\"}");
//$req->setSmsParam('{"code":"'.@$_SESSION['code'].'","product":"'.$sitetitle.'"}');
$req->setRecNum($name);
$req->setSmsTemplateCode($aliCode);
$resp = $c->execute($req);

echo $_SESSION['code'];
?>