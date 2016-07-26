<?php
function tpl($filename) {
	if ('.js' == substr($filename, -3)) {
		return TPL_PATH . $filename;
	}
	return TPL_PATH . $filename . '.phtml';
}
function act($filename) {
	return TPL_PATH . $filename . '.phtml';
}
function redirect($url) {
	header('Location: ' . $url);
}
function allowGuestAccess() {
	return defined('ALLOW_GUEST_ACCESS') && ALLOW_GUEST_ACCESS ? true : false;
}

function _get($name, $default = null, $options = array(), $flags = FILTER_FLAG_NONE) {
	return tuki_filter_input(INPUT_GET, $name, $default, $options, $flags);
}

function _post($name = null, $default = null, $options = array(), $flags = null) {
	return tuki_filter_input(INPUT_POST, $name, $default, $options, $flags);
}

function _put($name = null, $default = null, $options = array(), $flags = null) {
	global $_PUT;
	return $_PUT[$name];
}

function _isAjax() {
	return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 'XMLHttpRequest' == $_SERVER['HTTP_X_REQUESTED_WITH'];
}

function _isPost() {
	return 'post' == strtolower($_SERVER['REQUEST_METHOD']);
}

//tuki 输入内容过滤函数
function tuki_filter_input($type, $name = null, $default = null, $options = array(), $flags = null) {
	$options['default'] = $default;
	$opt = array('options' => $options);

	if (null == $name) {
		return filter_input_array($type);
	}

	$filter = FILTER_DEFAULT;
	switch (gettype($default)) {
	case 'NULL':
		break;
	case 'string':
		$filter = FILTER_SANITIZE_STRING;
		break;
	case 'boolean':
		$filter = FILTER_VALIDATE_BOOLEAN;
		break;
	case 'integer':
		$filter = FILTER_VALIDATE_INT;
		break;
	case 'double':
		$filter = FILTER_VALIDATE_FLOAT;
		break;
	case 'array':
		$flags = FILTER_FORCE_ARRAY;
		break;
	}
	$opt['flags'] = $flags;

	return filter_input($type, $name, $filter, $opt);
}

function guid() {
	$charid = strtoupper(md5(uniqid(mt_rand(), true)));
	$hyphen = chr(45); // "-"
	$uuid = substr($charid, 0, 8)
	. $hyphen . substr($charid, 8, 4)
	. $hyphen . substr($charid, 12, 4)
	. $hyphen . substr($charid, 16, 4)
	. $hyphen . substr($charid, 20, 12);
	return $uuid;
}

function glog($message) {
	error_log(date('Y-m-d H:i:s') . ': ' . $message . "\n", 3, ROOT_PATH . 'log/error.log');
}

function resp($data) {
	if (is_array($data)) {
		$data = json_encode($data);
	}

	//glog($data);
	exit($data);
}


function output_callback($output) {
	glog($output);
	return $output;
}