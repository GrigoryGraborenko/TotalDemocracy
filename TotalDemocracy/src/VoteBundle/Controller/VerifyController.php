<?php

namespace VoteBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;

use JMS\DiExtraBundle\Annotation as DI;
use Carbon\Carbon;
use GuzzleHttp\Client as HttpClient;
use Symfony\Component\DomCrawler\Crawler;

use VoteBundle\Controller\CommonController;
use VoteBundle\Exception\BadRequestException;
use VoteBundle\Exception\ErrorRedirectException;

use VoteBundle\Entity\Electorate;
use VoteBundle\Entity\Domain;
use VoteBundle\Entity\ServerEvent;

/**
 * Class VerifyController
 * @package VoteBundle\Controller
 */
class VerifyController extends CommonController {

    /** @DI\Inject("doctrine.orm.entity_manager") */
    protected $em;

    /** @DI\Inject("logger") */
    private $logger;

    private $verifySSL = true;

    private $AECBase = "check.aec.gov.au";

    /**
     * @Route("/verify", name="verify")
     * @Method("GET");
     */
    public function indexAction(Request $request) {

        $user = $this->getPotentialUser($this->em);
        if($user === NULL) {
            throw new ErrorRedirectException("error_page", "No user available");
        }

        $base_url = "https://$this->AECBase";
        $output = array(
            "givenNames" => $user->getGivenNames()
            ,"surname" => $user->getSurname()
            ,"postcode" => $user->getPostcode()
            ,"suburb" => $user->getSuburb()
            ,"street" => $user->getStreet()
            ,"streetNumber" => $user->getStreetNumber()
        );

        if($user->getDOB()) {
            $dob = Carbon::instance($user->getDOB());
            $output["dobDate"] = $dob->day;
            $output["dobMonth"] = $dob->month;
            $output["dobYear"] = $dob->year;
        }

        // will replace the previous values if a prior form submission occurred
        $output = $this->get('session')->getFlashBag()->get("previous_input", $output);

        $output['verify_button_text'] = ($user->getWhenVerified() === NULL) ? "Verify" : "Verify or Update Address";

        $this->get('vote.js')->output("suburb", $output["suburb"]);

        $output["month_names"] = array("January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December");
        $output["years"] = array();
        $curr_year = Carbon::now("UTC")->year;
        for($i = ($curr_year - 16); $i > ($curr_year - 120); $i--) {
            $output['years'][] = $i;
        }

        $client = new HttpClient(array(
            'verify' => $this->verifySSL
        ));

        $response = $client->request("GET", $base_url, array(
            'headers' => array(
                "Accept" => "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8"
                ,"Accept-Encoding" => "gzip, deflate, sdch, br"
                ,"Accept-Language" => "en-US,en;q=0.8"
                ,"Connection" => "keep-alive"
                ,"Host" => $this->AECBase
                ,"Upgrade-Insecure-Requests" => "1"
                ,"User-Agent" => "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/54.0.2840.71 Safari/537.36"
        )));

        if($response->getStatusCode() !== 200) {
            throw new ErrorRedirectException("error_page", "Cannot load verification page");
        }

        $cookie_list = array();
        foreach($response->getHeader("Set-Cookie") as $cookie_header) {
            $cookie_list[] = explode(";", $cookie_header)[0];
        }
        $cookie = implode("; ", $cookie_list);

        $crawler = new Crawler($response->getBody()->getContents());
        $image_src = $base_url . $crawler->filter('.LBD_CaptchaImage')->attr("src");

        // store the image and serve it up to the user
        $temp_img_dir = 'img/temp';
        if(!file_exists($temp_img_dir)) {
            mkdir($temp_img_dir, 0777, true);
        }

        // delete older files
        $now_timestamp = Carbon::now("UTC")->timestamp;
        $previous_files = scandir($temp_img_dir);
        foreach($previous_files as $previous) {
            $prior_timestamp = explode("-", $previous)[0];
            if(is_numeric($prior_timestamp) && (($now_timestamp - intval($prior_timestamp)) > 30)) {
                unlink("$temp_img_dir/$previous");
            }
        }

        $filename = "$temp_img_dir/$now_timestamp-" . mt_rand(0, 100000) . ".jpeg";
        $output['image'] = "/$filename";

        $myFile = fopen($filename,"w");
        $client->request("GET", $image_src, array(
            'save_to' => $myFile
            ,"headers" => array(
                "Host" => $this->AECBase
                ,"Cookie" => $cookie
                ,"User-Agent" => "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/48.0.2564.116 Safari/537.36"
            )
        ));

        // TODO: test for get failure of image, and the verification finish as well.

        $session = $this->get('session');
        $session->set('verify.view_state', $crawler->filter('#__VIEWSTATE')->attr("value"));
        $session->set('verify.view_state_generator', $crawler->filter('#__VIEWSTATEGENERATOR')->attr("value"));
        $session->set('verify.event_validation', $crawler->filter('#__EVENTVALIDATION')->attr("value"));
        $session->set('verify.VCID', $crawler->filter('#LBD_VCID_c_verifyenrolment_ctl00_contentplaceholderbody_captchaverificationcode')->attr("value"));
        $session->set('verify.cookie', $cookie);


        $event_repo = $this->em->getRepository('VoteBundle:ServerEvent');

        // check to see if this device is being tracked by a volunteer
        $cookies = $request->cookies->all();
        if(array_key_exists("tracking_token", $cookies)) {
            $events = $event_repo->findByJson("registration.track", $cookies['tracking_token'], false);
            if(count($events) > 0) {
                $track_event = $events[0];
                $close_time = Carbon::instance($track_event->getDateCreated())->addHours($track_event->getAmount());
                if(Carbon::now("UTC")->lte($close_time)) {
                    $output["can_skip"] = true;
                }
            }
        }

        // check to see if there were three failures
        $num_failures = count($event_repo->findBy(array("name" => "verify.failure", "user" => $user)));
        if($num_failures >= 3) {
            $output["can_skip"] = true;
        }

        return $this->render("VoteBundle:Pages:verify.html.twig", $output);
    }

    /**
     * @Route("/verify", name="finish_verify")
     * @Method("POST");
     */
    public function finishVerificationAction(Request $request) {

        $input = $request->request->all();

        $cookies = $request->cookies->all();
        $track_event = NULL;
        if(array_key_exists("tracking_token", $cookies)) {
            $events = $this->em->getRepository('VoteBundle:ServerEvent')->findByJson("registration.track", $cookies['tracking_token'], false);
            if(count($events) > 0) {
                $track_event = $events[0];
            }
        }


        list($is_success, $output) = $this->finishVerification($input, $track_event);
        $user = $this->getPotentialUser($this->em);

        if(!$is_success) {

            $event = new ServerEvent("verify.failure", $user, array_merge($input, array("error" => $output)));
            $this->em->persist($event);
            $this->em->flush();

            $this->get('session')->getFlashBag()->set("previous_input", $input);
            throw new ErrorRedirectException("verify", $output);
        }

        $event = new ServerEvent("verify.success", $user, $input);
        $this->em->persist($event);
        $this->em->flush();

        return $this->render("VoteBundle:Pages:verify_success.html.twig", $output);
    }

    /**
     * @Route("/verify-skip", name="skip_verify")
     */
    public function verifySkipAction(Request $request) {

        $user = $this->getPotentialUser($this->em);
        if($user === NULL) {
            throw new ErrorRedirectException("error_page", "No user available");
        }

        $output = array(
            "user" => $user
            ,"skip" => true
        );

        return $this->render("VoteBundle:Pages:verify_success.html.twig", $output);
    }

    /**
     * @param $input
     * @param null $tracking_event
     * @return array
     */
    private function finishVerification($input, $tracking_event = NULL) {

        $user = $this->getPotentialUser($this->em);
        if($user === NULL) {
            return array(false, "No user available");
        }

        if( (!array_key_exists("givenNames", $input)) ||
            (!array_key_exists("surname", $input)) ||
            (!array_key_exists("postcode", $input)) ||
            (!array_key_exists("suburb", $input)) ||
            (!array_key_exists("street", $input)) ||
            (!array_key_exists("verify", $input)) ||

            (!array_key_exists("streetNumber", $input)) ||
            (!array_key_exists("dobDate", $input)) ||
            (!array_key_exists("dobMonth", $input)) ||
            (!array_key_exists("dobYear", $input))) {

            return array(false, "Incorrect arguments");
        }
        if( (strlen($input["dobDate"]) <= 0) ||
            (strlen($input["dobMonth"]) <= 0) ||
            (strlen($input["dobYear"]) <= 0)) {
            return array(false, "Please enter date of birth");
        }
        if((strlen($input["streetNumber"]) <= 0)) {
            return array(false, "Please enter your street number");
        }
        $dob = Carbon::createFromDate($input['dobYear'], $input['dobMonth'], $input['dobDate'], "UTC")->startOfDay();
        if($dob->diffInYears(Carbon::now("UTC")) < 18) {
            return array(false, "Must be over 18 to verify");
        }

        $other_user = $this->em->getRepository('VoteBundle:User')->findOneBy(array(
            "givenNames" => ucwords(strtolower($input['givenNames']))
            ,"surname" => ucwords(strtolower($input['surname']))
            ,"postcode" => strtoupper($input['postcode'])
            ,"suburb" => strtoupper($input['suburb'])
            ,"street" => strtoupper($input['street'])
        ));
        if($other_user !== NULL) {

            if($other_user->getId() !== $user->getId()) {
                return array(false, "Another user has already verified with these details. If this was you, try your other email addresses.");
            }
            // TODO: check rate limiting, change of name?

        }

        $client = new HttpClient(array(
            'verify' => $this->verifySSL
        ));

        $session = $this->get('session');
        $base_url = "https://$this->AECBase";

        $form_params = array(
            '__LASTFOCUS' => ''
            ,'ctl00_ContentPlaceHolderBody_ToolkitScriptManager1_HiddenField' => ''
            ,'__EVENTTARGET' => ''
            ,'__EVENTARGUMENT' => ''
            ,'__VIEWSTATE' => $session->get('verify.view_state')
            ,'__VIEWSTATEGENERATOR' => $session->get('verify.view_state_generator')
            ,'__EVENTVALIDATION' => $session->get('verify.event_validation')
            ,'ctl00$ContentPlaceHolderBody$textGivenName' => $input['givenNames']
            ,'ctl00$ContentPlaceHolderBody$textSurname' => $input['surname']
            ,'ctl00$ContentPlaceHolderBody$textPostcode' => $input['postcode']
            ,'ctl00$ContentPlaceHolderBody$DropdownSuburb' => $input['suburb']
            ,'ctl00$ContentPlaceHolderBody$textStreetName' => $input['street']
            ,'LBD_VCID_c_verifyenrolment_ctl00_contentplaceholderbody_captchaverificationcode' => $session->get('verify.VCID')
            ,'LBD_BackWorkaround_c_verifyenrolment_ctl00_contentplaceholderbody_captchaverificationcode' => "0"
            ,'ctl00$ContentPlaceHolderBody$textVerificationCode' => $input['verify']
            ,'ctl00$ContentPlaceHolderBody$buttonVerify' => ' Verify Enrolment '
            ,'hiddenInputToUpdateATBuffer_CommonToolkitScripts' => "0"
        );

        $response = $client->request('POST', $base_url, array(
            'form_params' => $form_params
            ,'headers' => array(
                "Accept" => "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8"
                ,"Accept-Encoding" => "gzip, deflate"
                ,"Accept-Language" => "en-US,en;q=0.8"
                ,"Cache-Control" => "max-age=0"
                ,"Connection" => "keep-alive"
                ,"Cookie" => $session->get('verify.cookie')
                ,"Host" => $this->AECBase
                ,"Origin" => "https://$this->AECBase"
                ,"Referer" => "https://$this->AECBase/"
                ,"Upgrade-Insecure-Requests" => "1"
                ,"User-Agent" => "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/48.0.2564.116 Safari/537.36"
            )
        ));

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

            $state_descriptor = str_replace(":", "", $crawler->filter('#ctl00_ContentPlaceHolderBody_labelStateDistrict')->html());
            $local_descriptor = str_replace(":", "", $crawler->filter('#ctl00_ContentPlaceHolderBody_labelLGAWard')->html());

            $state_abb = substr($input['suburb'], strpos($input['suburb'], "(") + 1, -1);

            $user->setGivenNames(ucwords(strtolower($input['givenNames'])));
            $user->setSurname(ucwords(strtolower($input['surname'])));
            $user->setPostcode($input['postcode']);
            $user->setSuburb(strtoupper($input['suburb']));
            $user->setStreet(strtoupper($input['street']));
            $user->setWhenVerified(Carbon::now("UTC"));
            $user->setStreetNumber($input['streetNumber']);
            $user->setDOB($dob);

            $domain_repo = $this->em->getRepository('VoteBundle:Domain');
            $elect_repo = $this->em->getRepository('VoteBundle:Electorate');

            $fed_domain = $domain_repo->findOneBy(array("level" => "federal"));
            $state_domain = $domain_repo->findOneBy(array("level" => "state", "shortName" => $state_abb));
            if(($fed_domain === NULL) || ($state_domain === NULL)) {
                return array(false, "Could not find federal or state domain - please contact support");
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
                $state_elect = new Electorate($state_domain, $state_district, $state_descriptor);
                $this->em->persist($state_elect);
            }
            $user->addElectorate($state_elect);

            $local_domain = $domain_repo->findOneBy(array("level" => "local", "name" => $council));
            if($local_domain === NULL) {
                $this->logger->info("Creating new local domain: $council");
                $local_domain = new Domain("local", $council);
                $local_domain->setParent($state_domain);
                $this->em->persist($local_domain);
            }
            $local_elect = $elect_repo->findOneBy(array("domain" => $local_domain, "name" => $council_ward));
            if($local_elect === NULL) {
                $this->logger->info("Creating new $council local electorate: $council_ward");
                $local_elect = new Electorate($local_domain, $council_ward, $local_descriptor);
                $this->em->persist($local_elect);
            }
            $user->addElectorate($local_elect);

            // TODO: remove these two lines, only for retroactive fix
            $local_domain->setParent($state_domain);
            $state_domain->setParent($fed_domain);

            $this->em->flush();

            if($tracking_event !== NULL) {
                $json = $tracking_event->getJsonArray();
                if(array_key_exists("nationbuilder.api_token", $json)) {
                    $nationbuilder = $this->get("vote.nationbuilder");
                    $nationbuilder->setToken($json["nationbuilder.api_token"]);
                    $nationbuilder->syncPerson($user);
                }
            }
            // don't remove after verification so that new users can see all the domains they can vote in
//            if($session->has("new_user_id")) {
//                $session->remove("new_user_id");
//            }

            $output = array(
                "user" => $user
                ,"federal" => $fed_elect
                ,"state" => $state_elect
                ,"local" => $local_elect
            );
        } else if(($success_nodes->count() > 0) && (($success_nodes->html() === "Please contact the AEC on 13 23 26 for assistance") || ($success_nodes->html() === "Your enrolment could not be confirmed. Please check the information you have entered"))) {
            return array(false, "Could not find you on the electoral role. Try with/without your middle name, or a previous address.");
        } else {
            return array(false, "Verification code is incorrect.");
        }

        return array(true, $output);
//        return $this->render("VoteBundle:Pages:verify_success.html.twig", $output);
    }

    /**
     * @Route("/verify-autocomplete", name="verify_autocomplete", options={"expose"=true})
     * @Method("POST");
     */
    public function getAutoCompleteAction(Request $request) {

        $input = $request->request->all();

        if(array_key_exists("postcode", $input)) {
            $url = "https://$this->AECBase/VerifyEnrolment.aspx/GetDropDownContents";
            $send_data = array(
                "category" => "postcode"
                ,"knownCategoryValues" => $input['postcode']
            );
            $is_postcode = true;
        } else if(array_key_exists("prefix", $input) && array_key_exists("context", $input)) {
            $url = "https://$this->AECBase/VerifyEnrolment.aspx/GetStreetAutoCompleteList";
            $send_data = array(
                "contextKey" => $input['context']
                ,"count" => 50
                ,"prefixText" => $input['prefix']
            );
            $is_postcode = false;
        } else {
            throw new BadRequestException("Incorrect Parameters");
        }

        $client = new HttpClient(array(
            'verify' => $this->verifySSL
        ));

        $response = $client->request('POST', $url, array( 'json' => $send_data ));

        $suburbs = array();
        $json_response = json_decode($response->getBody()->getContents(), true);

        if($is_postcode) {
            foreach ($json_response['d'] as $item) {
                if (strlen($item['value']) > 0) {
                    $suburbs[] = $item['value'];
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