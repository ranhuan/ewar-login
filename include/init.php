<?php
define('INIT', true);
define('GUEST_USER_ID', 10000);

//定义路径
define('DOCUMENT_ROOT', realpath($_SERVER['DOCUMENT_ROOT']) . DIRECTORY_SEPARATOR);
define('ROOT_PATH', realpath(DOCUMENT_ROOT . '../') . DIRECTORY_SEPARATOR);
define('INCLUDE_PATH', ROOT_PATH . 'include' . DIRECTORY_SEPARATOR);
define('TPL_PATH', ROOT_PATH . 'tpl' . DIRECTORY_SEPARATOR);
define('LIB_PATH', ROOT_PATH . 'lib' . DIRECTORY_SEPARATOR);
define('MODEL_PATH', ROOT_PATH . 'model' . DIRECTORY_SEPARATOR);
define('CONF_PATH', ROOT_PATH . 'conf' . DIRECTORY_SEPARATOR);
define('REMOTE_ADDR', $_SERVER['REMOTE_ADDR']);

if (true || APP_NEV != 'prod') {
	ini_set('display_errors', 'On');
	error_reporting(E_ALL);
}

require CONF_PATH . 'env.php';
require CONF_PATH . APP_ENV . '.php';
date_default_timezone_set('PRC');

//composer
require ROOT_PATH . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

//引入库
require LIB_PATH . 'func.php';
ob_start('output_callback');

require LIB_PATH . 'mysql.php';
$db = new MySQL($config['db_server']);

require INCLUDE_PATH . 'intranet_ip.php';

if (is_intranet($_SERVER['REMOTE_ADDR'])) {
	$config['etcd']['services'] = $config['etcd']['intranet'];
} else {
	$config['etcd']['services'] = $config['etcd']['internet'];
}

//var_dump($config['etcd']['services']);

/*
spl_autoload_register(function ($class_name) {
if ('Model' == substr($class_name, -5)) {
require MODEL_PATH . strtolower(preg_replace(' / ( ?  <= [a - z])( ?  = [A - Z]) / ', '_', substr($class_name, 0, -5))) . ' . php';
}
});
 */