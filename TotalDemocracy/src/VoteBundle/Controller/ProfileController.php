<?php

namespace VoteBundle\Controller;

use Carbon\Carbon;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use FOS\RestBundle\Controller\FOSRestController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;

use JMS\DiExtraBundle\Annotation as DI;

use VoteBundle\Entity\ServerEvent;

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
     */
    public function profileAction(Request $request) {

        $user = $this->getUser();

        $output = array(
            "isVolunteer" => $user->getIsVolunteer()
            ,"isMember" => $user->getIsMember()
            ,"phone" => $user->getPhone()
            ,"homePostcode" => $user->getHomePostcode()
            ,"homeSuburb" => $user->getHomeSuburb()
            ,"homeStreet" => $user->getHomeStreet()
            ,"homeStreetNumber" => $user->getHomeStreetNumber()
        );

        if($user->getHomeSuburb()) {
            $this->get("vote.js")->output("suburb", $user->getHomeSuburb());
        }

        $is_admin = $this->get("security.authorization_checker")->isGranted("ROLE_ADMIN");
        $output['is_admin'] = $is_admin;
        if($is_admin) {

            $since = Carbon::now("UTC")->subDays(1);
            $events = $this->em->getRepository('VoteBundle:ServerEvent')->findEvents("registration.track", $user->getId(), $since, false);

            if(count($events) > 0) {
                $event = $events[0];
                $close_time = Carbon::instance($event->getDateCreated())->addHours($event->getAmount());
//                $close_time = Carbon::instance($event->getDateCreated())->addMinutes($event->getAmount());
                if(Carbon::now("UTC")->gt($close_time)) {
                    $event->setProcessed(true);
                    $this->em->flush();
                } else {
                    $json = $event->getJsonArray();
                    $diff_min = $close_time->diffInMinutes();
                    $diff_hours = floor($diff_min / 60);
                    $diff_min -= $diff_hours * 60;
                    $output['tracking_time_left'] = "$diff_hours hours and $diff_min minutes left";
                    $output['tracking_token'] = $json['token'];
                    $output['tracking_context'] = $json['context'];

                    $this->get("vote.js")->output("tracking_token", $json['token']);
                }
            }
            /*
            $latest = NULL;
            $latest_token = "";
            foreach($events as $event) {
                $close_time = Carbon::instance($event->getDateCreated())->addHours($event->getAmount());
                if(($latest === NULL) || ($close_time->gt($latest))) {
                    $latest = $close_time;
                }
            }*/
//            $output['track_close_time'] =
//            $output['is_admin'] = true;
        }

        return $this->render('VoteBundle:Pages:profile.html.twig', $output);
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

        $input = $request->request->all();
        $user = $this->getUser();

//        $this->get("logger")->info("INPUT: " .json_encode($input));

        if(!array_key_exists("phone", $input)) {
            throw new ErrorRedirectException("profile", "Incorrect parameters");
        }

        $is_volunteer = array_key_exists("isVolunteer", $input);

        if($is_volunteer) {
            if( (!array_key_exists("homePostcode", $input)) ||
                (!array_key_exists("homeSuburb", $input)) ||
                (!array_key_exists("homeStreet", $input)) ||
                (!array_key_exists("homeStreetNumber", $input))) {
                throw new ErrorRedirectException("profile", "Cannot volunteer without home address");
            }
            $user->setHomePostcode($input['homePostcode']);
            $user->setHomeSuburb($input['homeSuburb']);
            $user->setHomeStreet($input['homeStreet']);
            $user->setHomeStreetNumber($input['homeStreetNumber']);
        }
//        if($is_volunteer && (
//            (!array_key_exists("homePostcode", $input)) ||
//            (!array_key_exists("homeSuburb", $input)) ||
//            (!array_key_exists("homeStreet", $input)) ||
//            (!array_key_exists("homeStreetNumber", $input))
//            )) {
//            throw new ErrorRedirectException("profile", "Cannot volunteer without home address");
//        }

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

        $is_admin = $this->get("security.authorization_checker")->isGranted("ROLE_ADMIN");
        if(!$is_admin) {
            throw new ErrorRedirectException("profile", "Access denied");
        }

        $event_repo = $this->em->getRepository('VoteBundle:ServerEvent');
        $old_events = $event_repo->findBy(array("user" => $this->getUser(), "name" => "registration.track", "processed" => false));
        foreach($old_events as $old_event) {
            $old_event->setProcessed(true);
        }
        $this->em->flush();

        return new RedirectResponse($this->generateUrl('profile'));
    }

}
