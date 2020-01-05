# Import time for sleep
import logging
import requests
import sys
import json
import time

#urlBase = 'http://192.168.7.58:8092'
urlBase = 'https://barkersrandomprojects.com/api'

logging.basicConfig(level=logging.INFO, filename='/home/fpp/media/logs/vote.log', filemode='w', format='%(name)s - %(levelname)s - %(message)s')
playlist_file_location = ''
playlist_name = ''
private_key = ''
next_song_to_play = ''
next_song_to_play_id = ''


def get_new_private_key():
    logging.debug('Getting new private key')

    response = requests.request("GET", urlBase + '/v0/vote/getNewPrivateKey', headers={}, timeout=(10, 10))
    new_private_key = response.text.encode('utf-8')
    save_setting('privateKey', new_private_key )
    return new_private_key

def load_songs():
    global loadSongsTries

    if loadSongsTries == 0:
        set_status('Loading Songs...')
    else:
        set_status('Loading Songs, try ' + str(loadSongsTries))

    with open(playlist_file_location) as f:
        data = json.load(f)
    logging.info(data)
    song_names = []

    for idx, song in enumerate(data['mainPlaylist']):
        logging.info(song['enabled'])

        if song['enabled'] == 1:
            song_names.append({"id": idx, "title": str(song['sequenceName'])})

    payload = {
        'privateKey': private_key,
        'songs': song_names
    }

    logging.info("Songs: " + json.dumps(payload))
    url = urlBase + "/v0/vote/uploadSongs"
    headers = {
        'Content-Type': 'application/json'
    }
    logging.info(json.dumps(payload))

    try:
        response = requests.request("POST", url, headers=headers, data=json.dumps(payload), timeout=(10, 10))

        if response.status_code != 200:
            logging.error("Can't upload song data: " + str(response.status_code))
            retry_load_songs()

        loadSongsTries = 0

        logging.info('Status code: ' + str(response.status_code))
        public_api_key = response.text.encode('utf-8')
        logging.info("Public API key: " + public_api_key)
        save_setting('publicApiKey', public_api_key)
        set_status('Songs Loaded')
    except Exception, e:
        logging.error('Problem loading songs: ' + str(e))
        retry_load_songs()

def retry_load_songs():
    global loadSongsTries
    if loadSongsTries > 10:
        logging.error('Tried several times to upload songs, quitting...')
        set_status('Problem uploading songs to server. Please try restarting the service soon')
        sys.exit(1)
    else:
        loadSongsTries += 1
        time.sleep(loadSongsTries * 10)
        return load_songs()

def get_voting_results():
    global next_song_to_play
    global next_song_to_play_id

    set_status('Getting voting results')
    try:
        url = urlBase + "/v0/vote/results"

        payload = {'privateKey': private_key}
        headers = {
            'Content-Type': 'application/json'
        }

        response = requests.request("POST", url, headers=headers, data=json.dumps(payload), timeout=(10, 10))

        if response.status_code == 404:
            logging.info(response.text.encode('utf-8'))
            load_songs()
            return

        title = response.json()['title'].encode('utf-8')
        id = response.json()['id'].encode('utf-8')
        logging.info("Title of song to play next: " + title + " id: " + id)
        next_song_to_play = title
        next_song_to_play_id = id

    except Exception, e:
        logging.error("Problem getting voting results! " + str(e))
        set_status('Problem getting voting results...')
        time.sleep(5)

def get_status():
    set_status('Waiting for song to get close to ending..')
    try:
        url = "http://localhost/fppjson.php?command=getFPPstatus"
        response = requests.request("GET", url, headers={}, data={}, timeout=(10, 10))

        if response.status_code != 200:
            logging.info(response.text.encode('utf-8'))
            logging.error("Something went wrong getting fpp status")
            set_status('Cant get current falcon player song status...')
            return
        json = response.json()
        current_song = str(json['current_sequence'].encode('utf-8'))
        seconds_remaining = int(json['seconds_remaining'].encode('utf-8'))
        logging.debug("Current song: " + current_song + ", Seconds remaining: " + str(seconds_remaining))

        if seconds_remaining < 3:
            get_voting_results()
            play_next_song_now()
            time.sleep(5)
            #gracefullyStop()

    except Exception, e:
        logging.error("Problem getting status! " + str(e))

def gracefully_stop():
    logging.debug('Gracefully stop current song')
    url = 'http://localhost/fppxml.php?command=stopGracefully'
    r = requests.request("GET", url, headers={}, data={}, timeout=(10, 10))
    logging.info(r.status_code)

def stop_now():
    logging.debug('Stop song now')
    url = 'http://localhost/fppxml.php?command=stopNow'
    r = requests.request("GET", url, headers={}, data={}, timeout=(10, 10))
    logging.info(r.status_code)

def play_next_song_now():
    global next_song_to_play_id
    set_status('Starting next song')
    stop_now()
    logging.info('Play song now: ' + next_song_to_play_id + ', ' + playlist_name)
    url = 'http://localhost/fppxml.php?command=startPlaylist&repeat=checked&playList=' + playlist_name + '&playEntry=' + next_song_to_play_id
    r = requests.request("GET", url, headers={}, data={}, timeout=(10, 10))
    next_song_to_play_id = ''
    logging.info(r.status_code)


def save_setting(setting, value):
    url = "http://localhost/fppjson.php?command=setPluginSetting&plugin=brp-voting&key=" + setting + "&value=" + value
    response = requests.request("GET", url, headers={}, data={}, timeout=(10, 10))

def get_setting(setting):
    url = "fppjson.php?command=getPluginSetting&plugin=brp-voting&key=" + setting
    response = requests.request("GET", url, headers={}, data={}, timeout=(10, 10))
    return response.text.encode('utf-8')

def set_status(status):
    save_setting('status', status.replace(' ', '*'))

logging.info("arglength: " + str(len(sys.argv)))
logging.info("arglength: " + str(sys.argv[0]))
logging.info("arglength: " + str(sys.argv[1]))
if len(sys.argv) == 2:
    get_new_private_key()
    exit(0)

if len(sys.argv) >= 3:
    playlist_file_location = str(sys.argv[1])
    playlist_name = str(sys.argv[2])

if len(sys.argv) != 4:
    private_key = get_new_private_key()
else:
    private_key = str(sys.argv[3])

logging.info('Starting voting service with playlist ' + playlist_file_location)

loadSongsTries = 0


load_songs()

logging.debug('looping')
while(True):
    time.sleep(1)
    get_status()



