<?php
/**
 * Created by PhpStorm.
 * User: Grigory
 * Date: 20/07/2016
 * Time: 7:24 AM
 */

namespace VoteBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Class Task
 * @package VoteBundle\Entity
 *
 * @ORM\Entity
 * @ORM\Table(name="tasks")
 */
class Task {

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
     * @ORM\ManyToOne(targetEntity="VoteBundle\Entity\User")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id", nullable=true, onDelete="CASCADE")
     */
    protected $user;

    /** @ORM\Column(type="string", nullable=false) */
    protected $type;

    /** @ORM\Column(type="string", nullable=false) */
    protected $service;

    /** @ORM\Column(type="string", nullable=false) */
    protected $function;

    /** @ORM\Column(type="text", nullable=false) */
    protected $jsonParams;

    /** @ORM\Column(type="text", nullable=true) */
    protected $jsonResult;

    /** @ORM\Column(type="decimal", scale = 4, nullable=false) */
    protected $minSeconds;

    /** @ORM\Column(type="datetime", nullable=true) */
    protected $whenProcessed;

    /**
     * Task constructor.
     * @param $type
     * @param $service
     * @param $function
     * @param $minSeconds
     * @param $jsonParams
     * @param $user
     */
    public function __construct($type, $service, $function, $minSeconds, $jsonParams = array(), $user = NULL) {
        $this->type = $type;
        $this->service = $service;
        $this->function = $function;
        $this->minSeconds = $minSeconds;
        $this->jsonParams = $jsonParams;
        $this->user = $user;

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
    public function getUser() {
        return $this->user;
    }

    /**
     * @param mixed $user
     */
    public function setUser($user) {
        $this->user = $user;
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
    public function getService() {
        return $this->service;
    }

    /**
     * @param mixed $service
     */
    public function setService($service) {
        $this->service = $service;
    }

    /**
     * @return mixed
     */
    public function getFunction() {
        return $this->function;
    }

    /**
     * @param mixed $function
     */
    public function setFunction($function) {
        $this->function = $function;
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
    public function getJsonResult() {
        return $this->jsonResult;
    }

    /**
     * @param mixed $jsonResult
     */
    public function setJsonResult($jsonResult) {
        $this->jsonResult = $jsonResult;
    }

    /**
     * @return mixed
     */
    public function getJsonResultArray() {
        return json_decode($this->jsonResult, true);
    }

    /**
     * @return mixed
     */
    public function getMinSeconds() {
        return $this->minSeconds;
    }

    /**
     * @param mixed $minSeconds
     */
    public function setMinSeconds($minSeconds) {
        $this->minSeconds = $minSeconds;
    }

    /**
     * @return mixed
     */
    public function getWhenProcessed() {
        return $this->whenProcessed;
    }

    /**
     * @param mixed $whenProcessed
     */
    public function setWhenProcessed($whenProcessed) {
        $this->whenProcessed = $whenProcessed;
    }

}
