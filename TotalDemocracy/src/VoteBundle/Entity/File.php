<?php
/**
 * Created by PhpStorm.
 * User: Grigory
 * Date: 5/08/2017
 * Time: 11:30 AM
 */

namespace VoteBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

use Gedmo\Mapping\Annotation as Gedmo;

use Grigorygraborenko\RecursiveAdmin\Annotations\Admin;

/**
 * Class File
 * @package VoteBundle\Entity
 *
 * @ORM\Entity
 * @ORM\Table(name="file")
 * @Admin(read="ROLE_ADMIN", write="ROLE_ADMIN")
 */
class File {

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
    protected $mime;

    /**
     * @ORM\Column(type="string", nullable=false)
     * @Admin(read="ROLE_ADMIN", write="none")
     */
    protected $location;

    /**
     * @ORM\Column(type="boolean",nullable=false)
     * @Admin(read="ROLE_ADMIN", write="none")
     */
    protected $public;

    /**
     * @ORM\Column(type="integer", nullable=false)
     * @Admin(read="ROLE_ADMIN", write="none")
     */
    protected $size;

    /**
     * File constructor.
     * @param $name
     * @param $mime
     * @param $location
     * @param $size
     * @param $public
     */
    public function __construct($name, $mime, $location, $size, $public) {
        $this->type = "standard";
        $this->name = $name;
        $this->mime = $mime;
        $this->location = $location;
        $this->size = $size;
        $this->public = $public;
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
    public function getMime() {
        return $this->mime;
    }

    /**
     * @param mixed $mime
     */
    public function setMime($mime) {
        $this->mime = $mime;
    }

    /**
     * @return mixed
     */
    public function getLocation() {
        return $this->location;
    }

    /**
     * @param mixed $location
     */
    public function setLocation($location) {
        $this->location = $location;
    }

    /**
     * @return mixed
     */
    public function getSize() {
        return $this->size;
    }

    /**
     * @param mixed $size
     */
    public function setSize($size) {
        $this->size = $size;
    }

    /**
     * @param $container
     * @param $admin
     * @param $input
     * @return array
     */
    public function adminDelete($container, $admin, $input) {

        $em = $container->get('doctrine')->getEntityManager();
        unlink($this->location);
        $em->remove($this);
        $em->flush();

        return array("affected_fields" => true);
    }

    public function adminActions($container, $admin) {

        $actions = array(
            "delete" => array(
                "heading" => "Action"
                ,"callback" => "adminDelete"
                ,"permission" => "ROLE_ADMIN"
                ,"class" => "btn-danger"
                ,"label" => "DELETE"
                ,"input" => array()
                ,"description" => "Delete this file?"
            )
        );

        return $actions;
    }

    /*
    public function adminCreate($container, $admin, $input) {
        $em = $container->get('doctrine')->getEntityManager();

        $file = $input["file"];

        if(false) {
            return "dsfsdf";
        }
        return json_encode($input);

        $file = new File("sdfsd", "sfsd", "sdfsdf", 1234, true);

        return $file;
        // privat

//        $fileStorage = $container->get('xyg.file_storage_service');
//        $file = $input["image"];
//        $open_file = $input["openedImage"];
//        $filename = $fileStorage->uploadFileName($file->getPathName(), $file->getMimeType(), $file->getClientOriginalExtension());
//        $opened_filename = $fileStorage->uploadFileName($open_file->getPathName(), $open_file->getMimeType(), $open_file->getClientOriginalExtension());
//        $template->setImageURL($filename);
//        $template->setOpenedImageURL($opened_filename);

    }*/

    /**
     * @param $container
     * @param $admin
     * @return array
     */
    public static function adminStatic($container, $admin) {

        // currently file uploads don't work for create method
        $create_input = array(
            "file" => array("type" => "file", "label" => "File to upload", "required" => true)
            ,"open" => array("type" => "boolean", "label" => "Should this file be publicly accessible?", "default" => true, "required" => true)
        );

        return array(
            "headers" => array(
                array("label" => "Action", "permission" => "ROLE_ADMIN", "priority" => 200)
            )
//            ,"create" => array(
//                "callback" => "adminCreate"
//                ,"input" => $create_input
//            )
        );

    }

}