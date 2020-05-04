# Import time for sleep
import logging
import requests
import sys
import json
import time
import os
import re

#urlBase = 'http://192.168.7.58:8092'
urlBase = 'https://barkersrandomprojects.com/api'

plugin_version = '5'

logging.basicConfig(level=logging.INFO, filename='/home/fpp/media/logs/vote.log', filemode='w', format='%(name)s - %(levelname)s - %(message)s')
private_key = ''
public_api_key = ''
next_song_to_play = ''
next_song_to_play_id = ''
playing_songs = True
show_status = -1
last_loaded_playlist = ''
uploaded_song_name = ''
uploaded_song_id = ''
start_time = ''
status_iteration = 0
cached_all_settings = []

def get_new_private_key():
    logging.debug('Getting new private key')

    response = requests.request("GET", urlBase + '/v0/vote/get-new-private-key', headers={}, timeout=(10, 10))

    if response.status_code != 201:
        logging.error('could not generate a new private key')
        exit(0)

    new_private_key = response.text
    save_setting('privateKey', new_private_key )
    logging.info('Generated new private key: {}'.format(new_private_key))
    return new_private_key

def load_songs(playlist_to_load):
    global load_song_tries
    global last_loaded_playlist
    global show_status
    global uploaded_song_name
    global public_api_key

    if load_song_tries == 0:
        set_status('Loading Songs...')
    else:
        set_status('Loading Songs, try ' + str(load_song_tries))

    if playlist_to_load == '':
        for f in os.listdir('/home/fpp/media/playlists/'):
            if f.endswith(".json"):
                playlist_to_load = f[:-5]
                logging.info('No previous song loaded, loading temporarily: {}'.format(playlist_to_load))
                break

    if playlist_to_load == '':
        set_status('You need to create a playlist to use this plugin.')
        return

    file_location = '/home/fpp/media/playlists/{}.json'.format(playlist_to_load)
    with open(file_location) as f:
        data = json.load(f)
    logging.info(data)
    song_names = []

    lead_in_songs = 1

    if 'leadIn' in data:
        lead_in_songs = len(data['leadIn'])
        logging.info('Has lead in with {} song(s)'.format(lead_in_songs))

    title_pref = get_setting_from_cache('votingTitlePreference')
    for idx, song in enumerate(data['mainPlaylist'], start = lead_in_songs):
        if song['enabled'] != 1:
            continue

        title = ''
        logging.info('title pref {}'.format(title_pref))
        if title_pref is None or title_pref == 'sequenceName':
            logging.info('use sequence names')
            if 'sequenceName' in song:
                title = str(song['sequenceName'])
            elif 'mediaName' in song:
                title = str(song['mediaName'])
            else:
                title = 'Unknown'
        else:
            logging.info('use media names')
            if 'mediaName' in song:
                title = str(song['mediaName'])
            elif 'sequenceName' in song:
                title = str(song['sequenceName'])
            else:
                title = 'Unknown'

        song_names.append({"id": idx, "title": title})

    payload = {
        'privateKey': private_key,
        'songs': song_names,
        'status': get_show_status(),
        'nextShow': start_time,
        'pluginVersion': plugin_version
    }

    logging.info("Songs: " + json.dumps(payload))
    url = urlBase + "/v0/vote/upload-songs"
    headers = {
        'Content-Type': 'application/json'
    }
    logging.info(json.dumps(payload))

    try:
        response = requests.request("POST", url, headers=headers, data=json.dumps(payload), timeout=(10, 10))

        if response.status_code != 200:
            logging.error("Can't upload song data: " + str(response.status_code))
            retry_load_songs(playlist_to_load)

        load_song_tries = 0

        public_api_key = response.text
        logging.info("Public API key: " + public_api_key)
        save_setting('publicApiKey', public_api_key)
        set_status('Songs Loaded')
        last_loaded_playlist = playlist_to_load

        show_status = ''
        uploaded_song_name = ''
        upload_settings()
    except Exception as e:
        logging.error('Problem loading songs: ' + str(e))
        retry_load_songs(playlist_to_load)

def retry_load_songs(playlist_to_load):
    global load_song_tries
    if load_song_tries > 10:
        logging.error('Tried several times to upload songs, quitting...')
        set_status('Problem uploading songs to server. Please try restarting the service soon')
        sys.exit(1)
    else:
        load_song_tries += 1
        time.sleep(load_song_tries * 10)
        return load_songs(playlist_to_load)

def upload_settings():
    global cached_all_settings
    url = urlBase + "/v0/vote/upload-settings"
    headers = {
        'Content-Type': 'application/json'
    }

    all_settings = get_all_settings()
    logging.info(all_settings)

    payload = {
        'privateKey': private_key,
        'showSettings': {
            'votingMsg': get_setting_from_all_settings('votingMsg', all_settings),
            'allowCurrentSongVoting': get_setting_from_all_settings('allowCurrentSongVoting', all_settings) == 'true',
            'votingTitlePreference': get_setting_from_all_settings('votingTitlePreference', all_settings),
            'snowing': get_setting_from_all_settings('snowing', all_settings)
        }
    }

    logging.info(json.dumps(payload))
    response = requests.request("POST", url, headers=headers, data=json.dumps(payload), timeout=(10, 10))

    cached_all_settings = all_settings


def get_voting_results():
    global next_song_to_play
    global next_song_to_play_id
    global last_loaded_playlist

    set_status('Getting voting results')
    try:
        url = urlBase + "/v0/vote/results"

        payload = {'privateKey': private_key}
        headers = {
            'Content-Type': 'application/json'
        }

        response = requests.request("POST", url, headers=headers, data=json.dumps(payload), timeout=(10, 10))

        if response.status_code == 404:
            logging.info(response.text)
            load_songs(last_loaded_playlist)
            return


        if response.status_code == 204:
            logging.debug("No songs voted for, continue playing")
            next_song_to_play_id = '-1'
            return

        title = response.json()['title']
        id = response.json()['id']

        logging.info("Title of song to play next: " + title + " id: " + id)
        next_song_to_play = title
        next_song_to_play_id = id

    except Exception as e:
        logging.error("Problem getting voting results! " + str(e))
        set_status('Problem getting voting results...')
        time.sleep(5)

def get_status():
    global playing_songs
    global show_status
    global uploaded_song_name
    global uploaded_song_id
    global start_time
    global status_iteration

    try:
        url = "http://127.0.0.1:32322/fppd/status"
        response = requests.request("GET", url, headers={}, data={}, timeout=(10, 10))

        if response.status_code != 200:
            logging.info(response.text)
            logging.error("Something went wrong getting fpp status")
            set_status('Cant get current falcon player song status...')
            return
        json = response.json()
        current_song = str(json['current_sequence'])
        current_song_id = json['current_playlist']['index']
        seconds_remaining = int(json['seconds_remaining'])
        current_playlist = json['current_playlist']['playlist']


        logging.debug("Current playlist: {}, Current song: {}, Seconds remaining: {}"
                     .format(current_playlist, current_song, str(seconds_remaining)))

        current_show_status = int(json['status'])

        start_time = json['next_playlist']['start_time']

        if status_iteration >= 60:
            status_iteration = 0
            check_show_uploaded()

        if current_show_status != show_status:
            show_status = current_show_status
            upload_show_playing_status()

        if current_show_status != 1:
            logging.debug('Not currently playing songs')
            playing_songs = False
            return

        if not playing_songs:
            playing_songs = True
            logging.info('Load Playlist {}'.format(current_playlist))
            load_songs(current_playlist)
            upload_now_playing(uploaded_song_name, uploaded_song_id)

        # Check to see if our playlist changed
        if current_playlist != last_loaded_playlist:
            logging.info("Playlist changed from '{}' to '{}'".format(last_loaded_playlist, current_playlist))
            load_songs(current_playlist)

        if current_song != uploaded_song_name and current_song_id != uploaded_song_id :
            uploaded_song_name = current_song
            uploaded_song_id = current_song_id
            upload_now_playing(uploaded_song_name, uploaded_song_id)

        set_status('Waiting for song to get close to ending..')
        if seconds_remaining < 3:
            get_voting_results()
            play_next_song_now()
            time.sleep(5)

    except Exception as e:
        logging.error("Problem getting status! " + str(e))

def play_next_song_now():
    global next_song_to_play_id
    global last_loaded_playlist
    global uploaded_song_name

    if next_song_to_play_id == '-1':
        logging.debug('No songs voted for')
        return

    set_status('Starting next song')
    logging.info('Play song now: {}, {}'.format(next_song_to_play_id, last_loaded_playlist))

    url = 'http://127.0.0.1:32322/command/Start Playlist At Item/{}/{}/true'\
        .format(last_loaded_playlist, next_song_to_play_id)

    r = requests.request("GET", url, headers={}, data={}, timeout=(10, 10))
    next_song_to_play_id = ''
    uploaded_song_name = ''

def get_all_settings():
    url = 'http://127.0.0.1/api/configfile/plugin.brp-voting'
    response = requests.request("GET", url, headers={}, data={}, timeout=(10, 10))

    if response.status_code == 200:
        return response.text.splitlines()
    else:
        logging.error('There was a problem getting all of the settings')
        return []

def get_setting_from_all_settings(setting, all_settings):
    regex = setting + ' = "(.*)"$'

    for line in all_settings:
        m = re.match(regex, line)
        if m:
            return m.group(1)

    return None

def get_setting_from_cache(setting):
    global cached_all_settings

    if len(cached_all_settings) == 0:
        cached_all_settings = get_all_settings()

    return get_setting_from_all_settings(setting, cached_all_settings)

def save_setting(setting, value):
    url = 'http://127.0.0.1/api/configfile/plugin.brp-voting'

    all_settings = get_all_settings()

    regex = setting + ' = "(.*)"$'
    found = False
    replacement ='{} = "{}"'.format(setting, value)
    new_settings = []

    for line in all_settings:
        m = re.match(regex, line)
        if m:
            new_settings.append(replacement)
            found = True
        else:
            new_settings.append(line)

    if not found:
        new_settings.append(replacement)

    headers = {
        'Content-Type': 'text/html'
    }

    body = '\n'.join(new_settings)

    response = requests.request("POST", url, headers={}, data=body, timeout=(10, 10))

def set_status(status):
    save_setting('status', status)

def get_show_status():
    status = 'UNKNOWN'
    if show_status == 0:
        status = 'NOT_PLAYING'
        set_status('Idle, a playlist is not currently playing')
    if show_status == 1:
        status = 'PLAYING'
    if show_status == 2:
        status = 'STOPPING'
        set_status('Playlist is gracefully stopping')
    if show_status == 3:
        status = 'STOPPING_LOOP'
        set_status('Playlist is gracefully stopping loop')

    return status

def check_show_uploaded():
    global private_key

    url = urlBase + "/v0/vote/check-show-uploaded"
    headers = {
        'Content-Type': 'application/json'
    }

    payload = {
        'privateKey': private_key,
    }

    response = requests.request("POST", url, headers=headers, data=json.dumps(payload), timeout=(10, 10))

    if response.status_code == 404:
        logging.info('Songs are not loaded, the server must have restarted. Upload them again')
        load_songs(last_loaded_playlist)

def upload_show_playing_status():
    global private_key
    global show_status
    global start_time
    global public_api_key

    url = urlBase + "/v0/vote/current-show-status"
    headers = {
        'Content-Type': 'application/json'
    }

    status = get_show_status()

    payload = {
        'privateKey': private_key,
        'status': status,
        'nextShow': start_time
    }

    logging.info("Setting new status: {}".format(json.dumps(payload)))
    response = requests.request("POST", url, headers=headers, data=json.dumps(payload), timeout=(10, 10))
    logging.debug('Set show status response: {}'.format(response.status_code))

    if response.status_code == 404:
        load_songs(last_loaded_playlist)


    if response.status_code == 200 and public_api_key == '':
        public_api_key = response.text
        logging.info("Setting public API key from now playing status: " + public_api_key)
        save_setting('publicApiKey', public_api_key)

def upload_now_playing(song_name, song_id):
    global private_key

    url = urlBase + "/v0/vote/now-playing"
    headers = {
        'Content-Type': 'application/json'
    }

    payload = {
        'privateKey': private_key,
        'song': { 'id' : song_id, 'title' : song_name}
    }

    logging.info("Setting now playing: {}".format(json.dumps(payload)))
    response = requests.request("POST", url, headers=headers, data=json.dumps(payload), timeout=(10, 10))

    if response.status_code == 404:
        load_songs(last_loaded_playlist)
        upload_now_playing(song_name, song_id)

argument = str(sys.argv[1])

if argument == 'loadSettings':
    private_key = str(sys.argv[2])
    upload_settings()
    exit(0)

if argument == 'newPrivateKey':
    get_new_private_key()
    exit(0)

private_key = argument

logging.info("Private key: {}".format(private_key))
load_song_tries = 0

logging.debug('looping')
while True:
    time.sleep(1)
    get_status()
    status_iteration += 1

