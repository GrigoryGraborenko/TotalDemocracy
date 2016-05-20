<?php

namespace VoteBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

use FOS\RestBundle\Controller\FOSRestController;
//use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use JMS\DiExtraBundle\Annotation as DI;

/**
 * Class CommonController
 * @package VoteBundle\Controller
 */
class CommonController extends FOSRestController {

    /**
     * @return mixed|null
     */
    protected function getPotentialUser($em) {
        $user = $this->getUser();

        if($user === NULL) {
            $session = $this->get("session");
            if(!$session->has("new_user_id")) {
                return NULL;
            }
            $user_id = $session->get("new_user_id");
            $user = $em->getRepository('VoteBundle:User')->find($user_id);
            if($user === NULL) {
                $session->remove("new_user_id");
                return NULL;
            }
        }

        return $user;
    }

}
