<?php
require __DIR__ . '/vendor/autoload.php';
const DOWNLOAD_SUBDIR = '/';       # The default download location is home directory. Always end with a '/'
const SENDER_ADDRESS = 'milad.azh@ryerson.ca';      # Email address of the sender whose attachments are to be fetched

if (php_sapi_name() != 'cli') {
    throw new Exception('This application must be run on the command line.');
}

/**
 * Returns an authorized API client.
 * @return Google_Client the authorized client object
 */
function getClient()
{
    $client = new Google_Client();
    $client->setApplicationName('Gmail API PHP Quickstart');
    $client->setScopes(Google_Service_Gmail::GMAIL_READONLY);
    $client->setAuthConfig('credentials.json');
    $client->setAccessType('offline');

    // Load previously authorized credentials from a file.
    $credentialsPath = 'token.json';
    if (file_exists($credentialsPath)) {
        $accessToken = json_decode(file_get_contents($credentialsPath), true);
    } else {
        // Request authorization from the user.
        $authUrl = $client->createAuthUrl();
        printf("Open the following link in your browser:\n%s\n", $authUrl);
        print 'Enter verification code: ';
        $authCode = trim(fgets(STDIN));

        // Exchange authorization code for an access token.
        $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);

        // Check to see if there was an error.
        if (array_key_exists('error', $accessToken)) {
            throw new Exception(join(', ', $accessToken));
        }

        // Store the credentials to disk.
        if (!file_exists(dirname($credentialsPath))) {
            mkdir(dirname($credentialsPath), 0700, true);
        }
        file_put_contents($credentialsPath, json_encode($accessToken));
        printf("Credentials saved to %s\n", $credentialsPath);
    }
    $client->setAccessToken($accessToken);

    // Refresh the token if it's expired.
    if ($client->isAccessTokenExpired()) {
        $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        file_put_contents($credentialsPath, json_encode($client->getAccessToken()));
    }
    return $client;
}

/**
 * Grabs attachment and writes decoded data to file
 * @param type $service Google service instance
 * @param type $user_id user id
 * @param type $msg_id message id
 * @param type $out_dir attachment download location
 */
function getAttachments($service, $user_id, $msg_id, $out_dir){
	try{
		$message = $service->users_messages->get($user_id, $msg_id);
			foreach($message['payload']['parts'] as $part){
				if (isset($part['filename']) && strlen($part['filename']) > 0){
                                    $att_id = $part['body']['attachmentId'];
                                    $att_part = $service->users_messages_attachments->get($user_id, $msg_id, $att_id);
                                    
                                    $file_data = base64url_decode( $att_part['data'] );
                                    
                                    $path = $out_dir . $part['filename'];
                                    print $path . "\n";

                                    $file = fopen($path, 'w+b');
                                    fwrite($file, $file_data);
                                    fclose($file);
				}	
                        }
	} catch(\Google_Service_Exception $e){
		print $e;
	}

}

function base64url_encode($data) { 
  return rtrim(strtr(base64_encode($data), '+/', '-_'), '='); 
} 

function base64url_decode($data) { 
  return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT)); 
} 

// Get the API client and construct the service object.
$client = getClient();
$user = 'me';
$service = new Google_Service_Gmail($client);

// Check prerequisites
if(!isset($GLOBALS['_SERVER']['HOME']))
    die ("Environment variables not set!");

// Query mail and get attchments
$query_results = $service->users_messages->listUsersMessages($user, ['q' => 'from:'.SENDER_ADDRESS.' '.'has:attachment']);

if(count($query_results) == 0)
    print "No emails with attachments found!\n";
else
    foreach ($query_results->getMessages() as $messages)
        getAttachments( $service, $user, $messages->getId(), $GLOBALS['_SERVER']['HOME'].DOWNLOAD_SUBDIR );