<?php
require $_SERVER['INIT_SCRIPT'];

//google auth code
$gg_auth_code = trim(_get('gg_auth_code'));
if (strlen($gg_auth_code) < 2) {
	resp(['code' => 412, 'message' => 'Google Auth-Code is Required.']);
}

$fb_access_token = trim(_get('fb_access_token', ''));
if (strlen($fb_access_token) < 2) {
	resp(['code' => 412, 'message' => 'Facebook Access Token is Required.']);
}

//init sdk
set_include_path(get_include_path() . PATH_SEPARATOR . LIB_PATH . 'google-api-php-client/src' . PATH_SEPARATOR . LIB_PATH . 'facebook-php-sdk/src');
//gogole sdk
require LIB_PATH . 'google-api-php-client/src/Google/autoload.php';
$gg = new Google_Client();
$gg->setAuthConfigFile(CONF_PATH . $config['google']['auth_cfg']);
$gg->addScope([Google_Service_Oauth2::USERINFO_PROFILE, Google_Service_Oauth2::USERINFO_EMAIL]);
//proxy
if ($config['google']['proxy']) {
	$io = new Google_IO_Curl($client);
	$io->setOptions([CURLOPT_PROXY => $config['proxy'], CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5]);
	$gg->setIo($io);
}
//facebook sdk
require LIB_PATH . 'facebook-php-sdk/src/Facebook/autoload.php';
$fb = new Facebook\Facebook([
	//'http_client_handler' => 'stream',
	'app_id' => '891401940969295', // Replace {app-id} with your app id
	'app_secret' => '73a9147c91657731cd8cc99dd428cc80',
	'default_graph_version' => 'v2.2',
]);

try {
	//获取facebook账号信息
	$response = $fb->get('/me?fields=id,name', $fb_access_token);
	$fb_user = $response->getGraphUser();

	//mmoup user
	$user = $db->from('user')->where('fb_id=?', $fb_user->getId())->select()->fetch();
	if (false == $user) {
		resp(['code' => 404, 'message' => 'Facebook Account Not Found.']);
	}

	//获取google账号信息
	$gg->authenticate($gg_auth_code);
	$gg_user = $gg->verifyIdToken();

	if (strlen($user['gg_id']) > 2) { //如果当前google绑定号已绑定facebook号
		if ($user['gg_id'] == $gg_user->getUserId()) { //重复绑定本设备
			resp(['code' => 201, 'message' => 'Before Linked']);
		} else { //绑定到其它设备
			resp(['code' => 303, 'message' => 'The account already exists in the progress of the game, whether to switch']);
		}
	} else {
		if (1 == $db->update('user', ['gg_id' => $gg_user->getUserId()], 'id=?', $user['id'])) {
			resp(['code' => 200, 'message' => 'Success']);
		} else {
			resp(['code' => 500, 'message' => 'Internal Server Error']);
		}
	}
} catch (Facebook\Exceptions\FacebookResponseException $e) {
	resp([
		'code' => $e->getCode(),
		'message' => 'Graph returned an error: ' . $e->getMessage(),
	]);
} catch (Facebook\Exceptions\FacebookSDKException $e) {
	resp([
		'code' => $e->getCode(),
		'message' => 'Facebook SDK returned an error: ' . $e->getMessage(),
	]);
} catch (Exception $e) {
	resp([
		'code' => $e->getCode(),
		'message' => $e->getMessage(),
	]);
}