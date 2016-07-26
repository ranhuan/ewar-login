<?php
require $_SERVER['INIT_SCRIPT'];

//google php sdk
set_include_path(get_include_path() . PATH_SEPARATOR . LIB_PATH . 'google-api-php-client/src');
require LIB_PATH . 'google-api-php-client/src/Google/autoload.php';

$client = new Google_Client();
$client->setAuthConfigFile(CONF_PATH . $config['google']['auth_cfg']);
$client->addScope([Google_Service_Oauth2::USERINFO_PROFILE, Google_Service_Oauth2::USERINFO_EMAIL]);

$device_id = trim(_get('device_id', ''));
if (strlen($device_id) < 2) {
	resp(['code' => 412, 'message' => 'Invalid Device ID.']);
}

$auth_code = trim(_get('gg_auth_code', _get('code', '')));
if (strlen($auth_code) < 2) {
	//redirect($client->createAuthUrl());
	resp(['code' => 412, 'message' => 'Auth-Code is Required.']);
}

try {
	$user = $db->from('user')->where('device_id=?', $device_id)->select()->fetch();
	if (false == $user) {
		resp(['code' => 404, 'message' => 'Device ID Not Found.']);
	}
	glog($user['id']);

	//proxy
	if ($config['google']['proxy']) {
		$io = new Google_IO_Curl($client);
		$io->setOptions([CURLOPT_PROXY => $config['proxy'], CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5]);
		$client->setIo($io);
	}

	//auth
	$result = $client->authenticate($auth_code);
	glog($result);
	//glog($client->getAccessToken());
	if (false === strpos($result, 'id_token')) {
		resp(['code' => 403, 'message' => 'Insufficient Permission.']);
	}

	//glog(var_export((new Google_Service_Oauth2($client))->userinfo->get(), true));

	//parse id token
	$token_data = $client->verifyIdToken();
	$gg_id = $token_data->getUserId();

	//查询数据库是否已存在该google用户
	$gg_user_id = $db->from('user', 'id')->where('gg_id=?', $gg_id)->select()->fetchColumn();

	if ($gg_user_id) {
		//重复绑定本设备
		if ($gg_user_id == $user['id']) {
			resp(['code' => 201, 'message' => 'Before Linked']);
		} else {
			//绑定到其它设备
			resp(['code' => 303, 'message' => 'The account already exists in the progress of the game, whether to switch']);
		}
	} else {
		if (1 == $db->update('user', ['gg_id' => $gg_id], 'id=?', $user['id'])) {
			resp(['code' => 200, 'message' => 'Success']);
		} else {
			resp(['code' => 500, 'message' => 'Internal Server Error']);
		}
	}
} catch (Exception $e) {
	resp(['code' => $e->getCode(), 'message' => $e->getMessage()]);
}

resp(['code' => 500, 'message' => 'unknown error']);