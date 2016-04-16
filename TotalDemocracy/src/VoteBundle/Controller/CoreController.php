<?php

namespace VoteBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use FOS\RestBundle\Controller\FOSRestController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use JMS\DiExtraBundle\Annotation as DI;

use VoteBundle\Exception\BadRequestException;

/*
 * TODO FIXES:
 * Preserve wrong data between errors
 * Some way to log out of "new user" mode
 * Show all, even stuff you can't vote on, if you choose
 * Abstain vote?
 * Close vote
 * Ensure voters can't change their names easily?
 * Report bug button on all pages
 * Make sure that rego and verify both can be switched off for maintenance
 * Rate limit new email registrations
 * Rate limit re-votes
 * Load up email with social links
 * Flag users if imported from nationbuilder
 * Add concept of "user verified but mismatch"
 * Better verified page, with proper info from users
 * Checkbox for "live here"
 * when registering you need to put tick box that somone has accepted terms and conditions http://www.karelboele.com/ is more up to date
 * before verify button you need comment "When you click verify you are asking us to submit your details to the Australian Electoral Commission website to verify and save your enrollment details on our system."
 * what about metadata on electoral roll import?
 *
 * TODO:
 * do g_initial data JS stuff, it's pretty useful
 * Migrations
 * Scrape!
 * Special case for users with no email?
 * Admin for promoting users to candidate
 * Allowing uploads of electoral roll
 */

/**
 * Class CoreController
 * @package VoteBundle\Controller
 */
class CoreController extends FOSRestController {

    /** @DI\Inject("doctrine.orm.entity_manager") */
    private $em;

    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request) {

        return $this->render('VoteBundle:Pages:home.html.twig', array());
    }

    /**
     * @Route("/login_json", name="login_json")
     * @Method("POST");
     */
    public function loginJSONAction(Request $request) {

        $input = $request->request->all();
        $this->get("logger")->info("INPUT: " . json_encode($input));
        if((!array_key_exists("username", $input)) || (!array_key_exists("password", $input))) {
            throw new BadRequestException("Incorrect parameters");
        }

        $user = $this->em->getRepository('VoteBundle:User')->findOneBy(array("username" => $input["username"]));
        if($user === NULL) {
            throw new BadRequestException('Incorrect Username/Password');
        }
        $factory = $this->get('security.encoder_factory');
        $encoder = $factory->getEncoder($user);
        if($encoder->isPasswordValid($user->getPassword(), $input['password'], $user->getSalt()) !== true) {
            throw new BadRequestException('Incorrect Username/Password');
        }

        // TODO: actually log in!
        $token = new UsernamePasswordToken($user, null, 'main', $user->getRoles());
        $this->get('security.token_storage')->setToken($token);


        $output = array(
            "success" => true
            ,"token" => $this->get("session")->getId()
        );

        $this->get("logger")->info("INPUT: " . json_encode($input));

        $view = $this->view($output, 200);
        $view->setFormat('json');
        return $this->handleView($view);
    }

    /**
     * @Route("/logout_json", name="logout_json")
     * @Method("POST");
     */
    public function logoutJSONAction(Request $request) {

        $user = $this->getUser();
        if($user === NULL) {
            throw new BadRequestException('Not logged in');
        }

        $output = array(
            "success" => true
        );

        $view = $this->view($output, 200);
        $view->setFormat('json');
        return $this->handleView($view);
    }


}
