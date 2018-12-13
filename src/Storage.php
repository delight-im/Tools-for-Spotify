<?php

/*
 * Tools for Spotify (https://github.com/delight-im/Tools-for-Spotify)
 * Copyright (c) delight.im (https://www.delight.im/)
 * Licensed under the MIT License (https://opensource.org/licenses/MIT)
 */

final class Storage {

	public static function readConfiguration($pathRelative) {
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

	public static function readDatabase($pathRelative) {
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

	public static function writeDatabase($path, array $data) {
		$bytesWritten = @\file_put_contents($path, \json_encode($data, \JSON_PRETTY_PRINT));

		return $bytesWritten !== false;
	}

}
