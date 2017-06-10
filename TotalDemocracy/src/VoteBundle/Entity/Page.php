<?php
/**
 * Created by PhpStorm.
 * User: Grigory
 * Date: 10/06/2017
 * Time: 7:16 AM
 */

namespace VoteBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

use Gedmo\Mapping\Annotation as Gedmo;

use Grigorygraborenko\RecursiveAdmin\Annotations\Admin;

/**
 * Class Page
 * @package VoteBundle\Entity
 *
 * @ORM\Entity
 * @ORM\Table(name="page")
 * @Admin(read="ROLE_ADMIN", write="ROLE_ADMIN", create="ROLE_ADMIN", destroy="ROLE_ADMIN")
 */
class Page {

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
    protected $heading;

    /** @ORM\Column(type="string", nullable=false) */
    protected $name;

    /** @ORM\Column(type="string", nullable=false) */
    protected $url;

    /** @ORM\Column(type="boolean", nullable=false) */
    protected $visible;

    /** @ORM\Column(type="text", nullable=false) */
    protected $jsonParams;

    /**
     * Page constructor.
     * @param $type
     * @param $heading
     * @param $name
     * @param $url
     */
    public function __construct($type, $heading, $name, $url) {
        $this->type = $type;
        $this->heading = $heading;
        $this->name = $name;
        $this->url = $url;

        $this->visible = false;
        $this->jsonParams = json_encode(array());
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
    public function getHeading() {
        return $this->heading;
    }

    /**
     * @param mixed $heading
     */
    public function setHeading($heading) {
        $this->heading = $heading;
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
    public function getUrl() {
        return $this->url;
    }

    /**
     * @param mixed $url
     */
    public function setUrl($url) {
        $this->url = $url;
    }

    /**
     * @return mixed
     */
    public function getVisible() {
        return $this->visible;
    }

    /**
     * @param mixed $visible
     */
    public function setVisible($visible) {
        $this->visible = $visible;
    }

    /**
     * @return mixed
     */
    public function getJsonParams() {
        return $this->jsonParams;
    }

    /**
     * @param mixed $jsonParams
     */
    public function setJsonParams($jsonParams) {
        $this->jsonParams = $jsonParams;
        if(is_array($this->jsonParams)) {
            $this->jsonParams = json_encode($this->jsonParams);
        }
    }

    /**
     * @return array
     */
    public function getJsonParamsArray() {
        return json_decode($this->jsonParams, true);
    }

    /**
     * @return mixed
     */
    public function getJsonResultArray() {
        return json_decode($this->jsonResult, true);
    }

    /**
     * @param $container
     * @param $admin
     * @param $input
     * @return Newsletter
     */
    public static function adminCreate($container, $admin, $input) {

//        $em = $container->get('doctrine')->getEntityManager();

        $page = new Page("standard", $input["heading"], $input["name"], $input["url"]);
        if(!array_key_exists("sections", $input)) {
            $input["sections"] = array();
        }
        $page->setJsonParams(array(
            "sections" => $input["sections"]
        ));

        return $page;
    }

    /**
     * @param $container
     * @param $admin
     * @param $input
     * @return array
     */
    public function adminClone($container, $admin, $input) {

        $page = Page::adminCreate($container, $admin, $input);
        if(is_string($page)) {
            return $page;
        }

        $em = $container->get('doctrine')->getEntityManager();
        $em->persist($page);
//        $this->task_group->setReady(true);
//        $this->sent = true;
        $em->flush();

//        $html = $container->get("vote.email")->getNewsletterHTML($this->getJsonComponentsArray(), $admin);
//        $recipient = $input["email"];

        return array("affected_fields" => true, "report" => "Page has been cloned");
    }

    /**
     * @param $container
     * @param $admin
     * @param $input
     * @return array
     */
    public function adminEdit($container, $admin, $input) {

        if(!array_key_exists("sections", $input)) {
            $input["sections"] = array();
        }
        $em = $container->get('doctrine')->getEntityManager();
        $params = $this->getJsonParamsArray();
        $params["sections"] = $input["sections"];
        $this->setJsonParams($params);

        $em->flush();

        return array("affected_fields" => array("jsonParams"));
    }

    /**
     * @param $container
     * @param $admin
     * @param $input
     * @return array
     */
    public function adminSend($container, $admin, $input) {

        $em = $container->get('doctrine')->getEntityManager();

        $this->visible = !$this->visible;
        $em->flush();

        return array("affected_fields" => array("visible"));
    }

    /**
     * @param $container
     * @param null $current_page
     * @return array
     */
    private static function getStructureInput($container, $current_page = NULL) {

        $sections = array(
            "type" => "array", "label" => "Page sections", "input" => array(
                "text" => array("type" => "string", "label" => "Text", "required" => true, "lines" => 6)
                ,"type" => array("type" => "select", "choices" => array(
                    array("value" => "paragraph", "label" => "Paragraph")
                    ,array("value" => "raw", "label" => "Raw HTML")
                    ,array("value" => "minor", "label" => "Minor heading")
                    ,array("value" => "medium", "label" => "Medium heading")
                    ,array("value" => "major", "label" => "Major heading")
                ))
            ));
//        $subject = array("type" => "string", "label" => "Subject", "required" => true);
        if($current_page) {
            $params = $current_page->getJsonParamsArray();
            if(array_key_exists("sections", $params)) {
                $sections["default"] = $params["sections"];
            }
//            $subject["default"] = $current_newsletter->getSubject();
        }


        return array(
//            "subject" => $subject
            "sections" => $sections
        );
    }

    /**
     * @param $container
     * @param $admin
     * @return array
     */
    public function adminActions($container, $admin) {

        // edit, clone, enable/disable

        $send_verb = ($this->visible ? "Disable" : "Enable");

        $actions = array(
            "clone" => array(
                "heading" => "Action"
                ,"callback" => "adminClone"
                ,"permission" => "ROLE_ADMIN"
                ,"class" => "btn-success"
                ,"label" => "Clone"
                ,"input" => array(
                    "heading" => array("type" => "string", "label" => "Menu heading name", "required" => true, "default" => $this->heading)
                    ,"name" => array("type" => "string", "label" => "Menu name", "required" => true, "default" => $this->name)
                    ,"url" => array("type" => "string", "label" => "URL string", "required" => true, "default" => $this->url)
                )
                ,"description" => "Clone this page"
            )
            ,"edit" => array(
                "heading" => "Action"
                ,"callback" => "adminEdit"
                ,"permission" => "ROLE_ADMIN"
                ,"class" => "btn-primary"
                ,"label" => "Edit"
                ,"input" => Page::getStructureInput($container, $this)
                ,"description" => "Edit this page"
            )
            ,"send" => array(
                "heading" => "Action"
                ,"callback" => "adminSend"
                ,"permission" => "ROLE_ADMIN"
                ,"class" => ($this->visible ? "btn-danger" : "btn-warning")
                ,"label" => $send_verb
                ,"input" => array()
                ,"description" => "$send_verb this page"
            )
        );

        return $actions;
    }

    /**
     * @param $container
     * @param $admin
     * @return array
     */
    public static function adminStatic($container, $admin) {
        return array(
            "headers" => array(
                array("label" => "Action", "permission" => "ROLE_ADMIN", "priority" => 200)
            )
            ,"create" => array("callback" => "adminCreate", "input" => array_merge(
                array(
                    "heading" => array("type" => "string", "label" => "Menu heading name", "required" => true)
                    ,"name" => array("type" => "string", "label" => "Menu name", "required" => true)
                    ,"url" => array("type" => "string", "label" => "URL string", "required" => true)
                ), Page::getStructureInput($container))
            )
        );
    }

}