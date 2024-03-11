<?php
use M1\Vars\Vars;
use Manomite\{
    Engine\Platform\Platform,
    Services\Manomite\Client as Manomite,
    Engine\File
};
$data_dir = Platform::getDataDir().'/gim';
if(!is_dir($data_dir)){
    mkdir($data_dir, 0750, true);
}
define('PROJECT_ROOT', __DIR__ . '/../..');
$manager = new File($data_dir);
define('SYSTEM_DIR', $data_dir);

if(is_dir($data_dir.'/config')){
    $scanner = $manager->scanner($data_dir.'/config');
    $config = new Vars($scanner);
    $scanner = $manager->scanner($data_dir.'/lang/'.$config->get('app_language'));
    $lang = new Vars($scanner);

} else {
    //Fallback Helper
    $dir = PROJECT_ROOT.'/settings';
    if(is_dir($dir)){
        //copy all files here to production folder
        $manager->recursiveCopy($dir);
        $manager->deleteFilesThenSelf($dir);
        $scanner = $manager->scanner($data_dir.'/config');
        $config = new Vars($scanner);
        define('TIMEZONE', $config->get('app_timezone') ?: 'Africa/Lagos');
        $scanner = $manager->scanner($data_dir.'/lang/'.$config->get('app_language'));
        $lang = new Vars($scanner);
    }
}
define('LANG', $lang);
define('CONFIG', $config);


//App Configuration
define('APP_NAME', $config->get('app_name'));
define('APP_DOMAIN', $config->get('app_domain'));
define('APP_VERSION', $config->get('app_version'));
define('TIMEZONE', $config->get('app_timezone') ?: 'Africa/Lagos');
define('COPYRIGHTS', 'Copyrights '.date('Y').'. All Rights Reserved. Powered By <a href="https://manomite.net" target="_blank">Manomite</a>');
define('PHP_EXEC_BIN', 'php');
define('BODY_TEMPLATE_FOLDER', 'template/theme');
define('LOGIN_SESSION', strtolower(str_replace(' ', '_', APP_NAME)));
define('PROVIDER_SUPPORT_URL', '');

//Email Confgurations
define('SMTP_HOST', $config->get('smtp_host')); //SMTP HOST (api.sendgrid.com)
define('SMTP_PORT', $config->get('smtp_port')); //SMTP PORT (406)
define('SMTP_USERNAME', $config->get('smtp_username')); //SMTP USERNAME (lucychats)
define('SMTP_PASSWORD', $config->get('smtp_password')); //SMTP PASSWORD (your_password)
define('SENDER_EMAIL', $config->get('smtp_sender')); //Sender Email
define('NO_REPLY_EMAIL', $config->get('smtp_reply_email')); //No-Reply Email
define('MAIL_DRIVER', $config->get('mail_driver')); //Email Driver

//MB
define('KB', 1024);
define('MB', 1048576);
define('GB', 1073741824);
define('TB', 1099511627776);