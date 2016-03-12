<?php

namespace VoteBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use FOS\RestBundle\Controller\FOSRestController;
use Symfony\Component\HttpFoundation\Request;

use JMS\DiExtraBundle\Annotation as DI;

use GuzzleHttp\Client as HttpClient;
use Carbon\Carbon;

use Symfony\Component\DomCrawler\Crawler;

use VoteBundle\Exception\ErrorRedirectException;

/**
 * Class VerifyController
 * @package VoteBundle\Controller
 */
class VerifyController extends FOSRestController {

    /** @DI\Inject("doctrine.orm.entity_manager") */
    private $em;

    private function getPotentialUser() {
        $user = $this->getUser();

        if($user === NULL) {
            $session = $this->get("session");
            if(!$session->has("new_user_id")) {
                throw new ErrorRedirectException("homepage", "No user available");
            }
            $user_id = $session->get("new_user_id");
            $user = $this->em->getRepository('VoteBundle:User')->find($user_id);
        }
        if($user === NULL) {
            throw new ErrorRedirectException("homepage", "No user available");
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
            "names" => $user->getGivenNames()
            ,"surname" => $user->getSurname()
            ,"postcode" => $user->getPostcode()
            ,"suburb" => $user->getSuburb()
            ,"street" => $user->getStreet()
        );

        $client = new HttpClient([
            'verify' => false
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

        $client = new HttpClient([
            'verify' => false
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

            $user->setGivenNames($input['names']);
            $user->setSurname($input['surname']);
            $user->setPostcode($input['postcode']);
            $user->setSuburb($input['suburb']);
            $user->setStreet($input['street']);
            $user->setWhenVerified(Carbon::now("UTC"));
            $this->em->flush();

            $output = array(
                "message" => "SUCCESS $federal_electorate, $state_district, $council, $council_ward"
            );

        } else {
            throw new ErrorRedirectException("verify", "Unknown Error");
        }

        return $this->render("VoteBundle:Pages:verify.html.twig", $output);
    }
}
