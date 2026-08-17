<?php
# LiquidMS - federated master server
# Copyright (C) 2021-2026 Zibon Badi et al.
# 
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License as
# published by the Free Software Foundation, either version 3 of the
# License, or (at your option) any later version.
# 
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Affero General Public License for more details.
# 
# You should have received a copy of the GNU Affero General Public License
# along with this program.  If not, see <https://www.gnu.org/licenses/>.

require_once __DIR__.'/../ConfigModel.php';
require_once __DIR__.'/../WebfingerModel.php';

use LiquidMS\ConfigModel;
use LiquidMS\WebfingerModel;

$config = ConfigModel::getConfig();

set_time_limit(10);

function webfinger_error(int $code, string $message, $debuginfo = null){
	$config = ConfigModel::getConfig();

	http_response_code($code);
	header('Content-Type: application/json');

	$response_json = ["error" => $code, "message" => $message];
	if($debuginfo !== null && $config["debug_output"]){ $response_json["debuginfo"] = $debuginfo; }
	echo json_encode($response_json);

	exit;
}

// Only /.well-known/webfinger and its /webfinger alias are served.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if($path === false || $path === null){ $path = '/'; }
$path = rtrim($path, '/') ?: '/';
if($path !== '/.well-known/webfinger' && $path !== '/webfinger'){
	webfinger_error(404, "Not found", ["request_uri" => $_SERVER['REQUEST_URI']]);
}

if(($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET'){
	header('Allow: GET');
	webfinger_error(405, "Method not allowed", ["request_method" => $_SERVER['REQUEST_METHOD']]);
}

// Parse the raw query string for the resource parameter. This mirrors the
// ChaosNet approach and sidesteps PHP's dot-to-underscore key mangling.
$resource = '';
foreach(explode('&', $_SERVER['QUERY_STRING'] ?? '') as $pair){
	if($pair === ''){continue;}
	$parts = explode('=', $pair, 2);
	if($parts[0] === 'resource'){
		$resource = isset($parts[1]) ? urldecode($parts[1]) : '';
		break;
	}
}

if($resource === '' || !WebfingerModel::isValidResource($resource)){
	webfinger_error(400, "Invalid or missing resource parameter", ["resource" => $resource]);
}

$jrd = WebfingerModel::resolve($resource, $config);
if($jrd === null){
	webfinger_error(404, "Resource not found", ["resource" => $resource]);
}

header('Content-Type: application/jrd+json');
header('Cache-Control: max-age=86400');
echo json_encode($jrd);
?>
