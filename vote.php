<h1>Falcon Player Song Voting Plugin</h1>
<h2>Barkers Random Projects</h2>



<?php
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
    shell_exec("/usr/bin/python3 $path/scripts/vote-service.py $privateKey > /dev/null &");
}

function killService() {
    shell_exec("kill $(ps aux | grep 'vote-service.py' | grep -v grep | awk '{print $2}')");
    WriteSettingToFile("restartPlugin", "false", "brp-voting");
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
    shell_exec("/usr/bin/python3 $path/scripts/vote-service.py newPrivateKey > /dev/null");
}

if (isset($_POST['loadSettings'])) {
    $privateKey = ReadSettingFromFile('privateKey', 'brp-voting');
    shell_exec("/usr/bin/python3 $path/scripts/vote-service.py loadSettings $privateKey > /dev/null");
}

?>
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
        var url = 'api/configfile/plugin.brp-voting?cacheBust=' + new Date().getTime();
        $.ajax({
            type: 'GET',
            url: url,
            dataType: 'text',
            contentType: 'text/plain',
            headers: {
                'Accept': 'text/plain'
            },
            success: function (data) {
                console.log("configs: " + JSON.stringify(data));
                var allSettings = data.split('\n');
                callback(allSettings)
            },
            error: function () {
                callback('')
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

            if (privateKey === false || privateKey === null) {
                $('#keyDisabled').val('You need to generate a new key');
                $('#startServiceSection').hide();
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
            $('#votingUrl').show();
            $('#votingUrlSpinner').hide();
        });
    }

    function fillPluginSettings() {
        getAllSettings(function (allSettings) {
            $('#votingMsg').val(getSettingFromAllSettings('votingMsg', allSettings));
            var re = new RegExp('\\\\n', 'gm');
            var votingHtml = getSettingFromAllSettings('votingMsgHtml', allSettings);
            if (votingHtml) {
                votingHtml = votingHtml.replace(re, "\n");
            }

            $('#votingMsgHtml').val(votingHtml);
            $('#currentSongVoting').prop('checked', getSettingFromAllSettings('allowCurrentSongVoting', allSettings) === 'true');
            $('#snowing').prop('checked', getSettingFromAllSettings('snowing', allSettings) === 'true');
            $('#allowDuplicateVotes').prop('checked', getSettingFromAllSettings('allowDuplicateVotes', allSettings) === 'true');
            $('#launchOnReboot').prop('checked', getSettingFromAllSettings('launchOnReboot', allSettings) === 'true');
            $('#backgroundImage').val(getSettingFromAllSettings('backgroundImage', allSettings));

            var backgroundGradientFirst = getSettingFromAllSettings('backgroundGradientFirst', allSettings);
            if (backgroundGradientFirst) {
                $('#backgroundGradientFirst').val('#' + backgroundGradientFirst);
            }

            var backgroundGradientSecond = getSettingFromAllSettings('backgroundGradientSecond', allSettings)
            if (backgroundGradientSecond) {
                $('#backgroundGradientSecond').val('#' + backgroundGradientSecond);
            }

            var fontColorHeader = getSettingFromAllSettings('fontColorHeader', allSettings);
            if (fontColorHeader) {
                $('#fontColorHeader').val('#' + fontColorHeader);
            }

            var pref = '#' + getSettingFromAllSettings('votingTitlePreference', allSettings);
            if (pref) {
                $(pref).prop("checked", true);
            }

            // Mark the plugin as being restarted
            if (getSettingFromAllSettings('restartPlugin', allSettings) === 'true') {
                $('#indicateRestart').show();
            }

            $('#loadingSettingsIndicator').hide();
        });
    }

    function savePluginSettings() {
        $('#settingsSaveBtn').hide();
        $('#savingSettingsIndicator').show();
        getAllSettings(function (allSettings){
            allSettings = addSettingToAllSettings('votingMsg', $('#votingMsg').val(), allSettings);
            var re = new RegExp(/\n/, 'g');
            var votingMsgHtml = $('#votingMsgHtml').val().replace(re, '\\n');
            allSettings = addSettingToAllSettings('votingMsgHtml', votingMsgHtml, allSettings);
            allSettings = addSettingToAllSettings('allowCurrentSongVoting', $('#currentSongVoting').prop("checked") + '', allSettings);
            allSettings = addSettingToAllSettings('snowing', $('#snowing').prop("checked") + '', allSettings);
            allSettings = addSettingToAllSettings('allowDuplicateVotes', $('#allowDuplicateVotes').prop("checked") + '', allSettings);
            allSettings = addSettingToAllSettings('launchOnReboot', $('#launchOnReboot').prop("checked") + '', allSettings);
            var prefPrev = getSettingFromAllSettings('votingTitlePreference', allSettings);
            var prefNow = $('input[name="votingTitlePreference"]:checked').val();

            if (prefNow !== prefPrev) {
                allSettings = addSettingToAllSettings('restartPlugin', 'true', allSettings);
            }

            allSettings = addSettingToAllSettings('votingTitlePreference', prefNow, allSettings);
            allSettings = addSettingToAllSettings('backgroundImage', $('#backgroundImage option').filter(':selected').val(), allSettings);
            // Strip '#' from color values before saving
            var bgFirst = $('#backgroundGradientFirst').val();
            if (bgFirst && bgFirst.startsWith('#')) {
                bgFirst = bgFirst.substring(1);
            }
            allSettings = addSettingToAllSettings('backgroundGradientFirst', bgFirst, allSettings);


            var bgSecond = $('#backgroundGradientSecond').val();
            if (bgSecond && bgSecond.startsWith('#')) {
                bgSecond = bgSecond.substring(1);
            }
            allSettings = addSettingToAllSettings('backgroundGradientSecond', bgSecond, allSettings);

            var fontColor = $('#fontColorHeader').val();
            if (fontColor && fontColor.startsWith('#')) {
                fontColor = fontColor.substring(1);
            }

            allSettings = addSettingToAllSettings('fontColorHeader', fontColor, allSettings);
            allSettings = addSettingToAllSettings('clearStats', $('#clearStats').prop("checked") + '', allSettings);

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
        $('#brp-div').tooltip({
            "content": function(){
                return $(this).attr('data-title');
            }
        });
    });
    // color palettes for native color pickers
    $(function() {
        $('#backgroundGradientFirstPalette').on('change', function() {
            if ($(this).val() === '--') {
                $('#backgroundGradientFirst').val('#ffffff');
            } else {
                $('#backgroundGradientFirst').val($(this).val());
            }
        });
        $('#backgroundGradientSecondPalette').on('change', function() {
            if ($(this).val() === '--') {
                $('#backgroundGradientSecond').val('#ffffff');
            } else {
                $('#backgroundGradientSecond').val($(this).val());
            }
        });
        $('#fontColorHeaderPalette').on('change', function() {
            if ($(this).val() === '--') {
                $('#fontColorHeader').val('#ffffff');
            } else {
                $('#fontColorHeader').val($(this).val());
            }
        });
    });
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
                            print('<input id="generateKeyBtn" class="buttons" type="submit" name="generateNewKey" value="Generate New Key">');
                        }
                    ?>
                </td>
            </form>
        </tr>

        <div id="isServiceRunningDiv">
            <form method="post" id="serviceState">
                <?php
                if (isServiceRunning()) {
                    print("<tr>");
                    print("<td>Service is running.</td>");
                    print('<td><input id="stopSvcBtn" class="buttons" name="killService" type="submit" value="Stop Service"/>');
                    print ('<script>setVotingUrl()</script>');
                    print("</tr>");

                    print('<tr id="currentStatusDiv"><td>Current status:</td><td id="status"></td></tr>');
                    print('<script>monitorStatus()</script>');
                    print('
                          <tr>
                            <td>Unique voting URL:</td><td><a id="votingUrl" target="_blank" ></a> <i id="votingUrlSpinner" class="fas fa-circle-notch fa-spin"></i>
                            </td>
                          </tr>
                          ');

                } else {
                    print("<tr id='startServiceSection'>");
                    print("<td style='color:darkred;font-weight:bold;'>Service is not running!</td>");
                    print('<td><input id="startSvcBtn" class="buttons" name="startService" type="submit" value="Start Service"/></td>');
                    print("</tr>");

                }
                ?>
            </form>
        </div>
    </table>
    <hr>
    <div>
        <p>Please help support the upkeep and cost of the server along with other projects we are working on!</p>
        <form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
            <input type="hidden" name="cmd" value="_s-xclick" />
            <input type="hidden" name="hosted_button_id" value="PZJGWXKBQFFHG" />
            <input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_SM.gif" border="0" name="submit" alt="Donate with PayPal button" />
        </form>
    </div>
    <hr>
    <h2>Settings</h2>
    <div id="loadingSettingsIndicator">Loading... <i class="fas fa-circle-notch fa-spin"></i></div>
    <h2 style='color:darkred;font-weight:bold; display: none;' id="indicateRestart">Please stop and then start the plugin for all of the settings to take effect</h2>
    <table id="settingsTable">
        <tr>
            <td>Header Message <i class="fa fa-info-circle" data-bs-toggle="tooltip" data-bs-html="true" data-bs-placement="right" data-bs-title="Sets the message voters will see at the top of the page while a playlist is playing"></i></td>
            <td><input id="votingMsg" placeholder="Vote for the next song!"/></td>
        </tr>
        <tr>
            <td>HTML Message <i class="fa fa-info-circle" data-bs-toggle="tooltip" data-bs-html="true" data-bs-placement="right" data-bs-title="Sets the message voters will see at the top of the page, allowing the flexibility of using HTML"></i></td>
            <td><textarea id="votingMsgHtml"  rows="6" cols="80" placeholder="<h2>Vote for the next song!</h2>"></textarea></td>
        </tr>
        <tr>
            <td>Message Text Color <i class="fa fa-info-circle" data-bs-toggle="tooltip" data-bs-html="true" data-bs-placement="right" data-bs-title="Changes color of the header text
                <br><br>Click into the input box to activate a color picker, or use the dropdown next to the input
                box to select one of many predefined colors."></i></td>
            <td>
                <input type="color" id="fontColorHeader">
                Predefined colors:
                <select id="fontColorHeaderPalette">
                    <option>--</option>
                    <option value="#000000" style="background: #000000; color: #cacaca">Black</option>
                    <option value="#ffffff" style="background: #ffffff;">White</option>
                    <option value="#fce94f" style="background: #fce94f;">Butter</option>
                    <option value="#fcaf3e" style="background: #fcaf3e;">Orange</option>
                    <option value="#e9b96e" style="background: #e9b96e;">Chocolate</option>
                    <option value="#8ae234" style="background: #8ae234;">Chameleon</option>
                    <option value="#729fcf" style="background: #729fcf;">Sky blue</option>
                    <option value="#ad7fa8" style="background: #ad7fa8;">Plum</option>
                    <option value="#ef2929" style="background: #ef2929;">Scarlet red</option>
                </select>
            </td>
        </tr>
        <tr>
            <td>Background Gradient <i class="fa fa-info-circle" data-bs-toggle="tooltip" data-bs-html="true" data-bs-placement="right" data-bs-title="Changes the background color shown on the voting website.
                If two colors are picked, then a gradient will be displayed.<br><br>Click into the input box to activate a color picker, or use the dropdown next to the input
                box to select one of many predefined colors."></i></td>
            <td>
                <table>
                    <tr>
                        <td>Primary Color</td>
                        <td>
                            <input type="color" id="backgroundGradientFirst">
                            Predefined colors:
                            <select id="backgroundGradientFirstPalette">
                                <option>--</option>
                                <option value="#ffffff" style="background: #ffffff;">White</option>
                                <option value="#fce94f" style="background: #fce94f;">Butter</option>
                                <option value="#fcaf3e" style="background: #fcaf3e;">Orange</option>
                                <option value="#e9b96e" style="background: #e9b96e;">Chocolate</option>
                                <option value="#8ae234" style="background: #8ae234;">Chameleon</option>
                                <option value="#729fcf" style="background: #729fcf;">Sky blue</option>
                                <option value="#ad7fa8" style="background: #ad7fa8;">Plum</option>
                                <option value="#ef2929" style="background: #ef2929;">Scarlet red</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            Secondary Color
                        </td>
                        <td>
                            <input type="color" id="backgroundGradientSecond">
                            Predefined colors:
                            <select id="backgroundGradientSecondPalette">
                                <option>--</option>
                                <option value="#ffffff" style="background: #ffffff;">White</option>
                                <option value="#fce94f" style="background: #fce94f;">Butter</option>
                                <option value="#fcaf3e" style="background: #fcaf3e;">Orange</option>
                                <option value="#e9b96e" style="background: #e9b96e;">Chocolate</option>
                                <option value="#8ae234" style="background: #8ae234;">Chameleon</option>
                                <option value="#729fcf" style="background: #729fcf;">Sky blue</option>
                                <option value="#ad7fa8" style="background: #ad7fa8;">Plum</option>
                                <option value="#ef2929" style="background: #ef2929;">Scarlet red</option>
                            </select>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td>Background Image <i class="fa fa-info-circle" data-bs-toggle="tooltip" data-bs-html="true" data-bs-placement="right" data-bs-title="Changes the background image shown on the voting website"></i></td>
            <td>
                <select id="backgroundImage">
                    <option value="NONE">-- None --</option>
                    <option value="SNOWMAN">Snowman</option>
                    <option value="SNOWMAN_WITH_GLOVES">Snowman With Gloves</option>
                    <option value="NATIVITY">Nativity</option>
                    <option value="PUMPKIN">Pumpkin</option>
                    <option value="PUMPKIN_HAPPY">Pumpkin Happy</option>
                    <option value="PUMPKIN_SCARY">Pumpkin Scary</option>
                </select>
            </td>
        </tr>
        <tr>
            <td>Snowing Theme <i class="fa fa-info-circle" data-bs-toggle="tooltip" data-bs-html="true" data-bs-placement="right" data-bs-title="Creates a 'Snowing' effect on the voting website"></i></td>
            <td><input id="snowing" type="checkbox"/></td>
        </tr>
        <tr>
            <td>Title Preference <i class="fa fa-info-circle" data-bs-toggle="tooltip" data-bs-html="true" data-bs-placement="right" data-bs-title="This will either prefer the audio
            name or the sequence name for the title displayed on the voting website. If either the sequence or media is
            not available, it will fall back to the other. Underscores (e.g. '_') and the file extension (e.g. '.mp3')
            will be stripped off by default on the voting website."></i></td>
            <td>
                <div class="custom-control custom-radio">
                    <input type="radio" class="custom-control-input" name="votingTitlePreference" id="sequenceName" value="sequenceName" checked>
                    <label class="custom-control-label" for="sequenceName">Sequence Name</label>
                </div>
                <div class="custom-control custom-radio">
                    <input type="radio" class="custom-control-input" name="votingTitlePreference" id="audioName" value="audioName">
                    <label class="custom-control-label" for="audioName">Audio Name</label>
                </div>
                <div class="custom-control custom-radio">
                    <input type="radio" class="custom-control-input" name="votingTitlePreference" id="mp3Tag" value="mp3Tag">
                    <label class="custom-control-label" for="mp3Tag">MP3 Tag</label>
                </div>
            </td>
        </tr>
        <tr>
            <td>Current Song Voting <i class="fa fa-info-circle" data-bs-toggle="tooltip" data-bs-html="true" data-bs-placement="right" data-bs-title="This will allow the voters to vote for the current playing song, potentially playing the same song multiple times in a row"></i></td>
            <td><input id="currentSongVoting" type="checkbox"/></td>
        </tr>
        <tr>
            <td>Allow Duplicate Votes <i class="fa fa-info-circle" data-bs-toggle="tooltip" data-bs-html="true" data-bs-placement="right" data-bs-title="Allows the same person to vote multiple times"></i></td>
            <td><input id="allowDuplicateVotes" type="checkbox"/></td>
        </tr>
        <tr>
            <td>Launch On Reboot <i class="fa fa-info-circle" data-bs-toggle="tooltip" data-bs-html="true" data-bs-placement="right" data-bs-title="If Falcon Player is rebooted, this will enable the voting plugin to auto restart"></i></td>
            <td><input id="launchOnReboot" type="checkbox"/></td>
        </tr>
        <tr>
            <td>Clear Song Stats <i class="fa fa-info-circle" data-bs-toggle="tooltip" data-bs-html="true" data-bs-placement="right" data-bs-title="Clears out all of the song Statistics"></i></td>
            <td><input id="clearStats" type="checkbox"/></td>
        </tr>
        <tr>
            <td></td>
            <td>
                <button class="buttons btn-success" id="settingsSaveBtn">Save</button>
                <span id="savingSettingsIndicator" class="fas fa-circle-notch fa-spin" style="display: none;"></span>
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
    <hr>
    <h2>Song Statistics</h2>
    <p>Refresh your page to see updates. Updates will occur about once every minute</p>
    <p>This is to give some kind of insight on what is currently being voted on. Next season this will change to look
    much nicer, and be much more robust!</p>
    <?php
    include('brp-db-connect.php');
    $db = new BrpDbConnect();
    echo '<table border="1">';
    echo '<tr>';
//    echo '<td>Database id</td>';
    echo '<td>Song id</td>';
    echo '<td>Song Name</td>';
    echo '<td>Votes</td>';
    echo '</tr>';
    $count = $db->querySingle("SELECT COUNT(*) as count FROM song_stats");
    if ($count === 0) {
        echo '<tr><td colspan="3">No song votes have been recorded</td></tr>';
    } else {
        $result = $db->query('SELECT * FROM song_stats order by song_id');
        while($row = $result->fetchArray()){
            echo '<tr>';
//        echo '<td>'.$row['id'].'</td>';
            echo '<td>'.$row['song_id'].'</td>';
            echo '<td>'.$row['song_name'].'</td>';
            echo '<td>'.$row['votes'].'</td>';
            echo '</tr>';
        }
    }
    echo '</table>';
    $db->close();
    ?>
</div>
