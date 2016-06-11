<?php
/**
 * Created by PhpStorm.
 * User: Grisha
 */

namespace VoteBundle\Service;

use Doctrine\ORM\EntityManager;
use Symfony\Component\Validator\Constraints\Email as EmailConstraint;
use Symfony\Component\Validator\Validator;
use JMS\DiExtraBundle\Annotation as DI;

use Symfony\Component\DependencyInjection\ContainerAware;


/**
 * Class UserService
 * @package VoteBundle\Service
 *
 * Provides validation for passwords and emails
 *
 */
class UserService
{

    /** @var EntityManager em */
    private $em;

    /** @var Option service */
    private $option;

    /** @var Validator service */
    public $validator;

    /**
     * UserService constructor.
     * @param $em
     * @param $option
     * @param $validator
     */
    public function __construct($em, $option, $validator) {
        $this->em = $em;
        $this->option = $option;
        $this->validator = $validator;
    }

    /**
     * Return true if email is valid, error message string if not
     *
     * @param $email
     * @return bool|string
     */
    public function isEmailValid($email) {
        $emailConstraint = new EmailConstraint();
        $errors  = $this->validator->validate($email, $emailConstraint);
        if((count($errors) > 0) || (strlen($email) < 1)) {
            return "Invalid Email '$email'";
        }
        return true;
    }

    /**
     * Return true if password is valid, error message string if not
     *
     * @param $password
     * @return bool|string
     */
    public function isPasswordValid($password) {

        $min_len = $this->option->getInteger("password.length.min");

        $len = strlen($password);
        if($len < $min_len) {
            return "Password needs to be at least $min_len characters long";
        }

        return true;
    }

}