<h1>Falcon Player Song Voting Plugin</h1>
<h2>Barkers Random Projects</h2>



<?php
#$path = "/home/fpp/media/plugins/fpp-vote-dev";
$path = "/home/fpp/media/plugins/brp-fpp-voting";

function tailFile($filepath, $lines = 1) {
    return trim(implode("", array_slice(file($filepath), -$lines)));
}

function isServiceRunning() {
    $exists = false;
    exec("ps -ax | grep -i vote-service.py  | grep -v grep", $pids);
    if (count($pids) > 0) {
        $exists = true;
    }
    return $exists;
}

function startService() {
    global $path;

    $privateKey = ReadSettingFromFile('privateKey', 'brp-voting');
    WriteSettingToFile("publicApiKey", "false", "brp-voting");
    shell_exec("/usr/bin/python2.7 $path/scripts/vote-service.py $privateKey > /dev/null &");
}

function killService() {
    shell_exec("kill $(ps aux | grep 'vote-service.py' | grep -v grep | awk '{print $2}')");
}

function saveKey($key) {
    global $path;
    shell_exec("echo 'key: $key' > $path/key.txt");
}

if (isset($_POST['startService'])) {
    startService();
}

if (isset($_POST['killService'])) {
    killService();
}

if (isset($_POST['generateNewKey'])) {
    shell_exec("/usr/bin/python2.7 $path/scripts/vote-service.py newPrivateKey > /dev/null");
}

?>

<script>
    var voteUrlInterval;

    function saveSettings(setting, value, message) {

        var url = "fppjson.php?command=setPluginSetting&plugin=brp-voting&key=" + setting + "&value=" + value;

        $.ajax({
            type: 'GET',
            dataType: "json",
            url: url,
            success: function(data) {
                console.log(JSON.stringify(data));
                $.jGrowl(message)

            }
        });
    }

    function getSetting(setting, callback) {
        var url = "fppjson.php?command=getPluginSetting&plugin=brp-voting&key=" + setting;

        $.ajax({
            type: 'GET',
            dataType: "json",
            url: url,
            success: function(data) {
                console.log(JSON.stringify(data));
                callback(data);
            }
        });
    }

    function removeElement(elementId) {
        $(elementId).remove(elementId);
    }

    function setPrivateKeyField() {
        getSetting('privateKey', function (data) {
            var privateKey = data.privateKey;

            if (privateKey === false) {
                $('#keyDisabled').val('You need to generate a new key');
                return;
            }

            $('#keyDisabled').val(privateKey)
        });
    }

    function setVotingUrl() {
        getSetting('publicApiKey', function (data) {
            var publicKey = data.publicApiKey;

            if (publicKey === false || publicKey === 'false') {
                return
            }

            clearInterval(voteUrlInterval);
            var href = 'https://barkersrandomprojects.com/vote/' + publicKey;
            $('#votingUrl').attr('href', href).text(href);
            $('#votingUrlContainer').show();
        });
    }

    function getStatus() {
        getSetting('status', function(data) {
            $('#status').text(data.status.replace(/\*/g, ' '))
        })
    }

    function hasVoteUrl() {
        return $('#votingUrlContainer').css('visibility') === 'hidden';
    }

    function monitorStatus() {
        $('#currentStatusDiv').show();
        setInterval(function() {
            getStatus();
        }, 1000);

        voteUrlInterval = setInterval(function () {
            setVotingUrl();
            setPrivateKeyField();
        }, 1000);
    }
    setPrivateKeyField();
</script>

<?php
if (ReadSettingFromFile('playlistSpaceError', 'brp-voting') === 'true') {
    print("<span style='color:darkred;font-weight:bold;'>One of your playlists have a space in it's name.
This causes an error when trying to save the playlist settings because of a bug in FPP.
You will need to resolve this before the playlist will be selectable in the dropdown below</span><br>");
}
?>
<table>
    <tr>
        <form method="post">
            <td>Private Key<br>(DO NOT SHARE):</td>
            <td>
                <input id="keyDisabled" type="text" name="keyDisabled" size="36" disabled>
                <?php
                    if (!isServiceRunning()) {
                        print('<input id="generateKeyBtn" class="button" type="submit" name="generateNewKey" value="Generate New Key">');
                    }
                ?>
            </td>
        </form>
    </tr>

    <div id="isServiceRunningDiv">
        <form method="post">
            <?php
            if (isServiceRunning()) {
                print("<tr>");
                print("<td>Service is running.</td>");
                print('<td><input id="stopSvcBtn" class="button" name="killService" type="submit" value="Stop Service"/>');
                print ('<script>setVotingUrl()</script>');
                print("</tr>");

                print('<tr id="currentStatusDiv" hidden><td>Current status:</td><td id="status"></td></tr>');
                print ('<script>monitorStatus()</script>');

            } else {
                print("<tr>");
                print("<td style='color:darkred;font-weight:bold;'>Service is not running!</td>");
                print('<td><input id="startSvcBtn" class="button" name="startService" type="submit" value="Start Service"/></td>');
                print("</tr>");

            }
            ?>
        </form>
    </div>
    <tr>
        <td colspan="2">
        <div id="votingUrlContainer" hidden>
            Your unique voting URL is: <a id="votingUrl" target="_blank"></a>
        </div></td>
    </tr>
</table>
<div>
    <p>Please help support the upkeep and cost of the server along with other projects we are working on!</p>
    <form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
        <input type="hidden" name="cmd" value="_s-xclick" />
        <input type="hidden" name="hosted_button_id" value="PZJGWXKBQFFHG" />
        <input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_SM.gif" border="0" name="submit" alt="Donate with PayPal button" />
        <img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1" />
    </form>
</div>

