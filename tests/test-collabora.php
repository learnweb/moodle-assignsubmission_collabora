<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Standalone Collabora Test Script
 *
 * @copyright Sept. 2019 Benjamin Ellis <benellis@mukudu.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die(); // Uncomment if you need this testing script
// All the Error Reporting Please //
@error_reporting ( E_ALL | E_STRICT ); // NOT FOR PRODUCTION SERVERS!
@ini_set ( 'display_errors', '1' ); // NOT FOR PRODUCTION SERVERS!
//

$docextensions = array(
    'xlsx' => array('type' => 'Spreadsheet', 'template' => 'fixtures/blankspreadsheet.xlsx'),
    'docx' => array('type' => 'Wordprocessor Document', 'template' => 'fixtures/blankdocument.docx'),
    'pptx' => array('type' => 'Presentation', 'template' => 'fixtures/blankpresentation.pptx')
);

// Callback to here //.
$wopipath = '/wopi/files/';
$protocol = empty($_SERVER['HTTPS']) ? 'http://' : 'https://';
$thiscall = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];
$callbackurl = $thiscall . $wopipath;

$htmlcontent = '';

if ($action = empty($_REQUEST['action']) ? null : $_REQUEST['action']) {
    $collaboraurl = empty($_REQUEST['collaboraurl']) ? null : $_REQUEST['collaboraurl'];
    $docformat = empty($_REQUEST['docformat']) ? null : $_REQUEST['docformat'];
    $readonly = empty($_REQUEST['readonly']) ? 0 : $_REQUEST['readonly'];
    $urlsrc = empty($_REQUEST['urlsrc']) ? 0 : $_REQUEST['urlsrc'];
    $filename = empty($_REQUEST['filename']) ? null : $_REQUEST['filename'];
    $fileid  = empty($_REQUEST['fileid']) ? null : $_REQUEST['fileid'];

    switch ($action) {
        case 'getwopiurlsrc':
            $url = rtrim($collaboraurl, '/').'/hosting/discovery';
            if ($xmldoc = file_get_contents($url)) {
                if ($xml = new \SimpleXMLElement($xmldoc)) {
                    $appath = $xml->xpath("///action[@ext='{$docformat}']");
                    $pathdetails = $appath[0]->attributes();
                    $urlsrc = $pathdetails['urlsrc'];
                    $htmlcontent .= "Source URL for '$docformat' is '$urlsrc'";

                    // Let's also get the server capabilities.
                    $capabilities = null;
                    $cap = $xml->xpath("//app[@name='Capabilities']");
                    if ($capurl = $cap[0]->action['urlsrc']) {
                        $capabilities = file_get_contents($capurl);
                    }
                } else {
                    $htmlcontent .= 'Not an XML reponse';
                }
            } else {
                $htmlcontent .= "Failed to get '$url'";
            }
            break;
        case 'getstartingdoc' :
            $userid = uniqid();        // I know, I know but it will do for now.
            $permission = $readonly ? 400 : 600;
            $fileid = "{$userid}_{$docformat}_{$permission}";
            $filename = "{$userid}.{$docformat}";
            $wopisrc = $callbackurl . $fileid;      // ."?$callbackparam";   //NOTE:  Access Token is not part of the WOPI
            $callbackparam = http_build_query(array('access_token' => $userid));    // There are others such as access_token_ttl & permission
            $docdwnlink = $wopisrc . '/contents' ."?$callbackparam";        // Download Link
            $getfileinfolink = $wopisrc ."?$callbackparam";
            $params = http_build_query(array(
                'WOPISrc' => $wopisrc,
                'access_token' => $userid
            ));
            $doccollaboraurl = $urlsrc . $params;
            break;
        case 'getdownloadlink' :
            if ($filename) {
                $file = rtrim(sys_get_temp_dir(), '/') . '/' . $filename;
                $filelink = '';
                if (file_exists($file)) {
                    $filelink = $thiscall . '?action=sendbacknewfile&filename=' . $filename;
                } else {
                    if ($fileid) {
                        list($userfileid, $docformat, $permission) = explode('_', $fileid);
                        if ($canedit = ($permission == 600)) {
                            $htmlcontent = "You have not saved your file - no file to return.";
                        } else {
                            $htmlcontent = "Read-only file, please start again";
                        }
                    } else {
                        die("File Name is maybe incorrect - file not found - $file");
                    }
                }
            } else {
                die('Missing filename');
            }
            break;
        case 'sendbacknewfile' :
            if ($filename) {
                $file = rtrim(sys_get_temp_dir(), '/') . '/' . $filename;
                if (file_exists($file)) {
                    header('Content-Description: File Transfer');
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename="'.basename($file).'"');
                    header('Expires: 0');
                    header('Cache-Control: must-revalidate');
                    header('Pragma: public');
                    header('Content-Length: ' . filesize($file));
                    ob_clean();
                    flush();
                    readfile($file);
                    exit;
                } else {
                    die("File no longer exists '$file'");
                }
            } else {
                die("File Id is incorrect - file not found - $filename");
            }
    }
} else {
    // this will handle all the Collabora calls
    if ($relativepath = empty($_SERVER['PATH_INFO']) ? null : $_SERVER['PATH_INFO']) {
        error_log("Seen Collabora Call " . $_SERVER['REQUEST_URI']);
        $matches = array();
        preg_match('|/wopi/files/([^/]*)(/contents)?|', $relativepath, $matches);
        if (empty($matches)) {
            error_log("No matches in request path - $relativepath");
            die("Invalid WOPI Call");
        }
        if ($fileid = $matches[1]) {
            list($userfileid, $docformat, $permission) = explode('_', $fileid);
            $canedit = ($permission == 600);
        } else {
            die('File Id Not stipulated.');
        }
        $iscontentcall = isset($matches[2]);

        if (!$userid  = $_REQUEST['access_token']) {
            die("Access Token is required");
        }

        $sendfile = '';
        if ($docformat) {
            $initialfile = $docextensions[$docformat]['template'];
            if (file_exists(__DIR__ . '/' . $initialfile)) {
                $sendfile = __DIR__ . '/' . $initialfile;
            } else {
                die('Template file missing');
            }
        }

        $filedata = file_get_contents('php://input');

        if (!$iscontentcall && !$filedata) {
            error_log('This is a checkfile Info request');
            // This is a checkfile request.
            $ret = (object) array(
                'BaseFileName' => $fileid . ".$docformat",
                'OwnerId' => md5($_SERVER['HTTP_HOST']),          // always the same
                'Size' => filesize($sendfile),
                'UserId' => $userid,
                'UserFriendlyName' => 'Just Another User',
                'UserCanWrite' => $canedit,
                'ReadOnly' => !$canedit,
                'UserCanNotWriteRelative' => true,
                'UserCanRename' => false,
                'LastModifiedTime' => date('c', filemtime($sendfile)),
                'Version' => (string) time(),
            );
            error_log("Returning: " . print_r($ret, true));
            header("Content-Type: application/json");
            echo(json_encode($ret));     // Send back JSON Response.
            exit;
        } else if ($iscontentcall && !$filedata) {
            // This is a GET request - send back the file.
            // send the right headers - at least for browsers.
            error_log("This is a GET file request - '$sendfile'");
            header("Content-Type: " . mime_content_type($sendfile));
            header("Content-Length: " . filesize($sendfile));
            $savefilename = "{$userfileid}.{$docformat}";
            header('Content-Disposition: attachment; filename="'.basename($savefilename).'"');
            ob_clean();
            flush();
            if (($bytesent = readfile($sendfile)) === false) {
                error_log('Failed to send file');
            } else {
                error_log("Sent $bytesent bytes for file size " . filesize($sendfile));
            }
            exit;
        } else if ($iscontentcall && $filedata) {
            // we get the file and save it for the next call.
            error_log('This is a PUT file request');
            $savefile = rtrim(sys_get_temp_dir(), '/') . '/' . "{$userfileid}.{$docformat}";
            error_log('This is a PUT file request: Filename : ' . $savefile);
            $fp = fopen($savefile, 'w');
            fwrite($fp, $filedata);
            fclose($fp);
            exit;
        }
    } // and if not this is our default call
}

?>
<html>
<body>
<div>
    <h1>Collabora Test</h1>
</div>
<?php if (!$action) { ?>
    <!--  Default action - Original Form -->
    <div>
        <form method="POST">
            <input type="hidden" name="action" value="getwopiurlsrc"/>
            <div>
                Collabora URL: &nbsp;
                <input type="url" name="collaboraurl" value="http://127.0.0.1:9980"/>
            </div>
            <div>
                Type of Document : &nbsp;
                <select name="docformat">
                    <?php foreach ($docextensions as $ext => $dets) { ?>
                        <option value="<?php echo $ext; ?>"><?php echo $dets['type']; ?></option>
                    <?php } ?>
                </select>
            </div>
            <div>
                Read-Only? &nbsp;
                <input type="checkbox" name="readonly" value="1"></input>
            </div>
            <div>&nbsp;<input type="submit" name="submit" value="Get Discovery URL"/></div>
        </form>
    </div>
<?php } else if ($action == 'getwopiurlsrc') { ?>
    <!-- Report Back that we have the discovery XML and can ascertain the right URI for our doc. -->
    <?php if ($capabilities) { ?>
        <div>
            <h4>Server Capabilities</h4>
            <p>
            <pre><?php echo print_r((json_decode($capabilities)), true); ?></pre>
            </p>
        </div>
    <?php } ?>
    <form method="POST">
        <div>
            <input type="hidden" name="urlsrc" value="<?php echo $urlsrc; ?>"/>
            <input type="hidden" name="action" value="getstartingdoc"/>
            <?php foreach ($_REQUEST as $key => $value) { ?>
                <?php if ($key !== 'action' && $key !== 'submit') { ?>
                    <input type="hidden" name="<?php echo $key; ?>" value="<?php echo $value; ?>"/>
                <?php } ?>
            <?php } ?>
        </div>
        <div>
            <p><?php echo $htmlcontent; ?></p>
        </div>
        <div><input type="submit" name="submit" value="Get Initial Document"/></div>
    </form>
<?php } else if ($action == 'getstartingdoc') { ?>
    <!--  Here we load the collabora frame and report what we have set. -->
    <div>
        <p><strong>UserId:</strong> <?php echo $userid; ?> <strong>FileId:</strong> <?php echo $fileid; ?></p>
    </div>
    <div>
        <!-- Attempt to do what Collabora should be doing in another window -->
        <div>
            <h4>WOPI Tests Against this script.</h4>
        </div>
        <div>
            <p><a target="_blank" href="<?php echo $getfileinfolink; ?>">View WOPI File Information.</a>
                (<?php echo htmlentities($getfileinfolink); ?>)</p>
        </div>
        <div>
            <p><a target="_blank" href="<?php echo $docdwnlink; ?>">Download Initial File.</a>
                (<?php echo htmlentities($docdwnlink); ?>)</p>
        </div>
    </div>
    <div>
        <h3>Collabora Frame.</h3>
        <p>Frame URL: <?php echo $doccollaboraurl; ?></p>
        <div>
            <iframe src="<?php echo $doccollaboraurl; ?>" class="collabora-iframe" width="100%" height="600px" allow="fullscreen">
            </iframe>
        </div>
    </div>
    <div>
        <p><br/>Please press the 'Done' button to complete the process.</p>
        <form method="POST">
            <input type="hidden" name="action" value="getdownloadlink"/>
            <input type="hidden" name="filename" value="<?php echo $filename; ?>"/>
            <input type="hidden" name="fileid" value="<?php echo $fileid; ?>"/>
            <div><input type="submit" name="submit" value="Done"/></div>
        </form>
    </div>
<?php } else if ($action == 'getdownloadlink') { ?>
    <!-- After Saving the document, we can download it to check we have indeed saved an updated file. -->
    <div>
        <?php if ($filelink) { ?>
            <p>Click <a href="<?php echo $filelink; ?>">here to download the file</a> you have just saved.</p>
        <?php } else { ?>
            <p><?php echo $htmlcontent; ?></p>
        <?php } ?>
    </div>
<?php } else { ?>
    <div>
        <p>Not A valid request for this script.</p>
    </div>
<?php } ?>

<?php if ($action) { ?>
    <div>
        <p><a href="<?php echo $thiscall; ?>">Start Again</a></p>
    </div>
<?php } ?>

</body>
</html>
