<?php

namespace VoteBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use FOS\RestBundle\Controller\FOSRestController;
use Symfony\Component\HttpFoundation\Request;

use JMS\DiExtraBundle\Annotation as DI;
use Carbon\Carbon;
use GuzzleHttp\Client as HttpClient;
use Symfony\Component\DomCrawler\Crawler;

use VoteBundle\Exception\BadRequestException;
use VoteBundle\Exception\ErrorRedirectException;

use VoteBundle\Entity\Electorate;
use VoteBundle\Entity\Domain;

/**
 * Class VerifyController
 * @package VoteBundle\Controller
 */
class VerifyController extends FOSRestController {

    /** @DI\Inject("doctrine.orm.entity_manager") */
    private $em;

    /** @DI\Inject("logger") */
    private $logger;

    private $verifySSL = false;

    private function getPotentialUser() {
        $user = $this->getUser();

        if($user === NULL) {
            $session = $this->get("session");
            if(!$session->has("new_user_id")) {
                throw new ErrorRedirectException("homepage", "No user available");
            }
            $user_id = $session->get("new_user_id");
            $user = $this->em->getRepository('VoteBundle:User')->find($user_id);
            if($user === NULL) {
                $session->remove("new_user_id");
                throw new ErrorRedirectException("homepage", "No user available");
            }
        }

        return $user;
    }

    /**
     * @Route("/verify", name="verify")
     * @Method("GET");
     */
    public function indexAction(Request $request) {

        $user = $this->getPotentialUser();

        $base_url = "https://oevf.aec.gov.au/";
        $output = array(
            "givenNames" => $user->getGivenNames()
            ,"surname" => $user->getSurname()
            ,"postcode" => $user->getPostcode()
            ,"suburb" => $user->getSuburb()
            ,"street" => $user->getStreet()
        );

        $client = new HttpClient([
            'verify' => $this->verifySSL
        ]);

        $response = $client->request("GET", $base_url, [
            'headers' => [
                "Accept" => "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8"
                ,"Accept-Encoding" => "gzip, deflate"
                ,"Accept-Language" => "en-US,en;q=0.8"
                ,"Connection" => "keep-alive"
                ,"Host" => "oevf.aec.gov.au"
                ,"Upgrade-Insecure-Requests" => "1"
                ,"User-Agent" => "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/48.0.2564.116 Safari/537.36"
        ]]);

        $cookie = explode(";", $response->getHeader("Set-Cookie")[0])[0];

        $crawler = new Crawler($response->getBody()->getContents());
        $image_src = $base_url . $crawler->filter('.LBD_CaptchaImage')->attr("src");

        // store the image and serve it up to the user
        $temp_img_dir = 'img/temp';
        if(!file_exists($temp_img_dir)) {
            mkdir($temp_img_dir, 0777, true);
        }

        $filename = "$temp_img_dir/" . Carbon::now("UTC")->timestamp . "_" . mt_rand(0, 100000) . ".jpeg";
        $output['image'] = "/$filename";

        $myFile = fopen($filename,"w");
        $client->request("GET", $image_src,[
            'save_to' => $myFile
            ,"headers" => [
                "Host" => "oevf.aec.gov.au"
                ,"Cookie" => $cookie
                ,"User-Agent" => "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/48.0.2564.116 Safari/537.36"
            ]
        ]);

        $session = $this->get('session');
        $session->set('verify.view_state', $crawler->filter('#__VIEWSTATE')->attr("value"));
        $session->set('verify.view_state_generator', $crawler->filter('#__VIEWSTATEGENERATOR')->attr("value"));
        $session->set('verify.event_validation', $crawler->filter('#__EVENTVALIDATION')->attr("value"));
        $session->set('verify.VCID', $crawler->filter('#LBD_VCID_c_verifyenrolment_ctl00_contentplaceholderbody_captchaverificationcode')->attr("value"));
        $session->set('verify.cookie', $cookie);

        return $this->render("VoteBundle:Pages:verify.html.twig", $output);
    }

    /**
     * @Route("/verify", name="finish_verify")
     * @Method("POST");
     */
    public function finishVerification(Request $request) {

        $user = $this->getPotentialUser();
        $input = $request->request->all();

        if( (!array_key_exists("names", $input)) ||
            (!array_key_exists("surname", $input)) ||
            (!array_key_exists("postcode", $input)) ||
            (!array_key_exists("suburb", $input)) ||
            (!array_key_exists("street", $input)) ||
            (!array_key_exists("verify", $input))) {

            throw new ErrorRedirectException("verify", "Incorrect arguments");
        }

        $other_user = $this->em->getRepository('VoteBundle:User')->findOneBy(array(
            "givenNames" => ucwords(strtolower($input['names']))
            ,"surname" => ucwords(strtolower($input['surname']))
            ,"postcode" => strtoupper($input['postcode'])
            ,"suburb" => strtoupper($input['suburb'])
            ,"street" => strtoupper($input['street'])
        ));
        if($other_user !== NULL) {

            if($other_user->getId() !== $user->getId()) {
                throw new ErrorRedirectException("verify", "Another user has already verified with these details. If this was you, try your other email addresses.");
            }
            // TODO: check rate limiting, change of name?

        }

        $client = new HttpClient([
            'verify' => $this->verifySSL
        ]);

        $session = $this->get('session');
        $base_url = "https://oevf.aec.gov.au/";

        $form_params = [
            '__LASTFOCUS' => ''
            ,'ctl00_ContentPlaceHolderBody_ToolkitScriptManager1_HiddenField' => ''
            ,'__EVENTTARGET' => ''
            ,'__EVENTARGUMENT' => ''
            ,'__VIEWSTATE' => $session->get('verify.view_state')
            ,'__VIEWSTATEGENERATOR' => $session->get('verify.view_state_generator')
            ,'__EVENTVALIDATION' => $session->get('verify.event_validation')
            ,'ctl00$ContentPlaceHolderBody$textGivenName' => $input['names']
            ,'ctl00$ContentPlaceHolderBody$textSurname' => $input['surname']
            ,'ctl00$ContentPlaceHolderBody$textPostcode' => $input['postcode']
            ,'ctl00$ContentPlaceHolderBody$DropdownSuburb' => $input['suburb']
            ,'ctl00$ContentPlaceHolderBody$textStreetName' => $input['street']
            ,'LBD_VCID_c_verifyenrolment_ctl00_contentplaceholderbody_captchaverificationcode' => $session->get('verify.VCID')
            ,'LBD_BackWorkaround_c_verifyenrolment_ctl00_contentplaceholderbody_captchaverificationcode' => "1"
            ,'ctl00$ContentPlaceHolderBody$textVerificationCode' => $input['verify']
            ,'ctl00$ContentPlaceHolderBody$buttonVerify' => ' Verify Enrolment '
            ,'hiddenInputToUpdateATBuffer_CommonToolkitScripts' => "0"
        ];

        $response = $client->request('POST', $base_url, [
            'form_params' => $form_params
            ,'headers' => [
                "Accept" => "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8"
                ,"Accept-Encoding" => "gzip, deflate"
                ,"Accept-Language" => "en-US,en;q=0.8"
                ,"Cache-Control" => "max-age=0"
                ,"Connection" => "keep-alive"
                ,"Cookie" => $session->get('verify.cookie')
                ,"Host" => "oevf.aec.gov.au"
                ,"Origin" => "https://oevf.aec.gov.au"
                ,"Referer" => "https://oevf.aec.gov.au/"
                ,"Upgrade-Insecure-Requests" => "1"
                ,"User-Agent" => "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/48.0.2564.116 Safari/537.36"
            ]
        ]);

        $crawler = new Crawler($response->getBody()->getContents());

        $success_nodes = $crawler->filter('#ctl00_pageHeadingH1');
        if(($success_nodes->count() > 0) && ($success_nodes->html() === "You are enrolled to vote")) {

//            $names_found = $crawler->filter('#ctl00_ContentPlaceHolderBody_labelGivenNamesMatched')->html();
//            $surname_found = $crawler->filter('#ctl00_ContentPlaceHolderBody_labelSurnameMatched')->html();
//            $address_found = $crawler->filter('#ctl00_ContentPlaceHolderBody_labelAddress')->html();

            $federal_electorate = $crawler->filter('#ctl00_ContentPlaceHolderBody_linkProfile')->html();
            $state_district = $crawler->filter('#ctl00_ContentPlaceHolderBody_labelStateDistrict2')->html();
            $council = $crawler->filter('#ctl00_ContentPlaceHolderBody_labelLGA2')->html();
            $council_ward = $crawler->filter('#ctl00_ContentPlaceHolderBody_labelLGAWard2')->html();

            $state_abb = substr($input['suburb'], strpos($input['suburb'], "(") + 1, -1);

            $user->setGivenNames(ucwords(strtolower($input['names'])));
            $user->setSurname(ucwords(strtolower($input['surname'])));
            $user->setPostcode($input['postcode']);
            $user->setSuburb(strtoupper($input['suburb']));
            $user->setStreet(strtoupper($input['street']));
            $user->setWhenVerified(Carbon::now("UTC"));

            $domain_repo = $this->em->getRepository('VoteBundle:Domain');
            $elect_repo = $this->em->getRepository('VoteBundle:Electorate');

            $fed_domain = $domain_repo->findOneBy(array("level" => "federal"));
            $state_domain = $domain_repo->findOneBy(array("level" => "state", "shortName" => $state_abb));
            if(($fed_domain === NULL) || ($state_domain === NULL)) {
                throw new ErrorRedirectException("verify", "Could not find federal or state domain - please contact support");
            }

            $user->clearAllElectorates();

            $fed_elect = $elect_repo->findOneBy(array("domain" => $fed_domain, "name" => $federal_electorate));
            if($fed_elect === NULL) {
                $this->logger->info("Creating new federal electorate: $federal_electorate");
                $fed_elect = new Electorate($fed_domain, $federal_electorate);
                $this->em->persist($fed_elect);
            }
            $user->addElectorate($fed_elect);

            $state_elect = $elect_repo->findOneBy(array("domain" => $state_domain, "name" => $state_district));
            if($state_elect === NULL) {
                $this->logger->info("Creating new $state_abb state electorate: $state_district");
                $state_elect = new Electorate($state_domain, $state_district);
                $this->em->persist($state_elect);
            }
            $user->addElectorate($state_elect);

            $local_domain = $domain_repo->findOneBy(array("level" => "local", "name" => $council));
            if($local_domain === NULL) {
                $this->logger->info("Creating new local domain: $council");
                $local_domain = new Domain("local", $council);
                $this->em->persist($local_domain);
            }
            $local_elect = $elect_repo->findOneBy(array("domain" => $local_domain, "name" => $council_ward));
            if($local_elect === NULL) {
                $this->logger->info("Creating new $council local electorate: $council_ward");
                $local_elect = new Electorate($local_domain, $council_ward);
                $this->em->persist($local_elect);
            }
            $user->addElectorate($local_elect);

            $this->em->flush();

            // don't remove after verification so that new users can see all the domains they can vote in
//            if($session->has("new_user_id")) {
//                $session->remove("new_user_id");
//            }

            $output = array(
                "is_user_enabled" => $user->isEnabled()
                ,"federal" => $federal_electorate
                ,"state" => $state_district
                ,"council" => $council
                ,"ward" => $council_ward
            );
        } else if(($success_nodes->count() > 0) && (($success_nodes->html() === "Please contact the AEC on 13 23 26 for assistance") || ($success_nodes->html() === "Your enrolment could not be confirmed. Please check the information you have entered"))) {
            throw new ErrorRedirectException("verify", "Could not find you on the electoral role. Try with/without your middle name, or a previous address.");
        } else {
            throw new ErrorRedirectException("verify", "Verification code is incorrect.");
        }

        return $this->render("VoteBundle:Pages:verify_success.html.twig", $output);
    }

    /**
     * @Route("/verify-autocomplete", name="verify_autocomplete", options={"expose"=true})
     * @Method("POST");
     */
    public function getAutoCompleteAction(Request $request) {

        $input = $request->request->all();

        if(array_key_exists("postcode", $input)) {
            $url = 'https://oevf.aec.gov.au/VerifyEnrolment.aspx/GetDropDownContents';
            $send_data = [
                "category" => "postcode"
                ,"knownCategoryValues" => $input['postcode']
            ];
            $is_postcode = true;
        } else if(array_key_exists("prefix", $input) && array_key_exists("context", $input)) {
            $url = 'https://oevf.aec.gov.au/VerifyEnrolment.aspx/GetStreetAutoCompleteList';
            $send_data = [
                "contextKey" => $input['context']
                ,"count" => 50
                ,"prefixText" => $input['prefix']
            ];
            $is_postcode = false;
        } else {
            throw new BadRequestException("Incorrect Parameters");
        }

        $client = new HttpClient([
            'verify' => $this->verifySSL
        ]);

        $response = $client->request('POST', $url, [ 'json' => $send_data ]);

        $suburbs = array();
        $json_response = json_decode($response->getBody()->getContents(), true);

        if($is_postcode) {
            foreach ($json_response['d'] as $item) {
                if (strlen($item['value']) > 0) {
                    $suburbs[] = array($item['value']);
                }
            }
            $output = array("suburbs" => $suburbs);
        } else {
            $output = array("streets" => $json_response['d']);
        }

        $view = $this->view($output, 200);
        $view->setFormat('json');
        return $this->handleView($view);
    }

}