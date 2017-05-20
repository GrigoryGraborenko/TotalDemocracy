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

/**
 * Class Newsletter
 * @package VoteBundle\Entity
 *
 * @ORM\Entity
 * @ORM\Table(name="newsletter")
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
     * @Admin(read="ROLE_SUPPORT", write="none")
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
     * @param mixed $json_components
     */
    public function setJsonComponents($json_components) {
        $this->json_components = $json_components;
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

}