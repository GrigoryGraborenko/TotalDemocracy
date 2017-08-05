<?php
/**
 * Created by PhpStorm.
 * User: Grigory
 * Date: 5/08/2017
 * Time: 12:59 PM
 */

namespace VoteBundle\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

use VoteBundle\Entity\File;

/**
 * Class FileService
 * @package VoteBundle\Service
 */
class FileService {

    /** @var EntityManager em */
    private $em;

    /** @var Service Container container */
    private $container;

    /** @var Logger logger */
    private $logger;

    /**
     * OptionService constructor.
     * @param $em
     * @param $container
     * @param $logger
     */
    public function __construct($em, $container, $logger) {
        $this->em = $em;
        $this->container = $container;
        $this->logger = $logger;
    }

    /**
     * @param $container
     * @param $admin
     * @param $input
     * @return array|string
     */
    public function adminFileUpload($container, $admin, $input) {

        $input_file = $input["file"];

        if(!$input_file) {
            return "No file given";
        }

        $token_gen = $container->get('fos_user.util.token_generator');
        $token = $token_gen->generateToken();
        $input_file_extension = $input_file->getClientOriginalExtension();

        if($input["open"]) {
            $dir = 'files';
        } else {
            $dir = '../files';
        }

        $filename = $token . "." . $input_file_extension;
        $location = $dir . "/" . $filename;

        if(!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
        $file = new File($input_file->getClientOriginalName(), $input_file->getMimeType(), $location, $input_file->getClientSize(), $input["open"]);

        rename($input_file->getPathName(), $location);

        $em = $container->get('doctrine')->getEntityManager();
        $em->persist($file);
        $em->flush();

        $report = "File uploaded successfully";

        return array("report" => $report);
    }

    /**
     * @param $container
     * @param $admin
     * @return array
     */
    public function getAdminGlobalActions($container, $admin) {
        return array("file_upload" =>
            array(
                "callback" => "adminFileUpload"
                ,"label" => "Upload File"
                ,"classes" => "btn btn-xs btn-danger"
                ,"description" => "Upload a file"
                ,"permission" => "ROLE_ADMIN"
                ,"visible" => array('VoteBundle\Entity\File')
                ,"input" => array(
                    "file" => array("type" => "file", "label" => "File to upload", "required" => true)
                    ,"open" => array("type" => "boolean", "label" => "Should this file be publicly accessible?", "default" => true, "required" => true)
                )
            )
        );
    }
}