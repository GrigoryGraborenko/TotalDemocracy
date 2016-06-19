<?php

namespace VoteBundle\Controller;

use Carbon\Carbon;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Cookie;

use JMS\DiExtraBundle\Annotation as DI;

/**
 * Class APIController
 * @package VoteBundle\Controller
 */
class APIController extends Controller {

    /** @DI\Inject("doctrine.orm.entity_manager") */
    protected $em;

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

        $response = new JsonResponse($output);
        $response->headers->set('Access-Control-Allow-Origin', '*');
        return $response;
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

        $response = new JsonResponse($output);
        $response->headers->set('Access-Control-Allow-Origin', '*');
        return $response;
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


        $output = array("docs" => $docs_list);

        $response = new JsonResponse($output);
        $response->headers->set('Access-Control-Allow-Origin', '*');
        return $response;
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

}
