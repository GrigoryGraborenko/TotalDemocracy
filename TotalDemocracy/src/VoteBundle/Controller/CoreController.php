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
 *
 *
n) Can you make my karel@jnana.com.au account an admin account and delete all my other accounts karelb@me.com, contact@ecodirectory.com.au and contact@eserai.com?
o) On the voting page people will also want to be able to search and display bills by keyword (search for keyword in their title and summary)
p) On the voting page people will want to be able to opt-in to email notifications for new or updated Bills for their filter combination, eg: notify me when new or updated Federal Bills with keyword environment in them. *

//http://www.aph.gov.au/Parliamentary_Business/Bills_Legislation/Bills_Search_Results?page=1&drt=2&drv=7&drvH=7&pnu=44&pnuH=44&f=12%2f11%2f2013&to=27%2f05%2f2016&ps=10&ito=1&q=&bs=1&pbh=1&bhor=1&np=1&pmb=1&g=1&st=2

Have external "Get bills" url
b) I would get the official logo of Vyvyan for website and emails
you need to scrap these from aec because State District, Local Government Area and Local Ward/Division may be called something else in different states.
f) Please add Privacy Policy and Rules to footer and please link all including Terms to karelboele.com website so we change it we only have to change it in one place eg: karelboele.com/privacy-policy karelboele.com/rules karelboele.com/terms-conditions
g) BUG verification link in email not working, there is extra string before the address
h) Make a separate menu item “Volunteer" with “Volunteer address”. When they click activation link in email it takes them to Volunteer first ("Volunteer address" is mandatory if they didn’t verify and optional if they verified), then take them to settings with member tick box (only if they have verified), phone number and password
i) When Volunteer ticked it needs these options see tick boxes and fields http://www.peopledecide.org.au/volunteer (when to call and when available etc, not phone number and publish on website)
j) When logged in and verified change name “Verify" to ‘Verify or update address”

 *
 * Public bill list needs to be limited, and repo method used for vote page too
 * Minimum password complexity
 * Verification success and electorate listings should be prettier
 * Make sure that rego and verify both can be switched off for maintenance
 * Admin backend for candidates or volunteers
 * Logo on the menu should be the proper image
 * Remember details on rego confirm
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
 * Better verified page, with proper info from users
 * Checkbox for "live here"
 * what about metadata on electoral roll import?
 *
 * TODO:
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
     * @Route("/home", name="homepage")
     */
    public function indexAction(Request $request) {

        return $this->render('VoteBundle:Pages:home.html.twig', array());
    }

    /**
     * @Route("/terms_and_conditions", name="terms")
     */
    public function termsAction(Request $request) {
        return $this->render('VoteBundle:Pages:terms.html.twig', array());
    }

    /**
     * @Route("/api/login", name="api_login")
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
     * @Route("/api/documents", name="api_documents")
     * @Method("GET");
     */
    public function publicDocumentAction(Request $request) {

        $doc_repo = $this->em->getRepository('VoteBundle:Document');
        $vote_repo = $this->em->getRepository('VoteBundle:UserDocumentVote');

        $docs = $doc_repo->findBy(array(), array("whenCreated" => "DESC"), 2);

        $all_docs = $doc_repo->getDocumentsWithVoteTotals();
        $output = array("docs" => $all_docs);
//        \Symfony\Component\VarDumper\VarDumper::dump($all_docs);

        return $this->render("VoteBundle:API:documents.html.twig", $output);
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
