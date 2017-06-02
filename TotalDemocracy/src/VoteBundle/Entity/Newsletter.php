<?php
/**
 * Created by PhpStorm.
 * User: Grigory
 * Date: 20/05/2017
 * Time: 12:10 PM
 */

namespace VoteBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

use Gedmo\Mapping\Annotation as Gedmo;

use Grigorygraborenko\RecursiveAdmin\Annotations\Admin;

use VoteBundle\Entity\Task;
use VoteBundle\Entity\TaskGroup;

/**
 * Class Newsletter
 * @package VoteBundle\Entity
 *
 * @ORM\Entity
 * @ORM\Table(name="newsletter")
 * @Admin(read="ROLE_ADMIN", write="ROLE_ADMIN", create="ROLE_ADMIN")
 */
class Newsletter {

    /**
     * @ORM\Id
     * @ORM\Column(type="string")
     * @ORM\GeneratedValue(strategy="UUID")
     */
    protected $id;

    /**
     * @ORM\Column(type="datetime")
     * @Gedmo\Timestampable(on="create")
     */
    protected $date_created;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @Gedmo\Timestampable(on="update")
     */
    protected $date_updated;

    /** @ORM\Column(type="string", nullable=false) */
    protected $type;

    /** @ORM\Column(type="string", nullable=false) */
    protected $name;

    /** @ORM\Column(type="string", nullable=false) */
    protected $subject;

    /** @ORM\Column(type="text", nullable=false) */
    protected $json_components;

    /**
     * @ORM\Column(type="boolean",nullable=false)
     * @Admin(read="ROLE_ADMIN", write="none")
     */
    protected $sent;

    /**
     * @ORM\OneToOne(targetEntity="VoteBundle\Entity\TaskGroup")
     * @ORM\JoinColumn(name="task_group_id", referencedColumnName="id", nullable=false)
     */
    protected $task_group;

    /**
     * Newsletter constructor.
     * @param $type
     * @param $name
     * @param $subject
     * @param $json_components
     */
    public function __construct($type, $name, $subject, $json_components) {
        $this->type = $type;
        $this->name = $name;
        $this->subject = $subject;
        $this->sent = false;

        $this->json_components = $json_components;
        if(is_array($this->json_components)) {
            $this->json_components = json_encode($this->json_components);
        }
    }

    /**
     * @return mixed
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getDateCreated() {
        return $this->date_created;
    }

    /**
     * @return mixed
     */
    public function getDateUpdated() {
        return $this->date_updated;
    }

    /**
     * @return mixed
     */
    public function getType() {
        return $this->type;
    }

    /**
     * @param mixed $type
     */
    public function setType($type) {
        $this->type = $type;
    }

    /**
     * @return mixed
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @param mixed $name
     */
    public function setName($name) {
        $this->name = $name;
    }

    /**
     * @return mixed
     */
    public function getSubject() {
        return $this->subject;
    }

    /**
     * @param mixed $subject
     */
    public function setSubject($subject) {
        $this->subject = $subject;
    }

    /**
     * @return mixed
     */
    public function getJsonComponents() {
        return $this->json_components;
    }

    /**
     * @return array
     */
    public function getJsonComponentsArray() {
        return json_decode($this->json_components, true);
    }

    /**
     * @param mixed $json_components
     */
    public function setJsonComponents($json_components) {
        $this->json_components = $json_components;
        if(is_array($this->json_components)) {
            $this->json_components = json_encode($this->json_components);
        }
    }

    /**
     * @return mixed
     */
    public function getSent() {
        return $this->sent;
    }

    /**
     * @param mixed $sent
     */
    public function setSent($sent) {
        $this->sent = $sent;
    }

    /**
     * @return mixed
     */
    public function getTaskGroup() {
        return $this->task_group;
    }

    /**
     * @param mixed $task_group
     */
    public function setTaskGroup($task_group) {
        $this->task_group = $task_group;
    }

    /**
     * @param $container
     * @param $admin
     * @param $input
     * @return array
     */
    public function adminPreview($container, $admin, $input) {

        $sections = $this->getJsonComponentsArray();
        $html = $container->get("vote.email")->getNewsletterHTML($sections, $admin);

        /*
        if(!$this->task_group) {
            return "Error: No task group found";
        }
        $params = $this->task_group->getJsonParamsArray();

        $em = $container->get('doctrine')->getEntityManager();
        $newsletter = $em->getRepository('VoteBundle:Newsletter')->find($params["newsletter"]);
        if(!$newsletter) {
            return "Error: Could not find newsletter";
        }
        $sections = $newsletter->getJsonComponentsArray();
        $html = $container->get("vote.email")->getNewsletterHTML($sections, $admin);
*/
        return array("report" => array("type" => "html", "data" => $html));
    }

    /**
     * @param $container
     * @param $admin
     * @param $input
     * @return array
     */
    public function adminTest($container, $admin, $input) {

//        $sections = $this->getJsonComponentsArray();
//        $html = $container->get("vote.email")->getNewsletterHTML($sections, $admin);

        $email = $input["email"];

        $container->get("vote.email")->sendNewsletterToEmail($this, $email, $admin);

        return array("report" => "Email successfully sent to $email");
    }

    /**
     * @param $container
     * @param $admin
     * @param $input
     * @return array
     */
    public function adminEdit($container, $admin, $input) {

        $em = $container->get('doctrine')->getEntityManager();
        $this->setJsonComponents($input["sections"]);
        $em->flush();

        $html = $container->get("vote.email")->getNewsletterHTML($input["sections"], $admin);

        return array("affected_fields" => array("json_components"), "report" => array("type" => "html", "data" => $html));
    }

    /**
     * @param $container
     * @param $admin
     * @param $input
     * @return array
     */
    public function adminAdd($container, $admin, $input) {

        if(!array_key_exists("users", $input)) {
            return "At least one user must be added";
        }

        $em = $container->get('doctrine')->getEntityManager();
        $task_repo = $em->getRepository("VoteBundle:Task");
        $rate_limit = $container->getParameter("mailer_rate_limit_seconds");
//        $tasks = $task_repo->findBy(array("group" => $this->task_group));

        $added = 0;
        foreach($input["users"] as $user) {
            $existing = $task_repo->findOneBy(array("group" => $this->task_group, "user" => $user));
            if($existing) {
                continue;
            }
            $task = new Task("email", "vote.email", "emailNewsletter", $rate_limit, array(), $user);
            $task->setGroup($this->task_group);
            $em->persist($task);
            $em->flush();

            $added++;
        }


//        $em->getRe
//        $this->setJsonComponents($input["sections"]);
//        $em->flush();

//        $html = $container->get("vote.email")->getNewsletterHTML($input["sections"], $admin);

        return array("affected_fields" => array("users"), "report" => "Added $added new users to this newsletter");
    }

    /**
     * @param $container
     * @param $admin
     * @param $input
     * @return array
     */
    public function adminSend($container, $admin, $input) {

        $em = $container->get('doctrine')->getEntityManager();
        $this->task_group->setReady(true);
        $this->sent = true;
        $em->flush();

//        $html = $container->get("vote.email")->getNewsletterHTML($this->getJsonComponentsArray(), $admin);
//        $recipient = $input["email"];

        return array("affected_fields" => array("task_group"), "report" => "Emails flagged as ready to send. Task dispatcher will process them soon.");
    }

    /**
     * @param $container
     * @param $admin
     * @param $input
     * @return Newsletter
     */
    public static function adminCreate($container, $admin, $input) {

        $em = $container->get('doctrine')->getEntityManager();

        if(!array_key_exists("sections", $input)) {
            $input["sections"] = array();
        }
        if(!array_key_exists("users", $input)) {
            $input["users"] = array();
        }

        $newsletter = new Newsletter("standard", $input["name"], $input["subject"], $input["sections"]);
        $em->persist($newsletter);

        $group = new TaskGroup("newsletter", false, array("newsletter" => $newsletter->getId()));
        $em->persist($group);
        $newsletter->setTaskGroup($group);

        $rate_limit = $container->getParameter("mailer_rate_limit_seconds");

        foreach($input["users"] as $user) {

            $task = new Task("email", "vote.email", "emailNewsletter", $rate_limit, array(), $user);
            $task->setGroup($group);
            $em->persist($task);
        }

        return $newsletter;
    }

    public function adminActions($container, $admin) {

        $num_users = count($this->task_group->getTasks());

        $actions = array(
            "preview" => array(
                "heading" => "Manage"
                ,"callback" => "adminPreview"
                ,"permission" => "ROLE_ADMIN"
                ,"class" => "btn-success"
                ,"label" => "Preview"
                ,"input" => array()
                ,"description" => "View preview of newsletter"
            )
            ,"edit" => array(
                "heading" => "Manage"
                ,"callback" => "adminEdit"
                ,"permission" => "ROLE_ADMIN"
                ,"class" => "btn-primary"
                ,"label" => "Edit"
                ,"input" => Newsletter::getStructureInput($container, $this)
                ,"description" => "Edit this newsletter"
            )
            ,"test" => array(
                "heading" => "Send"
                ,"callback" => "adminTest"
                ,"permission" => "ROLE_ADMIN"
                ,"class" => "btn-warning"
                ,"label" => "Test"
                ,"input" => array("email" => array("type" => "text", "label" => "Recipient email address", "required" => true))
                ,"description" => "Send a test email to this address"
            )
        );

        if(!$this->sent) {
            $actions["add"] = array(
                "heading" => "Manage"
                ,"callback" => "adminAdd"
                ,"permission" => "ROLE_ADMIN"
                ,"class" => "btn-warning"
                ,"label" => "Add Users"
                ,"input" => array("users" => array(
                    "type" => "multientity"
                    ,"label" => "Users"
                    ,"entity" => 'VoteBundle\Entity\User'
                    ,"required" => true
                ))
//                ,"input" => array("check" => array("type" => "boolean", "label" => "Are you sure?", "default" => false))
                ,"description" => "Add users to the newsletter"
            );
            $actions["send"] = array(
                "heading" => "Send"
                ,"callback" => "adminSend"
                ,"permission" => "ROLE_ADMIN"
                ,"class" => "btn-danger"
                ,"label" => "SEND"
                ,"input" => array("check" => array("type" => "boolean", "label" => "Are you sure?", "default" => false))
                ,"description" => "Sends this newsletter out to all $num_users users"
            );
        }

        return $actions;
    }

    /**
     * @param $container
     * @param null $current_newsletter
     * @return array
     */
    private static function getStructureInput($container, $current_newsletter = NULL) {

        $sections = array(
            "type" => "array", "label" => "Newsletter sections", "input" => array(
                "text" => array("type" => "string", "label" => "Text", "required" => true)
                ,"type" => array("type" => "select", "choices" => array(
                    array("value" => "paragraph", "label" => "Paragraph")
                    ,array("value" => "minor", "label" => "Minor heading")
                    ,array("value" => "medium", "label" => "Medium heading")
                    ,array("value" => "major", "label" => "Major heading")
            ))
        ));
        $subject = array("type" => "string", "label" => "Subject", "required" => true);
        if($current_newsletter) {
            $sections["default"] = $current_newsletter->getJsonComponentsArray();
            $subject["default"] = $current_newsletter->getSubject();
        }

        $substitution_info = array("These strings will be replaced if you include them in any text:");
        $replaceable = $container->get("vote.email")->getReplaceableParameters();
        foreach($replaceable as $key => $replacement) {
            $substitution_info[] = "$key";
        }

        return array(
            "subject" => $subject
            ,"info" => array("type" => "info", "label" => "", "text" => implode("\n", $substitution_info))
            ,"sections" => $sections
        );
    }

    /**
     * @param $container
     * @param $admin
     * @return array
     */
    public static function adminStatic($container, $admin) {
        return array(
            "headers" => array(
                array("label" => "Manage", "permission" => "ROLE_ADMIN", "priority" => 200)
                ,array("label" => "Send", "permission" => "ROLE_ADMIN", "priority" => 190)
            )
            ,"create" => array("callback" => "adminCreate", "input" => array_merge(array(
                "name" => array("type" => "string", "label" => "Newsletter name (Used internally only)", "required" => true)
                ), Newsletter::getStructureInput($container)
                ,array("users" => array(
                    "type" => "multientity"
                    ,"label" => "Users"
                    ,"entity" => 'VoteBundle\Entity\User'
                    ,"required" => true
                ))
            ))
        );
    }

}