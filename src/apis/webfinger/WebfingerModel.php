<?php
# LiquidMS - distributable SRB2 master server
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

namespace LiquidMS;

require_once __DIR__.'/ConfigModel.php';

class WebfingerModel{

	/**
	 * Returns true when the resource has a WebFinger-recognisable form
	 * (an acct: URI with a host, or an http/https URL).
	 */
	public static function isValidResource(string $resource): bool{
		if($resource === ''){ return false; }

		$lower = strtolower($resource);
		if(str_starts_with($lower, 'acct:')){
			$body = substr($resource, 5);
			$at = strrpos($body, '@');
			return ($at !== false && $at > 0 && $at < strlen($body) - 1);
		}

		$scheme = strtolower((string)parse_url($resource, PHP_URL_SCHEME));
		if($scheme === 'https' || $scheme === 'http'){
			$host = parse_url($resource, PHP_URL_HOST);
			return ($host !== null && $host !== '');
		}

		return false;
	}

	/**
	 * Resolve a WebFinger resource to a JRD document, or null when it cannot
	 * be resolved. Local hosts resolve against the configured alias map;
	 * any other host is proxied to its well-known WebFinger endpoint.
	 */
	public static function resolve(string $resource, array $config): ?array{
		if(!self::isValidResource($resource)){ return null; }

		$lower = strtolower($resource);
		if(str_starts_with($lower, 'acct:')){
			$body = substr($resource, 5);
			$at = strrpos($body, '@');
			$local = substr($body, 0, $at);
			$host = strtolower(substr($body, $at + 1));
			if(self::isConfiguredHost($host, $config)){
				return self::resolveLocalAcct($local, $host, $config);
			}
			return self::proxyRemote($resource);
		}

		$host = strtolower((string)parse_url($resource, PHP_URL_HOST));
		if(self::isConfiguredHost($host, $config)){
			return self::resolveLocalUrl(rtrim($resource, '/'), $host, $config);
		}
		return self::proxyRemote($resource);
	}

	/** Look up an acct: local part on a configured host. Null when unknown. */
	private static function resolveLocalAcct(string $local, string $host, array $config): ?array{
		foreach(($config["hosts"] ?? []) as $configuredHost => $entries){
			if(strtolower($configuredHost) !== $host){ continue; }
			foreach($entries as $entry){
				if($entry["local"] === $local){
					return self::buildJrd("acct:{$entry["local"]}@{$configuredHost}", $entry["actor_uri"]);
				}
			}
			return null;
		}
		return null;
	}

	/** Look up an actor URL on a configured host. Null when unknown. */
	private static function resolveLocalUrl(string $normalized, string $host, array $config): ?array{
		foreach(($config["hosts"] ?? []) as $configuredHost => $entries){
			if(strtolower($configuredHost) !== $host){ continue; }
			foreach($entries as $entry){
				if(rtrim($entry["actor_uri"], '/') === $normalized){
					return self::buildJrd("acct:{$entry["local"]}@{$configuredHost}", $entry["actor_uri"]);
				}
			}
			return null;
		}
		return null;
	}

	private static function isConfiguredHost(string $host, array $config): bool{
		foreach(($config["hosts"] ?? []) as $configuredHost => $entries){
			if(strtolower($configuredHost) === $host){ return true; }
		}
		return false;
	}

	/**
	 * Proxy a resource for a foreign host by fetching its well-known WebFinger
	 * endpoint. Returns the remote JRD verbatim, or null when unavailable.
	 */
	private static function proxyRemote(string $resource): ?array{
		if(str_starts_with(strtolower($resource), 'acct:')){
			$body = substr($resource, 5);
			$at = strrpos($body, '@');
			if($at === false){ return null; }
			$host = substr($body, $at + 1);
		} else {
			$host = parse_url($resource, PHP_URL_HOST);
			if($host === null){ return null; }
		}
		if($host === ''){ return null; }

		$query = "resource=".rawurlencode($resource);
		$urls = [
			"https://{$host}/.well-known/webfinger?{$query}",
			"http://{$host}/.well-known/webfinger?{$query}",
		];

		foreach($urls as $url){
			$body = self::fetch($url);
			if($body === null){ continue; }
			$decoded = json_decode($body, true);
			if($decoded === null || !is_array($decoded)){ continue; }
			return $decoded;
		}
		return null;
	}

	/** GET a URL, returning the body on HTTP 200. */
	private static function fetch(string $url): ?string{
		$ch = curl_init();
		curl_setopt_array($ch, [
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS => 5,
			CURLOPT_TIMEOUT => 10,
			CURLOPT_USERAGENT => "LiquidMS-Webfinger/1.0",
			CURLOPT_HTTPHEADER => ["Accept: application/jrd+json, application/json"],
		]);
		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if($httpCode != 200 || $response === false){ return null; }
		return $response;
	}

	private static function buildJrd(string $subject, string $actorUri): array{
		return [
			"subject" => $subject,
			"aliases" => [$actorUri],
			"links" => [
				[
					"rel" => "self",
					"type" => "application/activity+json",
					"href" => $actorUri,
				],
			],
		];
	}
}

?>
