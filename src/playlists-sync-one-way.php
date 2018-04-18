<?php

/*
 * Tools for Spotify (https://github.com/delight-im/Tools-for-Spotify)
 * Copyright (c) delight.im (https://www.delight.im/)
 * Licensed under the MIT License (https://opensource.org/licenses/MIT)
 */

\error_reporting(\E_ALL);
\ini_set('display_errors', 'stdout');

\header('Content-Type: text/plain; charset=utf-8');

\define('CONFIG_PATH_RELATIVE', '../data/config.json');
\define('DATABASE_PATH_RELATIVE', '../data/database.json');
\define('SPOTIFY_API_SCOPES', 'playlist-modify-public playlist-modify-private');
\define('SPOTIFY_URI_PLAYLIST_REGEX', '/^spotify:user:([^:]+):playlist:([^:]+)$/');

$config = \readConfig(\CONFIG_PATH_RELATIVE);
$database = \readDatabase(\DATABASE_PATH_RELATIVE);

if (isset($_GET['code'])) {
	echo 'Starting ...' . "\n";

	$requestStartTime = \time();
	$responseJson = \fetchAccessToken($config['api']['clientId'], $config['api']['clientSecret'], $_GET['code']);

	if ($responseJson === false) {
		echo ' * Could not get an access token from the Spotify API ...' . "\n";
		echo ' * Cancelling ...' . "\n";
		echo 'Failed' . "\n";
		exit(2);
	}
	else {
		$response = \json_decode($responseJson, true);

		$database['auth']['accessToken'] = (string) $response['access_token'];
		$database['auth']['expiresAt'] = $requestStartTime + (int) $response['expires_in'];
		$database['auth']['refreshToken'] = (string) $response['refresh_token'];

		echo ' * Processing ' . \count($config['playlists']['sync']['oneWay']) . ' playlists from configuration' . "\n";

		foreach ($config['playlists']['sync']['oneWay'] as $oneWaySync) {
			if (isset($oneWaySync['from']) && isset($oneWaySync['to'])) {
				echo '   * From "' . $oneWaySync['from'] . '" to "' . $oneWaySync['to'] . '" ...' . "\n";

				if (\preg_match(\SPOTIFY_URI_PLAYLIST_REGEX, $oneWaySync['from'], $oneWaySyncFrom)) {
					if (\preg_match(\SPOTIFY_URI_PLAYLIST_REGEX, $oneWaySync['to'], $oneWaySyncTo)) {
						$trackUris = \fetchTrackUrisFromPlaylist(
							$database['auth']['accessToken'],
							$oneWaySyncFrom[1],
							$oneWaySyncFrom[2]
						);

						if ($trackUris !== null) {
							if (!isset($database['playlists'][$oneWaySync['to']]) || !\is_array($database['playlists'][$oneWaySync['to']])) {
								$database['playlists'][$oneWaySync['to']] = [];
							}

							if (!isset($database['playlists'][$oneWaySync['to']]['inserted']) || !\is_array($database['playlists'][$oneWaySync['to']]['inserted'])) {
								$database['playlists'][$oneWaySync['to']]['inserted'] = [];
							}

							$trackUris = \array_filter($trackUris, function ($each) use ($database, $oneWaySync) {
								return !\in_array($each, $database['playlists'][$oneWaySync['to']]['inserted'], true);
							});
							$trackUris = \array_values($trackUris);

							if (!empty($trackUris)) {
								$tracksSavedSuccessfully = \saveTrackUrisToPlaylist(
									$database['auth']['accessToken'],
									$oneWaySyncTo[1],
									$oneWaySyncTo[2],
									$trackUris
								);

								if ($tracksSavedSuccessfully) {
									$database['playlists'][$oneWaySync['to']]['inserted'] = \array_merge(
										$database['playlists'][$oneWaySync['to']]['inserted'],
										$trackUris
									);

									echo '     * Added ' . \count($trackUris) . ' tracks to playlist ...' . "\n";
								}
								else {
									echo '     * Could not save tracks to playlist ...' . "\n";
									echo '     * Skipping ...' . "\n";
								}
							}
							else {
								echo '     * Already up to date ...' . "\n";
							}
						}
						else {
							echo '     * Could not fetch tracks from playlist ...' . "\n";
							echo '     * Skipping ...' . "\n";
						}
					}
					else {
						echo '     * Invalid "to" URI ...' . "\n";
						echo '     * Skipping ...' . "\n";
					}
				}
				else {
					echo '     * Invalid "from" URI ...' . "\n";
					echo '     * Skipping ...' . "\n";
				}
			}
			else {
				echo '   * Skipping invalid playlist entry ...' . "\n";
			}
		}

		if (\writeDatabase(\DATABASE_PATH_RELATIVE, $database)) {
			echo 'Succeeded' . "\n";
		}
		else {
			echo ' * Could not update information in database ...' . "\n";
			echo 'Failed' . "\n";
			exit(10);
		}
	}
}
elseif (isset($_GET['error'])) {
	echo 'Starting ...' . "\n";
	echo ' * You have denied the authorization request with the Spotify API' . "\n";
	echo ' * Cancelling ...' . "\n";
	echo 'Failed' . "\n";
	exit(1);
}
else {
	\fetchAuthorizationCode($config['api']['clientId']);
}

function saveTrackUrisToPlaylist($accessToken, $ownerName, $id, array $uris) {
	$responseJson = makeHttpRequest(
		'POST',
		'https://api.spotify.com/v1/users/' . \urlencode($ownerName) . '/playlists/' . \urlencode($id) . '/tracks',
		[
			'Authorization: Bearer ' . $accessToken,
			'Content-Type: application/json'
		],
		\json_encode([ 'uris' => $uris ])
	);

	if ($responseJson !== false) {
		$response = \json_decode($responseJson, true);

		return $response !== false && isset($response['snapshot_id']);
	}
	else {
		return false;
	}
}

function fetchTrackUrisFromPlaylist($accessToken, $ownerName, $id, $offset = null) {
	$offset = isset($offset) ? (int) $offset : 0;

	$responseJson = makeHttpRequest(
		'GET',
		'https://api.spotify.com/v1/users/' . \urlencode($ownerName) . '/playlists/' . \urlencode($id) . '/tracks?offset=' . $offset . '&limit=100&fields=items(track(uri)),offset,limit,total',
		[
			'Authorization: Bearer ' . $accessToken
		]
	);

	if ($responseJson !== false) {
		$response = \json_decode($responseJson, true);

		if ($response !== false && isset($response['items'])) {
			$offset = isset($response['offset']) ? (int) $response['offset'] : null;
			$limit = isset($response['limit']) ? (int) $response['limit'] : null;
			$total = isset($response['total']) ? (int) $response['total'] : null;

			$tracks = $response['items'];

			$trackUris = \array_map(function ($each) {
				return $each['track']['uri'];
			}, $tracks);

			if (($offset + $limit) < $total) {
				$trackUris = \array_merge(
					$trackUris,
					\fetchTrackUrisFromPlaylist($accessToken, $ownerName, $id, $offset + $limit)
				);
			}

			return $trackUris;
		}
		else {
			return null;
		}
	}
	else {
		return null;
	}
}

function fetchAccessToken($clientId, $clientSecret, $authorizationCode) {
	return makeHttpRequest(
		'POST',
		\createAccessTokenEndpointUrl(),
		[
			'Content-Type: application/x-www-form-urlencoded'
		],
		\createAccessTokenEndpointBody($clientId, $clientSecret, $authorizationCode)
	);
}

function fetchAuthorizationCode($clientId) {
	\header('Location: ' . \createAuthorizationCodeEndpointUrl($clientId));
}

function createAccessTokenEndpointUrl() {
	return 'https://accounts.spotify.com/api/token';
}

function createAccessTokenEndpointBody($clientId, $clientSecret, $authorizationCode) {
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
	$body[] = \urlencode(\createRedirectUri());

	return \implode('', $body);
}

function createAuthorizationCodeEndpointUrl($clientId) {
	$url = [];

	$url[] = 'https://accounts.spotify.com/authorize';
	$url[] = '?client_id=';
	$url[] = \urlencode($clientId);
	$url[] = '&response_type=';
	$url[] = 'code';
	$url[] = '&redirect_uri=';
	$url[] = \urlencode(\createRedirectUri());
	$url[] = '&scope=';
	$url[] = \urlencode(\SPOTIFY_API_SCOPES);
	$url[] = '&show_dialog=';
	$url[] = 'false';

	return \implode('', $url);
}

function createRedirectUri() {
	$path = \explode('?', $_SERVER['REQUEST_URI'], 2)[0];

	return $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'] . $path;
}

function readDatabase($pathRelative) {
	$path = __DIR__ . '/' . $pathRelative;

	if (\file_exists($path) && \is_file($path)) {
		if (\is_readable($path)) {
			if (\is_writable($path)) {
				$json = @\file_get_contents($path, false);

				if ($json === false) {
					echo 'Starting ...' . "\n";
					echo ' * Could not read database ("' . $pathRelative . '") ...' . "\n";
					echo ' * Cancelling ...' . "\n";
					echo 'Failed' . "\n";
					exit(9);
				}
				else {
					$data = \json_decode($json, true);

					if (!\is_array($data)) {
						$data = [];
					}

					if (!isset($data['auth'])) {
						$data['auth'] = [];
					}

					if (!isset($data['playlists'])) {
						$data['playlists'] = [];
					}

					return $data;
				}
			}
			else {
				echo 'Starting ...' . "\n";
				echo ' * Could not modify database ("' . $pathRelative . '") ...' . "\n";
				echo ' * Cancelling ...' . "\n";
				echo 'Failed' . "\n";
				exit(8);
			}
		}
		else {
			echo 'Starting ...' . "\n";
			echo ' * Could not open database ("' . $pathRelative . '") ...' . "\n";
			echo ' * Cancelling ...' . "\n";
			echo 'Failed' . "\n";
			exit(7);
		}
	}
	else {
		echo 'Starting ...' . "\n";
		echo ' * Could not find database ("' . $pathRelative . '") ...' . "\n";
		echo ' * Cancelling ...' . "\n";
		echo 'Failed' . "\n";
		exit(6);
	}
}

function writeDatabase($path, array $data) {
	$bytesWritten = @\file_put_contents($path, \json_encode($data, \JSON_PRETTY_PRINT));

	return $bytesWritten !== false;
}

function readConfig($pathRelative) {
	$path = __DIR__ . '/' . $pathRelative;

	if (\file_exists($path) && \is_file($path)) {
		if (\is_readable($path)) {
			$json = @\file_get_contents($path, false);

			if ($json !== false) {
				$data = \json_decode($json, true);

				if (!\is_array($data)) {
					$data = [];
				}

				if (!isset($data['api']['clientId'])) {
					echo 'Starting ...' . "\n";
					echo ' * Missing API client ID in configuration ...' . "\n";
					echo ' * Cancelling ...' . "\n";
					echo 'Failed' . "\n";
					exit(11);
				}

				if (!isset($data['api']['clientSecret'])) {
					echo 'Starting ...' . "\n";
					echo ' * Missing API client secret in configuration ...' . "\n";
					echo ' * Cancelling ...' . "\n";
					echo 'Failed' . "\n";
					exit(12);
				}

				if (!isset($data['playlists'])) {
					$data['playlists'] = [];
				}

				if (!isset($data['playlists']['sync'])) {
					$data['playlists']['sync'] = [];
				}

				if (!isset($data['playlists']['sync']['oneWay'])) {
					$data['playlists']['sync']['oneWay'] = [];
				}

				return $data;
			}
			else {
				echo 'Starting ...' . "\n";
				echo ' * Could not read configuration ("' . $pathRelative . '") ...' . "\n";
				echo ' * Cancelling ...' . "\n";
				echo 'Failed' . "\n";
				exit(5);
			}
		}
		else {
			echo 'Starting ...' . "\n";
			echo ' * Could not open configuration ("' . $pathRelative . '") ...' . "\n";
			echo ' * Cancelling ...' . "\n";
			echo 'Failed' . "\n";
			exit(4);
		}
	}
	else {
		echo 'Starting ...' . "\n";
		echo ' * Could not find configuration ("' . $pathRelative . '") ...' . "\n";
		echo ' * Cancelling ...' . "\n";
		echo 'Failed' . "\n";
		exit(3);
	}
}

function makeHttpRequest($method, $url, array $headers = null, $body = null) {
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
