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
    }
}