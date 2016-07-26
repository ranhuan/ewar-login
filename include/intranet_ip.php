<?php
//是否本地网络
function is_intranet($ip) {
	global $db;

	if ($db->from('intranet_ip', 'id')->where('ip=?', $ip)->where('status=?', 'enable')->select()->fetchColumn() > 0) {
		return true;
	}

	return false;
}