<?php
/**
 * Created by PhpStorm.
 * User: james
 * Date: 13/08/2015
 * Time: 3:53 PM
 */

namespace VoteBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;

/**
 * @ORM\Entity(repositoryClass="VoteBundle\Repository\DocumentRepository")
 * @ORM\Table(name="document", indexes={@ORM\Index(name="external_idx", columns={"external_id"})})
 * @ExclusionPolicy("all")
 */
class Document {

    /**
     * @ORM\Id
     * @ORM\Column(type="string")
     * @ORM\GeneratedValue(strategy="UUID")
     * @Expose
     */
    protected $id;

    /**
     * @ORM\Column(type="datetime")
     * @Gedmo\Timestampable(on="create")
     */
    protected $whenCreated;

    /**
     * The domain the document applies in
     *
     * @ORM\ManyToOne(targetEntity="\VoteBundle\Entity\Domain", inversedBy="documents")
     * @ORM\JoinColumn(name="domain_id", referencedColumnName="id", nullable=false)
     */
    protected $domain;

    /**
     * @ORM\Column(type="string")
     * @Expose
     */
    protected $type;

    /**
     * @ORM\Column(type="string")
     * @Expose
     * should be open, passed, rejected, suspended
     */
    protected $state;

    /**
     * @ORM\Column(type="string")
     * @Expose
     */
    protected $name;

    /**
     * @ORM\Column(type="text")
     * @Expose
     */
    protected $summary;

    /**
     * @ORM\Column(type="datetime")
     * @Expose
     */
    protected $dateCreated;

    /**
     * @ORM\Column(type="string", name="external_id", nullable=true)
     * @Expose
     */
    protected $externalID;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @Expose
     */
    protected $externalURL;

    /**
     * @ORM\Column(type="text", nullable=true)
     * @Expose
     */
    protected $customData;

    /**
     * @ORM\Column(type="text", nullable=true)
     * @Expose
     */
    protected $text;

    /**
     * @ORM\OneToMany(targetEntity="VoteBundle\Entity\UserDocumentVote", mappedBy="document")
     */
    private $votes;

    /**
     * Document constructor.
     * @param $domain
     * @param $type
     * @param $name
     * @param $summary
     * @param $date_created
     * @param null $customData
     * @param null $text
     */
    public function __construct($domain, $type, $name, $summary, $date_created, $customData = NULL, $text = NULL) {
        $this->domain = $domain;
        $this->type = $type;
        $this->state = "open";
        $this->name = $name;
        $this->summary = $summary;
        $this->dateCreated = $date_created;
        $this->setCustomData($customData);
        $this->text = $text;
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
    public function getWhenCreated() {
        return $this->whenCreated;
    }

    /**
     * @return mixed
     */
    public function getDomain() {
        return $this->domain;
    }

    /**
     * @param mixed $domain
     */
    public function setDomain($domain) {
        $this->domain = $domain;
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
    public function getState() {
        return $this->state;
    }

    /**
     * @param mixed $state
     */
    public function setState($state) {
        $this->state = $state;
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
    public function getSummary() {
        return $this->summary;
    }

    /**
     * @param mixed $summary
     */
    public function setSummary($summary) {
        $this->summary = $summary;
    }

    /**
     * @return mixed
     */
    public function getDateCreated() {
        return $this->dateCreated;
    }

    /**
     * @param mixed $dateCreated
     */
    public function setDateCreated($dateCreated) {
        $this->dateCreated = $dateCreated;
    }

    /**
     * @return mixed
     */
    public function getExternalID() {
        return $this->externalID;
    }

    /**
     * @param mixed $externalID
     */
    public function setExternalID($externalID) {
        $this->externalID = $externalID;
    }

    /**
     * @return mixed
     */
    public function getExternalURL() {
        return $this->externalURL;
    }

    /**
     * @param mixed $externalURL
     */
    public function setExternalURL($externalURL) {
        $this->externalURL = $externalURL;
    }

    /**
     * @return mixed
     */
    public function getCustomData() {
        return $this->customData;
    }

    /**
     * @param mixed $customData
     */
    public function setCustomData($customData) {
        if(is_array($customData)) {
            $this->customData = json_encode($customData);
        } else {
            $this->customData = $customData;
        }
    }

    /**
     * @return mixed
     */
    public function getText() {
        return $this->text;
    }

    /**
     * @param mixed $text
     */
    public function setText($text) {
        $this->text = $text;
    }

    /**
     * @return mixed
     */
    public function getVotes() {
        return $this->votes;
    }

    /**
     * @param mixed $votes
     */
    public function setVotes($votes) {
        $this->votes = $votes;
    }

}
