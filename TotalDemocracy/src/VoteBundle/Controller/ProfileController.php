<?php

namespace VoteBundle\Controller;

use Carbon\Carbon;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Cookie;

use JMS\DiExtraBundle\Annotation as DI;
use OAuth2\Client;

use VoteBundle\Controller\CommonController;
use VoteBundle\Entity\ServerEvent;
use VoteBundle\Entity\Volunteer;

use VoteBundle\Exception\ErrorRedirectException;

/**
 * Class ProfileController
 * @package VoteBundle\Controller
 */
class ProfileController extends CommonController {

    /** @DI\Inject("doctrine.orm.entity_manager") */
    protected $em;

    /**
     * @Route("/settings", name="profile")
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws ErrorRedirectException
     */
    public function profileAction(Request $request) {

        $user = $this->getUser();
        if($user === NULL) {
            throw new ErrorRedirectException("error_page", "Not logged in");
        }

        $volunteer = $user->getVolunteer();
        $output = array(
            "user" => $user
        );

        if($volunteer && $volunteer->getHomeSuburb()) {
            $this->get("vote.js")->output("suburb", $volunteer->getHomeSuburb());
        }

        $session = $request->getSession();
        if($session->has("nationbuilder.api_token")) {
            $output['nationbuilder_login'] = true;
        }

        $is_admin = $this->get("security.authorization_checker")->isGranted("ROLE_ADMIN");
        $output['is_admin'] = $is_admin;
        $new_cookie = NULL;
        if($is_admin) {

//            $since = Carbon::now("UTC")->subDays(1);
            //$events = $this->em->getRepository('VoteBundle:ServerEvent')->findEvents("registration.track", $user->getId(), $since, false);
            $event = $this->em->getRepository('VoteBundle:ServerEvent')->findOneBy(array("name" => "registration.track", "user" => $user, "processed" => false));

            if($event !== NULL) {
                $close_time = Carbon::instance($event->getDateCreated())->addHours($event->getAmount());
                if(Carbon::now("UTC")->gt($close_time)) {
                    $event->setProcessed(true);
                    $event->removeKeyJson("nationbuilder.api_token");
                    $this->em->flush();
                } else {
                    $json = $event->getJsonArray();
                    $diff_min_total = $close_time->diffInMinutes();
                    $diff_hours = floor($diff_min_total / 60);
                    $diff_min = $diff_min_total - $diff_hours * 60;
                    $output['tracking_time_left'] = "$diff_hours hours and $diff_min minutes left";
                    $output['tracking_token'] = $json['token'];
                    $output['tracking_context'] = $json['context'];

                    if(array_key_exists("nationbuilder.api_token", $json) && (!$output['nationbuilder_login'])) {
                        $output['nationbuilder_login'] = true;
                        $session->set("nationbuilder.api_token", $json["nationbuilder.api_token"]);
                    }

                    $new_cookie = new Cookie("tracking_token", $json['token'], time() + $diff_min_total * 60);
                }
            }
        }

        $response = $this->render('VoteBundle:Pages:profile.html.twig', $output);
        if($new_cookie) {
            $response->headers->setCookie($new_cookie);
        }

        return $response;
    }

    /**
     * @Route("/unsubscribe/{token}", name="unsubscribe")
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws ErrorRedirectException
     */
    public function unsubscribeAction(Request $request, $token) {

        $user = $this->em->getRepository('VoteBundle:User')->findOneBy(array("emailOptOutToken" => $token));
        if($user) {
            $user->setEmailOptOut(true);
            $this->em->flush();
        }
        $response = $this->render('VoteBundle:Pages:unsubscribe.html.twig', array("user" => $user));
        return $response;
    }


    /**
     * @Route("/settings/update", name="profile_update", defaults={"via_profile"=true})
     * @Route("/volunteer", name="post_verify_volunteer", defaults={"via_profile"=false})
     * @Method("POST")
     *
     * @param Request $request
     * @return RedirectResponse
     * @throws ErrorRedirectException
     */
    public function profileUpdateAction(Request $request, $via_profile) {

        if($via_profile) {
            $endpoint = "profile";
            $user = $this->getUser();
        } else {
            $endpoint = "vote";
            $user = $this->getPotentialUser($this->em);
        }
        if($user === NULL) {
            throw new ErrorRedirectException("error_page", "Not logged in");
        }
        $input = $request->request->all();

//        $this->get("logger")->info("INPUT: " .json_encode($input));

        if(!array_key_exists("phone", $input)) {
            throw new ErrorRedirectException($endpoint, "Incorrect parameters");
        }

        $is_volunteer = array_key_exists("isVolunteer", $input);

        if($is_volunteer) {
            if( (!array_key_exists("homePostcode", $input)) ||
                (!array_key_exists("homeSuburb", $input)) ||
                (!array_key_exists("homeStreet", $input)) ||
                (!array_key_exists("homeStreetNumber", $input)) //||
//                (!array_key_exists("whenAvailable", $input)) ||
//                (!array_key_exists("whenToCall", $input)) ||
//                (!array_key_exists("bestCommunication", $input))
            ) {
                //throw new ErrorRedirectException("profile", "Incorrect parameters");
                throw new ErrorRedirectException($endpoint, "Need to enter an address if volunteering");
            }
            $volunteer = $user->getVolunteer();
            if($volunteer === NULL) {
                $volunteer = new Volunteer($user, $input["homePostcode"], $input["homeSuburb"], $input["homeStreetNumber"], $input["homeStreet"]);
                $this->em->persist($volunteer);
                $user->setVolunteer($volunteer);
            }

            $volunteer->setHomePostcode($input['homePostcode']);
            $volunteer->setHomeSuburb($input['homeSuburb']);
            $volunteer->setHomeStreet($input['homeStreet']);
            $volunteer->setHomeStreetNumber($input['homeStreetNumber']);
            $volunteer->setWillPollBooth(array_key_exists("willPollBooth", $input));
            $volunteer->setWillDoorKnock(array_key_exists("willDoorKnock", $input));
            $volunteer->setWillSignage(array_key_exists("willSignage", $input));
            $volunteer->setWillCall(array_key_exists("willCall", $input));
            $volunteer->setWillHouseParty(array_key_exists("willHouseParty", $input));
            $volunteer->setWillEnvelopes(array_key_exists("willEnvelopes", $input));

//            $volunteer->setWhenAvailable($input["whenAvailable"]);
//            $volunteer->setWhenToCall($input["whenToCall"]);
//            $volunteer->setBestCommunication($input["bestCommunication"]);

            if(array_key_exists("willOther", $input) && array_key_exists("willOtherText", $input) && ($input["willOtherText"] !== "")) {
                $volunteer->setWillOther($input["willOtherText"]);
            } else {
                $volunteer->setWillOther(NULL);
            }
        }

        $user->setIsVolunteer($is_volunteer);
        $user->setIsMember(array_key_exists("isMember", $input));
        $user->setPhone($input['phone']);

        $this->em->flush();

        if(!$via_profile) {
            $cookies = $request->cookies->all();
            if(array_key_exists("tracking_token", $cookies)) {
                $events = $this->em->getRepository('VoteBundle:ServerEvent')->findByJson("registration.track", $cookies['tracking_token'], false);
                if(count($events) > 0) {
                    $json = $events[0]->getJsonArray();
                    if(array_key_exists("nationbuilder.api_token", $json)) {
                        $nationbuilder = $this->get("vote.nationbuilder");
                        $nationbuilder->setToken($json["nationbuilder.api_token"]);
                        $nationbuilder->syncPerson($user);
                    }
                }
            }
        }

        return new RedirectResponse($this->generateUrl($endpoint));
    }

    /**
     * @Route("/settings/password", name="password_update")
     * @Method("POST")
     *
     * @param Request $request
     * @return RedirectResponse
     * @throws ErrorRedirectException
     */
    public function passwordUpdateAction(Request $request) {

        $user = $this->getUser();
        if($user === NULL) {
            throw new ErrorRedirectException("error_page", "Not logged in");
        }

        $input = $request->request->all();
        if((!array_key_exists("password", $input)) || (!array_key_exists("old-password", $input)) || (!array_key_exists("repeat-password", $input))) {
            throw new ErrorRedirectException("profile", "Incorrect parameters", "error-password");
        }

        $factory = $this->get('security.encoder_factory');
        $encoder = $factory->getEncoder($user);
        if($encoder->isPasswordValid($user->getPassword(), $input['old-password'], $user->getSalt()) !== true) {
            throw new ErrorRedirectException("profile", "Old password not entered correctly", "error-password");
        }

        $password = $input["password"];
        if($password !== $input["repeat-password"]) {
            throw new ErrorRedirectException("profile", "Passwords do not match", "error-password");
        }
        if(strlen($password) <= 0) {
            throw new ErrorRedirectException("profile", "Please enter a new password", "error-password");
        }

        $is_valid = $this->get("vote.user")->isPasswordValid($password);
        if($is_valid !== true) {
            throw new ErrorRedirectException("profile", $is_valid, "error-password");
        }

        $user->setPlainPassword($password);
        $user_manager = $this->get('fos_user.user_manager');
        $user_manager->updatePassword($user);
        $this->em->flush();

        $this->get("session")->getFlashBag()->set("success-password", "You have successfully updated your password");

        return new RedirectResponse($this->generateUrl('profile'));
    }

    /**
     * @Route("/settings/track", name="profile_track")
     * @Method("POST")
     *
     * @param Request $request
     * @return RedirectResponse
     * @throws ErrorRedirectException
     */
    public function profileTrackAction(Request $request) {

        $user = $this->getUser();
        if($user === NULL) {
            throw new ErrorRedirectException("error_page", "Not logged in");
        }

        $input = $request->request->all();
        if((!array_key_exists("time", $input)) || (!array_key_exists("context", $input))) {
            throw new ErrorRedirectException("profile", "Incorrect parameters");
        }

        $is_admin = $this->get("security.authorization_checker")->isGranted("ROLE_ADMIN");
        if(!$is_admin) {
            throw new ErrorRedirectException("profile", "Access denied");
        }

        $event_repo = $this->em->getRepository('VoteBundle:ServerEvent');
        $old_events = $event_repo->findBy(array("user" => $user, "name" => "registration.track", "processed" => false));
        foreach($old_events as $old_event) {
            $old_event->setProcessed(true);
            $old_event->removeKeyJson("nationbuilder.api_token");
        }

        $hours = intval($input['time']);

        $token = $this->get('fos_user.util.token_generator')->generateToken();

        $json = array(
            "token" => $token
            ,"context" => $input['context']
        );
        $session = $request->getSession();
        if($session->has("nationbuilder.api_token")) {
            $json["nationbuilder.api_token"] = $session->get("nationbuilder.api_token");
        }

        $event = new ServerEvent("registration.track", $this->getUser(), $json, $hours);
        $this->em->persist($event);
        $this->em->flush();

        return new RedirectResponse($this->generateUrl('profile'));
    }

    /**
     * @Route("/settings/untrack", name="profile_untrack")
     * @Method("POST")
     *
     * @param Request $request
     * @return RedirectResponse
     * @throws ErrorRedirectException
     */
    public function profileUnTrackAction(Request $request) {

        $user = $this->getUser();
        if($user === NULL) {
            throw new ErrorRedirectException("error_page", "Not logged in");
        }

        $is_admin = $this->get("security.authorization_checker")->isGranted("ROLE_ADMIN");
        if(!$is_admin) {
            throw new ErrorRedirectException("profile", "Access denied");
        }

        $event_repo = $this->em->getRepository('VoteBundle:ServerEvent');
        $old_events = $event_repo->findBy(array("user" => $user, "name" => "registration.track", "processed" => false));
        foreach($old_events as $old_event) {
            $old_event->setProcessed(true);
            $old_event->removeKeyJson("nationbuilder.api_token");
        }
        $this->em->flush();

        return new RedirectResponse($this->generateUrl('profile'));
    }

    /**
     * @Route("/oauth/nationbuilder", name="oauth_nationbuilder")
     */
    public function oauthAction(Request $request) {

        $user = $this->getUser();
        if($user === NULL) {
            throw new ErrorRedirectException("error_page", "Access denied");
        }

        $test_token = $this->getParameter('nationbuilder.testToken');
        if($test_token != NULL) {
            $session = $request->getSession();
            $session->set('nationbuilder.api_token', $test_token);

            $event = $this->em->getRepository('VoteBundle:ServerEvent')->findOneBy(array("name" => "registration.track", "user" => $user, "processed" => false));
            if($event !== NULL) {
                $event->updateJson("nationbuilder.api_token", $test_token);
                $this->em->flush();
            }

            return $this->redirectToRoute('profile');
        }

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
        $user = $this->getUser();
        if($user === NULL) {
            throw new ErrorRedirectException("error_page", "Access denied");
        }

        $input_query = $request->query->all();

        $session = $request->getSession();
        if(array_key_exists('code', $input_query)) {
            $session->set('nationbuilder.api_token', $input_query['code']);
            $this->get('logger')->debug("oauth token set successfully");

            $event = $this->em->getRepository('VoteBundle:ServerEvent')->findOneBy(array("name" => "registration.track", "user" => $user, "processed" => false));
            if($event !== NULL) {
                $event->updateJson("nationbuilder.api_token", $input_query['code']);
                $this->em->flush();
            }

            $session->getFlashBag()->set("oauth", "success");
        } else {
            $session->getFlashBag()->set("oauth", "failure");
        }

        return $this->redirectToRoute('profile');
    }

    /**
     * @Route("/oauth/nationbuilder/cancel", name="oauth_nationbuilder_cancel")
     */
    public function oauthCancelAction(Request $request) {

        $user = $this->getUser();
        if($user === NULL) {
            throw new ErrorRedirectException("error_page", "Access denied");
        }

        $event = $this->em->getRepository('VoteBundle:ServerEvent')->findOneBy(array("name" => "registration.track", "user" => $user, "processed" => false));
        if($event !== NULL) {
            $event->removeKeyJson("nationbuilder.api_token");
            $this->em->flush();
        }
        $request->getSession()->remove('nationbuilder.api_token');

        return $this->redirectToRoute('profile');
    }

}
