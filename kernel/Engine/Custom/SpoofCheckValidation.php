<?php
namespace Manomite\Engine;
use \Egulias\EmailValidator\EmailLexer;
use \Spoofchecker;

class SpoofCheckValidation extends \Egulias\EmailValidator\EmailValidator
{
    /**
     * @var InvalidEmail|null
     */
    private $error;

    public function __construct()
    {
        if (!extension_loaded('intl')) {
            throw new \Exception(sprintf('The %s class requires the Intl extension.'));
        }
    }

    /**
     * @psalm-suppress InvalidArgument
     */
    public function isValid($email, EmailLexer $emailLexer)
    {
        $checker = new Spoofchecker();
        $checker->setChecks(Spoofchecker::SINGLE_SCRIPT);

        if ($checker->isSuspicious($email)) {
            $this->error = 'The email contains mixed UTF8 chars that makes it suspicious';
        }

        return $this->error === null;
    }

    public function getError()
    {
        return $this->error;
    }

    public function getWarnings()
    {
        return [];
    }
}