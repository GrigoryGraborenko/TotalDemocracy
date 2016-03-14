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

        return $this->render('VoteBundle:Pages:register.html.twig', array());
    }

    /**
     * @Route("/signup-email", name="signup_email")
     */
    public function emailAction(Request $request) {

        $user = $this->getUser();
        if($user !== NULL) {
            throw new ErrorRedirectException('homepage', "Cannot register twice");
        }

        $input = $request->request->all();
        if(!array_key_exists("email", $input)) {
            throw new ErrorRedirectException('signup', "Email not specified", "email-error");
        }

        $email = $input["email"];

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
        $user = $userManager->createUser();
        $user->setEmail($email);
        $user->setUsername($email);
        $user->setPlainPassword(com_create_guid());
        $user->setConfirmationToken($this->get('fos_user.util.token_generator')->generateToken());

        $this->em->persist($user);
        $this->em->flush();

        $this->get("session")->set("new_user_id", $user->getId());

        $this->sendRegistrationEmail($user->getEmailCanonical(), $user->getConfirmationToken());

        return new RedirectResponse($this->generateUrl('verify'));
    }

    /**
     * @Route("/signup-finish/{email}/{confirm_token}", name="signup_finish")
     * @Method("GET");
     */
    public function signupFinishAction(Request $request, $email, $confirm_token) {

        $user = $this->getUser();
        if($user !== NULL) {
            throw new ErrorRedirectException('homepage', "Cannot register twice");
        }
        $min_length = $this->get("vote.option")->getInteger("password.length.min");

        return $this->render('VoteBundle:Pages:register_finish.html.twig', array("email" => $email, "token" => $confirm_token, "min_length" => $min_length));
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

        $input = $request->request->all();
        if(!array_key_exists("password", $input)) {
            throw new ErrorRedirectException('homepage', "Password not specified");
        }

        $password = $input['password'];
        $url_params = array("email" => $email, "confirm_token" => $confirm_token);

        $min_password_len = $this->get("vote.option")->getInteger("password.length.min");
        if(strlen($password) < $min_password_len) {
            throw new ErrorRedirectException('signup_finish', "Password is too short, needs to be at least $min_password_len characters long", "confirm-error", $url_params);
        }

        $new_user = $this->em->getRepository('VoteBundle:User')->findOneBy(array("emailCanonical" => $email, "confirmationToken" => $confirm_token, "enabled" => false));
        if($new_user === NULL) {
            throw new ErrorRedirectException('signup_finish', "Email or confirmation token is incorrect", "confirm-error", $url_params);
        }

        $new_user->setPlainPassword($password);
        $new_user->setEnabled(true);
        $new_user->setConfirmationToken(NULL);
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
                    'Emails/simple_email.html.twig',
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

}
