<?php

/*
 * Tools for Spotify (https://github.com/delight-im/Tools-for-Spotify)
 * Copyright (c) delight.im (https://www.delight.im/)
 * Licensed under the MIT License (https://opensource.org/licenses/MIT)
 */

final class Http {

	/** @var array the cache used for all requests declared to be cacheable */
	private static $requestCache = [];

	/**
	 * Makes an HTTP request
	 *
	 * @param string $method the request method to use, e.g. `GET` or `POST`
	 * @param string $url the target URL to request
	 * @param array|null $headers (optional) a list of additional HTTP headers
	 * @param string|null $body (optional) the request body
	 * @return string|bool the response data or `false` on failure
	 */
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

	/**
	 * Makes a cacheable HTTP request
	 *
	 * @param string $method the request method to use, e.g. `GET` or `POST`
	 * @param string $url the target URL to request
	 * @param array|null $headers (optional) a list of additional HTTP headers
	 * @param string|null $body (optional) the request body
	 * @return string|bool the response data or `false` on failure
	 */
	public static function makeCacheableRequest($method, $url, array $headers = null, $body = null) {
		$signature = \sha1(\json_encode(\func_get_args()));

		if (!isset(self::$requestCache[$signature])) {
			self::$requestCache[$signature] = self::makeRequest($method, $url, $headers, $body);
		}

		return self::$requestCache[$signature];
	}

}
