# Tools for Spotify

Tools and utilities for Spotify using the Spotify Web API

## Requirements

 * PHP 7.0.0+

## Installation

 1. Register your own application for the Spotify Web API

    1. Go to [Spotify’s developer site](https://developer.spotify.com/my-applications)

    1. Choose to create a new app

    1. Enter an arbitrary title and description for your new app

    1. Locate the “Client ID” for your new app and write it down

    1. “Edit” your app

    1. As a “Redirect URI”, add

       ```
       http://localhost/Tools-for-Spotify/src/playlists-sync-one-way.php
       http://localhost/Tools-for-Spotify/src/playlists-backup.php
       http://localhost/Tools-for-Spotify/src/playlists-deduplicate.php
       http://localhost/Tools-for-Spotify/src/playlists-clear.php
       ```

       using whatever may be the URL to your (local) version of this project

    1. Locate the “Client Secret” for your app and write it down

    1. Save the new settings

 1. Create an empty configuration file

    ```bash
    $ touch data/config.json
    # sudo chown www-data:www-data data/config.json
    # sudo chmod 0400 data/config.json
    ```

    Add your “Client ID” and “Client Secret” for the Spotify Web API, e.g.:

    ```json
    {
        "api": {
            "clientId": "u64v81mhz2ntxrilbkpfyd9q07oegjsc",
            "clientSecret": "wqyt6dbnk7235faxmre4c1lzhpvius80"
        }
    }
    ```

 1. Create an empty database file

    ```bash
    $ touch data/database.json
    # sudo chown www-data:www-data data/database.json
    # sudo chmod 0600 data/database.json
    ```

## Usage

### One-way synchronization between playlists

 1. Define your desired sources and targets in `data/config.json`, e.g. like this:

    ```json
    {
        "playlists": {
            "sync": {
                "oneWay": [
                    {
                        "from": "spotify:user:spotify:playlist:wvfdktjeqiurxghbymlzan",
                        "to": "spotify:user:obhqrwlenifzujsdkvtcpa:playlist:phmcblzvgwdisqyfakrenx"
                    },
                    {
                        "from": "spotify:user:ehkuimfzcpvdjxblorsgny:playlist:zlebkwmgyvtjrnudqacsix",
                        "to": "spotify:user:obhqrwlenifzujsdkvtcpa:playlist:gouscewqprnbkzhajyixvt",
                        "whereYearIn": [ 2010 ]
                    },
                    {
                        "from": "spotify:user:spotify:playlist:htkwibrfuvlyzdmxngqpaj",
                        "to": "spotify:user:obhqrwlenifzujsdkvtcpa:playlist:ulzhcpatfomqsvbynijwgd",
                        "whereYearIn": [ 1990, 1991, 1992, 1993, 1994, 1995, 1996, 1997, 1998, 1999 ]
                    },
                    {
                        "from": "me:tracks",
                        "to": "spotify:user:obhqrwlenifzujsdkvtcpa:playlist:kmnwohlftdxycsjgubvqea"
                    },
                    {
                        "from": "me:tracks",
                        "to": "spotify:user:obhqrwlenifzujsdkvtcpa:playlist:QXe53JVVBXNUKSq5DVqvmt",
                        "whereAnyArtistIn": [ "Jane Doe" ]
                    },
                    {
                        "from": "me:tracks",
                        "to": "spotify:user:obhqrwlenifzujsdkvtcpa:playlist:wTSJoQhAQqBVMOmxXfN59a",
                        "whereAnyArtistNotIn": [ "9HfCfBYiosO7t62GILFGxB", "LrN6zjcmbUqA0HCXIlNPVL" ]
                    },
                    {
                        "from": "spotify:user:spotify:playlist:KA2A81ktI0MXEPbtGlZs0S",
                        "to": "spotify:user:obhqrwlenifzujsdkvtcpa:playlist:YxUE0blqg47ipugIas7LhT",
                        "whereAllArtistsIn": [ "John Doe", "twba9w5BGoQilbkB3mTNW2", "Jane Doe" ]
                    },
                    {
                        "from": "spotify:user:spotify:playlist:VaOezYffujjiSF3BQnCCeu",
                        "to": "spotify:user:obhqrwlenifzujsdkvtcpa:playlist:9GiNzhW3zqDW9CkkKVbyyo",
                        "whereAllArtistsNotIn": [ "wUHkEcqvsWEOq6h85BYs4R" ]
                    }
                ]
            }
        }
    }
    ```

 1. Navigate your browser to

    ```
    http://localhost/Tools-for-Spotify/src/playlists-sync-one-way.php
    ```

### Backing up playlists

 1. Define your desired sources in `data/config.json`, e.g. like this:

    ```json
    {
        "playlists": {
            "backup": [
                {
                    "which": "spotify:user:obhqrwlenifzujsdkvtcpa:playlist:phmcblzvgwdisqyfakrenx"
                },
                {
                    "which": "me:tracks"
                }
            ]
        }
    }
    ```

 1. Make sure that the `backups` directory is writable by the web server, e.g. using the following command:

    ```bash
    $ sudo chown www-data:www-data backups
    ```

 1. Navigate your browser to

    ```
    http://localhost/Tools-for-Spotify/src/playlists-backup.php
    ```

### Deduplicating playlists

 1. Define your desired targets in `data/config.json`, e.g. like this:

    ```json
    {
        "playlists": {
            "deduplicate": [
                {
                    "which": "spotify:user:obhqrwlenifzujsdkvtcpa:playlist:phmcblzvgwdisqyfakrenx"
                }
            ]
        }
    }
    ```

 1. Navigate your browser to

    ```
    http://localhost/Tools-for-Spotify/src/playlists-deduplicate.php
    ```

### Clearing playlists

 1. Define your desired targets in `data/config.json`, e.g. like this:

    ```json
    {
        "playlists": {
            "clear": [
                {
                    "which": "spotify:user:obhqrwlenifzujsdkvtcpa:playlist:phmcblzvgwdisqyfakrenx"
                }
            ]
        }
    }
    ```

 1. Navigate your browser to

    ```
    http://localhost/Tools-for-Spotify/src/playlists-clear.php
    ```

## References

 * [Spotify Web API](https://developer.spotify.com/web-api/)
   * [Get a playlist’s tracks](https://developer.spotify.com/web-api/get-playlists-tracks/)
   * [Get a user’s saved tracks](https://developer.spotify.com/web-api/get-users-saved-tracks/)
   * [Add tracks to a playlist](https://developer.spotify.com/web-api/add-tracks-to-playlist/)
   * [Scopes (permissions)](https://developer.spotify.com/web-api/using-scopes/)

## Contributing

All contributions are welcome! If you wish to contribute, please create an issue first so that your feature, problem or question can be discussed.

## License

This project is licensed under the terms of the [MIT License](https://opensource.org/licenses/MIT).
