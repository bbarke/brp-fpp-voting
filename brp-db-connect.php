<?php
/**
 * Simple example of extending the SQLite3 class and changing the __construct
 * parameters, then using the open method to initialize the DB.
 */
class BrpDbConnect extends SQLite3
{
    function __construct()
    {
        $this->open('/home/fpp/media/plugins/brp-fpp-voting/brp-vote.db');
        // Same schema as vote-service.py; the page can load before the service has ever run.
        $this->exec('CREATE TABLE IF NOT EXISTS song_stats (id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT, song_id VARCHAR(5), song_name TEXT, votes INTEGER)');
    }
}