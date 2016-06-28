<?php

namespace VoteBundle\Controller;

use Carbon\Carbon;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use FOS\RestBundle\Controller\FOSRestController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

use JMS\DiExtraBundle\Annotation as DI;
use OAuth2\Client;

use VoteBundle\Exception\BadRequestException;

/**
 * Class APIController
 * @package VoteBundle\Controller
 */
class APIController extends FOSRestController {

    /** @DI\Inject("doctrine.orm.entity_manager") */
    protected $em;

    private function respondJSON($output) {
        $view = $this->view($output, 200);
        $view->setFormat('json');
        $view->setHeader("Access-Control-Allow-Origin", '*');
        return $this->handleView($view);
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
//        $this->get("logger")->info("INPUT: " . json_encode($input));
        if((!array_key_exists("username", $input)) || (!array_key_exists("password", $input)) || (!array_key_exists("load_documents", $input))) {
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
            ,"user" => array(
                "email" => $user->getEmail()
            )
        );

        if($input["load_documents"] === true) {
            $output['docs'] = $this->get("vote.document")->getDocumentsWithVotes(NULL, NULL, $user);
        }

//        $this->get("logger")->info("LOGIN OUTPUT: " . json_encode($output));

        return $this->respondJSON($output);;
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

        $this->container->get('security.token_storage')->setToken(null);
        $this->container->get('session')->invalidate();

        $output = array(
            "success" => true
        );
//        $this->get("logger")->info("LOGOUT OUTPUT: " . json_encode($output));

        return $this->respondJSON($output);
    }

    /**
     * @Route("/api/json-documents", name="api_json_documents")
     * @Method("POST");
     */
    public function getJSONDocumentsAction(Request $request) {

//        $doc_repo = $this->em->getRepository('VoteBundle:Document');

//        $max_amount = min($max_amount, 64);

//        $all_docs = $doc_repo->getDocumentsWithVoteTotals(16);
        $user = $this->getUser();

        $docs_list = $this->get("vote.document")->getDocumentsWithVotes(NULL, NULL, $user);

        $output = array("success" => true, "docs" => $docs_list);

//        $this->get("logger")->info("DOC REQUEST");

        if($user !== NULL) {
            $this->get("logger")->info("Already logged in");

            $output["token"] = $this->get("session")->getId();
            $output["user"] = array(
                "email" => $user->getEmail()
            );
        }

        return $this->respondJSON($output);
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
     * @Route("/oauth/nationbuilder", name="oauth_nationbuilder")
     */
    public function oauthAction(Request $request) {

//        $test_token = $this->getParameter('nationbuilder.testToken');
//        if($test_token != NULL) {

//            $this->get('logger')->info("Authenticating with test token");
//
//            $session = $request->getSession();
//            $session->set('oauth_token', $client_test_token);
//
//            return $this->redirectToRoute('homepage');
//        }

        $client_id = $this->getParameter('nationbuilder.clientID');
        $client_secret = $this->getParameter('nationbuilder.secret');
        $base_url = $this->getParameter('nationbuilder.baseURL');
        $send_url = $base_url . 'oauth/authorize';

        $client = new Client($client_id, $client_secret);
        $redirectUrl = $request->getSchemeAndHttpHost() . $this->get('router')->generate('oauth_nationbuilder_callback');

        $authUrl = $client->getAuthenticationUrl($send_url, $redirectUrl);

//        $this->get('logger')->info("Redirecting to $authUrl");

        return new RedirectResponse($authUrl);
    }

    /**
     * @Route("/oauth/nationbuilder/callback", name="oauth_nationbuilder_callback")
     */
    public function oauthCallbackAction(Request $request) {

        $this->get('logger')->debug("oauth redirected successfully");

        $input_query = $request->query->all();

        if(array_key_exists('code', $input_query)) {
            $session = $request->getSession();
            $session->set('nationbuilder.api_token', $input_query['code']);
            $this->get('logger')->debug("oauth token set successfully");
        }

        return $this->redirectToRoute('vote');
    }

}
