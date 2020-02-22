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

if (isset($_POST['loadSettings'])) {
    $privateKey = ReadSettingFromFile('privateKey', 'brp-voting');
    shell_exec("/usr/bin/python2.7 $path/scripts/vote-service.py loadSettings $privateKey > /dev/null");
}

?>

<script src="https://kit.fontawesome.com/4b00e40eba.js"></script>
<script>
    var voteUrlInterval;

    function getSetting(setting, callback) {
        getAllSettings(function (allSettings) {
            callback(getSettingFromAllSettings(setting, allSettings));
        });
    }

    function getSettingFromAllSettings(setting, allSettings) {
        var settingRegex = new RegExp(setting + ' = "(.*)"$');
        for (var i = 0; i < allSettings.length; i++) {
            var line = allSettings[i];
            if (line.match(settingRegex)) {
                return line.replace(settingRegex, "$1");
            }
        }
        return null;
    }

    function getAllSettings(callback) {
        var url = 'api/configfile/plugin.brp-voting';
        $.ajax({
            type: 'GET',
            url: url,
            success: function (data) {
                console.log(JSON.stringify(data));
                var allSettings = data.split('\n');
                callback(allSettings)
            }
        });
    }

    function addSettingToAllSettings(setting, value, allSettings) {
        var settingRegex = new RegExp(setting + ' = "(.*)"$');
        var settingToSave = setting + ' = "' + value + '"';
        var exists = false;

        for (var i = 0; i < allSettings.length; i++) {
            var line = allSettings[i];
            if (line.match(settingRegex)) {

                if (value && value.length > 0) {
                    allSettings[i] = settingToSave;
                } else {
                    allSettings.splice(i, 1);
                    console.log('splice');
                }

                exists = true;
                break;
            }
        }

        if (!exists) {
            allSettings.push(settingToSave)
        }

        console.log(allSettings);
        return allSettings;
    }

    function saveSettings(allSettings, callback) {
        var url = 'api/configfile/plugin.brp-voting';

        var settings = allSettings.join('\n');

        $.post(url, settings)
            .done(function( data ) {
                callback(settings);
        });
    }

    function removeElement(elementId) {
        $(elementId).remove(elementId);
    }

    function setPrivateKeyField() {
        getSetting('privateKey', function (data) {
            var privateKey = data;

            if (privateKey === false) {
                $('#keyDisabled').val('You need to generate a new key');
                return;
            }

            $('#keyDisabled').val(privateKey)
        });
    }

    function setVotingUrl() {
        getSetting('publicApiKey', function (data) {
            var publicKey = data;

            if (publicKey === false || publicKey === 'false') {
                return
            }

            clearInterval(voteUrlInterval);
            var href = 'https://barkersrandomprojects.com/vote/' + publicKey;
            $('#votingUrl').attr('href', href).text(href);
            $('#votingUrlContainer').show();
        });
    }

    function fillPluginSettings() {
        getAllSettings(function (allSettings) {
            $('#votingMsg').val(getSettingFromAllSettings('votingMsg', allSettings));
            $('#currentSongVoting').prop('checked', getSettingFromAllSettings('allowCurrentSongVoting', allSettings) === 'true');
        });
    }

    function savePluginSettings() {
        getAllSettings(function (allSettings){
            allSettings = addSettingToAllSettings('votingMsg', $('#votingMsg').val(), allSettings);
            allSettings = addSettingToAllSettings('allowCurrentSongVoting', $('#currentSongVoting').prop("checked") + '', allSettings);
            saveSettings(allSettings, function (data) {
                $('#loadSettingsBtn').click();
            })
        });
    }

    function getStatus() {
        getSetting('status', function(data) {
            $('#status').text(data)
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
    $( function() {
        $('#brp-div').tooltip();
    } );
</script>
<style>
    .ui-tooltip {
        padding: 4px 4px;
        box-shadow: 0 0 7px black;
        margin-left: 20px;
    }
    .ui-tooltip-content {
        font-size:12pt;
        font-family:"Times New Roman";
    }
    #brp-div {
        margin: 10px;
    }
</style>

<div id="brp-div">
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
    <h2>Settings</h2>
    <table>
        <tr>
            <td>Voting message <i class="fa fa-info-circle" aria-hidden="true" title="Sets the message voters will see at the top of the page while a playlist is playing"></i></td>
            <td><input id="votingMsg" placeholder="Vote for the next song!"/></td>
        </tr>
        <tr>
            <td>Allow voting for current song <i class="fa fa-info-circle" aria-hidden="true" title="This will allow the voters to vote for the current playing song, potentially playing the same song multiple times in a row"></i></td>
            <td><input id="currentSongVoting" type="checkbox"/></td>
        </tr>
        <tr>
            <td></td>
            <td>
                <button class="button" id="settingsSaveBtn">Save</button>
                <div id="submitSettings" hidden>
                    <form id="loadSettingsForm" method="post">
                        <input id="loadSettingsBtn"
                               class="button"
                               type="submit"
                               name="loadSettings"
                               value="Submit Settings">
                    </form>
                </div>
            </td>
        </tr>
    </table>
    <script>
        $('#settingsSaveBtn').click(function () {
            savePluginSettings();
        });
        fillPluginSettings()
    </script>
</div>