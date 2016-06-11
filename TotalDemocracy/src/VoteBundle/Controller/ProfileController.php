<?php

namespace VoteBundle\Controller;

use Carbon\Carbon;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use FOS\RestBundle\Controller\FOSRestController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Cookie;

use JMS\DiExtraBundle\Annotation as DI;

use VoteBundle\Entity\ServerEvent;
use VoteBundle\Entity\Volunteer;

use VoteBundle\Exception\ErrorRedirectException;

/**
 * Class ProfileController
 * @package VoteBundle\Controller
 */
class ProfileController extends FOSRestController {

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
            "isVolunteer" => $user->getIsVolunteer()
            ,"isMember" => $user->getIsMember()
            ,"phone" => $user->getPhone()
            ,"volunteer" => $volunteer
        );

        if($volunteer && $volunteer->getHomeSuburb()) {
            $this->get("vote.js")->output("suburb", $volunteer->getHomeSuburb());
        }

        $is_admin = $this->get("security.authorization_checker")->isGranted("ROLE_ADMIN");
        $output['is_admin'] = $is_admin;
        $new_cookie = NULL;
        if($is_admin) {

            $since = Carbon::now("UTC")->subDays(1);
            $events = $this->em->getRepository('VoteBundle:ServerEvent')->findEvents("registration.track", $user->getId(), $since, false);

            if(count($events) > 0) {
                $event = $events[0];
                $close_time = Carbon::instance($event->getDateCreated())->addHours($event->getAmount());
                if(Carbon::now("UTC")->gt($close_time)) {
                    $event->setProcessed(true);
                    $this->em->flush();
                } else {
                    $json = $event->getJsonArray();
                    $diff_min_total = $close_time->diffInMinutes();
                    $diff_hours = floor($diff_min_total / 60);
                    $diff_min = $diff_min_total - $diff_hours * 60;
                    $output['tracking_time_left'] = "$diff_hours hours and $diff_min minutes left";
                    $output['tracking_token'] = $json['token'];
                    $output['tracking_context'] = $json['context'];
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
     * @Route("/settings/update", name="profile_update")
     * @Method("POST")
     *
     * @param Request $request
     * @return RedirectResponse
     * @throws ErrorRedirectException
     */
    public function profileUpdateAction(Request $request) {

        $user = $this->getUser();
        if($user === NULL) {
            throw new ErrorRedirectException("error_page", "Not logged in");
        }
        $input = $request->request->all();

//        $this->get("logger")->info("INPUT: " .json_encode($input));

        if(!array_key_exists("phone", $input)) {
            throw new ErrorRedirectException("profile", "Incorrect parameters");
        }

        $is_volunteer = array_key_exists("isVolunteer", $input);

        if($is_volunteer) {
            if( (!array_key_exists("homePostcode", $input)) ||
                (!array_key_exists("homeSuburb", $input)) ||
                (!array_key_exists("homeStreet", $input)) ||
                (!array_key_exists("homeStreetNumber", $input)) ||
                (!array_key_exists("whenAvailable", $input)) ||
                (!array_key_exists("whenToCall", $input)) ||
                (!array_key_exists("bestCommunication", $input))
            ) {
                throw new ErrorRedirectException("profile", "Incorrect parameters");
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

            $volunteer->setWhenAvailable($input["whenAvailable"]);
            $volunteer->setWhenToCall($input["whenToCall"]);
            $volunteer->setBestCommunication($input["bestCommunication"]);

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

        return new RedirectResponse($this->generateUrl('profile'));
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
        }

        $hours = intval($input['time']);

        $token = $this->get('fos_user.util.token_generator')->generateToken();

        $json = array(
            "token" => $token
            ,"context" => $input['context']
        );

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
        }
        $this->em->flush();

        return new RedirectResponse($this->generateUrl('profile'));
    }

}
