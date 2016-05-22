<?php
/**
 * Created by PhpStorm.
 * User: Grigory
 * Date: 12/03/2016
 * Time: 3:15 PM
 */

namespace VoteBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use FOS\RestBundle\Controller\FOSRestController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Validator\Constraints\Email as EmailConstraint;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use JMS\DiExtraBundle\Annotation as DI;

use Carbon\Carbon;
use GuzzleHttp\Client as HttpClient;

use VoteBundle\Entity\ServerEvent;
use VoteBundle\Exception\ErrorRedirectException;

/**
 * Class RegisterController
 * @package VoteBundle\Controller
 */
class RegisterController extends FOSRestController {

    /** @DI\Inject("doctrine.orm.entity_manager") */
    private $em;

    /**
     * @Route("/signup", name="signup")
     * @Method("GET");
     */
    public function indexAction(Request $request) {

        $session = $this->get("session");
        if($session->has("new_user_id")) {
            $session->remove("new_user_id");
        }

        $user = $this->getUser();
        if($user !== NULL) {
            throw new ErrorRedirectException('homepage', "Cannot register twice");
        }

        $output = array();
        if($this->getParameter("recaptcha") === true) {
            $output['recaptcha'] = $this->getParameter("google.recaptcha.public");
        }

        $bag = $this->get("session")->getFlashBag();
        if($bag->has("email")) {
            $output["email"] = $bag->get("email")[0];
        } else {
            $output["email"] = NULL;
        }


        return $this->render('VoteBundle:Pages:register.html.twig', $output);
    }

    /**
     * @Route("/signup-email", name="signup_email")
     * @Method("POST")
     */
    public function emailAction(Request $request) {

        $user = $this->getUser();
        if($user !== NULL) {
            throw new ErrorRedirectException('homepage', "Cannot register twice", "email-error");
        }

        $input = $request->request->all();
        if(!array_key_exists("email", $input)) {
            throw new ErrorRedirectException('signup', "Email not specified", "email-error");
        }
        $email = $input["email"];

        $session = $this->get("session");
        $session->getFlashBag()->set("email", $email);

        if(!array_key_exists("understand", $input)) {
            throw new ErrorRedirectException('signup', "Cannot register without agreeing to the terms and conditions", "email-error");
        }

        if($this->getParameter("recaptcha") === true) {
            if(!array_key_exists("g-recaptcha-response", $input)) {
                throw new ErrorRedirectException('signup', "Recaptcha response not found", "email-error");
            }
            $recaptcha_response = $input["g-recaptcha-response"];
            $recapcha_private = $this->getParameter("google.recaptcha.private");

            $client = new HttpClient(array('exceptions' => false));
            $response = $client->post("https://www.google.com/recaptcha/api/siteverify", array('form_params' => array(
                "secret" => $recapcha_private
                ,"response" => $recaptcha_response
                ,"remoteip" => $request->getClientIp()
            )));
            if($response->getStatusCode() === 200) {
                $json_return = json_decode($response->getBody()->getContents(), true);
                if($json_return === NULL) {
                    throw new ErrorRedirectException('signup', "Could not process recaptcha response", "email-error");
                } else if((!array_key_exists("success", $json_return)) || ($json_return["success"] !== true)) {
                    throw new ErrorRedirectException('signup', "Please verify that you are not a bot with the reCaptcha below", "email-error");
                }
            } else {
                throw new ErrorRedirectException('signup', "Could not get recaptcha response", "email-error");
            }

        }

        //$this->get("logger")->info("INPUT " . json_encode($input));

        $validator = $this->get('validator');
        $emailConstraint = new EmailConstraint();
        $errors  = $validator->validate($email, $emailConstraint);
        if((count($errors) > 0) || (strlen($email) < 1)) {
            throw new ErrorRedirectException('signup', "$email is an invalid email", "email-error");
        }

        $userManager = $this->get('fos_user.user_manager');
        $prior = $userManager->findUserByEmail($email);
        if($prior !== NULL) {
            if($prior->isEnabled()) {
                throw new ErrorRedirectException('signup', "Email has already been registered", "email-error");
            }
            throw new ErrorRedirectException('signup', "$email is already taken, please check your inbox if this was you", "email-error");
        }

        // actually create and save the user
        $user = $userManager->createUser();
        $user->setEmail($email);
        $user->setUsername($email);
        $user->setPlainPassword($this->getGUID());
        $user->setConfirmationToken($this->get('fos_user.util.token_generator')->generateToken());
        $this->em->persist($user);

        $event = new ServerEvent("registration", $user, array("ip" => $request->getClientIp()));
        $this->em->persist($event);
        $cookies = $request->cookies->all();
        if(array_key_exists("tracking_token", $cookies)) {
            $events = $this->em->getRepository('VoteBundle:ServerEvent')->findByJson("registration.track", $cookies['tracking_token'], false);
            if(count($events) > 0) {
                $track_event = $events[0];
                $close_time = Carbon::instance($track_event->getDateCreated())->addHours($track_event->getAmount());
                if(Carbon::now("UTC")->gt($close_time)) {
                    $track_event->setProcessed(true);
                } else {
                    $event->setParent($track_event);
                }
            }
        }
        $this->em->flush();

        $session->set("new_user_id", $user->getId());

        $this->sendRegistrationEmail($user->getEmailCanonical(), $user->getConfirmationToken());

        return new RedirectResponse($this->generateUrl('verify'));
    }

    /**
     * @Route("/signup-finish/{email}/{confirm_token}", name="signup_finish")
     * @Method("GET");
     */
    public function signupFinishAction(Request $request, $email, $confirm_token) {

        if($this->getUser() !== NULL) {
            throw new ErrorRedirectException('homepage', "Cannot register twice");
        }

        $new_user = $this->em->getRepository('VoteBundle:User')->findOneBy(array("emailCanonical" => $email, "confirmationToken" => $confirm_token, "enabled" => false));
        if($new_user === NULL) {
            throw new ErrorRedirectException('homepage', "Email or confirmation token is incorrect");
        }

        if($new_user->getSuburb()) {
            $this->get("vote.js")->output("suburb", $new_user->getSuburb());
        }

        $pass_min_length = $this->get("vote.option")->getInteger("password.length.min");
        $phone_min_length = $this->get("vote.option")->getInteger("phone.length.min");
        $output = array(
            "email" => $email
            ,"token" => $confirm_token
            ,"password_min_length" => $pass_min_length
            ,"phone_min_length" => $phone_min_length

//            ,"postcode" => $new_user->getPostcode()
//            ,"suburb" => $new_user->getSuburb()
//            ,"street" => $new_user->getStreet()
//            ,"streetNumber" => $new_user->getStreetNumber()

            ,"isVolunteer" => $new_user->getIsVolunteer()
            ,"isMember" => $new_user->getIsMember()
            ,"phone" => $new_user->getPhone()
            ,"homePostcode" => $new_user->getPostcode() // all four default to what was verified
            ,"homeSuburb" => $new_user->getSuburb()
            ,"homeStreet" => $new_user->getStreet()
            ,"homeStreetNumber" => $new_user->getStreetNumber()

        );

        return $this->render('VoteBundle:Pages:register_finish.html.twig', $output);
    }

    /**
     * @Route("/signup-confirm/{email}/{confirm_token}", name="signup_confirm")
     * @Method("POST");
     */
    public function signupConfirmAction(Request $request, $email, $confirm_token) {

        $user = $this->getUser();
        if($user !== NULL) {
            throw new ErrorRedirectException('homepage', "Cannot register twice");
        }

        $url_params = array("email" => $email, "confirm_token" => $confirm_token);

        $input = $request->request->all();

        if( (!array_key_exists("password", $input)) ||
            (!array_key_exists("phone", $input))
            ) {
            throw new ErrorRedirectException('homepage', "Incorrect parameters specified");
        }

        $password = $input['password'];
        $phone = $input['phone'];
        $is_volunteer = array_key_exists('isVolunteer', $input);
        $is_member = array_key_exists('isMember', $input);

        $min_password_len = $this->get("vote.option")->getInteger("password.length.min");
        $phone_min_length = $this->get("vote.option")->getInteger("phone.length.min");
        if(strlen($password) < $min_password_len) {
            throw new ErrorRedirectException('signup_finish', "Password is too short, needs to be at least $min_password_len characters long", "confirm-error", $url_params);
        }
        if(strlen($phone) < $phone_min_length) {
            throw new ErrorRedirectException('signup_finish', "Phone number is too short, needs to be at least $phone_min_length characters long", "confirm-error", $url_params);
        }

        $new_user = $this->em->getRepository('VoteBundle:User')->findOneBy(array("emailCanonical" => $email, "confirmationToken" => $confirm_token, "enabled" => false));
        if($new_user === NULL) {
            throw new ErrorRedirectException('signup_finish', "Email or confirmation token is incorrect", "confirm-error", $url_params);
        }

        if($is_volunteer) {
            if( array_key_exists("homePostcode", $input) &&
                array_key_exists("homeSuburb", $input) &&
                array_key_exists("homeStreetNumber", $input) &&
                array_key_exists("homeStreet", $input)) {

                $new_user->setHomePostcode($input["homePostcode"]);
                $new_user->setHomeSuburb($input["homeSuburb"]);
                $new_user->setHomeStreetNumber($input["homeStreetNumber"]);
                $new_user->setHomeStreet($input["homeStreet"]);
            } else {
                $this->get("logger")->info(json_encode($url_params));
                throw new ErrorRedirectException('signup_finish', "If you wish to volunteer, please enter your home address", "confirm-error", $url_params);
            }
        }

        $new_user->setPlainPassword($password);
        $new_user->setEnabled(true);
        $new_user->setConfirmationToken(NULL);
        $new_user->setPhone($phone);
        $new_user->setIsVolunteer($is_volunteer);
        $new_user->setIsMember($is_member);
        $this->em->flush();

        $token = new UsernamePasswordToken($new_user, null, 'main', $new_user->getRoles());
        $this->get('security.token_storage')->setToken($token);

        return new RedirectResponse($this->generateUrl('homepage'));
    }

    /**
     * @param $email
     * @param $confirm_token
     * @throws ErrorRedirectException
     */
    private function sendRegistrationEmail($email, $confirm_token) {

        $url_params = array("email" => $email, "confirm_token" => $confirm_token);

        $register_url = $this->getParameter("url_base") . $this->generateUrl("signup_finish", $url_params);

        $paragraphs = array(
            "Thank you for signing up with People Decide. You're almost ready to vote."
            ,"<a href=\"$register_url\">Click here to finish registration</a>"
        );

        // send email
        $mailer = $this->container->get('mailer');
        $templating = $this->container->get('templating');

        $message = \Swift_Message::newInstance()
            ->setSubject('Registration Confirmation')
            ->setFrom('noreply@peopledecide.org')
            ->setTo($email)
            ->setBody(
                $templating->render(
                    'emails/simple_email.html.twig',
                    array(
                        'header' => "Welcome to the voting platform"
                        ,'paragraphs' => $paragraphs
                    )
                ),
                'text/html'
            );

        $num_recipients = $mailer->send($message);
        if($num_recipients <= 0) {
            throw new ErrorRedirectException('signup_finish', 'Could not send email', "confirm-error", $url_params);
        }

        $this->get("logger")->info("Registration email sent to $email with URL: $register_url ");
    }

    function getGUID() {
        if (function_exists('com_create_guid')){
            return com_create_guid();
        }else{
            mt_srand((double)microtime()*10000);//optional for php 4.2.0 and up.
            $charid = strtoupper(md5(uniqid(rand(), true)));
            $hyphen = chr(45);// "-"
            $uuid = chr(123)// "{"
                .substr($charid, 0, 8).$hyphen
                .substr($charid, 8, 4).$hyphen
                .substr($charid,12, 4).$hyphen
                .substr($charid,16, 4).$hyphen
                .substr($charid,20,12)
                .chr(125);// "}"
            return $uuid;
        }
    }

}
