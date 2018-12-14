<?php

/*
 * Tools for Spotify (https://github.com/delight-im/Tools-for-Spotify)
 * Copyright (c) delight.im (https://www.delight.im/)
 * Licensed under the MIT License (https://opensource.org/licenses/MIT)
 */

require_once __DIR__ . '/Http.php';

final class SpotifyPlaylist {

	/**
	 * Fetches a list of track URIs from the specified playlist
	 *
	 * @param string $accessToken the "Access Token" for access to the API
	 * @param string $ownerName the name of the playlist's owner
	 * @param string $id the ID of the playlist
	 * @param int|null $offset (optional) the offset within the playlist
	 * @param array|null $filterByYear (optional) a list of years to filter by
	 * @return array|null the list of URIs or `null`
	 */
	public static function fetchTrackUris($accessToken, $ownerName, $id, $offset = null, $filterByYear = null) {
		$offset = isset($offset) ? (int) $offset : 0;

		if (isset($ownerName) && isset($id)) {
			$apiUrl = 'https://api.spotify.com/v1/users/' . \urlencode($ownerName) . '/playlists/' . \urlencode($id) . '/tracks?offset=' . $offset . '&limit=100&fields=items(track(uri,album(release_date))),offset,limit,total';
		}
		else {
			$apiUrl = 'https://api.spotify.com/v1/me/tracks?offset=' . $offset . '&limit=50';
		}

		$responseJson = \Http::makeCacheableRequest(
			'GET',
			$apiUrl,
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

				if (isset($filterByYear)) {
					$tracks = \array_filter($tracks, function ($each) use ($filterByYear) {
						$releaseYear = isset($each['track']['album']['release_date']) ? (int) \substr($each['track']['album']['release_date'], 0, 4) : null;

						return \in_array($releaseYear, $filterByYear, true);
					});
				}

				$trackUris = \array_map(function ($each) {
					return $each['track']['uri'];
				}, $tracks);

				if (($offset + $limit) < $total) {
					$trackUris = \array_merge(
						$trackUris,
						self::fetchTrackUris($accessToken, $ownerName, $id, $offset + $limit, $filterByYear)
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

	/**
	 * Saves a list of track URIs to the specified playlist
	 *
	 * @param string $accessToken the "Access Token" for access to the API
	 * @param string $ownerName the name of the playlist's owner
	 * @param string $id the ID of the playlist
	 * @param array $uris the list of URIs
	 * @param int|null $offset (optional) the offset within the list of URIs
	 * @return bool whether the tracks could be saved to the playlist
	 */
	public static function saveTrackUris($accessToken, $ownerName, $id, array $uris, $offset = null) {
		$offset = isset($offset) ? (int) $offset : 0;
		$limit = 100;

		$responseJson = \Http::makeRequest(
			'POST',
			'https://api.spotify.com/v1/users/' . \urlencode($ownerName) . '/playlists/' . \urlencode($id) . '/tracks',
			[
				'Authorization: Bearer ' . $accessToken,
				'Content-Type: application/json'
			],
			\json_encode([ 'uris' => \array_slice($uris, $offset, $limit) ])
		);

		if ($responseJson !== false) {
			$response = \json_decode($responseJson, true);
			$success = $response !== false && isset($response['snapshot_id']);

			if ($success) {
				if (($offset + $limit) < \count($uris)) {
					$success = $success && self::saveTrackUris($accessToken, $ownerName, $id, $uris, $offset + $limit);
				}
			}

			return $success;
		}
		else {
			return false;
		}
	}

}
