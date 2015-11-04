<?php

namespace OCA\Eudat\Job;

use OCA\Eudat\AppInfo\Application;
use OCA\Eudat\Db\FilecacheStatusMapper;

use OC\BackgroundJob\QueuedJob;
use OC\Files\Filesystem;
use OCP\IConfig;
use OCP\Util;


class TransferHandler extends QueuedJob {

    protected $mapper;
    protected $config;

    public function __construct(FilecacheStatusMapper $mapper = null,
                                IConfig $config = null){
        if ($mapper === null || $config === null) {
            $this->fixTransferForCron();

        }
        else {
            $this->mapper = $mapper;
            $this->config = $config;
        }
    }

    protected function fixTransferForCron() {
        $application = new Application();
        $this->mapper = $application->getContainer()->query('FilecacheStatusMapper');
        $this->config = \OC::$server->getConfig();
    }

    /**
     * Check if current user is the requested user
     * @param \array     args
     * @return \null
     */
    public function run($args){
        if(!array_key_exists('transferId', $args)){
            Util::writeLog('transfer', 'Bad request, can not handle transfer without transferId', 3);
            return;
        }
        // get the file transfer object for current job
        $fcStatus = $this->mapper->find($args['transferId']);

        $fcStatus->setStatus("processing");
        $this->mapper->update($fcStatus);
        $user = $fcStatus->getOwner();
        $fileId = $fcStatus->getFileid();
        $b2share_url = $this->config->getAppValue('eudat', 'b2share_endpoint_url');

        Util::writeLog('transfer', 'Publishing to'.$b2share_url, 3);

        Filesystem::init($user, '/');
        $path = Filesystem::getPath($fileId);
        $has_access = Filesystem::isReadable($path);
        if ($has_access) {
            $view = Filesystem::getView();
            // TODO: is it good to take the owncloud fopen?
            $handle = $view->fopen($path, 'rb');
            $size = $view->filesize($path);

            $curl_client = curl_init($b2share_url.'/'.$args['transferId']);

            curl_setopt($curl_client, CURLOPT_INFILE, $handle);
            curl_setopt($curl_client, CURLOPT_INFILESIZE, $size);
            curl_setopt($curl_client, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl_client, CURLOPT_PUT, true);
            curl_setopt($curl_client, CURLOPT_HTTPHEADER, array('X-Auth-Token: ', 'Expect:'));

            $curl_success = curl_exec($curl_client);
            if (!$curl_success){
                Util::writeLog('transfer_path', 'Error communicating with B2SHARE'. $curl_success, 3);
                $fcStatus->setStatus("error while accessing b2share");
                $this->mapper->update($fcStatus);
            }
            Util::writeLog('transfer_path', 'Communication to B2SHARE successfull', 0);
            $fcStatus->setStatus("published");
            $this->mapper->update($fcStatus);
        }
        else {
            $fcStatus->setStatus("internal error while publishing");
            $this->mapper->update($fcStatus);
        }




        // init assertions
        #if(!function_exists('pcntl_fork')){
        #    Util::writeLog('transfer', 'no function `pcntl_fork` install `pcntl` extension', 3);
        #    die("no function `pcntl_fork` install `pcntl` extension" . PHP_EOL);
        #}
        #if(!function_exists('posix_getpid')){
        #    Util::writeLog('transfer', 'no function `posix_getpid`, this feature works on a posix OS only', 3);
        #    die("no function `posix_getpid`, this feature works on a posix OS only" . PHP_EOL);
        #}
        // print_r($args);

        // TODO: think of a fork alternative or make it possible to not loose the database connection. also it is running only one job per cron run...
        #$pid = \pcntl_fork();
        #if ($pid == -1) {
        #    Util::writeLog('transfer', 'forking error', 3);
        #    die("forking error" . PHP_EOL);
        #}
        #else if($pid) {
        #    Util::writeLog('transfer', 'parent', 3);
        #    return;
        #} else {
            #$this->forked(posix_getpid(), $args);
        #foreach ($args as &$value) {
        #    Util::writeLog('transfer_array', $value, 3);
        #}
        // get path of file

        #}
        // TODO: we need to be carefull of zombies here!
    }

    /**
     * Check if current user is the requested user
     * @param \string     $userId
     * @return \boolean
     */
    public function isPublishingUser($userId){
        return is_array($this->argument) &&
            array_key_exists('userId', $this->argument) &&
            $this->argument['userId'] == $userId;
    }

    /**
     * Get actual filename for fileId
     * @return \string
     */
    public function getFilename(){
        Filesystem::init($this->argument['userId'], '/');
        return Filesystem::getPath($this->argument['fileId']);
    }

    /**
     * Check if current user is the requested user
     * @return \boolean
     */
    public function getRequestDate(){
        return $this->argument['requestDate'];
    }

    /**
     * fork process that uploads the file to b2share
     * @param \string $pid
     * @param \array  $args
     * @return \null
     */
    public function forked($pid, $args){
        Util::writeLog('transfer', 'FORKED', 3);
        foreach ($args as &$value) {
            Util::writeLog('transfer_array', $value, 3);
        }
        // get path of file
        // TODO: make sure the user can access the file
        $fcStatus = $this->mapper->find($args['transferId']);

        $fcStatus->setStatus("processing");
        $this->mapper->update($fcStatus);
        Util::writeLog('transfer', 'FORKED2', 3);

        Filesystem::init($args['userId'], '/');
        $path = Filesystem::getPath($args['fileId']);
        Util::writeLog('transfer', 'FORKED3', 3);
        // detect failed lookups
        if (strlen($path) <= 0){
            Util::writeLog('transfer', "cannot find path for: `" . $args['userId'] . ":" . $args['fileId'] . "`", 3);
            return;
        }


        Util::writeLog('transfer', 'start...', 3);
        sleep(5);
        Util::writeLog('transfer', '...end', 3);

        // \OC\Files\Filesystem::getFileInfo($args['fileId']);

        // $view = new \OC\Files\View('/' . $args['userId'] . '/files');
        // $fileinfo = $view->getFileInfo("blaat.txt");
        // echo "{{ " . $fileinfo->getInternalPath() . " }}" .PHP_EOL;
        // echo "{{ " . $fileinfo->getId() . " }}" .PHP_EOL;
        // echo "{{ " . $fileinfo->getMountPoint() . " }}" .PHP_EOL;
        // echo "{{ " . $fileinfo->getSize() . " }}" .PHP_EOL;
        // echo "{{ " . $fileinfo->getType() . " }}" .PHP_EOL;
        // echo "{{ " . $fileinfo->stat() . " }}" .PHP_EOL;

        // echo "... forked end \t" . $pid . PHP_EOL;

        // TODO: start external session
        // TODO: start transfer

    }

}
