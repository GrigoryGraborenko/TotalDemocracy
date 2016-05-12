<?php
/**
 * Created by PhpStorm.
 * User: Grigory
 * Date: 26/02/2016
 * Time: 6:48 AM
 */

namespace VoteBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;

/**
 * @ORM\Entity(repositoryClass="VoteBundle\Repository\UserDocumentVoteRepository")
 * @ORM\Table(name="user_document_vote")
 * @ExclusionPolicy("all")
 */
class UserDocumentVote {

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
     * @Expose
     */
    protected $date_created;

    /**
     * The user this vote belongs to
     *
     * @ORM\ManyToOne(targetEntity="\VoteBundle\Entity\User", inversedBy="votes")
     */
    protected $user;

    /**
     * The document the user is voting for or against
     *
     * @ORM\ManyToOne(targetEntity="\VoteBundle\Entity\Document", inversedBy="votes")
     */
    protected $document;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $isSupporter;

    /**
     * UserDocumentVote constructor.
     * @param $user
     * @param $document
     * @param $isSupporter
     */
    public function __construct($user, $document, $isSupporter) {
        $this->user = $user;
        $this->document = $document;
        $this->isSupporter = $isSupporter;
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
    public function getDocument() {
        return $this->document;
    }

    /**
     * @param mixed $document
     */
    public function setDocument($document) {
        $this->document = $document;
    }

    /**
     * @return mixed
     */
    public function getIsSupporter() {
        return $this->isSupporter;
    }

    /**
     * @param mixed $isSupporter
     */
    public function setIsSupporter($isSupporter) {
        $this->isSupporter = $isSupporter;
    }



}