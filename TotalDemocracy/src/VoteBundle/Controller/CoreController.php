<?php

namespace VoteBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use FOS\RestBundle\Controller\FOSRestController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use JMS\DiExtraBundle\Annotation as DI;

use VoteBundle\Exception\BadRequestException;

/*
 * TODO FIXES:
 *

don't show closed bills
check that status is correct - failed is a possibility
make sure that you scrape the finished ones too - otherwise they may never close
filter out appropriation bills - create unvotable bills

Make sure that this is on the github project

TotalDemocracy is part of the eserai participatory democracy system
http://www.jnana.com.au/eserai_licence

remove three last volunteer fields

on verification success, put the volunteer/member/phone form under list of electorates
on verify failure, have a "skip" button that takes you to a verify failure page, that still has volunteer links in it

n) Can you make my karel@jnana.com.au account an admin account and delete all my other accounts karelb@me.com, contact@ecodirectory.com.au and contact@eserai.com?

h) Make a separate menu item “Volunteer" with “Volunteer address”. When they click activation link in email it takes them to Volunteer first ("Volunteer address" is mandatory if they didn’t verify and optional if they verified), then take them to settings with member tick box (only if they have verified), phone number and password

 * Verification success and electorate listings should be prettier
 * Make sure that rego and verify both can be switched off for maintenance
 * Admin backend for candidates or volunteers
 *
 * Some way to log out of "new user" mode
 * Abstain vote?
 * Close vote
 * Ensure voters can't change their names easily?
 * Report bug button on all pages
 * Rate limit new email registrations
 * Rate limit re-votes
 * Load up email with social links
 * Flag users if imported from nationbuilder
 * Add concept of "user verified but mismatch"
 * Checkbox for "live here"
 * what about metadata on electoral roll import?

p) On the voting page people will want to be able to opt-in to email notifications for new or updated Bills for their filter combination, eg: notify me when new or updated Federal Bills with keyword environment in them. *
 *
 * TODO:
 * Migrations
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
     * @Route("/home", name="homepage")
     */
    public function indexAction(Request $request) {

        return new RedirectResponse("http://www.peopledecide.org.au");
//        return $this->render('VoteBundle:Pages:home.html.twig', array());
    }

    /**
     * @Route("/error", name="error_page")
     */
    public function errorAction(Request $request) {

        return $this->render('VoteBundle:Errors:logic_error.html.twig', array());
    }

    /**
     * @Route("/api/login", name="api_login")
     * @Method("POST");
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws BadRequestException
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

        $token = new UsernamePasswordToken($user, null, 'main', $user->getRoles());
        $this->get('security.token_storage')->setToken($token);

        $output = array(
            "success" => true
            ,"token" => $this->get("session")->getId()
        );

//        $this->get("logger")->info("INPUT: " . json_encode($input));

        $view = $this->view($output, 200);
        $view->setFormat('json');
        return $this->handleView($view);
    }

    /**
     * @Route("/api/logout", name="api_logout")
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

    /**
     * @Route("/api/documents/{max_amount}", name="api_documents", defaults={"max_amount" = 6})
     * @Method("GET");
     */
    public function publicDocumentAction(Request $request, $max_amount) {

        $doc_repo = $this->em->getRepository('VoteBundle:Document');

        $max_amount = min($max_amount, 64);

        $all_docs = $doc_repo->getDocumentsWithVoteTotals($max_amount);
        $output = array("docs" => $all_docs);

        $response = $this->render("VoteBundle:API:documents.html.twig", $output);
        $response->headers->set('Access-Control-Allow-Origin', '*');
        return $response;
    }

    /**
     * This is called by base twig on every page. It's responsible for grabbing output data and stuffing it into JS
     */
    public function getJSOutputAction(Request $request) {

        $js_out = $this->get('vote.js');

        $user = $this->getUser();
        if($user === NULL) { // if logged out
//            return $this->getExternalJSOutputAction($request);
            $js_out->output("user", "-");
        } else {
            $js_out->output("user", $user->getUsername());
        }

//        $js_out->output("user", NULL);

        return $this->render("VoteBundle:Common:JSOutput.html.twig", array(
            'js_output_data' => $js_out->getParameters()
        ));
    }


}
