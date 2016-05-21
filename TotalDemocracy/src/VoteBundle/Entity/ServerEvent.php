<?php
/**
 * Created by PhpStorm.
 * User: Grigory
 * Date: 18-May-16
 * Time: 6:32 AM
 */

namespace VoteBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;

use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Class ServerEvent
 * @package VoteBundle\Entity
 *
 * @ORM\Entity(repositoryClass="VoteBundle\Repository\ServerEventRepository")
 * @ORM\Table(name="server_events", indexes={@ORM\Index(name="name_idx", columns={"name"})})
 */
class ServerEvent {

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
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id", nullable=true)
     */
    protected $user;

    /** @ORM\Column(type="string", nullable=false) */
    protected $name;

    /**
     * @ORM\ManyToOne(targetEntity="VoteBundle\Entity\ServerEvent", inversedBy="children")
     * @ORM\JoinColumn(name="parent_id", referencedColumnName="id", nullable=true, onDelete="set null")
     */
    protected $parent;

    /**
     * @ORM\OneToMany(targetEntity="VoteBundle\Entity\ServerEvent", mappedBy="parent")
     */
    protected $children;

    /** @ORM\Column(type="decimal", scale = 2, nullable=true) */
    protected $amount;

    /** @ORM\Column(type="boolean", nullable=false) */
    protected $processed;

    /** @ORM\Column(type="text", nullable=true) */
    protected $json;

    /**
     * ServerEvent constructor.
     * @param $name
     * @param $user
     * @param $json
     * @param $amount
     * @param $parent
     */
    public function __construct($name, $user, $json = NULL, $amount = NULL, $parent = NULL) {
        $this->name = $name;
        $this->user = $user;
        $this->json = $json;
        $this->parent = $parent;
        $this->amount = $amount;
        $this->processed = false;

        if(is_array($json)) {
            $this->json = json_encode($json);
        }
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return mixed
     */
    public function getDateCreated()
    {
        return $this->date_created;
    }

    /**
     * @param mixed $date_created
     */
    public function setDateCreated($date_created)
    {
        $this->date_created = $date_created;
    }

    /**
     * @return mixed
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param mixed $user
     */
    public function setUser($user)
    {
        $this->user = $user;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return mixed
     */
    public function getParent() {
        return $this->parent;
    }

    /**
     * @param mixed $parent
     */
    public function setParent($parent) {
        $this->parent = $parent;
    }

    /**
     * @return mixed
     */
    public function getChildren() {
        return $this->children;
    }

    /**
     * @return mixed
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @param mixed $amount
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;
    }

    /**
     * @return mixed
     */
    public function getProcessed()
    {
        return $this->processed;
    }

    /**
     * @param mixed $processed
     */
    public function setProcessed($processed)
    {
        $this->processed = $processed;
    }

    /**
     * @return mixed
     */
    public function getJson()
    {
        return $this->json;
    }

    /**
     * @param mixed $json
     */
    public function setJson($json)
    {
        $this->json = $json;
    }

    /**
     * @return array
     */
    public function getJsonArray() {
        return json_decode($this->json, true);
    }


}