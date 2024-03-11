<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: POST');
use \Manomite\{
    Exception\ManomiteException as ex,
    Engine\Image,
    Protect\Secret,
    Protect\PostFilter,
    Route\Route,
    Mouth\Auth,
    Database\DB,
    Services\Convex\Convex
};

include_once __DIR__ . '/../../../autoload.php';

$security = new PostFilter;
$auth_handler = new Auth();
$auth = $auth_handler->loggedin();
$errorLog = '_0826837_file';

if (isset($auth['status']) && !empty($auth['status']) && $auth['status'] !== false) {
    if ($security->strip($_SERVER['REQUEST_METHOD']) === 'POST') {
        if (isset($_POST) and isset($_FILES)) {
            try {

                if (!array_key_exists('passport', $_FILES)) {
                    exit(json_encode(array('status' => 400, 'error' => (new ex($errorLog, 6, 'Sorry! this file is bigger than the server requirements and has been rejected.'))->return()), JSON_PRETTY_PRINT));
                }

                $fn = $security->sanitize_file_name($_FILES["passport"]["name"]);
                $ftmp = $security->strip($_FILES['passport']['tmp_name']);

                if ($security->nothing($fn)) {
                    exit(json_encode(array('status' => 400, 'error' => (new ex($errorLog, 6, 'Invalid file name'))->return()), JSON_PRETTY_PRINT));
                }

                $route = new Route();
                $image = new Image($_FILES["passport"]);
                $allowed = array('jpg', 'png', 'jpeg', 'image/jpg', 'image/png', 'image/jpeg');
                $extension = $image->getExtension();
                $sourcePath = $ftmp;

                $filename = (new Secret())->randomKey;

                $fileName = $filename . '.' . $extension;
                $targetPath = PROJECT_ROOT . '/public/' . $fileName;
                $mimeType = $image->getMime();

                if (!$extension || empty($extension) || !in_array($extension, $allowed) || !in_array($mimeType, $allowed)) {
                    exit(json_encode(array("status" => 400, "error" => (new ex($errorLog, 6, 'ID could not be identified.'))->return()), JSON_PRETTY_PRINT));
                }
                $blacklist = array(".php", ".phtml", ".php3", ".php4", ".php7", ".php8", ".ph3", ".ph4");
                foreach ($blacklist as $item) {
                    if (preg_match("/$item\$/i", $extension)) {
                        exit(json_encode(array("status" => 400, "error" => (new ex($errorLog, 6, 'ID could not be identified.'))->return()), JSON_PRETTY_PRINT));
                    }
                }

                $imagesizedata = getimagesize($security->strip($_FILES['passport']['tmp_name']));
                if ($imagesizedata === false) {
                    exit(json_encode(array('status' => 400, 'error' => (new ex($errorLog, 3, 'ID could not be identified.'))->return()), JSON_PRETTY_PRINT));
                }
                $s = 1 * MB;
                $size = $security->strip($_FILES['passport']['size']);
                if ($size > $s) {
                    exit(json_encode(array('status' => 400, 'error' => (new ex($errorLog, 3, 'ID must not be greater than 1MB in size'))->return()), JSON_PRETTY_PRINT));
                }
                if (file_exists($targetPath)) {
                    unlink($targetPath);
                }
                //Extract identity data
                $results = (new \Manomite\Engine\Tesseract\Engine)->extract($sourcePath)->ID_Man();
                if ($results === false || !is_array($results)) {
                    exit(json_encode(array('status' => 400, 'error' => (new ex($errorLog, 3, 'ID cannot be extracted. Please make sure you are uploading a super clear Identity card.'))->return()), JSON_PRETTY_PRINT));
                }
                $convex = new Convex(CONFIG->get('convex_token'), CONFIG->get('convex_url'));
                $obj = new \stdClass();
                $obj->sessionId = $auth['session'];
                $response = $convex->mutation('user:getIdentityData', $obj);
                if (isset($response['status']) and isset($response['value'])) {
                    if ($response['status'] === 'success' and $response['value'] !== false) {
                        $tess = new \Manomite\Engine\Tesseract\ID('');
                        $match_percent = $tess->matchInformationWithProvider($results, $response['value']);
                        //Store ID Results temporarly
                        $db = new DB('identity/data');
                        $payload = [
                            'verify_id' => (new Secret())->tokenGenerator('-', 3, 4),
                            'card' => [
                                'data_capture' => $results,
                                'percentage_match' => $match_percent['matching_percentage'],
                            ],
                            'device_info' => (new \Manomite\Engine\Fingerprint())->scan()
                        ];
                        if ($db->has($auth['userData']['authToken'])) {
                            $db->updateDocument($auth['userData']['authToken'], $payload);
                        } else {
                            $db->createDocument($auth['userData']['authToken'], $payload);
                        }
                        if ($image->moveUploadedFile($sourcePath, $targetPath)) {
                            if (@file_get_contents($targetPath)) {
                                //Send ID Card to convex
                                $obj = new \stdClass();
                                $obj->sessionId = $auth['session'];
                                $response = $convex->mutation('user:getUserCard', $obj);
                                if (isset($response['status']) and isset($response['value'])) {
                                    if ($response['status'] === 'success' and $response['value'] !== false) {
                                        //Remove previous card
                                        $obj = new \stdClass();
                                        $obj->id = $response['value']['id'];
                                        $convex->mutation('user:deleteFile', $obj);
                                    }
                                }
                                $obj = new \stdClass();
                                $obj->sessionId = $auth['session'];
                                $obj->fileUrl = CONFIG->get('app_domain') . '/public/' . $fileName;
                                $response = $convex->action('fileAction:storeCard', $obj);
                                if (isset($response['status']) and isset($response['value'])) {
                                    if ($response['status'] === 'success' and $response['value'] !== false) {
                                        @unlink($targetPath);
                                        $page = file_get_contents(__DIR__ . '/../../../' . BODY_TEMPLATE_FOLDER . '/app/inline/signature.html');
                                        exit(json_encode(array('status' => 200, 'inline' => $page, 'id' => $auth['userData']['authToken']), JSON_PRETTY_PRINT));
                                    }
                                }
                            }
                        }
                        @unlink($targetPath);
                    }
                }
                exit(json_encode(array('status' => 400, 'error' => (new ex($errorLog, 3, 'ID cannot be processed. Please try again later.'))->return()), JSON_PRETTY_PRINT));
            } catch (\Throwable $e) {
                (new ex($errorLog, 3, $e))->return();
                exit(json_encode(array('status' => 400, 'error' => LANG->get('TECHNICAL_PROBLEM')), JSON_PRETTY_PRINT));
            }
        } else {
            exit(json_encode(array('status' => 400, 'error' => (new ex($errorLog, 3, 'Sorry file is empty'))->return()), JSON_PRETTY_PRINT));
        }
    } else {
        exit(json_encode(array('status' => 400, 'error' => (new ex($errorLog, 3, POST_ERROR))->return()), JSON_PRETTY_PRINT));
    }
} else {
    exit(json_encode(array('status' => 400, 'error' => (new ex($errorLog, 3, AUTH_ERROR))->return()), JSON_PRETTY_PRINT));
}