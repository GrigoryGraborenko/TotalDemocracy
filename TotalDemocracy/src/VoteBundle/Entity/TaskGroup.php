<?php
/**
 * Created by PhpStorm.
 * User: Grigory
 * Date: 20/05/2017
 * Time: 12:15 PM
 */

namespace VoteBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

use Gedmo\Mapping\Annotation as Gedmo;

use Grigorygraborenko\RecursiveAdmin\Annotations\Admin;

/**
 * Class TaskGroup
 * @package VoteBundle\Entity
 *
 * @ORM\Entity
 * @ORM\Table(name="task_group")
 */
class TaskGroup {

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

    /** @ORM\Column(type="boolean",nullable=false) */
    protected $ready;

    /**
     * @ORM\OneToMany(targetEntity="VoteBundle\Entity\Task", mappedBy="group")
     */
    protected $tasks;

    /** @ORM\Column(type="text", nullable=false) */
    protected $jsonParams;

    /**
     * TaskGroup constructor.
     * @param $type
     * @param $ready
     * @param $jsonParams
     */
    public function __construct($type, $ready, $jsonParams = array()) {
        $this->type = $type;
        $this->ready = $ready;
        $this->jsonParams = $jsonParams;

        if(is_array($this->jsonParams)) {
            $this->jsonParams = json_encode($this->jsonParams);
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
    public function getReady() {
        return $this->ready;
    }

    /**
     * @param mixed $ready
     */
    public function setReady($ready) {
        $this->ready = $ready;
    }

    /**
     * @return mixed
     */
    public function getJsonParams() {
        return $this->jsonParams;
    }

    /**
     * @return array
     */
    public function getJsonParamsArray() {
        return json_decode($this->jsonParams, true);
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
     * @return mixed
     */
    public function getTasks() {
        return $this->tasks;
    }

}
