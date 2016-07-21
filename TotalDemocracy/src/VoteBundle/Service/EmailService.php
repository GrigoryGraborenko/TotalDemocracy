<?php
/**
 * Created by PhpStorm.
 * User: Grigory
 * Date: 21/07/2016
 * Time: 7:21 AM
 */

namespace VoteBundle\Service;

/**
 * Class EmailService
 * @package VoteBundle\Service
 */
class EmailService {

    /** @var Service Container container */
    private $container;

    /**
     * EmailService constructor.
     * @param $container
     */
    public function __construct($container) {
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

}