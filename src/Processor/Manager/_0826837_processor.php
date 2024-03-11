<?php
//New User Account Register Controller
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: POST');
ini_set('max_execution_time', '300');
ini_set("pcre.backtrack_limit", "5000000");
use Manomite\{
    Exception\ManomiteException as ex,
    Route\Route,
    Protect\Secret,
    Protect\PostFilter,
    Mouth\SimpleRoute,
    Engine\CacheAdapter,
    Mouth\Auth,
    Mouth\Neck,
    Engine\DateHelper,
    Database\DB,
    Nerves\FileGrabber,
    Services\Convex\Convex,
    Engine\Email,
};

include_once __DIR__ . '/../../../autoload.php';
$auth_handler = new Auth();
$auth = $auth_handler->loggedin();
$errorLog = '_0826837_processor';

try {
    if (isset($auth['status']) && !empty($auth['status']) && $auth['status'] !== false) {
        $security = new PostFilter();
        $route = new Route();
        $neck = new Neck();
        $datehelper = new DateHelper();
        $filegrab = new FileGrabber();

        if ($security->strip($_SERVER['REQUEST_METHOD']) === 'POST') {
            if (isset($_POST)) {

                $request = $security->inputPost('request');
                $fingerprint = $security->inputPost('fingerprint');

                if ($security->nothing($request)) {
                    exit(json_encode(array('status' => 400, 'error' => (new ex('process', 3, 'Invalid request.'))->return()), JSON_PRETTY_PRINT));
                } elseif ($security->nothing($fingerprint)) {
                    exit(json_encode(array('status' => 400, 'error' => (new ex('process', 3, 'Failed security checks'))->return()), JSON_PRETTY_PRINT));
                }

                $mail = MAIL_DRIVER;
                ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
                $simpleRoute = new SimpleRoute();
                //All entire routes are placed here
                $allowedRoutes = [
                    '/logout',
                    '/loadApp',
                    '/agree',
                    '/reviewIdentity',
                    '/downloadIdentity',
                    '/personalIdentity',
                    '/recognition',
                    '/signature',
                ];
                $simpleRoute->registeredRoute = $allowedRoutes;
                $request = '/' . $request;
                $convex = new Convex(CONFIG->get('convex_token'), CONFIG->get('convex_url'));

                ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
                $simpleRoute->route('/logout', function () use ($auth, $convex) {
                    session_destroy();
                    unset ($_SESSION[LOGIN_SESSION]);
                    unset ($_SESSION['request_generator']);
                    unset ($_SESSION['payload_date']);

                    $obj = new \stdClass();
                    $obj->sessionId = $auth['session'];
                    $convex->mutation('user:sessionDestroy', $obj);

                    exit (json_encode(array ('status' => 200), JSON_PRETTY_PRINT));
                });

                $simpleRoute->route('/loadApp', function () use ($route, $convex, $auth, $datehelper) {

                    $obj = new \stdClass();
                    $obj->sessionId = $auth['session'];
                    $response = $convex->mutation('user:getAgreement', $obj);
                    if (isset ($response['status']) and isset ($response['value'])) {
                        if ($response['status'] === 'success' and $response['value'] !== false) {
                            //Load started page
                            $identity = $convex->mutation('user:getIdentityData', $obj);
                            $vd = $convex->mutation('user:getVerifiedData', $obj);
                            $todo = $convex->mutation('user:getTodo', $obj);

                            $check_icon = file_get_contents(__DIR__ . '/../../../' . BODY_TEMPLATE_FOLDER . '/app/inline/tick.html');
                            $cancel_icon = file_get_contents(__DIR__ . '/../../../' . BODY_TEMPLATE_FOLDER . '/app/inline/cancel.html');
                            $page = file_get_contents(__DIR__ . '/../../../' . BODY_TEMPLATE_FOLDER . '/app/inline/started.html');

                            $page = $route->textRender(
                                $page,
                                array (
                                    'root' => '',
                                    'provider' => APP_NAME,
                                    'id' => isset($vd['value']['id']) ? $vd['value']['id'] : '--',
                                    'name' => isset($identity['value']['name']) ? $identity['value']['name'] : $auth['userData']['name'],
                                    'email' => $auth['userData']['email'],
                                    'date_created' => isset($vd['value']['dateCreated']) ? $datehelper->formatDateFromTimestamp($vd['value']['dateCreated']) : '--',
                                    'last_updated' => isset($vd['value']['dateUpdated']) ? $datehelper->formatDateFromTimestamp($vd['value']['dateUpdated']) : '--',
                                    'revision_count' => isset($identity['value']['revision']) ? $identity['value']['revision'] : '0',
                                    'status' => isset($vd['value']['status']) ? $vd['value']['status'] : 'Unknown',
                                    'status_color' => isset($vd['value']['status']) ? (($vd['value']['status'] === 'Average') ? 'warning' : (($vd['value']['status'] === 'Excellent') ? 'success' : 'danger')) : 'danger',
                                    'personal_icon' => (isset($todo['value']['personal']) and !empty($todo['value']['personal'])) ? $check_icon : $cancel_icon,
                                    'facial_icon' => (isset($todo['value']['facial']) and !empty($todo['value']['facial'])) ? $check_icon : $cancel_icon,
                                    'idcard_icon' => (isset($todo['value']['idcard']) and !empty($todo['value']['idcard'])) ? $check_icon : $cancel_icon,
                                    'signing_icon' =>(isset($todo['value']['signature']) and !empty($todo['value']['signature'])) ? $check_icon : $cancel_icon,
                                )
                            );
                            exit (json_encode(array ('status' => 200, 'page' => $page), JSON_PRETTY_PRINT));
                        }
                    }
                    $cancel_icon = file_get_contents(__DIR__ . '/../../../' . BODY_TEMPLATE_FOLDER . '/app/inline/cancel.html');
                    $page = file_get_contents(__DIR__ . '/../../../' . BODY_TEMPLATE_FOLDER . '/app/inline/getting_started.html');
                    $page = $route->textRender(
                        $page,
                        array (
                            'root' => '',
                            'provider' => APP_NAME,
                            'contact' => CONFIG->get('contact'),
                            'personal_icon' => $cancel_icon,
                            'facial_icon' => $cancel_icon,
                            'idcard_icon' => $cancel_icon,
                            'signing_icon' => $cancel_icon,
                        )
                    );
                    exit (json_encode(array ('status' => 200, 'page' => $page), JSON_PRETTY_PRINT));
                });

                $simpleRoute->route('/agree', function () use ($route, $convex, $auth) {

                    $obj = new \stdClass();
                    $obj->sessionId = $auth['session'];
                    $response = $convex->mutation('user:getAgreement', $obj);
                    if (isset ($response['status']) and isset ($response['value'])) {
                        if ($response['status'] === 'success' and $response['value'] !== false) {
                            exit (json_encode(array ('status' => 400, 'error' => (new ex('process', 3, 'Sorry! Agreement has already been accepted. Please reload the page and continue your submissions.'))->return()), JSON_PRETTY_PRINT));
                        }
                    }
                    //Agree to data collections.
                    $obj = new \stdClass();
                    $obj->sessionId = $auth['session'];
                    $response = $convex->mutation('user:setAgreement', $obj);
                    if (isset ($response['status']) and isset ($response['value'])) {
                        if ($response['status'] === 'success' and $response['value'] === true) {
                            exit (json_encode(array ('status' => 200), JSON_PRETTY_PRINT));
                        }
                    }
                    exit (json_encode(array ('status' => 400, 'error' => (new ex('process', 3, 'Agreement cannot be signed. Please try again later.'))->return()), JSON_PRETTY_PRINT));
                });

                $simpleRoute->route('/reviewIdentity', function () use ($route, $convex, $auth) {
                    $obj = new \stdClass();
                    $obj->sessionId = $auth['session'];
                    $response = $convex->mutation('user:getAgreement', $obj);
                    if (isset ($response['status']) and isset ($response['value'])) {
                        if ($response['status'] === 'success' and $response['value'] !== false) {
                            $page = file_get_contents(__DIR__ . '/../../../' . BODY_TEMPLATE_FOLDER . '/app/modal/starting.html');
                            $page = $route->textRender(
                                $page,
                                array (
                                    'csrf_root' => (new Neck())->csrf_inject(),
                                )
                            );
                            exit (json_encode(array ('status' => 200, 'modal' => $page, 'modalID' => 'gimForm', 'id' => $auth['userData']['authToken']), JSON_PRETTY_PRINT));
                        }
                    }
                    exit (json_encode(array ('status' => 400, 'error' => (new ex('process', 3, 'Sorry! identity form cannot be opened. Please contact support for assistance.'))->return()), JSON_PRETTY_PRINT));
                });

                $simpleRoute->route('/personalIdentity', function () use ($security, $route, $auth, $convex) {
                    try {

                        $csrf_name = $security->inputPost('csrf_name');
                        $csrf_value = $security->inputPost('csrf_value');

                        if ($security->nothing($csrf_name) && $security->nothing($csrf_value)) {
                            exit (json_encode(array ('status' => 400, 'error' => (new ex('personalIdentity', 6, 'Invalid security token'))->return()), JSON_PRETTY_PRINT));
                        }

                        if ((new Neck())->validate_csrf($csrf_name, $csrf_value) === false) {
                            exit (json_encode(array ('status' => 400, 'error' => (new ex('personalIdentity', 6, LANG->get('EXTERNAL_REJECTED')))->return()), JSON_PRETTY_PRINT));
                        }

                        $fname = $security->inputPost('fname');
                        $phone = $security->inputPost('phone');
                        $dob = $security->inputPost('dob');
                        $gender = $security->inputPost('gender');
                        $address = $security->inputPost('address');
                        $city = $security->inputPost('rcity');
                        $state = $security->inputPost('rstate');
                        $country = $security->inputPost('rcountry');
                        $language = $security->inputPost('language');
                        $origin_country = $security->inputPost('country');
                        $origin_state = $security->inputPost('state');
                        $about = $security->inputPost('about');
                        $race = $security->inputPost('race');

                        if ($security->nothing($fname)) {
                            exit (json_encode(array ('status' => 400, 'error' => (new ex('personalIdentity', 6, 'Please provide your full name as its in your identity card'))->return()), JSON_PRETTY_PRINT));
                        } elseif (!$security->validate_name($fname)) {
                            exit (json_encode(array ('status' => 400, 'error' => (new ex('personalIdentity', 6, 'Please provide your full name as its in your identity card'))->return()), JSON_PRETTY_PRINT));
                        } elseif ($security->nothing($phone)) {
                            exit (json_encode(array ('status' => 400, 'error' => (new ex('personalIdentity', 6, 'Please provide your phone number'))->return()), JSON_PRETTY_PRINT));
                        } elseif ($security->nothing($dob)) {
                            exit (json_encode(array ('status' => 400, 'error' => (new ex('personalIdentity', 6, 'Please provide your date of birth'))->return()), JSON_PRETTY_PRINT));
                        } elseif ($security->nothing($gender)) {
                            exit (json_encode(array ('status' => 400, 'error' => (new ex('personalIdentity', 6, 'Please provide your gender'))->return()), JSON_PRETTY_PRINT));
                        } elseif ($security->nothing($address)) {
                            exit (json_encode(array ('status' => 400, 'error' => (new ex('personalIdentity', 6, 'Please provide your residential address'))->return()), JSON_PRETTY_PRINT));
                        } elseif ($security->nothing($city)) {
                            exit (json_encode(array ('status' => 400, 'error' => (new ex('personalIdentity', 6, 'Please provide your residential city'))->return()), JSON_PRETTY_PRINT));
                        } elseif ($security->nothing($state)) {
                            exit (json_encode(array ('status' => 400, 'error' => (new ex('personalIdentity', 6, 'Please provide your residential state'))->return()), JSON_PRETTY_PRINT));
                        } elseif ($security->nothing($country)) {
                            exit (json_encode(array ('status' => 400, 'error' => (new ex('personalIdentity', 6, 'Please provide your residential country'))->return()), JSON_PRETTY_PRINT));
                        } elseif ($security->nothing($language)) {
                            exit (json_encode(array ('status' => 400, 'error' => (new ex('personalIdentity', 6, 'Please provide your primary language'))->return()), JSON_PRETTY_PRINT));
                        } elseif ($security->nothing($origin_country)) {
                            exit (json_encode(array ('status' => 400, 'error' => (new ex('personalIdentity', 6, 'Please provide your origin country'))->return()), JSON_PRETTY_PRINT));
                        } elseif ($security->nothing($origin_state)) {
                            exit (json_encode(array ('status' => 400, 'error' => (new ex('personalIdentity', 6, 'Please provide your origin state'))->return()), JSON_PRETTY_PRINT));
                        } elseif ($security->nothing($about)) {
                            exit (json_encode(array ('status' => 400, 'error' => (new ex('personalIdentity', 6, 'Please tell us about yourself'))->return()), JSON_PRETTY_PRINT));
                        } elseif ($security->nothing($race)) {
                            exit (json_encode(array ('status' => 400, 'error' => (new ex('personalIdentity', 6, 'Please select race type'))->return()), JSON_PRETTY_PRINT));
                        }

                        $obj = new \stdClass();
                        $obj->sessionId = $auth['session'];
                        $obj->name = $fname;
                        $obj->phone = $phone;
                        $obj->dob = $dob;
                        $obj->gender = $gender;
                        $obj->residential_address = $address;
                        $obj->residential_city = $city;
                        $obj->residential_state = $state;
                        $obj->residential_country = $country;
                        $obj->origin_state = $origin_state;
                        $obj->origin_country = $origin_country;
                        $obj->primary_language = $language;
                        $obj->about = $about;
                        $obj->race = $race;

                        $response = $convex->mutation('user:createIdentity', $obj);
                        if (isset ($response['status']) and isset ($response['value'])) {

                            if ($response['status'] === 'success' and $response['value'] !== false) {

                                $page = file_get_contents(__DIR__ . '/../../../' . BODY_TEMPLATE_FOLDER . '/app/inline/facial.html');
                                $page = $route->textRender(
                                    $page,
                                    array (
                                        'csrf_root' => (new Neck())->csrf_inject(),
                                    )
                                );
                                exit (json_encode(array ('status' => 200, 'inline' => $page), JSON_PRETTY_PRINT));
                            }

                        }
                        $error = isset ($response['errorMessage']) ? $response['errorMessage'] : (isset ($response['errorData']) ? $response['errorData'] : LANG->get('TECHNICAL_ERROR'));
                        exit (json_encode(array ('status' => 400, 'error' => (new ex('personalIdentity', 6, $error))->return()), JSON_PRETTY_PRINT));
                    } catch (\Throwable $e) {
                        new ex('personalIdentity', 5, $e->getMessage());
                        exit (json_encode(array ('status' => 400, 'error' => LANG->get('TECHNICAL_ERROR')), JSON_PRETTY_PRINT));
                    }
                });

                $simpleRoute->route('/recognition', function () use ($security, $route, $auth, $convex) {
                    try {

                        $object = $security->inputPost('object', false);
                        $image = $security->inputPost('image', false);

                        if ($security->nothing($object)) {
                            exit (json_encode(array ('status' => 400, 'error' => (new ex('recognition', 3, 'Invalid request.'))->return()), JSON_PRETTY_PRINT));
                        } elseif ($security->nothing($image)) {
                            exit (json_encode(array ('status' => 400, 'error' => (new ex('recognition', 3, 'Sorry! error occured while decoding recognitions. Please try again later.'))->return()), JSON_PRETTY_PRINT));
                        }

                        $object = json_decode($object, true);

                        $objects = array ();
                        foreach ($object as $key => $value) {
                            $objects[$security->strip($key)] = $security->strip($value);
                        }

                        //Okay! i know this might sounds insecure to store files temporally in public directory! but the file gets deleted immediately after convex stores it. May be in the future, I will discourage this. But trust me. Its safe.
                        $imagePath = PROJECT_ROOT . '/public';
                        if (!is_dir($imagePath)) {
                            mkdir($imagePath, 0777, true);
                        }
                        $filename = (new Secret())->randomKey;

                        $img = str_replace('data:image/png;base64,', '', $image);
                        $img = str_replace(' ', '+', $img);
                        $data = base64_decode($img);
                        if ($objects['isHuman'] >= 0.85 and $objects['smile'] === 'happy' and isset ($objects['gender']) and isset ($objects['age']) and $objects['faceLeft'] === 'left' and $objects['faceRight'] === 'right' and $objects['eyeBlink'] === 'blinked' and $objects['mouthOpened'] === 'opened' and $objects['mouthClosed'] === 'closed') {
                            $page = file_get_contents(__DIR__ . '/../../../' . BODY_TEMPLATE_FOLDER . '/app/inline/passport.html');
                            file_put_contents($imagePath . '/' . $filename . '.jpg', $data);
                            $obj = new \stdClass();
                            $obj->sessionId = $auth['session'];
                            $response = $convex->mutation('user:getUserImage', $obj);
                            //Lets check if user have previous file to avoid loading the storage with huge data
                            if (isset ($response['status']) and isset ($response['value'])) {
                                if ($response['status'] === 'success' and $response['value'] !== false) {
                                    @unlink($imagePath . '/' . $filename . '.jpg');
                                    //User has an image. dont update. However if you wish you can update it calling
                                    exit (json_encode(array ('status' => 200, 'inline' => $page, 'isRecognition' => true), JSON_PRETTY_PRINT));
                                }
                            }
                            //Upload new image
                            $obj->fileUrl = CONFIG->get('app_domain') . '/public/' . $filename . '.jpg';
                            $response = $convex->action('fileAction:storeFile', $obj);
                            if (isset ($response['status']) and isset ($response['value'])) {
                                if ($response['status'] === 'success' and $response['value'] !== false) {
                                    $db = new DB('identity/photo');
                                    if (!$db->has($auth['userData']['authToken'])) {
                                        $db->createDocument($auth['userData']['authToken'], $objects);
                                    }
                                    @unlink($imagePath . '/' . $filename . '.jpg');
                                    exit (json_encode(array ('status' => 200, 'inline' => $page, 'isRecognition' => true), JSON_PRETTY_PRINT));
                                }
                            }
                            //Should incase
                            @unlink($imagePath . '/' . $filename . '.jpg');
                        }
                        exit (json_encode(array ('status' => 400, 'error' => 'Sorry! you failed the creterials required to move to the next stage. Please try again later.'), JSON_PRETTY_PRINT));
                    } catch (\Throwable $e) {
                        new ex('recognition', 5, $e->getMessage());
                        exit (json_encode(array ('status' => 400, 'error' => LANG->get('TECHNICAL_ERROR')), JSON_PRETTY_PRINT));
                    }
                });

                $simpleRoute->route('/signature', function () use ($security, $route, $auth, $convex, $filegrab, $datehelper) {
                    try {

                        $timeSpent = $security->inputPost('timeSpent');
                        $signature = $security->inputPost('signature', false);

                        if ($security->nothing($timeSpent)) {
                            exit (json_encode(array ('status' => 400, 'error' => (new ex('signature', 3, 'Invalid time request.'))->return()), JSON_PRETTY_PRINT));
                        } elseif ($security->nothing($signature)) {
                            exit (json_encode(array ('status' => 400, 'error' => (new ex('signature', 3, 'Sorry! error occured while decoding signature. Please try again later.'))->return()), JSON_PRETTY_PRINT));
                        }

                        $imagePath = PROJECT_ROOT . '/public';
                        if (!is_dir($imagePath)) {
                            mkdir($imagePath, 0777, true);
                        }
                        $filename = (new Secret())->randomKey;

                        $img = str_replace('data:image/png;base64,', '', $signature);
                        $img = str_replace(' ', '+', $img);
                        $data = base64_decode($img);
                        $imagePath = $imagePath . '/' . $filename . '.jpg';
                        file_put_contents($imagePath, $data);

                        //Delete previous signature if present

                        $obj = new \stdClass();
                        $obj->sessionId = $auth['session'];
                        $response = $convex->mutation('user:getUserSignature', $obj);
                        if (isset($response['status']) and isset($response['value'])) {
                            if ($response['status'] === 'success' and $response['value'] !== false) {
                                //Remove previous signature
                                $obj = new \stdClass();
                                $obj->id = $response['value']['id'];
                                $convex->mutation('user:deleteFile', $obj);
                            }
                        }
                        $obj = new \stdClass();
                        $obj->sessionId = $auth['session'];
                        $obj->fileUrl = CONFIG->get('app_domain') . '/public/' . $filename . '.jpg';
                        $response = $convex->action('fileAction:storeSignature', $obj);
                        $page = file_get_contents(__DIR__ . '/../../../' . BODY_TEMPLATE_FOLDER . '/app/inline/completed.html');
                        if (isset ($response['status']) and isset ($response['value'])) {
                            if ($response['status'] === 'success' and $response['value'] !== false) {
                                $db = new DB('identity/data');
                                if (!$db->has($auth['userData']['authToken'])) {
                                    @unlink($imagePath);
                                    exit (json_encode(array ('status' => 400, 'error' => 'Sorry! no ID Card data was found. Please retry again.'), JSON_PRETTY_PRINT));
                                }
                                $cardInfo = $db->find($auth['userData']['authToken']);

                                $dbp = new DB('identity/photo');
                                if (!$dbp->has($auth['userData']['authToken'])) {
                                    @unlink($imagePath);
                                    exit (json_encode(array ('status' => 400, 'error' => 'Sorry! no profile image was found. Please retry again.'), JSON_PRETTY_PRINT));
                                }
                                $imageInfo = $dbp->find($auth['userData']['authToken']);

                                $obj = new \stdClass();
                                $obj->sessionId = $auth['session'];
                                //Get personal informations
                                $identity = $convex->mutation('user:getIdentityData', $obj);
                                //Get ID Card
                                $card = $convex->mutation('user:getUserCard', $obj);
                                //Get Profile Image
                                $profile_image = $convex->mutation('user:getUserImage', $obj);

                                $id = (new Secret())->tokenGenerator('-', 4, 2);
                                //Generate pdf and send
                                $imf = '';
                                foreach ($imageInfo as $key => $value) {
                                    if(is_numeric($value)){
                                        $value = number_format((float)$value, 2, '.', '');
                                    }
                                    $imf .= '<span class="holdings">' . $key . ': </span> <span class="holdLabels">' . $value . '</span><br>';
                                }
                                $card_data = '<span class="holdings">Match Percent: </span> <span class="holdLabels">' . $cardInfo['card']['percentage_match'] . '</span><br>';
                                foreach ($cardInfo['card']['data_capture'] as $key => $value) {
                                    $card_data .= '<span class="holdings">' . $key . ': </span> <span class="holdLabels">' . $value . '</span><br>';
                                }
                                $image = isset ($profile_image['value']) ? $profile_image['value'] : SYSTEM_DIR . '/assets/profile.png';
                                $picture = $filegrab->getImage('', 'resource/profile', true, null, $image);

                                $logo = $filegrab->getImage(SYSTEM_DIR . '/assets/logo/logo-auth.png', 'assets/logo', true, null);

                                $idcard = isset ($card['value']['url']) ? $card['value']['url'] : SYSTEM_DIR . '/assets/profile.png';
                                $idcard = $filegrab->getImage('', 'resource/profile', true, null, $idcard);

                                //Store verified data
                                $status = 'Average';
                                $desc = 'Manual check is required';
                                $status_color = 'orange';
                                if($cardInfo['card']['percentage_match'] > 70){
                                    $status = 'Excellent';
                                    $desc = 'Manual check is not required.';
                                    $status_color = 'green';
                                }

                                $obj = new \stdClass();
                                $obj->sessionId = $auth['session'];
                                $obj->id = $id;
                                $obj->status = $status;
                                $obj->desc = $desc;
                                $obj->dataMatch = $cardInfo['card']['percentage_match'];
                                $obj->timeSpent = $timeSpent;
                                $res = $convex->mutation('user:verifyData', $obj);
                              
                                $generator = new Picqer\Barcode\BarcodeGeneratorPNG();
                                $barcode = base64_encode($generator->getBarcode(($res['value']) ? $res['value'] : $id, $generator::TYPE_CODE_128));

                                $payload = array (
                                    'provider' => CONFIG->get('app_name'),
                                    'id' => isset ($res['value']) ? $res['value'] : $id,
                                    'name' => isset ($identity['value']['name']) ? $identity['value']['name'] : '',
                                    'email' => isset ($auth['userData']['email']) ? $auth['userData']['email'] : '',
                                    'phone' => isset ($identity['value']['phone']) ? $identity['value']['phone'] : '',
                                    'dob' => isset ($identity['value']['dob']) ? $identity['value']['dob'] : '',
                                    'gender' => isset ($identity['value']['gender']) ? $identity['value']['gender'] : '',
                                    'race' => isset ($identity['value']['race']) ? $identity['value']['race'] : '',
                                    'lang' => isset ($identity['value']['primary_language']) ? $identity['value']['primary_language'] : '',
                                    'os' => isset ($identity['value']['origin_state']) ? $identity['value']['origin_state'] : '',
                                    'oco' => isset ($identity['value']['origin_country']) ? $identity['value']['origin_country'] : '',
                                    'address' => isset ($identity['value']['residential_address']) ? $identity['value']['residential_address'] : '',
                                    'rc' => isset ($identity['value']['residential_city']) ? $identity['value']['residential_city'] : '',
                                    'rs' => isset ($identity['value']['residential_state']) ? $identity['value']['residential_state'] : '',
                                    'rco' => isset ($identity['value']['residential_country']) ? $identity['value']['residential_country'] : '',
                                    'rev' => isset ($identity['value']['revision']) ? $identity['value']['revision'] : '',
                                    'date_created' => isset ($identity['value']['_creationTime']) ? $datehelper->formatDateFromTimestamp($identity['value']['_creationTime'] / 1000) : '',
                                    'date_updated' => isset ($identity['value']['dateUpdated']) ? $datehelper->formatDateFromTimestamp($identity['value']['dateUpdated']) : '',
                                    'about' => isset ($identity['value']['about']) ? $identity['value']['about'] : '',
                                    'photo_accessment' => $imf,
                                    'idcard' => $idcard,
                                    'card_data' => $card_data,
                                    'signature' => $imagePath,
                                    'logo' => $logo,
                                    'barcode' => $barcode,
                                    'picture' => $picture,
                                    'status' => $status,
                                    'status_color' => $status_color,
                                    'timeSpent' => $timeSpent,
                                );

                                //Check what configuration provider decides to receive data
                                $env = CONFIG->get('hook_type');
                                if ($env === 'email') {
                                    
                                    $email = CONFIG->get('hook_email');
                                    if (!isset ($email) || empty ($email)) {
                                        @unlink($imagePath);
                                        exit (json_encode(array ('status' => 400, 'error' => 'Sorry! provider did not provide how to receive your data. Please contact ' . CONFIG->get('app_name') . 'for assistance.'), JSON_PRETTY_PRINT));
                                    }

                                    $pdfTemp = file_get_contents(__DIR__ . '/../../../' . BODY_TEMPLATE_FOLDER . '/pdf/data.pdef');
                                    $conPdf = $route->textRender($pdfTemp, $payload);
                                    $pdf = new \Manomite\Engine\Pdf();
                                    $filename = $id;
                                    $fileName = PROJECT_ROOT . '/public/' . $filename;
                                    $pdf->generate_pdf($conPdf, $filename, $fileName, true, $logo);

                                    //lets send the email
                                    $mail = MAIL_DRIVER;
                                    (new Email($id . '- New Identity Data', array ($email), 'Please find the attached document for your perusal.', '', CONFIG->get('smtp_reply_email'), $fileName.'.pdf'))->$mail();
                                    @unlink($fileName.'.pdf');
                                }
                                //If its webhook
                                if ($env === 'webhook') {
                                    $webhook = CONFIG->get('hook_url');
                                    $webhook_secret = CONFIG->get('hook_secret');
                                    if (!isset ($webhook) || empty ($webhook)) {
                                        @unlink($imagePath);
                                        exit (json_encode(array ('status' => 400, 'error' => 'Sorry! provider did not provide how to receive your data. Please contact ' . CONFIG->get('app_name') . 'for assistance.'), JSON_PRETTY_PRINT));
                                    }

                                    if (!isset ($webhook_secret) || empty ($webhook_secret)) {
                                        @unlink($imagePath);
                                        exit (json_encode(array ('status' => 400, 'error' => 'Sorry! provider did not provide how to receive your data. Please contact ' . CONFIG->get('app_name') . 'for assistance.'), JSON_PRETTY_PRINT));
                                    }
                                    //Generate payload and push data to webhook url
                                    $hook = new Manomite\Engine\Http\Webhook;
                                    $hook->sendDataToWebhook($webhook, json_encode($payload), $webhook_secret);
                                }
                                //You can implement other hooks below to receive data
                                if ($env === null) {
                                    @unlink($imagePath);
                                    exit (json_encode(array ('status' => 400, 'error' => 'Sorry! provider did not provide how to receive your data. Please contact ' . CONFIG->get('app_name') . 'for assistance.'), JSON_PRETTY_PRINT));
                                }
                                @unlink($imagePath);
                                exit (json_encode(array ('status' => 200, 'inline' => $page), JSON_PRETTY_PRINT));
                            }
                        }
                        @unlink($imagePath);
                        exit (json_encode(array ('status' => 400, 'error' => 'Sorry! your signature could not be stored. Please try again later.'), JSON_PRETTY_PRINT));
                    } catch (\Throwable $e) {
                        new ex('signature', 5, $e->getMessage());
                        exit (json_encode(array ('status' => 400, 'error' => LANG->get('TECHNICAL_ERROR')), JSON_PRETTY_PRINT));
                    }
                });

                $simpleRoute->route('/downloadIdentity', function () use ($security, $route, $auth, $convex, $filegrab, $datehelper) {
                    try {
                        
                        $db = new DB('identity/data');
                        if (!$db->has($auth['userData']['authToken'])) {
                            exit (json_encode(array ('status' => 400, 'error' => 'Sorry! no ID Card data was found. Please retry again.'), JSON_PRETTY_PRINT));
                        }
                        $cardInfo = $db->find($auth['userData']['authToken']);
                        $dbp = new DB('identity/photo');
                        if (!$dbp->has($auth['userData']['authToken'])) {
                            exit (json_encode(array ('status' => 400, 'error' => 'Sorry! no profile image was found. Please retry again.'), JSON_PRETTY_PRINT));
                        }
                        $imageInfo = $dbp->find($auth['userData']['authToken']);

                        $obj = new \stdClass();
                        $obj->sessionId = $auth['session'];
                        //Get personal informations
                        $identity = $convex->mutation('user:getIdentityData', $obj);
                        //Get ID Card
                        $card = $convex->mutation('user:getUserCard', $obj);
                        //Get Profile Image
                        $profile_image = $convex->mutation('user:getUserImage', $obj);
                        //Verified Data
                        $res = $convex->mutation('user:getVerifiedData', $obj);

                        $sig = $convex->mutation('user:getUserSignature', $obj);

                        //Generate pdf and send
                        $imf = '';
                        foreach ($imageInfo as $key => $value) {
                            if(is_numeric($value)){
                                $value = number_format((float)$value, 2, '.', '');
                            }
                            $imf .= '<span class="holdings">' . $key . ': </span> <span class="holdLabels">' . $value . '</span><br>';
                        }
                        $card_data = '<span class="holdings">Match Percent: </span> <span class="holdLabels">' . $cardInfo['card']['percentage_match'] . '</span><br>';
                        foreach ($cardInfo['card']['data_capture'] as $key => $value) {
                            $card_data .= '<span class="holdings">' . $key . ': </span> <span class="holdLabels">' . $value . '</span><br>';
                        }
                        $image = isset ($profile_image['value']) ? $profile_image['value'] : SYSTEM_DIR . '/assets/profile.png';
                        $picture = $filegrab->getImage('', 'resource/profile', true, null, $image);

                        $logo = $filegrab->getImage(SYSTEM_DIR . '/assets/logo/logo-auth.png', 'assets/logo', true, null);

                        $idcard = isset ($card['value']['url']) ? $card['value']['url'] : SYSTEM_DIR . '/assets/profile.png';
                        $idcard = $filegrab->getImage('', 'resource/profile', true, null, $idcard);
                              
                        $generator = new Picqer\Barcode\BarcodeGeneratorPNG();
                        $barcode = base64_encode($generator->getBarcode(isset($res['value']['id']) ? $res['value']['id'] : 'none', $generator::TYPE_CODE_128));

                        $status_color = 'orange';
                        if($cardInfo['card']['percentage_match'] > 70){
                            $status_color = 'green';
                        }

                        $payload = array (
                            'provider' => CONFIG->get('app_name'),
                            'id' => isset($res['value']['id']) ? $res['value']['id'] : '--',
                            'name' => isset ($identity['value']['name']) ? $identity['value']['name'] : '',
                            'email' => isset ($auth['userData']['email']) ? $auth['userData']['email'] : '',
                            'phone' => isset ($identity['value']['phone']) ? $identity['value']['phone'] : '',
                            'dob' => isset ($identity['value']['dob']) ? $identity['value']['dob'] : '',
                            'gender' => isset ($identity['value']['gender']) ? $identity['value']['gender'] : '',
                            'race' => isset ($identity['value']['race']) ? $identity['value']['race'] : '',
                            'lang' => isset ($identity['value']['primary_language']) ? $identity['value']['primary_language'] : '',
                            'os' => isset ($identity['value']['origin_state']) ? $identity['value']['origin_state'] : '',
                            'oco' => isset ($identity['value']['origin_country']) ? $identity['value']['origin_country'] : '',
                            'address' => isset ($identity['value']['residential_address']) ? $identity['value']['residential_address'] : '',
                            'rc' => isset ($identity['value']['residential_city']) ? $identity['value']['residential_city'] : '',
                            'rs' => isset ($identity['value']['residential_state']) ? $identity['value']['residential_state'] : '',
                            'rco' => isset ($identity['value']['residential_country']) ? $identity['value']['residential_country'] : '',
                            'rev' => isset ($identity['value']['revision']) ? $identity['value']['revision'] : '',
                            'date_created' => isset ($identity['value']['_creationTime']) ? $datehelper->formatDateFromTimestamp($identity['value']['_creationTime'] / 1000) : '',
                            'date_updated' => isset ($identity['value']['dateUpdated']) ? $datehelper->formatDateFromTimestamp($identity['value']['dateUpdated']) : '',
                            'about' => isset ($identity['value']['about']) ? $identity['value']['about'] : '',
                            'photo_accessment' => $imf,
                            'idcard' => $idcard,
                            'card_data' => $card_data,
                            'signature' => isset($sig['value']['url']) ? $sig['value']['url'] : '--',
                            'logo' => $logo,
                            'barcode' => $barcode,
                            'picture' => $picture,
                            'status' => isset($res['value']['status']) ? $res['value']['status'] : '--',
                            'status_color' => $status_color,
                            'timeSpent' => isset($res['value']['timeSpent']) ? $res['value']['timeSpent'] : '--',
                        );    
                                  
                        $pdfTemp = file_get_contents(__DIR__ . '/../../../' . BODY_TEMPLATE_FOLDER . '/pdf/data.pdef');
                        $conPdf = $route->textRender($pdfTemp, $payload);
                        $pdf = new \Manomite\Engine\Pdf();
                        $filename = isset($res['value']['id']) ? $res['value']['id'] : 'none';
                        $fileName = PROJECT_ROOT . '/public/' . $filename;
                        $pdf->generate_pdf($conPdf, $filename, $fileName, true, $logo);

                        //lets send the email
                        $mail = MAIL_DRIVER;
                        (new Email((isset($res['value']['id']) ? $res['value']['id'] : 'none') . ' - Your Identity Data', array ($auth['userData']['email']), 'Please find the attached document for your perusal.', '', CONFIG->get('smtp_reply_email'), $fileName.'.pdf'))->$mail();
                        @unlink($fileName.'.pdf');
                        
                        exit (json_encode(array ('status' => 200, 'message' => 'We have sent a copy of your verification slip to your email address. Please download it from there.'), JSON_PRETTY_PRINT));
                
                    } catch (\Throwable $e) {
                        new ex('downloadIdentity', 5, $e->getMessage());
                        exit (json_encode(array ('status' => 400, 'error' => LANG->get('TECHNICAL_ERROR')), JSON_PRETTY_PRINT));
                    }
                });
                //DO NOT TOUCH
                ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
                $simpleRoute->dispatch($request);
                //////////////////////////////////////////////////////////////////////////////////////////////////////////////////
            }
        }
        exit(json_encode(array('status' => 400, 'error' => (new ex('_0826837_processor', 3, LANG->get('REQUEST_ERROR')))->return()), JSON_PRETTY_PRINT));
    }
    exit(json_encode(array('status' => 200, 'reload' => 'true'), JSON_PRETTY_PRINT));
} catch (\Exception $e) {
    exit(json_encode(array('status' => 400, 'error' => (new ex($errorLog, 3, $e->getMessage()))->return()), JSON_PRETTY_PRINT));
}