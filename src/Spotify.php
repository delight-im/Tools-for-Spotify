<?php

/*
 * Tools for Spotify (https://github.com/delight-im/Tools-for-Spotify)
 * Copyright (c) delight.im (https://www.delight.im/)
 * Licensed under the MIT License (https://opensource.org/licenses/MIT)
 */

require_once __DIR__ . '/Http.php';

final class Spotify {

	/**
	 * Fetches an “Access Token” for access to the API
	 *
	 * @param string $clientId the “Client ID” for access to the API
	 * @param string $clientSecret the “Client Secret” for access to the API
	 * @param string $authorizationCode the “Authorization Code” previously retrieved
	 * @return string|bool the token or `false` on failure
	 */
	public static function fetchAccessToken($clientId, $clientSecret, $authorizationCode) {
		return \Http::makeRequest(
			'POST',
			self::createAccessTokenEndpointUrl(),
			[
				'Content-Type: application/x-www-form-urlencoded'
			],
			self::createAccessTokenEndpointBody($clientId, $clientSecret, $authorizationCode)
		);
	}

	/**
	 * Fetches an “Authorization Code” for access to the API
	 *
	 * @param string $clientId the “Client ID” for access to the API
	 * @param array $scopes the “Scopes” or permissions to request for access to the API
	 */
	public static function fetchAuthorizationCode($clientId, array $scopes) {
		\header('Location: ' . self::createAuthorizationCodeEndpointUrl($clientId, $scopes));
	}

	/**
	 * Creates the URL for the endpoint where an “Access Token” can be retrieved
	 *
	 * @return string
	 */
	private static function createAccessTokenEndpointUrl() {
		return 'https://accounts.spotify.com/api/token';
	}

	/**
	 * Creates the request body for the endpoint where an “Access Token” can be retrieved
	 *
	 * @param string $clientId the “Client ID” for access to the API
	 * @param string $clientSecret the “Client Secret” for access to the API
	 * @param string $authorizationCode the “Authorization Code” previously retrieved
	 * @return string
	 */
	private static function createAccessTokenEndpointBody($clientId, $clientSecret, $authorizationCode) {
		$body = [];

		$body[] = 'client_id=';
		$body[] = \urlencode($clientId);
		$body[] = '&client_secret=';
		$body[] = \urlencode($clientSecret);
		$body[] = '&grant_type=';
		$body[] = 'authorization_code';
		$body[] = '&code=';
		$body[] = \urlencode($authorizationCode);
		$body[] = '&redirect_uri=';
		$body[] = \urlencode(self::createRedirectUrl());

		return \implode('', $body);
	}

	/**
	 * Creates the URL for the endpoint where an “Authorization Code” can be retrieved
	 *
	 * @param string $clientId the “Client ID” for access to the API
	 * @param array $scopes the “Scopes” or permissions to request for access to the API
	 * @return string
	 */
	private static function createAuthorizationCodeEndpointUrl($clientId, array $scopes) {
		$url = [];

		$url[] = 'https://accounts.spotify.com/authorize';
		$url[] = '?client_id=';
		$url[] = \urlencode($clientId);
		$url[] = '&response_type=';
		$url[] = 'code';
		$url[] = '&redirect_uri=';
		$url[] = \urlencode(self::createRedirectUrl());
		$url[] = '&scope=';
		$url[] = \urlencode(
			\implode(' ', $scopes)
		);
		$url[] = '&show_dialog=';
		$url[] = 'false';

		return \implode('', $url);
	}

	/**
	 * Creates a URL to redirect to after an authorization attempt
	 *
	 * @return string
	 */
	private static function createRedirectUrl() {
		$path = \explode('?', $_SERVER['REQUEST_URI'], 2)[0];

		return $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'] . $path;
	}

}
