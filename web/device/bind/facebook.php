<?php
require $_SERVER['INIT_SCRIPT'];

$fb_user_id = trim(_get('fb_user_id', ''));
$fb_access_token = trim(_get('fb_access_token', ''));

//code
//state
$device_id = trim(_get('device_id', ''));
if (strlen($device_id) < 2) {
	resp(['code' => 412, 'message' => 'Invalid Device ID.']);
}

$user = $db->from('user')->where('device_id=?', $device_id)->select()->fetch();
if (false == $user) {
	resp(['code' => 404, 'message' => 'Device ID Not Found.']);
}
glog($user['id']);

set_include_path(get_include_path() . PATH_SEPARATOR . LIB_PATH . 'facebook-php-sdk/src');
require LIB_PATH . 'facebook-php-sdk/src/Facebook/autoload.php';
$fb = new Facebook\Facebook([
	//'http_client_handler' => 'stream',
	'app_id' => '891401940969295', // Replace {app-id} with your app id
	'app_secret' => '73a9147c91657731cd8cc99dd428cc80',
	'default_graph_version' => 'v2.2',
]);

$helper = $fb->getRedirectLoginHelper();

if (strlen($fb_user_id) < 2) {
	//redirect($helper->getLoginUrl('http://login.mmoup.cn/facebook/login', []));
	exit(json_encode(['code' => 412, 'message' => 'user id is required.']));
}
if (strlen($fb_access_token) < 2) {
	//redirect($helper->getLoginUrl('http://login.mmoup.cn/facebook/login', []));
	exit(json_encode(['code' => 412, 'message' => 'access token is required.']));
}

try {
	// Returns a `Facebook\FacebookResponse` object
	$response = $fb->get('/me?fields=id,name', $fb_access_token);
} catch (Facebook\Exceptions\FacebookResponseException $e) {
	echo 'Graph returned an error: ' . $e->getMessage();
	exit;
} catch (Facebook\Exceptions\FacebookSDKException $e) {
	echo 'Facebook SDK returned an error: ' . $e->getMessage();
	exit;
}

$fb_user = $response->getGraphUser();
glog(var_export($fb_user, true));
$fb_id = $fb_user->getId();

echo 'Name: ' . $fb_user['name'];
//查询数据库是否已存在该google用户
$fb_user_id = $db->from('user', 'id')->where('fb_id=?', $fb_id)->select()->fetchColumn();
if ($fb_user_id) {
	//重复绑定本设备
	if ($fb_user_id == $fb_user['id']) {
		glog('Before Linked');
		exit(json_encode(['code' => 201, 'message' => 'Before Linked']));
	}

	//绑定到其它设备
	glog('The account already exists in the progress of the game, whether to switch');
	exit(json_encode(['code' => 303, 'message' => 'The account already exists in the progress of the game, whether to switch']));
}

if (1 == $db->update('user', ['fb_id' => $fb_id], 'id=?', $user['id'])) {
	glog('success');
	exit(json_encode(['code' => 200, 'message' => 'Success']));
}

glog('internal server error');
exit(json_encode(['code' => 500, 'message' => 'Internal Server Error']));