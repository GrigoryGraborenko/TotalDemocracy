<?php
/**
 * Created by PhpStorm.
 * User: Grigory
 * Date: 21/07/2016
 * Time: 7:21 AM
 */

namespace VoteBundle\Service;

use FOS\UserBundle\Mailer\Mailer;
use FOS\UserBundle\Model\UserInterface;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Class EmailService
 * @package VoteBundle\Service
 */
class EmailService extends Mailer {

    /** @var Service Container container */
    private $container;

    /**
     * EmailService constructor.
     * @param \Swift_Mailer $container
     * @param UrlGeneratorInterface $mailer
     * @param EngineInterface $router
     * @param array $templating
     */
    public function __construct($container, $mailer, $router, $templating) {
        parent::__construct($mailer, $router, $templating, array(
            "confirmation.template" => $container->getParameter("fos_user.registration.confirmation.template")
            ,"resetting.template" => $container->getParameter("fos_user.resetting.email.template")
            ,"from_email" => array(
                "confirmation" => $container->getParameter("fos_user.registration.confirmation.from_email")
                ,"resetting" => $container->getParameter("fos_user.resetting.email.from_email")
            )
        ));
        $this->container = $container;
    }

    /**
     * @param $params
     * @param $user
     * @return array
     */
    public function emailImported($params, $user) {

        // todo: finish
        return "To be completed";

        $email = $user->getEmail();

        $email = "thegrisha@gmail.com";

        $logger = $this->container->get("logger");
        $logger->info("Emailing " . $email);

        $subject = "Exciting new voting system, results and membership";
        $paragraphs = array();

        $fname = $user->getGivenNames();
        if($fname === NULL) {
            $fname = "Sir/Madam";
        } else {
            $fname = explode(" ", $fname)[0];
        }
        $paragraphs[] = "Dear $fname,";

        $result = $this->sendSimpleEmail($email, $subject, NULL, $paragraphs);

        return array("success" => $result);
    }

    /**
     * @param $email
     * @param $subject
     * @param $header
     * @param $paragraphs
     * @return bool
     */
    public function sendSimpleEmail($email, $subject, $header, $paragraphs) {

        $params = array('paragraphs' => $paragraphs);
        if($header !== NULL) {
            $params['header'] = $header;
        }

        $mailer = $this->container->get('mailer');
        $templating = $this->container->get('templating');
        $message = \Swift_Message::newInstance()
            ->setSubject($subject)
            ->setFrom(array('noreply@peopledecide.org' => 'People Decide'))
            ->setTo($email)
            ->setBody(
                $templating->render('emails/simple_email.html.twig', $params),
                'text/html'
            );
        $num_recip = $mailer->send($message);
        return ($num_recip > 0);
    }

     /**
     * {@inheritdoc}
     */
    public function sendResettingEmailMessage(UserInterface $user) {

        $router = $this->container->get('router');
        $url = $router->generate('fos_user_resetting_reset', array('token' => $user->getConfirmationToken()), UrlGeneratorInterface::ABSOLUTE_URL);

        $this->sendSimpleEmail($user->getEmail(), "PeopleDecide Password Reset", "", array(
            "You have requested a password reset. To choose a new password, <a href='$url'>click on this link</a> and follow the instructions."
            ,"If you don't remember asking for a password to be reset, please contact support."
        ));
    }

}