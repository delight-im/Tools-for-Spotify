<?php

/*
 * Tools for Spotify (https://github.com/delight-im/Tools-for-Spotify)
 * Copyright (c) delight.im (https://www.delight.im/)
 * Licensed under the MIT License (https://opensource.org/licenses/MIT)
 */

final class Http {

	private static $requestCache = [];

	public static function makeRequest($method, $url, array $headers = null, $body = null) {
		$options = [
			'http' => [
				'method' => $method
			]
		];

		if (isset($headers)) {
			$options['http']['header'] = $headers;
		}

		if (isset($body)) {
			$options['http']['content'] = $body;
		}

		$context = \stream_context_create($options);
		$result = @\file_get_contents($url, false, $context);

		return $result;
	}

	public static function makeCacheableRequest($method, $url, array $headers = null, $body = null) {
		$signature = \sha1(\json_encode(\func_get_args()));

		if (!isset(self::$requestCache[$signature])) {
			self::$requestCache[$signature] = self::makeRequest($method, $url, $headers, $body);
		}

		return self::$requestCache[$signature];
	}

}
