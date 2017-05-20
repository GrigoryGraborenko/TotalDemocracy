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

    /** @ORM\Column(type="boolean",nullable=false) */
    protected $ready;

    /**
     * @ORM\OneToMany(targetEntity="VoteBundle\Entity\Task", mappedBy="task_group")
     */
    protected $tasks;

    /**
     * TaskGroup constructor.
     * @param $ready
     */
    public function __construct($ready) {
        $this->ready = $ready;
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
    public function getTasks() {
        return $this->tasks;
    }

}
